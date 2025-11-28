<?php
// 세션이 필요할 수 있으니 안전하게 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 로그인한 사용자 이름
$sessionUsername = isset($_SESSION['username'])
    ? (string)$_SESSION['username']
    : '알 수 없음';

// (선택) 리스트 소스 표시용 변수 안전 처리
$searchKeywordSafe = isset($searchKeyword) ? $searchKeyword : '';
$listSourceSafe    = isset($listSource) ? $listSource : '';
?>
<header class="header">
    <div class="header-left">
        <!-- ✅ 모바일용 사이드바 토글 버튼 (햄버거 버튼) -->
        <button type="button"
            class="sidebar-toggle-btn"
            aria-label="메뉴 열기"
            onclick="toggleSidebar()">
            &#9776;
        </button>


        <h1 class="header-title">Password 관리 시스템</h1>
    </div>

    <div class="header-right">
        <span class="user-info">
            관리자:
            <?php echo htmlspecialchars($sessionUsername, ENT_QUOTES, 'UTF-8'); ?>
        </span>

        <button type="button"
            class="password-change-button"
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
<script>
    // ✅ 사이드바 열고 닫기
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        sidebar.classList.toggle('open');
    }

    // ✅ (선택) 모바일에서 메뉴 항목 클릭하면 자동으로 닫기
    document.addEventListener('DOMContentLoaded', function () {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        var menuItems = sidebar.querySelectorAll('li');
        menuItems.forEach(function (item) {
            item.addEventListener('click', function () {
                if (window.innerWidth <= 900) {
                    sidebar.classList.remove('open');
                }
            });
        });
    });
</script>