<?php

// ✅ 1. 세션 시작 (다른 파일에서 include 되어도 한 번만 호출됨)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ (선택) Redis Stub 로드 – 필요하면 경로 맞춰서 사용
// require_once __DIR__ . '/../../connection/redis_stubs.php';

// ✅ 2. DBConnection 클래스 불러오기
// 현재 파일: /PASS/public/password_50_getAllTableNameColumnNameAutoload/password_50_getAllTableNameColumnNameAutoload.php
// DBConnection: /PASS/connection/DBConnection.php
require_once __DIR__ . '/../connection/DBConnection.php';


/**
 * GetAllTableNameAutoload 클래스
 *
 * - 세션 로그인 체크
 * - 전체 테이블 이름 조회
 * - 각 테이블의 컬럼 정보 조회
 * - (옵션) Redis 캐시 사용
 */
class GetAllTableNameAutoload
{
    /**
     * @var PDO DB 접속을 위한 PDO 객체
     */
    private $connection;

    /**
     * @var string 로그인 여부를 확인할 세션 키 이름 (예: 'user_no', 'userid' 등)
     */
    private $loginSessionKey;

    /**
     * @var mixed Redis 인스턴스 (phpredis / Predis 등)
     *           - 없어도 동작해야 하므로 강한 타입은 걸지 않음
     */
    private $cache;

    /**
     * @var string|null 마지막 데이터 출처 ( 'redis' | 'db' | null )
     *         - getAllTablesWithColumnsCached() 호출 시 설정
     */
    private $dataSource = null;  // [SOURCE FLAG]

    /**
     * 생성자
     *
     * @param PDO    $connection      이미 생성된 PDO 객체
     * @param string $loginSessionKey 로그인 상태 확인할 세션 키 이름 (기본값: 'uid')
     * @param mixed  $cache           (옵션) Redis 같은 캐시 객체
     */
   
    public function __construct(PDO $connection, $loginSessionKey = 'uid', $cache = null)
    {
        $this->connection      = $connection;
        $this->loginSessionKey = $loginSessionKey;
        $this->cache           = $cache;   // [REDIS] 캐시 객체 보관

        // ❌ 여기서는 더 이상 로그인 체크를 자동으로 하지 않는다.
        // $this->checkLogin();
    }
    /**
     * 세션으로 로그인 여부 확인
     * - 지정한 세션 키가 비어있으면 로그인 안 된 상태로 보고 리다이렉트
     */
    public function checkLogin()
    {
        if (
            !isset($_SESSION[$this->loginSessionKey]) ||
            $_SESSION[$this->loginSessionKey] === '' ||
            $_SESSION[$this->loginSessionKey] === null
        ) {
            $loginUrl = '/password_5_passwordRegister/password_5_passwordRegister_Route/password_5_passwordRegister_Route.php';
            header('Location: ' . $loginUrl);
            exit;
        }
    }
    /**
     * 현재 선택된 DB의 전체 테이블 이름 배열로 가져오기
     *
     * @return array 테이블 이름 배열, 실패 시 빈 배열
     */
    public function getAllTableNames()
    {
        try {
            $sql  = "SHOW TABLES";
            $stmt = $this->connection->query($sql);

            // SHOW TABLES 결과는 한 컬럼뿐이므로 FETCH_COLUMN 사용
            $tableNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return $tableNames;
        } catch (PDOException $e) {
            echo "테이블 이름을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
            return array();
        }
    }

