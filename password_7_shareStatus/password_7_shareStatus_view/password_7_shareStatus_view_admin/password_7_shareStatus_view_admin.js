
// ==========================================================
// 1. URL 열기 / 클립보드 복사 함수
// ==========================================================
function openUrl(raw) {
    if (!raw) return;
    var url = raw.trim();
    if (!url) return;

    if (!/^https?:\/\//i.test(url)) {
        url = "https://" + url;
    }

    // 모바일/태블릿 포함: 현재 페이지 이동 없이 새창(새탭)으로 열기
    var win = window.open(url, "_blank", "noopener,noreferrer");
    if (win) {
        win.opener = null;
        return;
    }

    // 일부 모바일 브라우저의 팝업 차단 대비 fallback
    var a = document.createElement("a");
    a.href = url;
    a.target = "_blank";
    a.rel = "noopener noreferrer";
    a.style.display = "none";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function copyToClipboard(text) {
    if (!text) return;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
            .writeText(text)
            .then(function () {
                alert("복사되었습니다.");
            })
            .catch(function () {
                fallbackCopy(text);
            });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    var temp = document.createElement("textarea");
    temp.value = text;
    document.body.appendChild(temp);
    temp.select();

    try {
        document.execCommand("copy");
        alert("복사되었습니다.");
    } catch (e) {
        alert("복사에 실패했습니다. 직접 선택해서 복사해 주세요.");
    } finally {
        document.body.removeChild(temp);
    }
}

// ==========================================================
// 2. DOM 로드 후 전체 로직 실행
// ==========================================================
function bindRailInteraction(shellEl, inputEl) {
    if (!shellEl || !inputEl) return;

    var pauseTimer = null;
    var resumeTimer = null;

    function clearPauseTimer() {
        if (!pauseTimer) return;
        clearTimeout(pauseTimer);
        pauseTimer = null;
    }

    function clearResumeTimer() {
        if (!resumeTimer) return;
        clearTimeout(resumeTimer);
        resumeTimer = null;
    }

    function schedulePause() {
        clearResumeTimer();
        clearPauseTimer();
        pauseTimer = setTimeout(function () {
            shellEl.classList.add("is-paused");
            pauseTimer = null;
        }, 500);
    }

    function scheduleResume() {
        clearPauseTimer();
        clearResumeTimer();
        resumeTimer = setTimeout(function () {
            shellEl.classList.remove("is-paused");
            resumeTimer = null;
        }, 1000);
    }

    function onScrollAway() {
        var hasTyped = String(inputEl.value || "").trim() !== "";
        var isFocused = (document.activeElement === inputEl);
        if (!hasTyped && !isFocused) return;

        if (isFocused) {
            inputEl.blur();
        }
        scheduleResume();
    }

    inputEl.addEventListener("focus", schedulePause);
    inputEl.addEventListener("blur", scheduleResume);
    shellEl.addEventListener("mouseenter", schedulePause);
    shellEl.addEventListener("mouseleave", function () {
        if (document.activeElement === inputEl) return;
        scheduleResume();
    });

    document.addEventListener("scroll", onScrollAway, { passive: true, capture: true });
    window.addEventListener("wheel", onScrollAway, { passive: true });
    window.addEventListener("touchmove", onScrollAway, { passive: true });
}

document.addEventListener("DOMContentLoaded", function () {
    var globalSearchShell = document.getElementById("globalSearchShell");
    var globalSearchInputEl = document.getElementById("globalShareSearch");

    function syncGlobalSearchShellSize() {
        if (!globalSearchShell) return;

        if (window.innerWidth <= 900) {
            globalSearchShell.style.width = "100%";
            globalSearchShell.style.minWidth = "0";
            globalSearchShell.style.maxWidth = "none";
            return;
        }

        var w = Math.round(window.innerWidth * 0.4);
        if (w < 360) w = 360;
        if (w > 960) w = 960;

        globalSearchShell.style.width = w + "px";
        globalSearchShell.style.minWidth = "360px";
        globalSearchShell.style.maxWidth = "960px";

        var h = Math.round(globalSearchShell.getBoundingClientRect().height);
        globalSearchShell.setAttribute("data-size", w + "x" + h);
    }

    syncGlobalSearchShellSize();
    window.addEventListener("resize", syncGlobalSearchShellSize);

    if (globalSearchShell && globalSearchInputEl) {
        bindRailInteraction(globalSearchShell, globalSearchInputEl);
    }

    // --------------------------------------------------
    // (0) 탭 전환: App / Web / Tablet (각각 테이블 1개만 표시)
    // --------------------------------------------------
    var shareTabButtons = Array.prototype.slice.call(
        document.querySelectorAll(".share-tab-btn")
    );
    var shareTabPanels = Array.prototype.slice.call(
        document.querySelectorAll(".share-tab-panel")
    );

    function activateShareTab(tabKey) {
        shareTabButtons.forEach(function (btn) {
            var active = btn.getAttribute("data-tab") === tabKey;
            btn.classList.toggle("is-active", active);
            btn.setAttribute("aria-selected", active ? "true" : "false");
        });

        shareTabPanels.forEach(function (panel) {
            var active = panel.getAttribute("data-tab-panel") === tabKey;
            panel.hidden = !active;
        });
    }

    if (shareTabButtons.length > 0 && shareTabPanels.length > 0) {
        shareTabButtons.forEach(function (btn) {
            btn.addEventListener("click", function () {
                var tabKey = btn.getAttribute("data-tab") || "app";
                activateShareTab(tabKey);
            });
        });

        var initialTab = shareTabButtons[0].getAttribute("data-tab") || "app";
        activateShareTab(initialTab);
    }

    // --------------------------------------------------
    // (1) 사이트 이동 버튼
    // --------------------------------------------------
    var openButtons = document.querySelectorAll(".btn-open-url");
    openButtons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            var url = btn.getAttribute("data-url");
            openUrl(url);
        });
    });

    // --------------------------------------------------
    // (2) 아이디 / 비밀번호 복사 버튼
    // --------------------------------------------------
    var copyLoginButtons = document.querySelectorAll(".btn-copy-login");
    copyLoginButtons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            var login = btn.getAttribute("data-login");
            copyToClipboard(login);
        });
    });

    var copyPasswordButtons = document.querySelectorAll(".btn-copy-password");
    copyPasswordButtons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            var pw = btn.getAttribute("data-password");
            copyToClipboard(pw);
        });
    });

    // --------------------------------------------------
    // (3) 폼 / 체크박스 요소
    // --------------------------------------------------
    var byMeForm      = document.getElementById("sharedByMeForm");
    var toMeForm      = document.getElementById("sharedToMeForm");
    var unsharedForm  = document.getElementById("unsharedPasswordsForm");

    var byMeCheckAll  = document.getElementById("byMeCheckAll");
    var toMeCheckAll  = document.getElementById("toMeCheckAll");

    // 전체 선택 체크박스 - 내가 공유한
    if (byMeCheckAll && byMeForm) {
        byMeCheckAll.addEventListener("change", function () {
            var boxes = byMeForm.querySelectorAll('input[name="share_ids[]"]');
            boxes.forEach(function (cb) {
                cb.checked = byMeCheckAll.checked;
            });
        });
    }

    // 전체 선택 체크박스 - 내가 공유받은
    if (toMeCheckAll && toMeForm) {
        toMeCheckAll.addEventListener("change", function () {
            var boxes = toMeForm.querySelectorAll('input[name="share_ids[]"]');
            boxes.forEach(function (cb) {
                cb.checked = toMeCheckAll.checked;
            });
        });
    }

    // --------------------------------------------------
    // (4) 삭제 버튼 submit 전 확인
    // --------------------------------------------------
    if (byMeForm) {
        byMeForm.addEventListener("submit", function (e) {
            var checked = byMeForm.querySelectorAll('input[name="share_ids[]"]:checked');
            if (checked.length === 0) {
                e.preventDefault();
                alert("삭제할 항목을 하나 이상 선택해 주세요.");
                return;
            }
            if (!confirm("선택한 공유 설정을 정말 삭제하시겠습니까?")) {
                e.preventDefault();
            }
        });
    }

    if (toMeForm) {
        toMeForm.addEventListener("submit", function (e) {
            var checked = toMeForm.querySelectorAll('input[name="share_ids[]"]:checked');
            if (checked.length === 0) {
                e.preventDefault();
                alert("삭제할 항목을 하나 이상 선택해 주세요.");
                return;
            }
            if (!confirm("선택한 공유 설정을 정말 삭제하시겠습니까?")) {
                e.preventDefault();
            }
        });
    }

    // --------------------------------------------------
    // (5) 상단 고정 통합 검색: 3개 테이블 동시 필터링
    // --------------------------------------------------
    var globalSearchInput = document.getElementById("globalShareSearch");

    function debounce(fn, delayMs) {
        var timer = null;
        return function () {
            var context = this;
            var args = arguments;
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delayMs);
        };
    }

    // 공통 필터 함수
    function runGlobalFilter() {
        var lower = (globalSearchInput && globalSearchInput.value
            ? globalSearchInput.value
            : "").trim().toLowerCase();

        var allRows = document.querySelectorAll(
            ".share-container .password-table tbody tr"
        );

        allRows.forEach(function (row) {
            var hasOnlyColspanCell =
                row.children &&
                row.children.length === 1 &&
                row.children[0].hasAttribute("colspan");

            // "데이터 없음" 안내행은 검색 중에는 숨김
            if (hasOnlyColspanCell) {
                row.style.display = lower ? "none" : "";
                return;
            }

            var hay = (row.getAttribute("data-search") || row.innerText || "").toLowerCase();
            row.style.display = (!lower || hay.indexOf(lower) !== -1) ? "" : "none";
        });
    }

    if (globalSearchInput) {
        var debouncedGlobalFilter = debounce(runGlobalFilter, 90);
        globalSearchInput.addEventListener("input", function () {
            debouncedGlobalFilter();
        });
        globalSearchInput.addEventListener("keydown", function (e) {
            if (e.key === "Enter" || e.keyCode === 13) {
                e.preventDefault();
                runGlobalFilter();
            }
        });
    }
});
