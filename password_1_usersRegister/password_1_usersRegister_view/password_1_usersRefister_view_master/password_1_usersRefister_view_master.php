<?php

/**
 * password_1_usersRegister_view_user.php
 *
 * 일반 사용자용 회원가입 페이지
 * - users 테이블에 새 사용자 INSERT
 * - 비밀번호는 password_hash() (bcrypt) 로 저장
 * - 회원가입 성공 시 "로그인 화면으로 이동" 버튼 표시
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================================================
 * 1. DB 연결 클래스 로드
 *    현재 파일 위치:
 *    /public/password_1_usersRegister/password_1_usersRegister_view/password_1_usersRegister_view_user/...
 *
 *    PASS 루트까지 4단계 올라간 후 /connection/DBConnection.php
 * ========================================================== */
require_once __DIR__ . '/../../../../connection/DBConnection.php';

/* ==========================================================
 * 2. DBConnection 객체 생성
 * ========================================================== */
$dbConnection = new DBConnection();
$pdo          = $dbConnection->getDB();

/* ==========================================================
 * 3. 화면에서 사용할 변수들 (초기값)
 *    - 폼에 다시 채워 넣을 값
 *    - 에러/성공 메시지
 * ========================================================== */

// 폼 입력값 기억용 (에러 발생 시 다시 보여주기)
$input_userid   = '';
$input_username = '';
$input_email    = '';
$input_phone    = '';
$input_birth    = '';
$input_gender   = '';

// 메시지
$errorMessage   = '';
$successMessage = '';

/* ==========================================================
 * 4. POST 요청 처리 (회원가입 폼 전송 시)
 * ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 4-1) 폼에서 넘어온 값들 읽기
    $input_userid   = trim($_POST['userid'] ?? '');
    $input_username = trim($_POST['username'] ?? '');
    $plain_password = trim($_POST['password'] ?? '');
    $input_email    = trim($_POST['email'] ?? '');
    $input_phone    = trim($_POST['phone'] ?? '');
    $input_birth    = trim($_POST['birthdate'] ?? '');
    $input_gender   = trim($_POST['gender'] ?? '');

    // 4-2) 필수값 검증 (아이디 / 이름 / 비밀번호)
    if ($input_userid === '' || $input_username === '' || $plain_password === '') {
        $errorMessage = '아이디, 이름, 비밀번호는 필수 입력 항목입니다.';
    } else {

        try {
            // 4-3) 아이디 중복 체크
            $sql  = "SELECT COUNT(*) AS cnt
                     FROM users
                     WHERE userid = :userid";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':userid', $input_userid, PDO::PARAM_STR);
            $stmt->execute();
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);
            $cnt  = $row ? (int)$row['cnt'] : 0;

            if ($cnt > 0) {
                // 이미 같은 아이디가 있을 때
                $errorMessage = '이미 사용 중인 아이디입니다. 다른 아이디를 입력해 주세요.';
            } else {
                // 4-4) 비밀번호 해시 생성 (bcrypt)
                $hashedPassword = password_hash($plain_password, PASSWORD_BCRYPT);

                // 4-5) INSERT 할 값들 정리
                //      users 테이블 구조 (중요 필드만 사용)
                //      - user_no (PK, AUTO_INCREMENT) → INSERT 목록에서 제외
                //      - userid, username, password, email, phone, birthdate, gender, status, user_type
                $status   = 1;        // 기본: 활성 계정
                $userType = 'master';   // 마스터 사용자

                $sql = "
                    INSERT INTO users (
                        userid,              -- 로그인 아이디
                        username,            -- 이름
                        password,            -- bcrypt 해시 비밀번호
                        email,               -- 이메일
                        phone,               -- 전화번호
                        birthdate,           -- 생년월일 (NULL 가능)
                        gender,              -- 성별 (NULL 가능)
                        status,              -- 계정 상태 (1 = 활성)
                        user_type            -- 권한 유형 ('user')
                    ) VALUES (
                        :userid,
                        :username,
                        :password,
                        :email,
                        :phone,
                        :birthdate,
                        :gender,
                        :status,
                        :user_type
                    )
                ";

                $stmt = $pdo->prepare($sql);

                // 필수/일반 문자열 바인딩
                $stmt->bindValue(':userid',   $input_userid,   PDO::PARAM_STR);
                $stmt->bindValue(':username', $input_username, PDO::PARAM_STR);
                $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
                $stmt->bindValue(':email',    $input_email,    PDO::PARAM_STR);
                $stmt->bindValue(':phone',    $input_phone,    PDO::PARAM_STR);

                // birthdate / gender 는 빈문자열이면 NULL 로 저장
                if ($input_birth === '') {
                    $stmt->bindValue(':birthdate', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':birthdate', $input_birth, PDO::PARAM_STR);
                }

                if ($input_gender === '') {
                    $stmt->bindValue(':gender', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':gender', $input_gender, PDO::PARAM_STR);
                }

                // 상태/권한
                $stmt->bindValue(':status',    $status,   PDO::PARAM_INT);
                $stmt->bindValue(':user_type', $userType, PDO::PARAM_STR);

                // 4-6) 실제 INSERT 실행
                $stmt->execute();

                // 4-7) 성공 메시지 세팅
                $successMessage = '회원가입이 완료되었습니다. 아래 버튼을 눌러 로그인 화면으로 이동해 주세요.';

                // 폼 값은 비워 주기 (성공 후 재입력 방지)
                $input_userid   = '';
                $input_username = '';
                $input_email    = '';
                $input_phone    = '';
                $input_birth    = '';
                $input_gender   = '';
            }
        } catch (PDOException $e) {
            // DB 에러 발생 시 사용자에게는 일반 메시지, 로그에는 상세 내용
            $errorMessage = '회원가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.';
            // 개발 중에는 에러 확인용으로 아래 주석을 잠깐 풀어도 됨
            // $errorMessage .= ' (디버그: ' . $e->getMessage() . ')';
        }
    }
}

/* ==========================================================
 * 5. HTML 출력 영역
 * ========================================================== */
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>회원가입 - PASS 시스템</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    
    <link rel="stylesheet" href="/assets/app.css">