    /**
     * 특정 테이블의 컬럼 목록 가져오기
     *
     * @param string $tableName 컬럼을 조회할 테이블 이름
     * @return array 컬럼 정보 배열 (컬럼명, 데이터 타입 등)
     */
    public function getColumnsByTable($tableName)
    {
        try {
            $sql = "
                SELECT 
                    COLUMN_NAME,
                    DATA_TYPE,
                    IS_NULLABLE,
                    COLUMN_KEY,
                    COLUMN_DEFAULT,
                    EXTRA
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :tableName
                ORDER BY ORDINAL_POSITION
            ";

            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':tableName', $tableName, PDO::PARAM_STR);
            $stmt->execute();

            // 각 행은 컬럼 하나에 대한 정보
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "컬럼 정보를 가져오는 중 오류가 발생했습니다 ({$tableName}): " . $e->getMessage();
            return array();
        }
    }

    /**
     * 전체 테이블 + 각 테이블의 컬럼 목록 한 번에 가져오기
     *
     * @return array
     *   예)
     *   [
     *     'users' => [
     *         ['COLUMN_NAME' => 'user_no', 'DATA_TYPE' => 'int', ...],
     *         ['COLUMN_NAME' => 'userid', ...],
     *         ...
     *     ],
     *     'password' => [
     *         ['COLUMN_NAME' => 'user_no', ...],
     *         ...
     *     ],
     *     ...
     *   ]
     */
    public function getAllTablesWithColumns()
    {
        $result     = array();
        $tableNames = $this->getAllTableNames(); // 이미 있는 메서드 재사용

        foreach ($tableNames as $tableName) {
            $result[$tableName] = $this->getColumnsByTable($tableName);
        }

        return $result;
    }

    /**
     * [REDIS] 전체 테이블 + 컬럼 정보를 Redis 캐시에서 가져오거나, 없으면 DB에서 조회 후 캐시
     *
     * - 캐시 구조:
     *   {
     *     "generated_at": 1732670000,    // 생성 시각 (time())
     *     "data": { ... 실제 테이블/컬럼 배열 ... }
     *   }
     *
     * - 동작:
     *   1) Redis에 값이 있고, generated_at 기준으로 5분(300초) 이내면 → 캐시 사용
     *   2) Redis에 값이 없거나, 5분 이상 지났으면 → DB에서 다시 조회 후 캐시에 덮어쓰기
     *
     * @return array 실제 테이블/컬럼 배열
     */
    public function getAllTablesWithColumnsCached()
    {
        // [REDIS] 캐시에 사용할 키 이름
        $cacheKey = 'pass:db_schema:tables_with_columns';

        // [REDIS] 최대 허용 시간(초) = 5분
        $maxAge = 300;

        // 기본값은 null → 호출마다 최신 출처로 덮어쓰기
        $this->dataSource = null;  // [SOURCE FLAG 초기화]

        // 1) Redis 객체가 제대로 들어와 있을 때만 캐시 시도
        if ($this->cache instanceof Redis) {
            $cached = $this->cache->get($cacheKey);

            if ($cached !== false && $cached !== null) {
                $payload = json_decode($cached, true);

                // 구조가 정상인지 확인
                if (
                    is_array($payload) &&
                    isset($payload['generated_at'], $payload['data']) &&
                    is_array($payload['data'])
                ) {
                    $generatedAt = (int)$payload['generated_at'];
                    $age         = time() - $generatedAt;

                    // 5분 이내라면 → 캐시 사용
                    if ($age >= 0 && $age < $maxAge) {
                        $this->dataSource = 'redis';
                        return $payload['data'];
                    }
                    // 5분 이상 지난 경우 → 캐시를 버리고 DB에서 새로 조회해서 덮어쓸 것
                }
                // 구조가 이상하면 그냥 무시하고 DB 조회로 이동
            }
        }

        // 2) 여기까지 왔다는 건
        //   - Redis를 사용하지 않거나
        //   - 캐시에 데이터가 없거나
        //   - 캐시 데이터가 손상되었거나
        //   - 생성된 지 5분 이상 지난 경우
        //  → 실제 DB에서 전체 스키마 조회
        $data = $this->getAllTablesWithColumns();

        // 출처 플래그
        $this->dataSource = 'db';

        // 3) 조회한 결과를 Redis에 다시 저장
        if ($this->cache instanceof Redis) {
            $payload = [
                'generated_at' => time(),
                'data'         => $data,
            ];

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

            if ($json !== false) {
                // set + expire 로 2중 안전장치 (TTL도 5분으로 맞춰줌)
                $this->cache->set($cacheKey, $json);
                $this->cache->expire($cacheKey, $maxAge);
            }
        }

        return $data;
    }

    /**
     * 마지막으로 getAllTablesWithColumnsCached() 가
     * 어디서 데이터를 가져왔는지 반환
     *
     * @return string|null 'redis' | 'db' | null
     */
    public function getLastDataSource()
    {
        return $this->dataSource;
    }

    /**
     * (옵션) 디버깅용: 테이블 이름만 바로 출력
     */
    public function printAllTableNames()
    {
        $tables = $this->getAllTableNames();

        echo "<pre>";
        echo "=== 데이터베이스 내 전체 테이블 목록 ===\n";
        foreach ($tables as $tableName) {
            echo $tableName . "\n";
        }
        echo "</pre>";
    }
}

