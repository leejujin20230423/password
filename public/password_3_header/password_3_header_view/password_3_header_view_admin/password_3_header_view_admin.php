  <header class="header">
      <h1>Password 관리 시스템</h1>



      <div class="header-right">
          <span class="user-info">관리자:
              <?php echo htmlspecialchars($sessionUsername, ENT_QUOTES, 'UTF-8'); ?>
          </span>

          <!-- ✅ 비밀번호 변경 버튼 (경로 수정) -->
          <button type="button"
              onclick="window.location.href='/password_2_passwordChange/password_2_passwordChange_view/password_2_passwordChange_view_admin/password_2_passwordChange_view_admin.php';">
              비밀번호 변경
          </button>


          <button type="button"
              class="logout-button"
              onclick="window.location.href='/password_9_logout/password_9_logout_Route/password_9_logout_Route.php';">
              로그아웃
          </button>
      </div>
  </header>