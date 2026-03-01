<?php
// public/password_60_CRUD/password_60_CRUD.php
// --------------------------------------------------
// 공통 Generic CRUD + Redis 캐시 연동
//  - 이 파일은 "클래스 정의만" 가지고 있는 라이브러리 파일
//  - 실제 사용은 view/route 파일에서:
//      1) DBConnection, GetAllTableNameAutoload, Redis 준비
//      2) $crud = new GenericCrud($pdo, $schemaLoader, $tableName, $redis);
//      3) $crud->insert()/update()/delete()/getListCached() 등 호출
// -------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB 연결 클래스

require_once __DIR__ . '/../connection/DBConnection.php';


// 테이블/컬럼 자동 로더
require_once __DIR__ . '/../password_50_getAllTableNameColumnNameAutoload/password_50_getAllTableNameColumnNameAutoload.php';

// (옵션) Redis 스텁 (개발환경에서 phpredis 없을 때 에러 방지용)
// require_once __DIR__ . '/../../connection/redis_stubs.php';

/**
 * 범용 CRUD 클래스
 *
 *  특징
 *  - 테이블명만 바꿔서 재사용 가능
 *  - GetAllTableNameAutoload 를 사용해 컬럼/PK 정보를 자동으로 로딩
 *  - getListCached() 호출 시 Redis 캐시를 사용
 *  - INSERT / UPDATE / DELETE 후에는 항상 해당 테이블 리스트 캐시를 전부 무효화
 */
class GenericCrud
{
    /** @var PDO */
    private $db;

    /** @var GetAllTableNameAutoload */
    private $schemaLoader;

    /** @var string 현재 대상 테이블명 */
    private $table;

    /** @var string[] 현재 테이블 컬럼 목록 */
    private $columns = array();

    /** @var string|null PK 컬럼명 */
    private $primaryKey = null;

    /** @var Redis|null Redis 인스턴스 (없으면 null) */
    private $cache;

    /** @var string|null 마지막 getListCached() 결과 출처 ('db' | 'redis' | null) */
    private $lastListSource = null;

    /**
     * 🔧 Redis 캐시 설정
     *  - REDIS_CACHE_TTL         : 개별 키 TTL (10분)
     *  - REDIS_GLOBAL_FLUSH_KEY  : 마지막 전역 flush 시각을 저장하는 키
     *  - REDIS_GLOBAL_FLUSH_SEC  : 전역 flush 주기 (10분)
     *
     * ⚠ 같은 Redis DB 를 다른 서비스(cash, foodzone, jayu 등)와 공유한다면
     *    전역 flush(FLUSHALL)는 그쪽 캐시도 모두 지우니 주의!
     */
    private const REDIS_CACHE_TTL        = 600;                    // 10분 (초)
    private const REDIS_GLOBAL_FLUSH_KEY = 'pass:global:last_flush';
    private const REDIS_GLOBAL_FLUSH_SEC = 600;                    // 10분마다 전체 flush

    /**
     * @param PDO                     $db            DB 커넥션
     * @param GetAllTableNameAutoload $schemaLoader  스키마 로더
     * @param string                  $table         사용할 테이블명
     * @param Redis|null              $cache         (옵션) Redis 인스턴스
     */
    public function __construct(
        PDO $db,
        GetAllTableNameAutoload $schemaLoader,
        string $table,
        $cache = null
    ) {
        $this->db           = $db;
        $this->schemaLoader = $schemaLoader;
        $this->cache        = $cache;

        $this->setTable($table);
    }

    /**
     * 테이블 설정 + 컬럼/PK 재로딩
     */
    public function setTable(string $table): void
    {
        $this->table = $table;

        // 스키마 로더에서 컬럼 정보 가져오기
        $colsInfo = $this->schemaLoader->getColumnsByTable($table);

        $this->columns    = array();
        $this->primaryKey = null;

        foreach ($colsInfo as $col) {
            $this->columns[] = $col['COLUMN_NAME'];

            // PRIMARY KEY 인 컬럼 찾기
            if ($col['COLUMN_KEY'] === 'PRI') {
                $this->primaryKey = $col['COLUMN_NAME'];
            }
        }

        if (empty($this->columns)) {
            throw new RuntimeException("테이블 {$table} 의 컬럼 정보를 불러오지 못했습니다.");
        }
    }

