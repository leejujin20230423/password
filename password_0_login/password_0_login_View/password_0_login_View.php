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
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#111827">

    <!-- Global App CSS -->
    <link rel="stylesheet" href="/assets/app.css">

    <link rel="stylesheet" href="/password_0_login/password_0_login_View/password_0_login_View.css">
</head>

<body>
  <div class="login-container">
    <div class="pw-card login-card">
      <div class="pw-card-inner">
        <div class="login-brand">
          <div class="login-logo" aria-hidden="true"></div>
          <div>
            <h1 class="pw-title">Password</h1>
            <p class="pw-subtitle">안전하고 빠르게 로그인하세요.</p>
          </div>
        </div>

        <?php if (isset($_GET['error'])): ?>
          <div class="pw-alert" style="margin-top:14px;">아이디 또는 비밀번호가 잘못되었습니다.</div>
        <?php endif; ?>

        <form class="pw-form"
              action="/password_0_login/password_0_login_API/password_0_login_API.php"
              method="POST" autocomplete="on">

          <div class="pw-field">
            <label class="pw-label" for="password_admin_userid">아이디</label>
            <input class="pw-input"
                   type="text"
                   id="password_admin_userid"
                   name="password_admin_userid"
                   placeholder="아이디를 입력하세요"
                   required>
          </div>

          <div class="pw-field">
            <label class="pw-label" for="password_admin_pass">비밀번호</label>
            <input class="pw-input"
                   type="password"
                   id="password_admin_pass"
                   name="password_admin_pass"
                   placeholder="비밀번호를 입력하세요"
                   required>
          </div>

          <button class="pw-btn pw-btn-primary" type="submit">로그인</button>

          <div class="pw-help" style="margin-top:4px;">
            처음 이용하시나요? 아래에서 회원가입 후 이용할 수 있어요.
          </div>

          <div class="login-actions">
            <button class="pw-btn pw-btn-ghost" type="button"
                    onclick="window.location.href='/password_1_usersRegister/password_1_usersRegister_view/password_1_usersRegister_view_admin/password_1_usersRegister_view_admin.php';">
              회원가입
            </button>

            <button class="pw-btn" id="installBtn" type="button" style="display:none;">
              앱 설치 / 홈 화면 추가
            </button>
          </div>

          <div style="margin-top:14px;">
            <div class="pw-help">직원/가족에게 이 로그인 주소를 공유해 보세요.</div>
            <div class="login-share">
              <button class="pw-btn" type="button" id="copyUrlBtn">주소 복사</button>
              <button class="pw-btn" type="button" id="shareUrlBtn">카톡·문자로 보내기</button>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>

  <!-- ===============================
   ✅ PWA, Service Worker Script + URL 복사/공유
   =============================== -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {

      const installBtn = document.getElementById('installBtn');
      let deferredPrompt = null;

      // ✅ PWA 설치 버튼 이벤트
      window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (installBtn) installBtn.style.display = 'inline-block';
      });

      if (installBtn) {
        installBtn.addEventListener('click', async () => {
          if (!deferredPrompt) return;
          deferredPrompt.prompt();
          await deferredPrompt.userChoice;
          deferredPrompt = null;
          installBtn.style.display = 'none';
        });
      }

      // ✅ Service Worker 등록
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/serviceWorker.js').catch(() => {});
      }

      // ✅ URL 복사
      const copyBtn = document.getElementById('copyUrlBtn');
      const shareBtn = document.getElementById('shareUrlBtn');
      const loginUrl = window.location.href;

      if (copyBtn) {
        copyBtn.addEventListener('click', async () => {
          try {
            await navigator.clipboard.writeText(loginUrl);
            copyBtn.textContent = '복사 완료!';
            setTimeout(() => (copyBtn.textContent = '주소 복사'), 1200);
          } catch (e) {
            alert('복사에 실패했습니다. 주소를 직접 복사해 주세요.');
          }
        });
      }

      // ✅ Web Share API (지원 안 하면 복사로 대체)
      if (shareBtn) {
        shareBtn.addEventListener('click', async () => {
          try {
            if (navigator.share) {
              await navigator.share({ title: 'Password 로그인', text: '로그인 주소', url: loginUrl });
            } else {
              await navigator.clipboard.writeText(loginUrl);
              alert('공유 기능이 없어 주소를 복사했습니다.');
            }
          } catch (e) {}
        });
      }
    });
  </script>
</body>

</html>
