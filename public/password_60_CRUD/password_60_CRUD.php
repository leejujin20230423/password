<?php
// ==================================================
// 1. 기본 설정: 세션 + 필요한 클래스 로드
// ==================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DBConnection
require_once __DIR__ . '/../../connection/classes/DBConnection.php';

// 테이블/컬럼 자동 로더
require_once __DIR__ . '/../password_50_getAllTableNameColumnNameAutoload/password_50_getAllTableNameColumnNameAutoload.php';

// (선택) Redis Stub – 개발환경에서 phpredis 없을 때 대비
// require_once __DIR__ . '/../../connection/redis_stubs.php';


// ==================================================
// 2. GenericCrud 클래스
// ==================================================
class GenericCrud
{
    /** @var PDO */
    private $db;

    /** @var GetAllTableNameAutoload */
    private $schemaLoader;

    /** @var string 현재 대상 테이블명 */
    private $table;

    /** @var string[] 현재 테이블 컬럼명 리스트 */
    private $columns = array();

    /** @var string|null PK 컬럼명 */
    private $primaryKey = null;

    /** @var Redis|null 캐시 객체 (없으면 null) */
    private $cache;

    /** @var string|null 마지막 리스트 조회 출처 ('db' | 'redis' | null) */
    private $lastListSource = null;

    /**
     * @param PDO                     $db
     * @param GetAllTableNameAutoload $schemaLoader  스키마 로더 인스턴스
     * @param string                  $table         사용할 테이블명
     * @param Redis|null              $cache         (옵션) Redis 인스턴스
     */
    public function __construct(
        PDO $db,
        GetAllTableNameAutoload $schemaLoader,
        $table,
        $cache = null
    ) {
        $this->db           = $db;
        $this->schemaLoader = $schemaLoader;
        $this->cache        = $cache;

        // 처음 생성될 때 테이블 설정
        $this->setTable($table);
    }

    /**
     * 테이블 변경 + 컬럼/PK 정보 다시 로딩
     */
    public function setTable($table)
    {
        $this->table = $table;

        // 1) 해당 테이블 컬럼 정보 가져오기
        $colsInfo = $this->schemaLoader->getColumnsByTable($table);

        $this->columns    = array();
        $this->primaryKey = null;

        foreach ($colsInfo as $col) {
            $this->columns[] = $col['COLUMN_NAME'];

            // PRIMARY KEY 컬럼 찾기
            if ($col['COLUMN_KEY'] === 'PRI') {
                $this->primaryKey = $col['COLUMN_NAME'];
            }
        }

        if (empty($this->columns)) {
            throw new RuntimeException("테이블 {$table} 에 대한 컬럼 정보를 불러오지 못했습니다.");
        }
    }

    // ✅ 컬럼 & PK 정보 조회용
    public function getColumns()
    {
        return $this->columns;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    // ==================================================
    // 🔥 캐시 무효화 헬퍼 (이 테이블 관련 리스트 캐시 전부 삭제)
    // ==================================================
    private function invalidateListCaches()
    {
        if (!($this->cache instanceof Redis)) {
            return;
        }

        try {
            // 캐시 키 패턴: pass:crud:list:{table}:*
            $pattern = 'pass:crud:list:' . $this->table . ':*';
            $keys    = $this->cache->keys($pattern);  // 주의: KEYS는 규모 커지면 부담이지만, 여기선 OK

            if (!empty($keys)) {
                // del()은 배열도 받을 수 있음 (phpredis 기준)
                $this->cache->del($keys);
            }
        } catch (Exception $e) {
            // 캐시 무효화 실패해도 메인 로직은 계속 가도록 무시
        }
    }

    // ==================================================
    // INSERT
    //   - $data = ['컬럼명' => 값, ...]
    //   - PK(auto_increment)는 null/빈값이면 자동으로 제외
    //   - 성공 시: 이 테이블 관련 Redis 리스트 캐시 전부 삭제
    // ==================================================
    public function insert(array $data)
    {
        $insertColumns = array();
        $placeholders  = array();
        $params        = array();

        foreach ($data as $col => $value) {
            // 실제 테이블에 없는 컬럼은 무시
            if (!in_array($col, $this->columns, true)) {
                continue;
            }

            // auto_increment PK는 값이 없으면 INSERT 목록에서 제외
            if ($col === $this->primaryKey && ($value === null || $value === '')) {
                continue;
            }

            $insertColumns[] = "`{$col}`";
            $ph              = ":" . $col;
            $placeholders[]  = $ph;
            $params[$ph]     = $value;
        }

        if (empty($insertColumns)) {
            throw new RuntimeException("Insert 할 수 있는 유효한 컬럼이 없습니다.");
        }

        $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $insertColumns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $lastId = (int)$this->db->lastInsertId();

        // 👉 INSERT 성공 후 캐시 무효화 (다음 조회는 무조건 DB → 새로 캐시)
        $this->invalidateListCaches();

        return $lastId;
    }

    // ==================================================
    // UPDATE
    //   - $id : PK 값
    //   - $data = ['컬럼명' => 값, ...]
    //   - PK는 수정하지 않음
    //   - 성공 시: 이 테이블 관련 Redis 리스트 캐시 전부 삭제
    // ==================================================
    public function update($id, array $data)
    {
        if ($this->primaryKey === null) {
            throw new RuntimeException("PRIMARY KEY 가 없는 테이블은 update()를 사용할 수 없습니다.");
        }

        $setParts = array();
        $params   = array();

        foreach ($data as $col => $value) {
            if (!in_array($col, $this->columns, true)) {
                continue;
            }
            if ($col === $this->primaryKey) {
                continue; // PK는 바꾸지 않음
            }

            $ph          = ":" . $col;
            $setParts[]  = "`{$col}` = {$ph}";
            $params[$ph] = $value;
        }

        if (empty($setParts)) {
            // 변경할 값이 없는 경우
            return false;
        }

        $params[':pk'] = $id;

        $sql = "UPDATE `{$this->table}` 
                SET " . implode(', ', $setParts) . "
                WHERE `{$this->primaryKey}` = :pk";

        $stmt   = $this->db->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            // 👉 UPDATE 성공 후 캐시 무효화
            $this->invalidateListCaches();
        }

        return $result;
    }

