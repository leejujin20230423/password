
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
    window.open(url, "_blank");
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
document.addEventListener("DOMContentLoaded", function () {
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
    // (5) 검색 input / 버튼
    // --------------------------------------------------
    var byMeInput     = document.getElementById("byMeSearch");
    var byMeBtn       = document.getElementById("byMeSearchBtn");

    var toMeInput     = document.getElementById("toMeSearch");
    var toMeBtn       = document.getElementById("toMeSearchBtn");

    var unsharedInput = document.getElementById("unsharedSearch");
    var unsharedBtn   = document.getElementById("unsharedSearchBtn");

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
    function filterRows(formElem, keyword) {
        if (!formElem) return;

        var rows  = formElem.querySelectorAll("tbody tr");
        var lower = (keyword || "").trim().toLowerCase();

        rows.forEach(function (row) {
            var hay = (
                row.getAttribute("data-search") ||
                row.innerText ||
                ""
            ).toLowerCase();

            if (!lower || hay.indexOf(lower) !== -1) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });

    }

    function filterByMe() {
        if (!byMeForm || !byMeInput) return;
        filterRows(byMeForm, byMeInput.value);
    }

    function filterToMe() {
        if (!toMeForm || !toMeInput) return;
        filterRows(toMeForm, toMeInput.value);
    }

    function filterUnshared() {
        if (!unsharedForm || !unsharedInput) return;
        filterRows(unsharedForm, unsharedInput.value);
    }

    var debouncedByMe = debounce(filterByMe, 160);
    var debouncedToMe = debounce(filterToMe, 160);
    var debouncedUnshared = debounce(filterUnshared, 160);

    // 👉 버튼 클릭 시에만 검색 실행
    if (byMeBtn) {
        byMeBtn.addEventListener("click", function () {
            filterByMe();
        });
    }
    if (toMeBtn) {
        toMeBtn.addEventListener("click", function () {
            filterToMe();
        });
    }
    if (unsharedBtn) {
        unsharedBtn.addEventListener("click", function () {
            filterUnshared();
        });
    }

    // 👉 입력 시 자동 필터링 (디바운스 적용)
    if (byMeInput) {
        byMeInput.addEventListener("input", function () {
            debouncedByMe();
        });
    }
    if (toMeInput) {
        toMeInput.addEventListener("input", function () {
            debouncedToMe();
        });
    }
    if (unsharedInput) {
        unsharedInput.addEventListener("input", function () {
            debouncedUnshared();
        });
    }

    // 👉 Enter 키를 눌렀을 때만 검색 실행 (타이핑 중 실시간 X)
    function handleSearchEnter(e) {
        if (e.key === "Enter" || e.keyCode === 13) {
            e.preventDefault(); // 폼 submit 막기 (페이지 리로드 방지)

            if (e.target.id === "byMeSearch") {
                filterByMe();
            } else if (e.target.id === "toMeSearch") {
                filterToMe();
            } else if (e.target.id === "unsharedSearch") {
                filterUnshared();
            }
        }
    }

    if (byMeInput) {
        byMeInput.addEventListener("keydown", handleSearchEnter);
    }
    if (toMeInput) {
        toMeInput.addEventListener("keydown", handleSearchEnter);
    }
    if (unsharedInput) {
        unsharedInput.addEventListener("keydown", handleSearchEnter);
    }
});
