<?php
// ===============================
// 관리자 비밀번호 변경 페이지 (View + 처리)
// ===============================

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --------------------------------
// DB 연결 클래스 로드 (DBConnection)
//  - 위치 예시: /connection/DBConnection.php
// --------------------------------
require_once __DIR__ . '/../../../../connection/DBConnection.php';

// DBConnection 인스턴스 생성 후 PDO 객체 가져오기
$dbConnection = new DBConnection();
$db           = $dbConnection->getDB(); // ← 여기서부터 $db는 PDO

// ---------------------------
// 로그인 체크 (user_no 기준)
// ---------------------------
$userNo = null;

// adminLogin()에서 설정한 세션 키 사용
// $_SESSION['user_no'], $_SESSION['userid'], $_SESSION['username'], $_SESSION['user_type']
if (isset($_SESSION['user_no'])) {
    $userNo = (int) $_SESSION['user_no'];
}

// 로그인 안 되어 있으면 로그인 화면으로 이동
if ($userNo === null || $userNo === 0) {
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php');
    exit;
}

// 상단 헤더에서 사용할 이름 표시용 세션 값
$sessionUsername = '';
if (isset($_SESSION['username'])) {
    $sessionUsername = $_SESSION['username'];
} elseif (isset($_SESSION['userid'])) {
    $sessionUsername = $_SESSION['userid'];
}

// 메시지 변수
$errorMessage   = '';
$successMessage = '';

// ---------------------------------------------
// POST 전송이 들어온 경우 (비밀번호 변경 처리)
// ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) 값 읽기 + 공백 제거
    $currentPassword = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $newPassword     = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $newPassword2    = isset($_POST['new_password_confirm']) ? trim($_POST['new_password_confirm']) : '';

    // 2) 기본 유효성 검사
    if ($currentPassword === '' || $newPassword === '' || $newPassword2 === '') {
        $errorMessage = '모든 항목을 입력해 주세요.';
    } elseif ($newPassword !== $newPassword2) {
        $errorMessage = '새 비밀번호와 확인 비밀번호가 일치하지 않습니다.';
    } elseif (mb_strlen($newPassword) < 8) {
        // 길이 규칙은 필요에 따라 조정
        $errorMessage = '새 비밀번호는 최소 8자 이상이어야 합니다.';
    } else {
        try {
            // 3) 현재 계정의 비밀번호 조회
            //    - users 테이블 구조에 맞게: user_no, userid, password
            $sql = "SELECT user_no, userid, password
                    FROM users
                    WHERE user_no = :user_no
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_no', $userNo, PDO::PARAM_INT);
            $stmt->execute();
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userRow) {
                $errorMessage = '사용자 정보를 찾을 수 없습니다.';
            } else {
                // 4) 현재 비밀번호 검증
                $passwordOk = false;

                // (1) bcrypt 해시(정상 케이스)인지 확인: algo가 0이 아니면 해시로 판단
                if (!empty($userRow['password']) && password_get_info($userRow['password'])['algo'] !== 0) {
                    if (password_verify($currentPassword, $userRow['password'])) {
                        $passwordOk = true;
                    }
                }

                // (2) 혹시 예전에 평문으로 저장된 경우 대비
                if (!$passwordOk && $userRow['password'] === $currentPassword) {
                    $passwordOk = true;
                }

                if (!$passwordOk) {
                    $errorMessage = '현재 비밀번호가 일치하지 않습니다.';
                } else {
                    // 5) 새 비밀번호 해시 생성
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

                    // 6) 비밀번호 업데이트
                    $updateSql = "UPDATE users
                                  SET password   = :password,
                                      updated_at = NOW()
                                  WHERE user_no  = :user_no";

                    $updateStmt = $db->prepare($updateSql);
                    $updateStmt->bindValue(':password', $newHash, PDO::PARAM_STR);
                    $updateStmt->bindValue(':user_no', $userNo, PDO::PARAM_INT);
                    $updateStmt->execute();

                    // TODO: users_update_log 같은 로그 테이블이 있다면
                    //       여기서 함께 INSERT 해주면 됨.

                    $successMessage = '비밀번호가 정상적으로 변경되었습니다.';
                }
            }
        } catch (Exception $e) {
            // ⚠️ 디버깅용: 실제 원인 확인 위해 잠깐 에러 내용을 같이 띄워보자
            $errorMessage = '비밀번호 변경 처리 중 오류가 발생했습니다. (DEBUG: '
                . $e->getMessage() . ')';
            // 실제 운영 시에는 위 줄 대신 다음 한 줄만 두는 걸 추천:
            // $errorMessage = '비밀번호 변경 처리 중 오류가 발생했습니다.';
        }
    }
}

// 현재 로그인한 아이디를 상단에 보여주기 (선택)
$currentUserid = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    
    <link rel="stylesheet" href="/assets/app.css">
<meta charset="UTF-8">
    <title>관리자 비밀번호 변경</title>
    <!-- 같은 폴더에 있는 CSS -->
    <link rel="stylesheet" href="password_2_passwordChange_view_admin.css?v=20251128">
</head>

<body>

    <div class="page-wrapper">

        <div class="change-card">

            <h1 class="page-title">관리자 비밀번호 변경</h1>

            <?php if ($currentUserid !== ''): ?>
                <p class="current-user">
                    현재 로그인 계정 :
                    <strong><?php echo htmlspecialchars($currentUserid, ENT_QUOTES, 'UTF-8'); ?></strong>
                </p>
            <?php endif; ?>

            <!-- 에러 / 성공 메시지 영역 -->
            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>


            <form method="post"ㅌ
                class="change-form"
                id="passwordChangeForm"
                autocomplete="off">


                <!-- 현재 비밀번호 -->
                <div class="form-group">
                    <label for="current_password">현재 비밀번호</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            required
                            placeholder="현재 비밀번호를 입력하세요">
                        <button type="button"
                            class="toggle-password-btn"
                            data-target="current_password">
                            보기
                        </button>
                    </div>
                </div>

                <!-- 새 비밀번호 -->
                <div class="form-group">
                    <label for="new_password">새 비밀번호</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            required
                            placeholder="새 비밀번호 (최소 8자)">
                        <button type="button"
                            class="toggle-password-btn"
                            data-target="new_password">
                            보기
                        </button>
                    </div>
                    <small class="help-text">
                        영문/숫자/특수문자를 조합하면 더 안전합니다.
                    </small>
                </div>

                <!-- 새 비밀번호 확인 -->
                <div class="form-group">
                    <label for="new_password_confirm">새 비밀번호 확인</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="new_password_confirm"
                            name="new_password_confirm"
                            required
                            placeholder="새 비밀번호를 다시 입력하세요">
                        <button type="button"
                            class="toggle-password-btn"
                            data-target="new_password_confirm">
                            보기
                        </button>
                    </div>
                    <small id="passwordMatchMessage" class="help-text"></small>
                </div>

                <!-- 버튼 영역 -->
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        비밀번호 변경
                    </button>

                    <button type="button"
                        class="btn-secondary"
                        onclick="window.location.href='/index.php';">
                        메인으로
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- 같은 폴더의 JS -->
    <script src="password_2_passwordChange_view_admin.js?v=20251128"></script>
</body>

</html>