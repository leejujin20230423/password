<?php
// 필요하면 여기서 세션 체크 등 가능
?>

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