    // ==================================================
    // DELETE
    //   - 성공 시: 이 테이블 관련 Redis 리스트 캐시 전부 삭제
    // ==================================================
    public function delete($id)
    {
        if ($this->primaryKey === null) {
            throw new RuntimeException("PRIMARY KEY 가 없는 테이블은 delete()를 사용할 수 없습니다.");
        }

        $sql  = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :pk";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':pk', $id);
        $result = $stmt->execute();

        if ($result) {
            // 👉 DELETE 성공 후 캐시 무효화
            $this->invalidateListCaches();
        }

        return $result;
    }

    // ==================================================
    // 단일 조회 (info)
    // ==================================================
    public function getById($id)
    {
        if ($this->primaryKey === null) {
            throw new RuntimeException("PRIMARY KEY 가 없는 테이블은 getById()를 사용할 수 없습니다.");
        }

        $sql  = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :pk LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':pk', $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    // ==================================================
    // 리스트 조회 (list) - 캐시 안 쓰는 순수 버전
    // ==================================================
    public function getList(array $conditions = array(), $orderBy = '', $limit = null, $offset = null)
    {
        $whereParts = array();
        $params     = array();

        foreach ($conditions as $col => $value) {
            if (!in_array($col, $this->columns, true)) {
                continue;
            }
            $ph           = ':w_' . $col;
            $whereParts[] = "`{$col}` = {$ph}";
            $params[$ph]  = $value;
        }

        $sql = "SELECT * FROM `{$this->table}`";

        if (!empty($whereParts)) {
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . $orderBy;
        }

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================================================
    // 리스트 조회 (list) - Redis 캐시 사용 버전
    //
    // 동작:
    //   1) Redis 없음 → getList() 바로 호출
    //   2) Redis에 캐시 있으면:
    //       - payload 구조:
    //           {
    //             "generated_at": timestamp,
    //             "row_count":   n,
    //             "data":        [...]
    //           }
    //       - generated_at 이 1시간(3600초) 이내인가?
    //       - 같은 조건으로 SELECT COUNT(*) 해서 row 수 비교
    //       - row 수 같으면 → 캐시 사용
    //       - 다르면 → DB에서 다시 조회 후 캐시 덮어쓰기
    //   3) Redis 캐시가 없거나 깨져 있으면 → DB 쿼리 후 캐시 저장
    // ==================================================
    public function getListCached(array $conditions = array(), $orderBy = '', $limit = null, $offset = null)
    {
        $this->lastListSource = null;

        // 0) Redis 객체가 없으면 캐시 사용 안 함 → 바로 DB 쿼리
        if (!($this->cache instanceof Redis)) {
            $this->lastListSource = 'db';
            return $this->getList($conditions, $orderBy, $limit, $offset);
        }

        // 1) 캐시 키 구성 (테이블 + 조건 + 정렬 + limit/offset)
        $cacheKeyData = array(
            'table'      => $this->table,
            'conditions' => $conditions,
            'orderBy'    => $orderBy,
            'limit'      => $limit,
            'offset'     => $offset,
        );
        // ✅ 테이블명을 prefix에 포함 → 이 테이블만 별도로 무효화 가능
        $cacheKey = 'pass:crud:list:' . $this->table . ':' . md5(json_encode($cacheKeyData));

        // 2) 허용 시간 (1시간 = 3600초)
        $maxAge = 3600;

        // 3) Redis 에서 캐시 존재 여부 확인
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false && $cached !== null) {
            $payload = json_decode($cached, true);

            if (
                is_array($payload) &&
                isset($payload['generated_at'], $payload['row_count'], $payload['data']) &&
                is_array($payload['data'])
            ) {
                $generatedAt = (int)$payload['generated_at'];
                $age         = time() - $generatedAt;

                // (1) 시간 조건: 1시간 이내인가?
                if ($age >= 0 && $age < $maxAge) {

                    // (2) row 수 비교용 쿼리: 같은 조건으로 COUNT(*) 수행
                    $whereParts = array();
                    $params     = array();

                    foreach ($conditions as $col => $value) {
                        if (!in_array($col, $this->columns, true)) {
                            continue;
                        }
                        $ph           = ':c_' . $col;
                        $whereParts[] = "`{$col}` = {$ph}";
                        $params[$ph]  = $value;
                    }

                    $countSql = "SELECT COUNT(*) AS cnt FROM `{$this->table}`";
                    if (!empty($whereParts)) {
                        $countSql .= " WHERE " . implode(' AND ', $whereParts);
                    }

                    $stmt = $this->db->prepare($countSql);
                    $stmt->execute($params);
                    $row     = $stmt->fetch(PDO::FETCH_ASSOC);
                    $dbCount = $row ? (int)$row['cnt'] : 0;

                    // (3) row 수가 동일하면 → 캐시 사용
                    if ($dbCount === (int)$payload['row_count']) {
                        $this->lastListSource = 'redis';
                        return $payload['data'];
                    }
                    // row 수 다르면 캐시 오래됨 → 아래에서 DB 재조회 후 캐시 덮어쓰기
                }
                // 시간이 1시간 이상 지났거나 payload 구조가 이상하면 → DB 재조회
            }
        }

        // 4) 여기까지 왔다면:
        //    - 캐시가 없거나
        //    - payload 구조 불량
        //    - 1시간 지남
        //    - row 수가 DB와 달라짐
        //    → DB에서 다시 조회하고 캐시를 덮어씀
        $data     = $this->getList($conditions, $orderBy, $limit, $offset);
        $rowCount = count($data);

        $payload = array(
            'generated_at' => time(),
            'row_count'    => $rowCount,
            'data'         => $data,
        );

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            $this->cache->set($cacheKey, $json);
            $this->cache->expire($cacheKey, $maxAge);
        }

        $this->lastListSource = 'db';
        return $data;
    }

