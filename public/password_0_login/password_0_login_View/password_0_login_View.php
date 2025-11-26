 <?php
// ViewModel 필요하면 유지, 아니면 삭제해도 무방
// require_once '../password_0_login_ViewModel/password_0_login_ViewModel.php';


?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password 관리자 로그인</title>
    <link rel="stylesheet" href="/password_0_login/password_0_login_View/password_0_login_View.css">

    
</head>

<body>

    <div class="login-container">

        <h1>Password 관리자 로그인</h1>

        <?php if (isset($_GET['error'])): ?>
            <p style="color:#d9534f; text-align:center;">아이디 또는 비밀번호가 잘못되었습니다.</p>
        <?php endif; ?>

        <form action="/password_0_login/password_0_login_API/password_0_login_API.php" method="POST">

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

        <!-- PWA 설치 버튼 영역 -->
        <div style="text-align:center; margin-top:20px;">
            <button id="installBtn"
                    style="display:none; padding:10px 20px; background:#0070f3; color:#fff; border:none; border-radius:6px; cursor:pointer;">
                앱 설치하기
            </button>
        </div>

    </div>

    <!-- PWA, Service Worker Script -->
    <script>
        let deferredPrompt;
        const installBtn = document.getElementById('installBtn');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installBtn.style.display = 'block';
        });

        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const result = await deferredPrompt.userChoice;

                if (result.outcome === 'accepted') {
                    console.log('PWA 설치 성공');
                } else {
                    console.log('PWA 설치 취소');
                }
                deferredPrompt = null;
            }
        });

        // SW 등록
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("/sw.js")
                .then(() => console.log("Service Worker 등록 완료"))
                .catch(err => console.error("Service Worker 등록 실패:", err));
        }
    </script>

</body>
</html>