<style>
        /* 간단한 중앙 카드 레이아웃 (원하면 나중에 별도 CSS 파일로 분리) */
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .register-container {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            padding: 24px 28px 28px 28px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
        }

        .register-title {
            margin: 0 0 4px 0;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
        }

        .register-subtitle {
            margin: 0 0 16px 0;
            font-size: 13px;
            color: #6b7280;
            text-align: center;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            margin-bottom: 4px;
            color: #374151;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 10px;
            font-size: 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #2563eb;
        }

        .helper-text {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .message {
            margin-bottom: 12px;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 13px;
        }

        .message.error {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .message.success {
            background-color: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .button-row {
            margin-top: 16px;
            display: flex;
            gap: 8px;
        }

        .btn {
            flex: 1;
            border: none;
            border-radius: 6px;
            padding: 9px 12px;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-primary {
            background-color: #2563eb;
            color: #ffffff;
        }

        .btn-secondary {
            background-color: #e5e7eb;
            color: #111827;
        }

        .btn-login-link {
            width: 100%;
            margin-top: 8px;
            background-color: #10b981;
            color: #ffffff;
        }
    </style>
</head>

<body>

    <div class="register-container">
        <h1 class="register-title">회원가입</h1>
        <p class="register-subtitle">PASS 시스템에 가입하세요.</p>

        <?php if ($errorMessage !== ''): ?>
            <div class="message error">
                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="message success">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <!-- 가입 완료 후 로그인 화면으로 이동하는 버튼 -->
            <button type="button"
                class="btn btn-login-link"
                onclick="window.location.href='/password_0_login/password_0_login_View/password_0_login_View.php';">
                로그인 화면으로 이동
            </button>
        <?php endif; ?>

        <!-- 성공했어도 폼을 계속 보여주고 싶지 않으면
         위 if ($successMessage !== '') 블록 아래에서 return; 해도 됨 -->

        <form method="post" action="">
            <!-- 아이디 -->
            <div class="form-group">
                <label for="userid">아이디 <span style="color:#dc2626;">*</span></label>
                <input type="text"
                    id="userid"
                    name="userid"
                    required
                    value="<?php echo htmlspecialchars($input_userid, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <!-- 이름 -->
            <div class="form-group">
                <label for="username">이름 <span style="color:#dc2626;">*</span></label>
                <input type="text"
                    id="username"
                    name="username"
                    required
                    value="<?php echo htmlspecialchars($input_username, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <!-- 비밀번호 -->
            <div class="form-group">
                <label for="password">비밀번호 <span style="color:#dc2626;">*</span></label>
                <input type="password"
                    id="password"
                    name="password"
                    required>
                <div class="helper-text">영문/숫자/특수문자 조합을 권장합니다.</div>
            </div>

            <!-- 이메일 -->
            <div class="form-group">
                <label for="email">이메일 <span style="color:#dc2626;">*</span></label>
                <input type="email"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($input_email, ENT_QUOTES, 'UTF-8'); ?>"
                    required>
            </div>

            <!-- 전화번호 -->
            <div class="form-group">
                <label for="phone">전화번호 <span style="color:#dc2626;">*</span></label>
                <input type="text"
                    id="phone"
                    name="phone"
                    value="<?php echo htmlspecialchars($input_phone, ENT_QUOTES, 'UTF-8'); ?>"
                    required>
            </div>

            <!-- 생년월일 -->
            <div class="form-group">
                <label for="birthdate">생년월일 <span style="color:#dc2626;">*</span></label>
                <input type="date"
                    id="birthdate"
                    name="birthdate"
                    value="<?php echo htmlspecialchars($input_birth, ENT_QUOTES, 'UTF-8'); ?>"
                    required>
            </div>

            <!-- 성별 -->
            <div class="form-group">
                <label for="gender">성별 <span style="color:#dc2626;">*</span></label>
                <select id="gender" name="gender" required>
                    <option value="" <?php echo $input_gender === '' ? 'selected' : ''; ?>>선택 안 함</option>
                    <option value="M" <?php echo $input_gender === 'M' ? 'selected' : ''; ?>>남자</option>
                    <option value="F" <?php echo $input_gender === 'F' ? 'selected' : ''; ?>>여자</option>
                </select>
            </div>

            <!-- 버튼 영역 -->
            <div class="button-row">
                <button type="submit" class="btn btn-primary">회원가입</button>
                <button type="button"
                    class="btn btn-secondary"
                    onclick="window.location.href='/password_0_login/password_0_login_View/password_0_login_View.php';">
                    로그인으로 돌아가기
                </button>
            </div>
        </form>
    </div>

</body>

</html>