    /**
     * 마지막 리스트 데이터 출처 조회용
     * @return string|null 'db' | 'redis' | null
     */
    public function getLastListSource()
    {
        return $this->lastListSource;
    }
}


// ==================================================
// 3. 실제 사용 예시 (테스트용)
// ==================================================

// 3-1) DB & Redis & 스키마 로더 준비
$dbConnection = new DBConnection();
$pdo          = $dbConnection->getDB();

// Redis 연결 시도 (없으면 null 로 놓고 캐시 없이 동작)
$redis = null;
try {
    if (class_exists('Redis')) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379, 0.5);
        // $redis->auth('your_redis_password');
        // $redis->select(0);
    }
} catch (Exception $e) {
    $redis = null;
}

// 스키마 로더
$schemaLoader = new GetAllTableNameAutoload($pdo, 'user_no', $redis);

// 3-2) 어떤 테이블을 대상으로 할지 선택 (?table=password)
$targetTable = isset($_GET['table']) && $_GET['table'] !== ''
    ? $_GET['table']
    : 'password';  // 기본값은 password 테이블

// 3-3) CRUD 인스턴스 생성 (Redis 전달)
$crud = new GenericCrud($pdo, $schemaLoader, $targetTable, $redis);

// 3-4) 정보 뽑아보기 (캐시 사용 버전)
$pk      = $crud->getPrimaryKey();
$columns = $crud->getColumns();
$rows    = $crud->getListCached([], $pk ? $pk . ' DESC' : '', null, 0);
$source  = $crud->getLastListSource();

// 3-5) 화면에 출력
echo "<h2>GenericCrud 테스트 - 테이블: {$targetTable}</h2>";
echo "<p>Primary Key: " . htmlspecialchars($pk ?? '없음', ENT_QUOTES, 'UTF-8') . "</p>";
echo "<p>List Source: " . htmlspecialchars($source ?? 'unknown', ENT_QUOTES, 'UTF-8') . "</p>";

echo "<h3>컬럼 목록</h3>";
echo '<pre>';
print_r($columns);
echo '</pre>';

echo "<h3>레코드 조회</h3>";
echo '<pre>';
print_r($rows);
echo '</pre>';