/* ========================================================
   ⬇️ 여기부터는 "직접 이 파일에 접속했을 때만" 실행되는 테스트 코드
   - 다른 파일에서 require_once 할 때는 실행되지 않음
   ======================================================== */
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {

    // 🔍 세션 디버그 출력
    echo '=========== 세션 정보 ==========';
    if (!empty($_SESSION)) {
        echo '<pre>';
        print_r($_SESSION);
        echo '</pre>';
    } else {
        echo 'No session';
    }
    echo '===============================';

    // ✅ 3. DBConnection 인스턴스 생성 후 PDO 가져오기
    $dbConnection = new DBConnection();
    $pdo          = $dbConnection->getDB();

    // ✅ 4. (옵션) Redis 연결 시도
    $redis = null;

    try {
        // [REDIS] Redis 인스턴스 생성
        $redis = new Redis();

        // [REDIS] Redis 서버에 연결 (host, port, timeout)
        $redis->connect('127.0.0.1', 6379, 0.5);

        // [REDIS] 비밀번호를 쓰는 서버라면 여기서 auth 필요
        // $redis->auth('your_redis_password');

        // [REDIS] DB index 선택 (기본은 0번)
        // $redis->select(0);

    } catch (Exception $e) {
        $redis = null;
    }

    // ✅ 5. 테이블 이름 + 컬럼 정보 로더 생성
    //    - 로그인 세션 키가 실제로 무엇인지에 맞게 'user_no' 사용
    //    - 세 번째 인자에 Redis 객체를 넘겨줌 (없으면 null)
    $getAllTable = new GetAllTableNameAutoload($pdo, 'user_no', $redis);

    // ✅ 6. 전체 테이블 + 컬럼 정보를 (Redis 캐시 포함해서) 가져오기
    $tablesWithColumns = $getAllTable->getAllTablesWithColumnsCached();

    // ✅ 6-1. 이 데이터가 어디에서 왔는지 표시
    $dataSource = $getAllTable->getLastDataSource();
    $sourceText = 'Unknown source';

    if ($dataSource === 'redis') {
        $sourceText = 'Redis call memory cache';
    } elseif ($dataSource === 'db') {
        $sourceText = 'database call data';
    }

    // 상단에 한 줄 안내 출력
    echo '<h3 style="font-family:monospace;">[Source] ' . $sourceText . "</h3>\n";

    // ✅ 7. 보기 좋게 출력
    echo '<pre>';

    foreach ($tablesWithColumns as $tableName => $columns) {
        echo "테이블: {$tableName}\n";
        echo "------------------------\n";

        foreach ($columns as $col) {
            // 컬럼 이름 + 타입만 간단히 표시 (필요하면 다른 정보도 출력 가능)
            echo "  - {$col['COLUMN_NAME']} ({$col['DATA_TYPE']})";

            // PK, AUTO_INCREMENT 같은 속성도 간단히 붙여줌
            $extraInfo = array();

            if ($col['COLUMN_KEY'] === 'PRI') {
                $extraInfo[] = 'PK';
            }
            if ($col['IS_NULLABLE'] === 'NO') {
                $extraInfo[] = 'NOT NULL';
            }
            if (!is_null($col['COLUMN_DEFAULT'])) {
                $extraInfo[] = 'DEFAULT=' . $col['COLUMN_DEFAULT'];
            }
            if (!empty($col['EXTRA'])) {
                $extraInfo[] = $col['EXTRA'];
            }

            if (!empty($extraInfo)) {
                echo '  [' . implode(', ', $extraInfo) . ']';
            }

            echo "\n";
        }

        echo "\n";
    }

    echo '</pre>';
}
