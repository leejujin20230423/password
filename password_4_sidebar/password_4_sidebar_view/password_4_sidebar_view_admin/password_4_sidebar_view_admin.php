<?php
// 필요하면 여기서 세션 체크 등 가능
?>

<style>
/* Sidebar component: HTML + CSS + JS in one file */
body#page-pw5 .sidebar,
body#page-share .sidebar,
body#page-share-status .sidebar {
    width: 260px;
    flex: 0 0 260px;
    border: 1px solid var(--line, rgba(255, 255, 255, 0.12));
    border-radius: 16px;
    background: rgba(18, 28, 51, 0.88);
    padding: 10px;
}

body#page-pw5 .sidebar-title,
body#page-share .sidebar-title,
body#page-share-status .sidebar-title {
    font-size: 12px;
    color: var(--muted, #aab6cf);
    margin: 4px 6px 8px;
}

body#page-pw5 .sidebar ul,
body#page-share .sidebar ul,
body#page-share-status .sidebar ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

body#page-pw5 .sidebar li,
body#page-share .sidebar li,
body#page-share-status .sidebar li {
    border: 1px solid rgba(127, 95, 255, 0.55);
    border-radius: 12px;
    padding: 12px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    touch-action: manipulation;
}

@media (max-width: 900px) {
    body#page-pw5 .sidebar,
    body#page-share .sidebar,
    body#page-share-status .sidebar {
        position: fixed;
        top: calc(10px + var(--header-h, 62px) + var(--gap, 5px));
        left: 10px;
        width: min(82vw, 300px);
        height: calc(100vh - (10px + var(--header-h, 62px) + var(--gap, 5px) + 12px));
        z-index: 2950;
        overflow: auto;
        pointer-events: auto;
        -webkit-overflow-scrolling: touch;
        transform: translateX(-110%);
        transition: transform 0.22s ease;
    }

    body#page-pw5 .sidebar.open,
    body#page-share .sidebar.open,
    body#page-share-status .sidebar.open {
        transform: translateX(0);
    }
}
</style>

<!-- ✅ toggleSidebar()가 찾을 수 있도록 id="sidebar" 추가 -->
<aside id="sidebar" class="sidebar">
    <!-- 상단 제목 -->
    <div class="sidebar-title">메뉴 선택</div>

    <nav>
        <ul>
               <!-- 비밀번호 공유현황 -->
            <li
                class="active"
                onclick="window.location.href='/password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_route_admin.php';">
                비밀번호 공유현황
            </li>
            
            <!-- 비밀번호 등록하기 -->
            <li
                class="active"
                onclick="window.location.href='/password_5_passwordRegister/password_5_passwordRegister_Route/password_5_passwordRegister_Route.php';">
                비밀번호 등록/삭제
            </li>

            <!-- 비밀번호 공유하기 -->
            <li
                class="active"
                onclick="window.location.href='/password_6_share/password_6_share_route/password_6_share_route_view_admin.php';">
                비밀번호 공유하기
            </li>

         

        </ul>
    </nav>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        // 모바일에서 터치 반응을 더 안정적으로 처리
        sidebar.addEventListener('touchstart', function() {}, { passive: true });
    });
</script>
