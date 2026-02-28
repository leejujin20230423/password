<?php

/**
 * ==========================================================
 *  세션 시작 및 로그인 사용자 확인
 * ==========================================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// 로그인 여부 체크 (한 번만 선언해두고 밑에서 사용)
$isLoggedIn = isset($_SESSION['userid']) || isset($_SESSION['user_no']);



/**
 * 이 페이지는 "로그인된 사용자 전용" 페이지로 가정한다.
 * - users 테이블의 PK: user_no
 * - password 테이블의 FK: user_no_Fk
 */

if (empty($_SESSION['user_no'])) {
    // 로그인 안 되어 있으면 로그인 페이지로 보냄
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php');
    exit;
}

// 현재 로그인한 사용자 PK (users.user_no)
$currentUserNo   = (int)$_SESSION['user_no'];
$sessionUsername = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';

/**
 * ==========================================================
 * 0. AES 암호화/복호화 설정
 *    - password.encrypted_password 컬럼과 연동
 * ==========================================================
 */

// 사용할 암호화 알고리즘 (대칭키 방식)
const PASSWORD_CIPHER_METHOD = 'AES-256-CBC';

// ⚠ 실제 서비스에서는 .env / 설정 파일로 분리하는 것이 안전함
const PASSWORD_SECRET_KEY = 'change-this-to-your-own-strong-secret-key-32byte';
const PASSWORD_SECRET_IV  = 'change-this-iv-16b';

/**
 * 비밀번호 암호화 함수 (평문 → 암호문 base64 문자열)
 */