    /** 컬럼 목록 반환 */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** PK 컬럼명 반환 (없으면 null) */
    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    /* =========================================================
     * INSERT
     *   - $data = ['컬럼명' => 값, ...]
     *   - PK(auto_increment)는 값이 없으면 INSERT 목록에서 제외
     *   - INSERT 후에는 항상 리스트 캐시를 전부 무효화
     * ======================================================= */
    public function insert(array $data): int
    {
        $insertColumns = array();
        $placeholders  = array();
        $params        = array();

        foreach ($data as $col => $value) {
            // 실제 테이블에 없는 컬럼은 무시
            if (!in_array($col, $this->columns, true)) {
                continue;
            }

            // auto_increment PK는 값이 없으면 제외
            if ($col === $this->primaryKey && ($value === null || $value === '')) {
                continue;
            }

            $insertColumns[] = "`{$col}`";
            $ph              = ':' . $col;
            $placeholders[]  = $ph;
            $params[$ph]     = $value;
        }

        if (empty($insertColumns)) {
            throw new RuntimeException('INSERT 할 컬럼이 없습니다.');
        }

        $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $insertColumns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        // ✅ INSERT 후, 이 테이블에 대한 리스트 캐시 전체 무효화
        $this->invalidateListCaches();

        return (int)$this->db->lastInsertId();
    }

    /* =========================================================
     * UPDATE
     *   - $id : PK 값
     *   - $data = ['컬럼명' => 값, ...]
     *   - PK는 수정하지 않음
     *   - UPDATE 성공 후 리스트 캐시 무효화
     * ======================================================= */
    public function update($id, array $data): bool
    {
        if ($this->primaryKey === null) {
            throw new RuntimeException('PRIMARY KEY 없는 테이블은 update 불가');
        }

        $setParts = array();
        $params   = array();

        foreach ($data as $col => $value) {
            if (!in_array($col, $this->columns, true)) {
                continue;
            }
            if ($col === $this->primaryKey) {
                // PK는 변경하지 않음
                continue;
            }

            $ph          = ':' . $col;
            $setParts[]  = "`{$col}` = {$ph}";
            $params[$ph] = $value;
        }

        if (empty($setParts)) {
            // 변경할 내용이 없음
            return false;
        }

        $params[':pk'] = $id;

        $sql = "UPDATE `{$this->table}`
                SET " . implode(', ', $setParts) . "
                WHERE `{$this->primaryKey}` = :pk";

        $stmt   = $this->db->prepare($sql);
        $result = $stmt->execute($params);

        // ✅ UPDATE 성공 시, 리스트 캐시 무효화
        if ($result) {
            $this->invalidateListCaches();
        }

        return $result;
    }

    /* =========================================================
     * DELETE
     *   - $id : PK 값
     *   - DELETE 성공 후 리스트 캐시 무효화
     * ======================================================= */
    public function delete($id): bool
    {
        if ($this->primaryKey === null) {
            throw new RuntimeException('PRIMARY KEY 없는 테이블은 delete 불가');
        }

        $sql  = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :pk";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':pk', $id);
        $result = $stmt->execute();

        // ✅ DELETE 성공 시, 리스트 캐시 무효화
        if ($result) {
            $this->invalidateListCaches();
        }

        return $result;
    }

