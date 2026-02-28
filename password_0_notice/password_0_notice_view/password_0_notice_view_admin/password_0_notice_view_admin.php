<?php
require_once dirname(__DIR__, 4) . '/app_bootstrap.php';
pass_require_loader_or_die();

$db = (new DBConnection())->getDB();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 로그인 사용자 이름 세션에서 꺼내기
 * - 세션에 없으면 빈 문자열로 처리
 */
$sessionUsername = isset($_SESSION['username'])
    ? (string)$_SESSION['username']
    : '';

// 로그인 안 되어 있으면 로그인 페이지로
if (empty($_SESSION['user_no'])) {
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php');
    exit;
}

$userNo = (int)$_SESSION['user_no'];

/**
 * ✅ 안내 이후 이동할 페이지: "비밀번호 공유현황 (관리자)"
 *    - 등록 페이지가 아니라, 공유현황 페이지로 고정
 */
$afterNoticeUrl = '/password_7_shareStatus/password_7_shareStatus_view/password_7_shareStatus_view_admin/password_7_shareStatus_view_admin.php';

// 필요하면 세션에도 저장해둘 수 있음 (선택)
$_SESSION['after_notice_url'] = $afterNoticeUrl;

// ================================
// 1) 현재 notice_view_count 조회
// ================================
try {
    $sqlSelect = "
        SELECT notice_view_count
        FROM users
        WHERE user_no = :user_no
        LIMIT 1
    ";
    $stmt = $db->prepare($sqlSelect);
    $stmt->bindValue(':user_no', $userNo, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $noticeViewCount = $row ? (int)$row['notice_view_count'] : 0;

    // ================================
    // 2) 이미 5번 이상 본 사람은
    //    → 안내 페이지 스킵하고 바로 공유현황 페이지로 보내기
    // ================================
    if ($noticeViewCount >= 5) {
        header('Location: ' . $afterNoticeUrl);
        exit;
    }

    // ================================
    // 3) 아직 5번 미만이면 +1 해 주고
    //    → 아래 HTML로 안내 페이지 보여줌
    // ================================
    $sqlUpdate = "
        UPDATE users
        SET notice_view_count = notice_view_count + 1
        WHERE user_no = :user_no
        LIMIT 1
    ";
    $stmtUp = $db->prepare($sqlUpdate);
    $stmtUp->bindValue(':user_no', $userNo, PDO::PARAM_INT);
    $stmtUp->execute();

} catch (PDOException $e) {
    // 에러가 나면 일단 안내는 보여주되, 카운터는 안 올릴 수 있음
    // error_log('[NOTICE_ERROR] ' . $e->getMessage());
    // 굳이 막지 말고 그냥 안내 페이지 보여주도록 놔둬도 됨
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>Password 사용 안내 (관리자)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    
    <link rel="stylesheet" href="/assets/app.css">
<style>
        /* 전체 배경 */
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: #f3f4f6;
            color: #111827;
        }

        /* 상단 헤더 */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background-color: #111827;
            color: #fff;
        }

        .header h1 {
            font-size: 18px;
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-info {
            font-size: 12px;
        }

        .logout-button {
            padding: 6px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            background-color: #f87171;
            color: #fff;
            font-size: 12px;
        }

        .logout-button:hover {
            opacity: 0.9;
        }

        /* 메인 영역: 가운데 카드 레이아웃 */
        .container {
            min-height: calc(100vh - 50px);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .notice-card {
            background-color: #ffffff;
            max-width: 720px;
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 24px 28px;
        }

        .notice-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .notice-subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-top: 18px;
            margin-bottom: 8px;
        }

        .desc {
            font-size: 14px;
            line-height: 1.6;
            color: #374151;
        }

        ul {
            padding-left: 18px;
            margin-top: 6px;
            margin-bottom: 8px;
        }

        li {
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 4px;
        }

        .highlight {
            font-weight: 600;
            color: #111827;
        }

        .required-text {
            font-size: 13px;
            color: #b91c1c;
            margin-top: 8px;
        }

        .button-area {
            margin-top: 22px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-primary {
            padding: 9px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            background-color: #2563eb;
            color: #ffffff;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            padding: 9px 16px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            background-color: #f9fafb;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-secondary:hover {
            background-color: #e5e7eb;
        }

        @media (max-width: 600px) {
            .notice-card {
                padding: 18px 16px;
            }
        }
    </style>
</head>

<body>

    <!-- 상단 헤더 -->
    <header class="header"><div class="header-left"><div class="header-title-wrap"><h1 class="header-title">Password 관리 시스템 (관리자)</h1></div></div><div class="header-right">
            <span class="user-info">
                로그인 계정:
                <?php echo htmlspecialchars($sessionUsername, ENT_QUOTES, 'UTF-8'); ?>
            </span>

            <button type="button"
                class="logout-button"
                onclick="window.location.href='/password_9_logout/password_9_logout_Route/password_9_logout_Route.php';">
                로그아웃
            </button>
        </div></header>

    <!-- 메인 공지/메뉴얼 카드 -->
    <main class="container">
        <section class="notice-card">
            <h2 class="notice-title">Password 사용 안내</h2>
            <p class="notice-subtitle">
                영업 및 매장 운영에 필요한 계정들의 비밀번호를 한 곳에 모아 관리하는 관리자용 화면입니다.
                아래 안내를 확인하신 후 <strong>“비밀번호 등록하러 가기”</strong> 버튼을 눌러주세요.
            </p>
            <p class="notice-subtitle">
                이 페이지는 PC에서도 동일하게 사용 가능하며, 모든 관리자는 동일한 주소에서 비밀번호 등록을 진행할 수 있습니다.
            </p>

            <h3 class="section-title">1. 어떤 비밀번호를 등록해야 하나요?</h3>
            <p class="desc">
                이 시스템은 <span class="highlight">매장 운영 및 업무용 계정</span>을 중심으로 관리합니다.
            </p>
            <ul>
                <li><span class="highlight">배달/영업 앱</span>: 쿠팡(쿠팡이츠), 배달의민족, 요기요, 땡겨요, 위메프오, 푸드테크 등</li>
                <li><span class="highlight">세무·정산·매장 관련 사이트</span>: 홈택스, 4대보험, 카드매출 정산, POS/매장관리, CCTV 등</li>
                <li><span class="highlight">업무용 계정</span>: 회사 이메일, 공유 구글 계정, 내부 시스템 로그인 계정 등</li>
                <li><span class="highlight">개인용 계정</span> (선택): 사장님 개인 금융/카드, 이메일, SNS 등도 정리 가능</li>
            </ul>

            <h3 class="section-title">2. 이 시스템으로 할 수 있는 일</h3>
            <ul>
                <li><span class="highlight">비밀번호를 한 번만 입력</span>해두면, 이후에는 목록에서 빠르게 찾아볼 수 있습니다.</li>
                <li><span class="highlight">전화번호 저장</span> 후 버튼 클릭으로 바로 전화 연결(모바일 사용 시) 기능을 지원합니다.</li>
                <li><span class="highlight">사이트 주소(URL) 저장</span> 후 “사이트 열기” 버튼으로 즉시 해당 사이트로 이동할 수 있습니다.</li>
                <li>채널/구분·매장별 검색, 업무/개인 구분 등으로 나중에 더 빠르게 찾을 수 있도록 확장 가능합니다.</li>
            </ul>

            <h3 class="section-title">3. 비밀번호 공유의 중요성</h3>
            <p class="desc">
                <span class="highlight">비밀번호는 민감한 개인정보</span>이므로 이를 타인에게 공유하는 행위는 개인정보 유출의 위험을 증가시킬 수 있습니다.
            </p>
            <p class="desc">
                비밀번호를 타인에게 공유한 후 발생하는 개인정보 유출 및 기타 사고에 대해서는 본인이 책임을 져야 합니다. 비밀번호를 공유할 때 주의가 필요합니다.
            </p>

            <h3 class="section-title">4. 비밀번호는 공유 허락된 비밀번호 외에는 절대 노출되지 않습니다.</h3>
            <p class="desc">
                이 시스템에서는 공유가 허락된 비밀번호만 외부로 노출됩니다. 그 외의 비밀번호는 절대로 타인에게 공개되지 않으며, 시스템 내에서 안전하게 암호화되어 처리됩니다.
            </p>

            <h3 class="section-title">5. 비밀번호 등록 시 필수/권장 항목</h3>
            <ul>
                <li><span class="highlight">매장명 (필수)</span>: 어느 매장 계정인지 구분하기 위한 기본 항목입니다.</li>
                <li><span class="highlight">채널/구분</span>: 쿠팡, 배민, 요기요, 홈택스, CCTV, 개인용 등</li>
                <li><span class="highlight">로그인 아이디</span>: 이메일, 휴대폰 번호, 사용자 ID 등</li>
                <li><span class="highlight">비밀번호</span>: 실제 로그인에 사용하는 비밀번호 (서버 AES 암호화 저장)</li>
                <li><span class="highlight">전화번호</span>: 고객센터, 담당자, 매장 전화번호 등</li>
                <li><span class="highlight">사이트 주소(URL)</span>: 로그인/관리 페이지 링크</li>
                <li><span class="highlight">메모</span>: 추가 설명, 변경 이력, 담당자 정보 등</li>
            </ul>

            <p class="required-text">
                ※ <strong>매장명은 반드시 선택/입력</strong>하셔야 저장이 가능합니다. (매장별로 비밀번호를 구분하여 관리하기 위함입니다.)
            </p>

            <div class="button-area">
                <button type="button"
                    class="btn-primary"
                    onclick="window.location.href='<?php echo htmlspecialchars($afterNoticeUrl, ENT_QUOTES, 'UTF-8'); ?>';">
                    사용 방법 확인했습니다. 비밀번호 공유현황 보러 가기
                </button>
            </div>
        </section>
    </main>
</body>

</html>
