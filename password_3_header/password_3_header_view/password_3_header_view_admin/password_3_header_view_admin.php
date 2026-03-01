<?php
// 세션이 필요할 수 있으니 안전하게 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 로그인한 사용자 이름
$sessionUsername = isset($_SESSION['username'])
    ? (string)$_SESSION['username']
    : '알 수 없음';

// (선택) 리스트 소스 / 검색어 표시용 변수 안전 처리
$searchKeywordSafe = isset($searchKeyword) ? $searchKeyword : '';
$listSourceSafe    = isset($listSource) ? $listSource : '';

// ✅ 화면에 보여줄 List Source 라벨 (db → DBQuery 로 변경)
$listSourceLabel = '';
if (!empty($listSourceSafe)) {
    $sourceMap = [
        'db'    => 'DBQuery',      // DB 조회인 경우
        'redis' => 'Redis cache',  // Redis 캐시인 경우
    ];

    $listSourceLabel = isset($sourceMap[$listSourceSafe])
        ? $sourceMap[$listSourceSafe]
        : $listSourceSafe; // 매핑이 없으면 원래 값 그대로
}
?>

<style>
/* Header component: HTML + CSS + JS in one file */
body#page-pw5 .header,
body#page-share .header,
body#page-share-status .header {
    position: fixed;
    top: 10px;
    left: var(--gutter, 30px);
    right: var(--gutter, 30px);
    height: var(--header-h, 62px);
    border: 1px solid var(--line, rgba(255, 255, 255, 0.12));
    border-radius: 18px;
    background: linear-gradient(90deg, rgba(53, 32, 110, 0.45), rgba(19, 70, 95, 0.34));
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    z-index: 3000;
    pointer-events: auto;
}

body#page-pw5 .header-left,
body#page-pw5 .header-right,
body#page-pw5 .header-title-wrap,
body#page-share .header-left,
body#page-share .header-right,
body#page-share .header-title-wrap,
body#page-share-status .header-left,
body#page-share-status .header-right,
body#page-share-status .header-title-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

body#page-pw5 .header-title,
body#page-share .header-title,
body#page-share-status .header-title {
    margin: 0;
    font-size: 34px;
    font-weight: 800;
    white-space: nowrap;
    line-height: 1;
}

body#page-pw5 .list-source-label,
body#page-pw5 .user-info,
body#page-share .list-source-label,
body#page-share .user-info,
body#page-share-status .list-source-label,
body#page-share-status .user-info {
    font-size: 12px;
    color: var(--muted, #aab6cf);
    white-space: nowrap;
}

body#page-pw5 .password-change-button,
body#page-pw5 .logout-button,
body#page-share .password-change-button,
body#page-share .logout-button,
body#page-share-status .password-change-button,
body#page-share-status .logout-button {
    border: 1px solid var(--line, rgba(255, 255, 255, 0.12));
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.06);
    color: var(--text, #e8edf8);
    padding: 7px 10px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
}

body#page-pw5 .logout-button,
body#page-share .logout-button,
body#page-share-status .logout-button {
    border-color: rgba(255, 85, 130, 0.45);
}

body#page-pw5 .sidebar-toggle-btn,
body#page-share .sidebar-toggle-btn,
body#page-share-status .sidebar-toggle-btn {
    display: none;
    width: 40px;
    height: 40px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(0, 0, 0, 0.12);
    color: rgba(255, 255, 255, 0.92);
    font-size: 20px;
    cursor: pointer;
    pointer-events: auto;
    touch-action: manipulation;
    position: relative;
    z-index: 3100;
    align-items: center;
    justify-content: center;
}

body#page-pw5 .sidebar-overlay,
body#page-share .sidebar-overlay,
body#page-share-status .sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 2900;
    pointer-events: none !important;
}

body#page-pw5 .sidebar-overlay.open,
body#page-share .sidebar-overlay.open,
body#page-share-status .sidebar-overlay.open {
    display: block;
    pointer-events: none !important;
}

@media (max-width: 900px) {
    body#page-pw5,
    body#page-share,
    body#page-share-status {
        --gutter: 10px;
    }

    body#page-pw5 .header,
    body#page-share .header,
    body#page-share-status .header {
        padding: 6px 8px;
    }

    body#page-pw5 .header-left,
    body#page-pw5 .header-right,
    body#page-share .header-left,
    body#page-share .header-right,
    body#page-share-status .header-left,
    body#page-share-status .header-right {
        gap: 4px;
    }

    body#page-pw5 .header-title,
    body#page-share .header-title,
    body#page-share-status .header-title {
        font-size: 22px;
    }

    body#page-pw5 .list-source-label,
    body#page-pw5 .user-info,
    body#page-share .list-source-label,
    body#page-share .user-info,
    body#page-share-status .list-source-label,
    body#page-share-status .user-info {
        display: none;
    }

    body#page-pw5 .password-change-button,
    body#page-pw5 .logout-button,
    body#page-share .password-change-button,
    body#page-share .logout-button,
    body#page-share-status .password-change-button,
    body#page-share-status .logout-button {
        padding: 6px 8px;
        font-size: 11px;
        border-radius: 10px;
    }

    body#page-pw5 .sidebar-toggle-btn,
    body#page-share .sidebar-toggle-btn,
    body#page-share-status .sidebar-toggle-btn {
        display: inline-flex;
    }
}
</style>

<header class="header">
    <div class="header-left">
        <!-- ✅ 모바일용 사이드바 토글 버튼 (햄버거 버튼) -->
        <button type="button"
            class="sidebar-toggle-btn"
            aria-label="메뉴 열기">
            &#9776;
        </button>

        <div class="header-title-wrap">
            <h1 class="header-title">
                Password

                <?php if (!empty($listSourceLabel)): ?>
                    <!-- ✅ 리스트 소스를 제목 옆에 표시 (db → DBQuery로 매핑된 값) -->
                    <span style="text-align:left; font-size:10px;" class="list-source-label">
                        Source:
                        <?php echo htmlspecialchars($listSourceLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </h1>
        </div>
    </div>

    <div class="header-right">
        <span class="user-info">
            
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

<!-- 전역 오버레이 -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    // ==========================================
    // 사이드바 토글 + 오버레이 제어
    // ==========================================
    function closeSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }

    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (!sidebar) return;

        var willOpen = !sidebar.classList.contains('open');

        if (willOpen) {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('open');
        } else {
            closeSidebar();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggleBtn = document.querySelector('.sidebar-toggle-btn');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        // ✅ 모바일에서 메뉴 클릭 시 자동 닫기
        if (sidebar) {
            var menuItems = sidebar.querySelectorAll('li');
            menuItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 900) {
                        setTimeout(closeSidebar, 0);
                    }
                });
            });
        }

        // ✅ 오버레이 클릭 시 사이드바 닫기
        if (overlay) {
            overlay.addEventListener('click', function() {
                closeSidebar();
            });
        }

        // ✅ ESC 키로 닫기
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                closeSidebar();
            }
        });

        // ✅ 데스크톱 폭으로 돌아가면 강제 닫기
        window.addEventListener('resize', function() {
            if (window.innerWidth > 900) {
                closeSidebar();
            }
        });
    });
</script>