function encryptPasswordAES(string $plain): string
{
    if ($plain === '') {
        return '';
    }

    $key = hash('sha256', PASSWORD_SECRET_KEY, true);               // 32 bytes
    $iv  = substr(hash('sha256', PASSWORD_SECRET_IV, true), 0, 16); // 16 bytes

    $cipherRaw = openssl_encrypt(
        $plain,
        PASSWORD_CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($cipherRaw === false) {
        return '';
    }

    return base64_encode($cipherRaw);
}

/**
 * 비밀번호 복호화 함수 (암호문 base64 → 평문)
 */
function decryptPasswordAES(string $encryptedBase64): string
{
    if ($encryptedBase64 === '') {
        return '';
    }

    $cipherRaw = base64_decode($encryptedBase64, true);
    if ($cipherRaw === false) {
        return '';
    }

    $key = hash('sha256', PASSWORD_SECRET_KEY, true);
    $iv  = substr(hash('sha256', PASSWORD_SECRET_IV, true), 0, 16);

    $plain = openssl_decrypt(
        $cipherRaw,
        PASSWORD_CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return $plain === false ? '' : $plain;
}

/**
 * ==========================================================
 * 1. 이 페이지에서 사용할 테이블명
 * ==========================================================
 */
$tableName = 'password';

/**
 * ==========================================================
 * 2. Generic CRUD 라이브러리 로드
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
    $redis = null;
}

// 스키마 로더 (두 번째 인자 'user_no' 는 로그인 세션 키 이름)
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

// 평문 비밀번호 (초기에는 비워둠, AJAX로 채움)
$decryptedPassword = '';

// 검색어: 사이트 주소 / 메모에 포함된 텍스트 검색
$searchKeyword = trim($_GET['q'] ?? '');

// 현재 폼이 수정 모드인지 여부
$isEdit = false;

/**
 * ==========================================================
 * 5. POST 처리 (등록 / 수정 / 삭제 / 보기 / AJAX 복호화)
 * ==========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * -------------------------------
     * 5-0) AJAX: 로그인 비밀번호 검증 + 지정된 password 레코드 복호화
     *      (암호 보기 버튼에서 사용)
     * -------------------------------
     */
    if (isset($_POST['ajax']) && $_POST['ajax'] === 'decrypt_password') {
        header('Content-Type: application/json; charset=utf-8');

        // 1) 세션 로그인 체크
        if (empty($_SESSION['user_no'])) {
            echo json_encode([
                'ok'  => false,
                'msg' => '로그인이 필요합니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $currentUserNoAjax = (int)$_SESSION['user_no'];
        $loginPassword     = $_POST['login_password'] ?? '';
        $passwordId        = (int)($_POST['password_idno'] ?? 0);

        // 2) 기본 유효성 검사
        if ($loginPassword === '' || $passwordId <= 0) {
            echo json_encode([
                'ok'  => false,
                'msg' => '잘못된 요청입니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3) users 테이블에서 현재 로그인 사용자의 비밀번호 해시 조회
        //    ⚠️ 비밀번호 컬럼명이 다르면 아래 password 를 실제 컬럼명으로 변경
        $sql = "SELECT password
                FROM users
                WHERE user_no = :user_no
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_no', $currentUserNoAjax, PDO::PARAM_INT);
        $stmt->execute();
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $hash = $userRow['password'] ?? null;
        if (!$hash || !password_verify($loginPassword, $hash)) {
            echo json_encode([
                'ok'  => false,
                'msg' => '로그인 비밀번호가 일치하지 않습니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 4) password 테이블에서 해당 레코드 조회 (본인 소유인지 확인)
        $row = $crud->getById($passwordId);

        if (
            !$row ||
            (int)($row['user_no_Fk'] ?? 0) !== $currentUserNoAjax
        ) {
            echo json_encode([
                'ok'  => false,
                'msg' => '해당 비밀번호를 찾을 수 없습니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 5) AES 복호화
        $plain = decryptPasswordAES($row['encrypted_password'] ?? '');

        if ($plain === '') {
            echo json_encode([
                'ok'  => false,
                'msg' => '복호화에 실패했습니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 6) 정상 응답
        echo json_encode([
            'ok'    => true,
            'plain' => $plain
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 5-0-b) AJAX: 복사 버튼 전용
     *   - 로그인 비밀번호 재확인 없이
     *   - 세션 user_no 와 password.user_no_Fk 만 확인 후 복호화
     */
    if (isset($_POST['ajax']) && $_POST['ajax'] === 'decrypt_password_copy') {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user_no'])) {
            echo json_encode([
                'ok'  => false,
                'msg' => '로그인이 필요합니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $currentUserNoAjax = (int)$_SESSION['user_no'];
        $passwordId        = (int)($_POST['password_idno'] ?? 0);

        if ($passwordId <= 0) {
            echo json_encode([
                'ok'  => false,
                'msg' => '잘못된 요청입니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 해당 password 레코드 조회
        $row = $crud->getById($passwordId);

        // 본인 소유인지 확인
        if (
            !$row ||
            (int)($row['user_no_Fk'] ?? 0) !== $currentUserNoAjax
        ) {
            echo json_encode([
                'ok'  => false,
                'msg' => '해당 비밀번호를 찾을 수 없습니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // AES 복호화
        $plain = decryptPasswordAES($row['encrypted_password'] ?? '');

        if ($plain === '') {
            echo json_encode([
                'ok'  => false,
                'msg' => '복호화에 실패했습니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok'    => true,
            'plain' => $plain
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * -------------------------------
     * 여기부터는 일반 폼 submit (create/update/delete/view)
     * -------------------------------
     */
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
        $data = [
            'user_no_Fk'         => $currentUserNo,                  // FK: users.user_no
            'storename'          => $_POST['storename'] ?? '',       // ✅ 매장명 추가
            'category'           => $_POST['category'] ?? '',
            'site_url'           => $_POST['site_url'] ?? '',
            'login_id'           => $_POST['login_id'] ?? '',
            'encrypted_password' => $encrypted,
            'contact_phone'      => $_POST['contact_phone'] ?? '',   // 연락처
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
                'storename'     => $_POST['storename'] ?? '',   // ✅ 매장명 추가
                'category'      => $_POST['category'] ?? '',
                'site_url'      => $_POST['site_url'] ?? '',
                'login_id'      => $_POST['login_id'] ?? '',
                'contact_phone' => $_POST['contact_phone'] ?? '',
                'memo'          => $_POST['memo'] ?? '',
            ];

            // 2) 새 비밀번호 입력값
            $newPlain = $_POST['encrypted_password'] ?? '';

            // 3) 새 비밀번호가 비어있지 않다면 → 기존 값 대신 교체
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
            // 트랜잭션 시작
            $pdo->beginTransaction();

            try {
                // 1. password_share 테이블에서 공유 기록 삭제
                //    - 공유가 없을 수도 있으므로 rowCount() 체크로 에러 처리하지 않음
                $deleteShareQuery = "
                DELETE FROM password_share
                WHERE password_idno_Fk = :password_idno_Fk
            ";
                $stmt2 = $pdo->prepare($deleteShareQuery);
                $stmt2->bindValue(':password_idno_Fk', $id, PDO::PARAM_INT);
                $stmt2->execute();
                // ❌ 여기서는 rowCount() 체크 안 함
                // $deletedShare = $stmt2->rowCount();

                // 2. password 테이블에서 비밀번호 삭제
                $deletePasswordQuery = "
                DELETE FROM password
                WHERE password_idno = :password_idno
            ";
                $stmt1 = $pdo->prepare($deletePasswordQuery);
                $stmt1->bindValue(':password_idno', $id, PDO::PARAM_INT);
                $stmt1->execute();

                // 삭제된 비밀번호가 있는지 확인 (여기는 반드시 체크)
                if ($stmt1->rowCount() === 0) {
                    throw new Exception(
                        "비밀번호 삭제 실패: 해당 비밀번호가 존재하지 않거나 이미 삭제되었습니다."
                    );
                }

                // 트랜잭션 커밋
                $pdo->commit();

                // 삭제 후 리디렉션
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } catch (Exception $e) {
                // 트랜잭션 롤백
                $pdo->rollBack();
                echo "오류 발생: " . $e->getMessage();
                exit;
            }
        }

        // 만약 id가 비어있다면 삭제 실패
        echo "삭제할 비밀번호가 없습니다.";
        exit;
    }


    /**
     * -------------------------------
     * 5-4) 보기 (view)
     *   - 여기서는 복호화하지 않고
     *   - 단지 $editRow 만 채워서 폼에 값 뿌리고 수정 모드로 전환.
     * -------------------------------
     */
    elseif ($action === 'view') {

        $id = $_POST['password_idno'] ?? null;

        if ($id !== null && $id !== '') {
            // 1) PK 기준으로 한 행 조회
            $editRow = $crud->getById($id);
            $isEdit  = !empty($editRow);
        }
        // view 는 리다이렉트 없이 그대로 HTML 렌더링
    }
}

/**
 * ==========================================================
 * 6. 화면에 뿌릴 리스트 데이터
 * ==========================================================
 */

$pk = $crud->getPrimaryKey();  // password_idno (PK 컬럼명)

// 리스트 데이터가 어디에서 왔는지 표시용 ('db', 'redis', 'db-search')
$listSource   = null;
$passwordRows = [];

if ($searchKeyword !== '') {

    /**
     * 🔎 검색 모드
     */
    $like = '%' . $searchKeyword . '%';

    $sql = "SELECT *
        FROM `{$tableName}`
        WHERE user_no_Fk = :user_no
          AND (
                site_url  LIKE :kw
             OR memo      LIKE :kw
             OR storename LIKE :kw 
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
     */
    $orderBy    = 'category ASC' . ($pk ? ', ' . $pk . ' DESC' : '');
    $conditions = ['user_no_Fk' => $currentUserNo];

    $passwordRows = $crud->getListCached($conditions, $orderBy);
    $listSource   = $crud->getLastListSource();  // 'db' or 'redis'
}

// view 액션이 실행되었다면 $editRow 가 채워져 있을 것
$isEdit = !empty($editRow);

?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>Password 등록</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    
    <link rel="stylesheet" href="/assets/app.css">
<?php
    // 세션이 아직 시작 안 되었으면 시작
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 로그인 시 세팅해 둔 asset_version 사용, 없으면 기본값
    $assetVersion = isset($_SESSION['asset_version'])
        ? $_SESSION['asset_version']
        : '20251204_01'; // 첫 접속/비로그인용 기본 버전
    ?>

    <!-- ✅ 헤더 전용 CSS -->
    <link rel="stylesheet"
          href="/password_3_header/password_3_header_view/password_3_header_view_admin/password_3_header_view_admin.css?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ✅ 사이드바 전용 CSS -->
    <link rel="stylesheet"
          href="/password_4_sidebar/password_4_sidebar_view/password_4_sidebar_view_admin/password_4_sidebar_view_admin.css?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ✅ 비밀번호 등록 화면 전용 CSS -->
    <link rel="stylesheet"
          href="/password_5_passwordRegister/password_5_passwordRegister_View/password_5_passwordRegister_View_admin/password_5_passwordRegister_View_admin.css?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>



<body>
    <div class="layout">


        <div class="layout">

            <!-- 상단 헤더 있던곳-->
            <?php
            require_once $_SERVER['DOCUMENT_ROOT'] . '/password_3_header/password_3_header_view/password_3_header_view_admin/password_3_header_view_admin.php';
            ?>

            <!-- ✅ 모바일에서 사이드바 열렸을 때 바탕 클릭용 오버레이 -->
            <div id="sidebarOverlay" class="sidebar-overlay"></div>

            <div class="main">

                <?php
                // 헤더, 사이드바 include
                require_once $_SERVER['DOCUMENT_ROOT'] . '/password_4_sidebar/password_4_sidebar_view/password_4_sidebar_view_admin/password_4_sidebar_view_admin.php';
                ?>



                <!-- 가운데 등록 / 수정 폼 -->
                <section class="content">
  <div class="container">
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
                                placeholder="개발용 / 개인용 / 업무용 / 매장관리 / 세무관리 (직접 입력도 가능)"
                                required
                                onfocus="this.select();"
                                onclick="this.select();">

                            <datalist id="category_list">
                                <option value="매장관리">매장관리</option>
                                <option value="세무관리">세무관리</option>
                                <option value="개발용">개발용</option>
                                <option value="개인용">개인용</option>
                                <option value="업무용">업무용</option>
                            </datalist>
                        </div>


                        <div class="form-group">
                            <label for="storename" id="storenameLabel">항목</label>
                            <input
                                type="text"
                                id="storename"
                                name="storename"
                                value="<?php echo htmlspecialchars($editRow['storename'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="예: 넷플릭스 / 더존 / 쿠팡 / 카카오톡 / 등"
                                required
                                onfocus="this.select();"
                                onclick="this.select();">
                        </div>

                        <div class="form-group">
                            <label for="site_url">사이트 주소 또는 프로그램명을 입력</label>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text"
                                    id="site_url"
                                    name="site_url"
                                    style="flex:1;"
                                    value="<?php echo htmlspecialchars($editRow['site_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                    placeholder="사이트의 url 주소를 입력하세요.">
                                <!-- URL 이동 버튼 -->
                                <button type="button"
                                    onclick="openUrl(document.getElementById('site_url').value);">
                                    이동
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="login_id">사이트 아이디</label>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text"
                                    id="login_id"
                                    name="login_id"
                                    style="flex:1;"
                                    value="<?php echo htmlspecialchars($editRow['login_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                    placeholder="사이트의 로그인 아이디를 입력해주세요.">

                                <?php if ($isEdit && !empty($editRow)): ?>
                                    <!-- 보기(수정) 모드에서만 아이디 복사 버튼 표시 -->
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
                                    <input type="password"
                                        id="password_encrypted_view"
                                        readonly
                                        data-encrypted="<?php echo htmlspecialchars($editRow['encrypted_password'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        value="<?php echo htmlspecialchars($editRow['encrypted_password'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        style="flex:1;">

                                    <!-- ✅ 암호/평문 토글 버튼 -->
                                    <button type="button" id="togglePasswordView">
                                        암호 보기
                                    </button>

                                    <!-- ✅ 평문 비밀번호 복사 버튼 -->
                                    <button type="button" id="copyPasswordBtn">
                                        복사
                                    </button>
                                </div>

                                <!-- ✅ 평문 비밀번호는 hidden에 숨겨두고 JS에서만 사용 -->
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
                                value=""
                                <?php if ($isEdit): ?>
                                placeholder="사이트의 변경된 비밀번호를 입력후 수정하기 클릭하세요."
                                <?php endif; ?>>
                        </div>
                        <div class="form-group">
                            <label for="contact_phone">연락처(전화번호)</label>

                            <div style="display:flex; gap:8px; align-items:center;">
                                <input
                                    type="tel"
                                    id="contact_phone"
                                    name="contact_phone"
                                    style="flex:1;"
                                    placeholder="예: 010-1234-5678"
                                    value="<?php echo htmlspecialchars($editRow['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    required>
                                <?php if ($isEdit && !empty($editRow['contact_phone'])): ?>
                                    <?php
                                    $telCleanForm = preg_replace('/\D+/', '', $editRow['contact_phone']);
                                    ?>
                                    <?php if (!empty($telCleanForm)): ?>
                                        <a href="tel:<?php echo htmlspecialchars($telCleanForm, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="button">전화</button>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="memo">메모(특이사항을 입력해주세요.)</label>
                            <textarea id="memo" name="memo" rows="4"><?php
                                                                        echo htmlspecialchars($editRow['memo'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                        ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button
                                type="submit"
                                class="<?php echo $isEdit ? 'btn-update' : ''; ?>">
                                <?php echo $isEdit ? '수정하기' : '등록'; ?>
                            </button>

                            <button type="button" class="btn-secondary"
                                onclick="window.location.href='<?php echo htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ENT_QUOTES, 'UTF-8'); ?>';">
                                새로 작성
                            </button>
                        </div>

                    </form>
                  </div>
</section>

                <!-- 우측 리스트 -->
                <aside class="list-panel">
                    <h2 style="display: flex;">등록된 비밀번호 목록

                    </h2>
                    <span style="font-size: 14px; color: red;">(사이트의 비밀번호를 보려면 보기버튼을 눌러주세요.)
                        <!-- (디버깅용) 리스트 데이터 출처 표시 -->

                    </span>
                    <!-- 🔎 검색 박스: 사이트 주소 / 메모 검색 -->
                    <div class="search-box">
                        <form method="get" action="">
                            <input type="text"
                                name="q"
                                placeholder="매장명 / 사이트 주소 / 메모에서 검색"
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

                    <!-- 테이블 가로 스크롤용 래퍼 -->
                    <div class="table-wrapper">
                        <table class="password-table" id="passwordTable">
                            <thead>
                                <tr>
                                    <th>순번</th>
                                    <th>구분</th>
                                    <th>항목</th>
                                    <th>사이트 주소/프로그램명 </th>
                                    <th>아이디</th>
                                    <th>연락처</th>
                                    <th>메모</th>
                                    <th class="col-actions">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (!empty($passwordRows)): ?>
                                    <?php $seq = 1; ?>
                                    <?php foreach ($passwordRows as $row): ?>
                                        <tr>
                                            <td><?php echo $seq++; ?></td>

                                            <td><?php echo htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($row['storename'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>

                                            <td>
                                                <div style="display:flex; gap:6px; align-items:center;">
                                                    <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                        <?php echo htmlspecialchars($row['site_url'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                    <button type="button"
                                                        onclick="openUrl('<?php echo htmlspecialchars($row['site_url'], ENT_QUOTES, 'UTF-8'); ?>');">
                                                        이동
                                                    </button>
                                                </div>
                                            </td>

                                            <td><?php echo htmlspecialchars($row['login_id'], ENT_QUOTES, 'UTF-8'); ?></td>

                                            <!-- 연락처 + 전화걸기 버튼 -->
                                            <td>
                                                <?php if (!empty($row['contact_phone'])): ?>
                                                    <div style="display:flex; gap:6px; align-items:center;">
                                                        <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                            <?php echo htmlspecialchars($row['contact_phone'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                        <?php
                                                        $telClean = preg_replace('/\D+/', '', $row['contact_phone']);
                                                        ?>
                                                        <?php if (!empty($telClean)): ?>
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

                                            <!-- 보기 / 삭제 -->
                                            <td class="col-actions">
                                                <!-- 보기 -->
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
    </div>
    <script src="password_5_passwordRegister_View_admin.js?v=20251128_03"></script>

</body>

</html>