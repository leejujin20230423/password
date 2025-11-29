<?php
// password_0_notice_view_admin.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 로그인 체크
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php');
    exit;
}

$sessionUsername = isset($_SESSION['username']) ? $_SESSION['username'] : '알 수 없음';

// ✅ 공지 이후 이동 페이지를 "공유현황(관리자)" 으로 고정
$targetUrl = '/password_7_shareStatus/password_7_shareStatus_view/password_7_shareStatus_view_admin/password_7_shareStatus_view_admin.php';

// 혹시 전에 저장된 after_notice_url 이 있어도 항상 공유현황으로 덮어쓰기
$_SESSION['after_notice_url'] = $targetUrl;
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>Password 사용 안내 (관리자)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
    <header class="header">
        <h1>Password 관리 시스템 (관리자)</h1>
        <div class="header-right">
            <span class="user-info">
                로그인 계정:
                <?php echo htmlspecialchars($sessionUsername, ENT_QUOTES, 'UTF-8'); ?>
            </span>

            <button type="button"
                class="logout-button"
                onclick="window.location.href='/password_9_logout/password_9_logout_Route/password_9_logout_Route.php';">
                로그아웃
            </button>
        </div>
    </header>

    <!-- 메인 공지/메뉴얼 카드 -->
    <main class="container">
        <section class="notice-card">
            <h2 class="notice-title">Password 사용 안내</h2>
            <p class="notice-subtitle">
                영업 및 매장 운영에 필요한 계정들의 비밀번호를 한 곳에 모아 관리하는 관리자용 화면입니다.
                아래 안내를 확인하신 후 <strong>“비밀번호 등록하러 가기”</strong> 버튼을 눌러주세요.
            </p>

            <h3 class="section-title">1. 어떤 비밀번호를 등록해야 하나요?</h3>
            <p class="desc">
                이 시스템은 <span class="highlight">매장 운영 및 업무용 계정</span>을 중심으로 관리합니다.
            </p>
            <ul>
                <li><span class="highlight">배달/영업 앱</span>:
                    쿠팡(쿠팡이츠), 배달의민족, 요기요, 땡겨요, 위메프오, 푸드테크 등</li>
                <li><span class="highlight">세무·정산·매장 관련 사이트</span>:
                    홈택스, 4대보험, 카드매출 정산, POS/매장관리, CCTV 등</li>
                <li><span class="highlight">업무용 계정</span>:
                    회사 이메일, 공유 구글 계정, 내부 시스템 로그인 계정 등</li>
                <li><span class="highlight">개인용 계정</span> (선택):
                    사장님 개인 금융/카드, 이메일, SNS 등도 정리 가능</li>
            </ul>

            <h3 class="section-title">2. 이 시스템으로 할 수 있는 일</h3>
            <ul>
                <li><span class="highlight">비밀번호를 한 번만 입력</span>해두면,
                    이후에는 목록에서 빠르게 찾아볼 수 있습니다.</li>
                <li><span class="highlight">전화번호 저장</span> 후 버튼 클릭으로
                    바로 전화 연결(모바일 사용 시) 기능을 지원합니다.</li>
                <li><span class="highlight">사이트 주소(URL) 저장</span> 후
                    “사이트 열기” 버튼으로 즉시 해당 사이트로 이동할 수 있습니다.</li>
                <li>채널/구분·매장별 검색, 업무/개인 구분 등으로
                    나중에 더 빠르게 찾을 수 있도록 확장 가능합니다.</li>
            </ul>

            <h3 class="section-title">3. 비밀번호 등록 시 필수/권장 항목</h3>
            <ul>
                <li><span class="highlight">매장명 (필수)</span>:
                    어느 매장 계정인지 구분하기 위한 기본 항목입니다.</li>
                <li><span class="highlight">채널/구분</span>:
                    쿠팡, 배민, 요기요, 홈택스, CCTV, 개인용 등</li>
                <li><span class="highlight">로그인 아이디</span>:
                    이메일, 휴대폰 번호, 사용자 ID 등</li>
                <li><span class="highlight">비밀번호</span>:
                    실제 로그인에 사용하는 비밀번호 (서버 AES 암호화 저장)</li>
                <li><span class="highlight">전화번호</span>:
                    고객센터, 담당자, 매장 전화번호 등</li>
                <li><span class="highlight">사이트 주소(URL)</span>:
                    로그인/관리 페이지 링크</li>
                <li><span class="highlight">메모</span>:
                    추가 설명, 변경 이력, 담당자 정보 등</li>
            </ul>

            <p class="required-text">
                ※ <strong>매장명은 반드시 선택/입력</strong>하셔야 저장이 가능합니다.
                (매장별로 비밀번호를 구분하여 관리하기 위함입니다.)
            </p>

            <div class="button-area">
                <button type="button"
                    class="btn-primary"
                    onclick="window.location.href='<?php echo htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8'); ?>';">
                    사용 방법 확인했습니다. 비밀번호 등록하러 가기
                </button>
                <!-- 필요하면 문구도 이렇게 바꿀 수 있음:
                사용 방법 확인했습니다. 공유현황으로 이동
                -->
            </div>
        </section>
    </main>
</body>

</html>
