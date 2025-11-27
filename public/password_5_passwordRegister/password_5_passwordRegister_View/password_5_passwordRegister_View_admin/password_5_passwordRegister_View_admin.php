<?php

/**
 * ==========================================================
 *  세션 시작 및 로그인 사용자 확인
 * ==========================================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 이 페이지는 "로그인된 사용자 전용" 페이지로 가정한다.
 * - users 테이블의 PK: user_no
 * - password 테이블의 FK: user_no_Fk
 * => 따라서 로그인 시 세션에 user_no 를 넣어두고,
 *    여기서는 그 값을 가져다가 FK 로 사용한다.
 *
 *  예: 로그인 시
 *      $_SESSION['user_no']  = $user['user_no'];
 *      $_SESSION['userid']   = $user['userid'];
 *      $_SESSION['username'] = $user['username'];
 */

if (empty($_SESSION['user_no'])) {
    // 로그인 안 되어 있으면 로그인 페이지로 보냄
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php');
    exit;
}

// 현재 로그인한 사용자 PK (users.user_no)
$currentUserNo = (int)$_SESSION['user_no'];

$sessionUsername = (string)$_SESSION['username'];



/**
 * ==========================================================
 * 0. AES 암호화/복호화 설정
 *    - password.encrypted_password 컬럼과 연동
 * ==========================================================
 */

// 사용할 암호화 알고리즘 (대칭키 방식)
const PASSWORD_CIPHER_METHOD = 'AES-256-CBC';

// ⚠ 실제 서비스에서는 .env / 설정 파일로 분리하는 것이 안전함
//   여기서는 예제이므로 하드코딩
const PASSWORD_SECRET_KEY = 'change-this-to-your-own-strong-secret-key-32byte';
const PASSWORD_SECRET_IV  = 'change-this-iv-16b';

/**
 * 비밀번호 암호화 함수 (평문 → 암호문 base64 문자열)
 *
 * @param string $plain 사용자가 입력한 평문 비밀번호
 * @return string base64 인코딩된 암호문
 */
