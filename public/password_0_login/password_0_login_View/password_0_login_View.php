<?php
// ViewModel 필요하면 유지, 아니면 삭제 가능
// require_once '../password_0_login_ViewModel/password_0_login_ViewModel.php';
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password 로그인</title>

    <!-- ✅ PWA: manifest + theme-color -->
    <link rel="manifest" href="/../manifest.json">
    <meta name="theme-color" content="#111827">

    <!-- 로그인 페이지 전용 CSS -->
    <link rel="stylesheet" href="/password_0_login/password_0_login_View/password_0_login_View.css">
</head>

<body>

    <div class="login-container">

        <h1>Password 로그인</h1>

        <?php if (isset($_GET['error'])): ?>
            <p style="color:#d9534f; text-align:center;">아이디 또는 비밀번호가 잘못되었습니다.</p>
        <?php endif; ?>

        <!-- ===============================
         로그인 폼 (단 한 번만 사용)
         =============================== -->
        <form action="/password_0_login/password_0_login_API/password_0_login_API.php"
              method="POST">

            <input type="text"
                   id="password_admin_userid"
                   name="password_admin_userid"
                   placeholder="관리자 아이디"
                   required>

            <input type="password"
                   id="password_admin_pass"
                   name="password_admin_pass"
                   placeholder="관리자 비밀번호"
                   required>

            <button type="submit">로그인</button>
        </form>

        <!-- ===============================
         회원가입 버튼 영역
         =============================== -->
        <div style="text-align:center; margin-top:16px; font-size:13px; color:#555;">
            처음 이용하시나요?
        </div>

        <div style="text-align:center; margin-top:8px;">
            <button type="button"
                    style="padding:9px 18px; border:none; border-radius:6px;
                           background:#4b5563; color:#fff; cursor:pointer; font-size:14px;"
                    onclick="window.location.href='/password_1_usersRegister/password_1_usersRegister_view/password_1_usersRegister_view_admin/password_1_usersRegister_view_admin.php';">
                회원가입
            </button>
        </div>

        <!-- ===============================
              PWA 설치 / 홈 화면 안내 버튼
         =============================== -->
        <div style="text-align:center; margin-top:20px;">
            <button id="installBtn"
                    style="display:none; padding:10px 20px; background:#0070f3;
                           color:#fff; border:none; border-radius:6px; cursor:pointer;">
                앱 설치 / 홈 화면 추가
            </button>
        </div>

        <!-- ===============================
         로그인 주소 복사 + 공유 버튼
         =============================== -->
        <div style="margin-top:18px; text-align:center; font-size:13px; color:#555;">
            직원/가족에게 이 로그인 주소를 공유해 보세요.
        </div>

        <div style="margin-top:8px; display:flex; justify-content:center; gap:8px;">
            <button type="button"
                    id="copyUrlBtn"
                    style="color:#5d3e49; padding:8px 12px; border:none; border-radius:6px;
                           background:#bdebf8; cursor:pointer; font-size:13px;">
                주소 복사
            </button>

            <button type="button"
                    id="shareUrlBtn"
                    style="padding:8px 12px; border:none; border-radius:6px;
                           background:#10b981; color:#fff; cursor:pointer; font-size:13px;">
                카톡·문자로 보내기
            </button>
        </div>

    </div>

    <!-- ===============================
     ✅ PWA, Service Worker Script + URL 복사/공유
     =============================== -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const installBtn = document.getElementById('installBtn');
            let deferredPrompt = null;

            // 버튼이 없으면 더 이상 진행할 필요 없음 (그래도 SW는 등록)
            const ua = navigator.userAgent.toLowerCase();
            const isIos = /iphone|ipad|ipod/.test(ua);
            const isAndroid = /android/.test(ua);
            const isStandalone =
                window.matchMedia('(display-mode: standalone)').matches ||
                ('standalone' in navigator && navigator.standalone);

            // -----------------------------
            // 1) 안드로이드 / PC 크롬: beforeinstallprompt 이벤트 사용
            // -----------------------------
            if (!isIos) {
                window.addEventListener('beforeinstallprompt', (e) => {
                    // 기본 설치 배너 막기
                    e.preventDefault();
                    // 나중에 쓸 수 있게 이벤트 저장
                    deferredPrompt = e;

                    // "앱 설치 / 홈 화면 추가" 버튼 보이게
                    if (installBtn) {
                        installBtn.style.display = 'inline-block';
                        installBtn.textContent = '앱 설치 / 홈 화면 추가';
                    }
                });
            }

            // -----------------------------
            // 2) iOS (아이폰/아이패드): beforeinstallprompt 없음
            //    → 우리가 직접 "홈 화면 추가 방법" 안내용 버튼만 보여줌
            // -----------------------------
            if (installBtn && isIos && !isStandalone) {
                installBtn.style.display = 'inline-block';
                installBtn.textContent = '홈 화면에 추가하는 방법';
            }

            // -----------------------------
            // 3) 설치 버튼 클릭 시 동작
            // -----------------------------
            if (installBtn) {
                installBtn.addEventListener('click', async () => {

                    // (1) 안드/PC 크롬에서 설치 프롬프트 준비되어 있으면 먼저 사용
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const result = await deferredPrompt.userChoice;

                        if (result.outcome === 'accepted') {
                            console.log('PWA 설치 성공');
                            // 설치 후 버튼 숨겨도 됨
                            installBtn.style.display = 'none';
                        } else {
                            console.log('PWA 설치 취소');
                        }

                        // 한 번 사용하면 이벤트는 비움
                        deferredPrompt = null;
                        return;
                    }

                    // (2) 그 외에는 각 환경별로 "홈 화면 추가" 방법 안내
                    if (isAndroid) {
                        alert(
                            '안드로이드 크롬에서 홈 화면에 추가하는 방법\n\n' +
                            '1. 오른쪽 위 ⋮(세 점) 메뉴를 누릅니다.\n' +
                            '2. "앱 설치" 또는 "홈 화면에 추가" 메뉴를 선택합니다.\n' +
                            '3. 안내에 따라 "설치" / "추가"를 누르면 홈 화면에 아이콘이 생깁니다.'
                        );
                    } else if (isIos) {
                        alert(
                            '크롬에서 앱 설치하는 방법\n\n' +
                            '1. 주소창 오른쪽에 보이는 "컴퓨터+휴대폰" 모양 아이콘(앱 설치 아이콘)을 클릭합니다.\n' +
                            '2. "설치" 버튼을 누르면 바탕화면/앱 목록에 아이콘이 추가됩니다.\n\n' +

                            'iPhone Safari에서 홈 화면에 추가하는 방법\n\n' +
                            '1. 반드시 Safari로 이 사이트에 접속합니다.\n' +
                            '2. 화면 아래 중앙의 네모 + 화살표 아이콘(공유 버튼)을 누릅니다.\n' +
                            '3. 아래로 스크롤해서 "홈 화면에 추가"를 선택합니다.\n' +
                            '4. 이름을 확인한 뒤, 오른쪽 위 "추가"를 누르면 홈 화면에 아이콘이 생깁니다.'
                        );
                    } else {
                        alert(
                            'PC 크롬에서 앱 설치하는 방법\n\n' +
                            '1. 주소창 오른쪽에 보이는 "컴퓨터+휴대폰" 모양 아이콘(앱 설치 아이콘)을 클릭합니다.\n' +
                            '2. "설치" 버튼을 누르면 바탕화면/앱 목록에 아이콘이 추가됩니다.'
                        );
                    }
                });
            }

            // -----------------------------
            // 4) Service Worker 등록
            // -----------------------------
            if ("serviceWorker" in navigator) {
                navigator.serviceWorker.register("/serviceWorker.js")
                    .then(() => console.log("Service Worker 등록 완료"))
                    .catch(err => console.error("Service Worker 등록 실패:", err));
            }

            // ======================================================
            // 5) 로그인 주소 복사 + 카톡/문자 공유 기능
            //    (로그인 페이지 전용 버튼: #copyUrlBtn, #shareUrlBtn)
            // ======================================================
            const SITE_URL = 'https://pass.bizstore.co.kr';

            const copyBtn  = document.getElementById('copyUrlBtn');
            const shareBtn = document.getElementById('shareUrlBtn');

            // 5-1) 주소 복사 버튼
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(SITE_URL).then(function () {
                            alert(
                                '로그인 주소가 복사되었습니다.\n\n' +
                                '카카오톡, 문자, 메신저 등에 붙여넣기(Ctrl+V / 길게 눌러 붙여넣기) 해서 보내주세요.'
                            );
                        }).catch(function () {
                            alert('복사에 실패했습니다. 다시 시도해 주세요.');
                        });
                    } else {
                        // 구형 브라우저 대응 (자동 복사 안 될 때)
                        prompt('아래 주소를 직접 복사해 주세요.', SITE_URL);
                    }
                });
            }

            // 5-2) 카톡·문자로 보내기 버튼
            if (shareBtn) {
                shareBtn.addEventListener('click', function () {
                    const shareText = 'PASS 비밀번호 관리 로그인 주소\n' + SITE_URL;

                    // ✅ Web Share API 지원 (모바일 크롬/사파리 등)
                    if (navigator.share) {
                        navigator.share({
                            title: 'PASS 비밀번호 관리',
                            text: '매장/업무 비밀번호 관리를 이 주소에서 같이 해요.',
                            url: SITE_URL
                        }).catch(function (err) {
                            console.log('share cancelled or failed', err);
                        });
                        return;
                    }

                    // ✅ Web Share 미지원 → 문자 앱으로 보내기 시도 (모바일 기준)
                    const smsBody = encodeURIComponent(shareText);
                    window.location.href = 'sms:?body=' + smsBody;
                });
            }

        });
    </script>

</body>

</html>