    /* =========================================================
     * 단일 조회 (PRIMARY KEY 기준)
     * ======================================================= */
    public function getById($id): ?array
    {
        if ($this->primaryKey === null) {
            throw new RuntimeException('PRIMARY KEY 없는 테이블은 getById 불가');
        }

        $sql  = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :pk LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':pk', $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /* =========================================================
     * 순수 리스트 조회 (캐시 X)
     *   - $conditions = ['컬럼명' => 값]  → WHERE 조건
     *   - $orderBy    = 'password_idno DESC' 등
     *   - $limit, $offset → 페이징
     * ======================================================= */
    public function getList(
        array $conditions = [],
        string $orderBy = '',
        ?int $limit = null,
        ?int $offset = null
    ): array {
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
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
            if ($offset !== null) {
                $sql .= ' OFFSET ' . (int)$offset;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
     * 리스트 조회 (Redis 캐시 사용)
     *
     * 캐시 동작 방식
     *  - 캐시 키: pass:crud:list:{테이블명}:{조건/정렬/limit/offset 해시}
     *  - payload 구조:
     *      {
     *        "generated_at": timestamp,
     *        "row_count":   n,
     *        "data":        [...]
     *      }
     *  - 사용 시:
     *      1) 캐시가 REDIS_CACHE_TTL 이내 AND
     *      2) 동일 조건으로 SELECT COUNT(*) 한 결과 == row_count 이면
     *         → "추가 로우 없음"으로 보고 캐시 사용
     *      3) 아니면 DB 재조회 + 캐시 덮어쓰기
     *
     *  - INSERT / UPDATE / DELETE 후에는 invalidateListCaches()로
     *    이 테이블의 리스트 캐시를 전부 삭제
     *
     *  - 추가: maybeGlobalFlushEvery10Minutes() 에서
     *    10분마다 Redis 전체 FLUSHALL 수행 (주의!)
     * ======================================================= */
    public function getListCached(
        array $conditions = [],
        string $orderBy = '',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $this->lastListSource = null;

        // 🔔 1) 10분마다 Redis 전체 FLUSHALL 시도 (주의!)
        $this->maybeGlobalFlushEvery10Minutes();

        // 2) Redis가 없으면 그냥 DB 쿼리
        if (!($this->cache instanceof Redis)) {
            $this->lastListSource = 'db';
            return $this->getList($conditions, $orderBy, $limit, $offset);
        }

        // 캐시 키 데이터 → md5 로 압축
        $cacheKeyData = [
            'table'      => $this->table,
            'conditions' => $conditions,
            'orderBy'    => $orderBy,
            'limit'      => $limit,
            'offset'     => $offset,
        ];

        // ✅ 테이블명을 prefix 로 붙여서, invalidateListCaches() 패턴과 일치시키기
        //   예) pass:crud:list:password:ab12cd34...
        $cacheKey = 'pass:crud:list:' . $this->table . ':' . md5(json_encode($cacheKeyData));

        $maxAge = self::REDIS_CACHE_TTL; // 🔧 10분
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

                // TTL 이내인 경우에만 row 수 비교
                if ($age >= 0 && $age < $maxAge) {

                    // 같은 조건으로 COUNT(*) 쿼리
                    $whereParts = [];
                    $params     = [];

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
                        $countSql .= ' WHERE ' . implode(' AND ', $whereParts);
                    }

                    $stmt = $this->db->prepare($countSql);
                    $stmt->execute($params);
                    $row     = $stmt->fetch(PDO::FETCH_ASSOC);
                    $dbCount = $row ? (int)$row['cnt'] : 0;

                    // DB row 수와 캐시 row_count 가 같으면 → 캐시 사용
                    if ($dbCount === (int)$payload['row_count']) {
                        $this->lastListSource = 'redis';
                        return $payload['data'];
                    }
                }
            }
        }

        // 여기까지 오면 캐시가 없거나, 오래됐거나, row 수가 달라진 경우 → DB 재조회
        $data     = $this->getList($conditions, $orderBy, $limit, $offset);
        $rowCount = count($data);

        $payload = [
            'generated_at' => time(),
            'row_count'    => $rowCount,
            'data'         => $data,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json !== false && ($this->cache instanceof Redis)) {
            // TTL 함께 설정
            $this->cache->set($cacheKey, $json);
            $this->cache->expire($cacheKey, $maxAge);
        }

        $this->lastListSource = 'db';
        return $data;
    }

    /** 마지막 리스트 데이터가 어디서 왔는지( 'db' | 'redis' ) */
    public function getLastListSource(): ?string
    {
        return $this->lastListSource;
    }

    /* =========================================================
     * INSERT / UPDATE / DELETE 후 리스트 캐시 무효화
     *  - 이 테이블에 대해 만들어진 모든 list 캐시 키 삭제
     *  - 키 패턴: pass:crud:list:{table}:*
     * ======================================================= */
    private function invalidateListCaches(): void
    {
        if (!($this->cache instanceof Redis)) {
            return;
        }

        try {
            $pattern = 'pass:crud:list:' . $this->table . ':*';
            $keys    = $this->cache->keys($pattern);   // 주의: 규모 커지면 SCAN 으로 교체 가능

            if (!empty($keys)) {
                // phpredis 는 배열도 del() 인자로 받을 수 있음
                $this->cache->del($keys);
            }
        } catch (Exception $e) {
            // 캐시 무효화는 실패해도 메인 로직은 계속 가도록 조용히 무시
        }
    }

    /* =========================================================
     * 🔥 10분마다 Redis 전체 FLUSHALL
     *
     *  - pass:global:last_flush 키에 마지막 실행 시각(UNIX timestamp) 저장
     *  - 지금 시각 - 마지막 시각 >= 10분 이면:
     *      1) FLUSHALL
     *      2) pass:global:last_flush 에 지금 시각 다시 기록
     *
     * ⚠️ 주의: 같은 Redis DB 를 cash/foodzone/jayu 등과 공유하면
     *    그쪽 캐시까지 전부 날아감. 가능하면 pass 전용 Redis DB 사용.
     * ======================================================= */
    private function maybeGlobalFlushEvery10Minutes(): void
    {
        if (!($this->cache instanceof Redis)) {
            return;
        }

        try {
            $now      = time();
            $key      = self::REDIS_GLOBAL_FLUSH_KEY;
            $interval = self::REDIS_GLOBAL_FLUSH_SEC;

            $last = (int)$this->cache->get($key);

            // 마지막 flush 기록이 없거나, 10분 이상 지났으면 전체 flush
            if ($last === 0 || ($now - $last) >= $interval) {
                // 모든 DB, 모든 키 삭제 (⚠)
                $this->cache->flushAll();

                // flush 후, 다시 마지막 시각 기록
                $this->cache->set($key, (string)$now);
            }
        } catch (Exception $e) {
            // 캐시 정리 실패해도 메인 로직은 그대로 진행
        }
    }
}