function encryptPasswordAES(string $plain): string
{
    // 빈 문자열이면 굳이 암호화하지 않고 빈 문자열 반환
    if ($plain === '') {
        return '';
    }

    // 1) 키와 IV(초기화 벡터) 생성
    //    - sha256 해시의 binary 결과(32바이트)를 키로 사용
    $key = hash('sha256', PASSWORD_SECRET_KEY, true);               // 32 bytes
    //    - sha256 해시에서 앞 16바이트만 잘라서 IV 로 사용
    $iv  = substr(hash('sha256', PASSWORD_SECRET_IV, true), 0, 16); // 16 bytes

    // 2) OPENSSL_RAW_DATA 옵션으로 "바이너리 암호문"을 얻는다.
    $cipherRaw = openssl_encrypt(
        $plain,
        PASSWORD_CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($cipherRaw === false) {
        // 암호화 실패 시 빈 문자열 반환 (실제 서비스에서는 예외 처리 권장)
        return '';
    }

    // 3) 바이너리 암호문을 그대로 DB에 넣기 어렵기 때문에
    //    base64 문자열로 인코딩해서 저장한다.
    return base64_encode($cipherRaw);
}

/**
 * 비밀번호 복호화 함수 (암호문 base64 → 평문)
 *
 * @param string $encryptedBase64 DB에 저장된 base64 문자열
 * @return string 복호화된 평문 비밀번호
 */
function decryptPasswordAES(string $encryptedBase64): string
{
    if ($encryptedBase64 === '') {
        return '';
    }

    // 1) base64 를 다시 바이너리로 디코딩
    $cipherRaw = base64_decode($encryptedBase64, true);
    if ($cipherRaw === false) {
        return '';
    }

    // 2) 암호화 때 사용한 것과 동일한 키/IV 재구성
    $key = hash('sha256', PASSWORD_SECRET_KEY, true);
    $iv  = substr(hash('sha256', PASSWORD_SECRET_IV, true), 0, 16);

    // 3) 복호화 수행
    $plain = openssl_decrypt(
        $cipherRaw,
        PASSWORD_CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    // 실패 시 빈 문자열 반환
    return $plain === false ? '' : $plain;
}

/**
 * ==========================================================
 * 1. 이 페이지에서 사용할 테이블명
 *    - GenericCrud 가 이 테이블을 기준으로 동작
 * ==========================================================
 */
$tableName = 'password';

/**
 * ==========================================================
 * 2. Generic CRUD 라이브러리 로드
 *    - 내부에서 DBConnection, GetAllTableNameAutoload 를 사용
 * ==========================================================
 */
require_once __DIR__ . '/../../../password_60_CRUD/password_60_CRUD.php';

/**
 * ==========================================================
 * 3. DB & Redis & 스키마 로더 생성
 * ==========================================================
 */
$dbConnection = new DBConnection();
$pdo          = $dbConnection->getDB();

// (선택) Redis 연결
$redis = null;
try {
    if (class_exists('Redis')) {
        $redis = new Redis();
        // 로컬 개발 환경 기준 127.0.0.1:6379
        $redis->connect('127.0.0.1', 6379, 0.5);
        // $redis->auth('your_redis_password'); // 필요시
        // $redis->select(0);                   // 필요시
    }
} catch (Exception $e) {
    // Redis 연결에 실패해도, 캐시 없이 DB만 이용하도록 null 로 둠
    $redis = null;
}

// 스키마 로더
//  - 두 번째 인자 'user_no' 는 로그인 세션 키 이름
//  - GetAllTableNameAutoload 내부에서 로그인 체크에 사용될 수 있음
$schemaLoader = new GetAllTableNameAutoload($pdo, 'user_no', $redis);

// GenericCrud 인스턴스 생성
$crud = new GenericCrud($pdo, $schemaLoader, $tableName, $redis);

/**
 * ==========================================================
 * 4. 화면에서 사용할 변수들
 * ==========================================================
 */

// "보기" 버튼을 눌렀을 때 선택된 한 행 데이터
$editRow = null;

// 복호화된 평문 비밀번호 (보기 모드에서 "보기" 버튼 눌렀을 때 사용)
$decryptedPassword = '';

// 검색어: 사이트 주소 / 메모에 포함된 텍스트 검색
$searchKeyword = trim($_GET['q'] ?? '');

// 현재 폼이 수정 모드인지 여부를 나중에 판단하기 위해 사용
$isEdit = false;


/**
 * ==========================================================
 * 5. POST 처리 (등록 / 수정 / 삭제 / 보기)
 *     - action 값 기준으로 분기
 * ==========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /**
     * -------------------------------
     * 5-1) 신규 등록 (create)
     * -------------------------------
     */
    if ($action === 'create') {

        // 1) 사용자가 입력한 평문 비밀번호
        $plainPassword = $_POST['encrypted_password'] ?? '';

        // 2) AES로 암호화 (base64 문자열 반환)
        $encrypted = encryptPasswordAES($plainPassword);

        // 3) INSERT 에 사용할 데이터 배열 생성
        //    - user_no_Fk 에 현재 로그인한 사용자 PK를 기록
        $data = [
            'user_no_Fk'         => $currentUserNo,                  // FK: users.user_no
            'category'           => $_POST['category'] ?? '',
            'site_url'           => $_POST['site_url'] ?? '',
            'login_id'           => $_POST['login_id'] ?? '',
            'encrypted_password' => $encrypted,
            'contact_phone'      => $_POST['contact_phone'] ?? '',   // ✅ 신규: 연락처
            'memo'               => $_POST['memo'] ?? '',
        ];


        // 4) GenericCrud 로 INSERT 실행
        $crud->insert($data);

        // 5) F5 새로고침으로 인한 중복 POST 방지
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    /**
     * -------------------------------
     * 5-2) 수정 (update)
     * -------------------------------
     */
    elseif ($action === 'update') {

        // 어떤 레코드를 수정할지: PK (password_idno)
        $id = $_POST['password_idno'] ?? null;

        if ($id !== null && $id !== '') {
            // 1) 기본 컬럼들 (카테고리, 사이트 주소, 아이디, 메모)
            $data = [
                'category'      => $_POST['category'] ?? '',
                'site_url'      => $_POST['site_url'] ?? '',
                'login_id'      => $_POST['login_id'] ?? '',
                'contact_phone' => $_POST['contact_phone'] ?? '',    // ✅ 신규: 연락처
                'memo'          => $_POST['memo'] ?? '',
            ];


            // 2) 새 비밀번호 입력값
            $newPlain = $_POST['encrypted_password'] ?? '';

            // 3) 새 비밀번호가 비어있지 않다면 → 기존 값 대신 교체
            //    비어있다면 → 비밀번호는 그대로 두고 다른 필드만 수정
            if (trim($newPlain) !== '') {
                $data['encrypted_password'] = encryptPasswordAES($newPlain);
            }

            // 4) UPDATE 실행
            $crud->update($id, $data);
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    /**
     * -------------------------------
     * 5-3) 삭제 (delete)
     * -------------------------------
     */
    elseif ($action === 'delete') {

        $id = $_POST['password_idno'] ?? null;

        if ($id !== null && $id !== '') {
            // (필요하다면 여기서, 이 레코드의 user_no_Fk 가
            //  $currentUserNo 와 같은지 확인 후 삭제하는 안전장치 추가 가능)
            $crud->delete($id);
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    /**
     * -------------------------------
     * 5-4) 보기 (view)
     *   - 이 경우에는 리다이렉트 없이 같은 페이지에서
     *     $editRow, $decryptedPassword 를 채워서
     *     폼에 값을 뿌려주고 "수정 모드" 로 전환하는 용도
     * -------------------------------
     */
    elseif ($action === 'view') {

        $id = $_POST['password_idno'] ?? null;

        if ($id !== null && $id !== '') {
            // 1) PK 기준으로 한 행 조회
            $editRow = $crud->getById($id);

            // 2) 암호화된 비밀번호가 있다면 복호화해서 변수에 저장
            if (!empty($editRow) && isset($editRow['encrypted_password'])) {
                $decryptedPassword = decryptPasswordAES($editRow['encrypted_password']);
            }

            // 3) 이 아래에서 $isEdit 를 true 로 바꿔서
            //    HTML 폼이 "수정 모드"로 보이게 만들 수 있음
            $isEdit = true;
        }
        // ※ view 는 리다이렉트 없이 그대로 HTML 렌더링
    }
}


/**
 * ==========================================================
 * 6. 화면에 뿌릴 리스트 데이터
 *    - 검색어(q)가 있으면: site_url / memo 에 LIKE 검색
 *    - 없으면: 현재 사용자(user_no_Fk) 기준 전체 목록
 *              (category ASC, 같은 category 안에서는 최근순)
 * ==========================================================
 */

$pk = $crud->getPrimaryKey();  // password_idno (PK 컬럼명)

// 리스트 데이터가 어디에서 왔는지 표시용 ('db', 'redis', 'db-search')
$listSource   = null;
$passwordRows = [];

if ($searchKeyword !== '') {

    /**
     * 🔎 검색 모드
     *  - 조건:
     *      user_no_Fk = 현재 로그인 사용자
     *      AND (site_url LIKE '%q%' OR memo LIKE '%q%')
     */

    $like = '%' . $searchKeyword . '%';

    $sql = "SELECT *
            FROM `{$tableName}`
            WHERE user_no_Fk = :user_no
              AND (
                    site_url LIKE :kw
                 OR memo     LIKE :kw
              )
            ORDER BY category ASC, {$pk} DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_no', $currentUserNo, PDO::PARAM_INT);
    $stmt->bindValue(':kw',      $like,          PDO::PARAM_STR);
    $stmt->execute();

    $passwordRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $listSource   = 'db-search';
} else {

    /**
     * 기본 목록 모드
     *  - 조건:
     *      user_no_Fk = 현재 로그인 사용자
     *  - 정렬:
     *      category 오름차순
     *      + 같은 category 안에서는 password_idno 내림차순(최근순)
     *  - GenericCrud 의 getListCached() 를 사용하여
     *    Redis 캐시를 우선 활용
     */

    $orderBy    = 'category ASC' . ($pk ? ', ' . $pk . ' DESC' : '');
    $conditions = ['user_no_Fk' => $currentUserNo];

    $passwordRows = $crud->getListCached($conditions, $orderBy);
    $listSource   = $crud->getLastListSource();  // 'db' or 'redis'
}

// 위에서 view 액션이 실행되었다면 $isEdit 가 true 로 세팅됨
// 그 값을 이용해서 HTML 폼에서 "등록" vs "수정" 버튼을 조건부로 바꿔줄 수 있다.
$isEdit = !empty($editRow);

?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>Password 등록</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="password_5_passwordRegister_View_admin.css">
    <style>
        /* 검색박스 살짝만 스타일 */
        .search-box {
            padding: 8px 0 12px 0;
        }

        .search-box form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .search-box input[type="text"] {
            flex: 1;
            padding: 6px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .search-box button {
            padding: 6px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }

        .search-reset-btn {
            background: #eee;
        }
    </style>
</head>

<body>
    <div class="layout">

        <!-- 상단 헤더 -->
        <header class="header">
            <h1>Password 관리 시스템</h1>
            <div class="header-right">
                <span class="user-info">관리자: <?php echo htmlspecialchars($sessionUsername, ENT_QUOTES, 'UTF-8'); ?></span>

                <button type="button"
                    class="logout-button"
                    onclick="window.location.href='/password_9_logout/password_9_logout_Route/password_9_logout_Route.php';">
                    로그아웃
                </button>
            </div>
        </header>

        <!-- (디버깅용) 리스트 데이터 출처 표시 -->
        <div style="padding:8px 16px; font-size:12px; color:#555;">
            List Source:
            <strong>
                <?php
                if ($searchKeyword !== '') {
                    echo 'Database search';
                } elseif ($listSource === 'redis') {
                    echo 'Redis cache';
                } elseif ($listSource === 'db') {
                    echo 'Database query';
                } else {
                    echo 'Unknown';
                }
                ?>
            </strong>
        </div>

        <div class="main">

            <!-- 좌측 사이드바 -->
            <aside class="sidebar">
                <nav>
                    <ul>
                        <li class="active">비밀번호 등록</li>
                        <li>비밀번호 검색</li>
                        <li>환경 설정</li>
                    </ul>
                </nav>
            </aside>

            <!-- 가운데 등록 / 수정 폼 -->
            <section class="content">
                <h2>비밀번호 <?php echo $isEdit ? '수정' : '등록'; ?></h2>

                <form id="passwordForm" method="post" action="">
                    <!-- 모드: create / update -->
                    <input type="hidden" name="action"
                        value="<?php echo $isEdit ? 'update' : 'create'; ?>">

                    <!-- PK -->
                    <input type="hidden" name="password_idno" id="password_idno"
                        value="<?php echo htmlspecialchars($editRow['password_idno'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-group">
                        <label for="category">구분</label>

                        <input
                            type="text"
                            id="category"
                            name="category"
                            list="category_list"
                            value="<?php echo htmlspecialchars($editRow['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="개발용 / 개인용 / 업무용 / 매장관리 (직접 입력도 가능)"
                            required
                            onfocus="this.select();"
                            onclick="this.select();">

                        <datalist id="category_list">
                            <option value="개발용">개발용</option>
                            <option value="개인용">개인용</option>
                            <option value="업무용">업무용</option>
                            <option value="매장관리">매장관리</option>
                        </datalist>
                    </div>




                    <div class="form-group">
                        <label for="site_url">사이트 주소</label>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="text"
                                id="site_url"
                                name="site_url"
                                style="flex:1;"
                                value="<?php echo htmlspecialchars($editRow['site_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required>
                            <!-- ✅ 폼에서도 URL 이동 버튼 -->
                            <button type="button"
                                onclick="openUrl(document.getElementById('site_url').value);">
                                이동
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="login_id">아이디</label>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="text"
                                id="login_id"
                                name="login_id"
                                style="flex:1;"
                                value="<?php echo htmlspecialchars($editRow['login_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required>

                            <?php if ($isEdit && !empty($editRow)): ?>
                                <!-- ✅ 보기(수정) 모드에서만 아이디 복사 버튼 표시 -->
                                <button type="button" id="copyLoginIdBtn">
                                    복사
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>



                    <?php if ($isEdit && !empty($editRow)): ?>
                        <!-- 저장된 비밀번호 (암호화 값 / 평문 토글 + 복사) -->
                        <div class="form-group">
                            <label for="password_encrypted_view">
                                저장된 비밀번호
                                <span style="font-size:11px; color:#888;">
                                    (기본은 암호화된 값, 버튼으로 평문 보기/복사)
                                </span>
                            </label>

                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text"
                                    id="password_encrypted_view"
                                    readonly
                                    data-encrypted="<?php echo htmlspecialchars($editRow['encrypted_password'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    value="<?php echo htmlspecialchars($editRow['encrypted_password'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    style="flex:1;">
                                <!-- ✅ 암호/평문 토글 버튼 -->
                                <button type="button" id="togglePasswordView">
                                    암호 보기
                                </button>
                                <!-- ✅ 복호화된 비밀번호 복사 버튼 -->
                                <button type="button" id="copyPasswordBtn">
                                    복사
                                </button>
                            </div>

                            <!-- 평문 비밀번호는 hidden에 숨겨두고 JS에서만 사용 -->
                            <input type="hidden"
                                id="password_plain_hidden"
                                value="<?php echo htmlspecialchars($decryptedPassword, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    <?php endif; ?>


                    <div class="form-group">
                        <label for="encrypted_password">
                            <?php echo $isEdit ? '새 비밀번호' : '비밀번호'; ?>
                            <?php if ($isEdit): ?>
                                <span style="font-size:11px; color:#888;">
                                    (변경 시에만 새 비밀번호를 입력하세요)
                                </span>
                            <?php endif; ?>
                        </label>
                        <!-- 새 비밀번호 입력칸: 평문 입력 → 서버에서 AES 암호화 후 저장 -->
                        <input type="password"
                            id="encrypted_password"
                            name="encrypted_password"
                            value="">
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">연락처(전화번호)</label>
                        <input type="tel"
                            id="contact_phone"
                            name="contact_phone"
                            placeholder="예: 010-1234-5678"
                            value="<?php echo htmlspecialchars($editRow['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>


                    <div class="form-group">
                        <label for="memo">메모</label>
                        <textarea id="memo" name="memo" rows="4"><?php
                                                                    echo htmlspecialchars($editRow['memo'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                    ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit">
                            <?php echo $isEdit ? '수정하기' : '등록'; ?>
                        </button>

                        <button type="button" class="btn-secondary"
                            onclick="window.location.href='<?php echo htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ENT_QUOTES, 'UTF-8'); ?>';">
                            새로 작성
                        </button>
                    </div>
                </form>
            </section>

            <!-- 우측 리스트 -->
            <aside class="list-panel">
                <h2>등록된 비밀번호 목록</h2>

                <!-- 🔎 검색 박스: 사이트 주소 / 메모 검색 -->
                <div class="search-box">
                    <form method="get" action="">
                        <input type="text"
                            name="q"
                            placeholder="사이트 주소 또는 메모에서 검색"
                            value="<?php echo htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit">검색</button>
                        <?php if ($searchKeyword !== ''): ?>
                            <button type="button"
                                class="search-reset-btn"
                                onclick="window.location.href='<?php echo htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ENT_QUOTES, 'UTF-8'); ?>';">
                                초기화
                            </button>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- ✅ 테이블 가로 스크롤용 래퍼 추가 -->
                <div class="table-wrapper">
                    <table class="password-table" id="passwordTable">
                        <thead>
                            <tr>
                                <th>순번</th>
                                <th>구분</th>
                                <th>사이트 주소</th>
                                <th>아이디</th>
                                <th>연락처</th>
                                <th>메모</th>
                                <th>동작</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!empty($passwordRows)): ?>
                                <?php $seq = 1; ?>
                                <?php foreach ($passwordRows as $row): ?>
                                    <tr>
                                        <!-- ✅ 순번 (현재 정렬 기준에 따른 1,2,3...) -->
                                        <td><?php echo $seq++; ?></td>

                                        <td><?php echo htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8'); ?></td>

                                        <td>
                                            <div style="display:flex; gap:6px; align-items:center;">
                                                <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                    <?php echo htmlspecialchars($row['site_url'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <!-- URL 이동 버튼 -->
                                                <button type="button"
                                                    onclick="openUrl('<?php echo htmlspecialchars($row['site_url'], ENT_QUOTES, 'UTF-8'); ?>');">
                                                    이동
                                                </button>
                                            </div>
                                        </td>

                                        <td><?php echo htmlspecialchars($row['login_id'], ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- ✅ 연락처 + 전화걸기 버튼 -->
                                        <td>
                                            <?php if (!empty($row['contact_phone'])): ?>
                                                <div style="display:flex; gap:6px; align-items:center;">
                                                    <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                        <?php echo htmlspecialchars($row['contact_phone'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                    <?php
                                                    // 전화번호에서 숫자만 추출해서 tel 링크용으로 사용
                                                    $telClean = preg_replace('/\D+/', '', $row['contact_phone']);
                                                    ?>
                                                    <?php if (!empty($telClean)): ?>
                                                        <!-- 📱 휴대폰에서 누르면 전화 앱 실행 (tel:) -->
                                                        <a href="tel:<?php echo htmlspecialchars($telClean, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <button type="button">전화</button>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td><?php echo htmlspecialchars($row['memo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <!-- 보기 (폼에 값 채우기) -->
                                            <form method="post" action="" style="display:inline;">
                                                <input type="hidden" name="action" value="view">
                                                <input type="hidden" name="password_idno"
                                                    value="<?php echo (int)$row['password_idno']; ?>">
                                                <button type="submit">보기</button>
                                            </form>

                                            <!-- 삭제 -->
                                            <form method="post" action="" style="display:inline; margin-left:4px;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="password_idno"
                                                    value="<?php echo (int)$row['password_idno']; ?>">
                                                <button type="submit" onclick="return confirm('정말 삭제할까요?');">
                                                    삭제
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;">등록된 비밀번호가 없습니다.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </aside>


        </div><!-- /.main -->
    </div><!-- /.layout -->

    <script src="password_5_passwordRegister_View_admin.js?v=20251127"></script>
</body>

</html>