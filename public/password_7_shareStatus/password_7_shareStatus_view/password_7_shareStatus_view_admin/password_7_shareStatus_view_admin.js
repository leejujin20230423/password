// ========================================================
// 0. 공통 유틸: 사이트 이동 / 비밀번호 복사
// ========================================================

// URL 새 창으로 열기 (다른 곳에서 이미 정의되어 있어도 문제 없음)
if (typeof openUrl !== "function") {
    function openUrl(raw) {
        if (!raw) return;
        var url = String(raw).trim();
        if (!url) return;

        // http/https 없으면 자동으로 https:// 붙이기
        if (!/^https?:\/\//i.test(url)) {
            url = "https://" + url;
        }
        window.open(url, "_blank");
    }
}

// ✅ Clipboard API 사용이 안 될 때 쓸 폴백 함수
function fallbackCopyTextToClipboard(text) {
    var textarea = document.createElement("textarea");
    textarea.value = text;

    textarea.style.position = "fixed";
    textarea.style.top = "0";
    textarea.style.left = "0";
    textarea.style.opacity = "0";

    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
        var successful = document.execCommand("copy");
        if (successful) {
            alert("비밀번호가 클립보드에 복사되었습니다.\n사이트 로그인 화면에서 바로 붙여넣기 하세요.");
        } else {
            alert("복사에 실패했습니다. 직접 드래그해서 복사해주세요.");
        }
    } catch (e) {
        console.error("복사 중 오류:", e);
        alert("복사에 실패했습니다. 직접 드래그해서 복사해주세요.");
    }

    document.body.removeChild(textarea);
}

// ✅ 비밀번호 복사 버튼 (data-password에 담긴 복호화 비밀번호 복사)
function copyPassword(btn) {
    if (!btn) return;

    // PHP에서 넣어준 data-password 속성 읽기 (복호화된 평문 비밀번호)
    var pw = btn.getAttribute("data-password") || "";
    pw = pw.trim();

    if (!pw) {
        alert("복사할 비밀번호가 없습니다.");
        return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
            .writeText(pw)
            .then(function () {
                alert("비밀번호가 클립보드에 복사되었습니다.\n사이트 로그인 화면에서 바로 붙여넣기 하세요.");
            })
            .catch(function (err) {
                console.error("Clipboard API 복사 실패:", err);
                fallbackCopyTextToClipboard(pw);
            });
    } else {
        // 구형 브라우저용 fallback
        fallbackCopyTextToClipboard(pw);
    }
}

// ========================================================
// 1. DOMContentLoaded 이후 이벤트 바인딩
// ========================================================
document.addEventListener("DOMContentLoaded", function () {
    var byMeForm = document.getElementById("sharedByMeForm");
    var toMeForm = document.getElementById("sharedToMeForm");

    // -----------------------------
    // 1-1. 전체 선택 체크박스
    // -----------------------------
    var byMeCheckAll = document.getElementById("byMeCheckAll");
    if (byMeCheckAll && byMeForm) {
        byMeCheckAll.addEventListener("change", function () {
            var boxes = byMeForm.querySelectorAll('input[name="share_ids[]"]');
            boxes.forEach(function (cb) {
                cb.checked = byMeCheckAll.checked;
            });
        });
    }

    var toMeCheckAll = document.getElementById("toMeCheckAll");
    if (toMeCheckAll && toMeForm) {
        toMeCheckAll.addEventListener("change", function () {
            var boxes = toMeForm.querySelectorAll('input[name="share_ids[]"]');
            boxes.forEach(function (cb) {
                cb.checked = toMeCheckAll.checked;
            });
        });
    }

    // -----------------------------
    // 1-2. 삭제 버튼 누르기 전 확인
    // -----------------------------
    if (byMeForm) {
        byMeForm.addEventListener("submit", function (e) {
            var checked = byMeForm.querySelectorAll(
                'input[name="share_ids[]"]:checked'
            );
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
            var checked = toMeForm.querySelectorAll(
                'input[name="share_ids[]"]:checked'
            );
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

    // ====================================================
    // 2. 검색 기능 (버튼 + 엔터키)
    // ====================================================
    var byMeInput = document.getElementById("byMeSearch");
    var byMeBtn = document.getElementById("byMeSearchBtn");
    var toMeInput = document.getElementById("toMeSearch");
    var toMeBtn = document.getElementById("toMeSearchBtn");

    // 공통 필터 함수
    function filterRows(formElem, keyword) {
        if (!formElem) return;
        var rows = formElem.querySelectorAll("tbody tr");
        var lower = keyword.trim().toLowerCase();

        rows.forEach(function (row) {
            // data-search 가 없을 경우를 대비해서 innerText 도 같이 사용
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
        if (!byMeInput || !byMeForm) return;
        filterRows(byMeForm, byMeInput.value);
    }

    function filterToMe() {
        if (!toMeInput || !toMeForm) return;
        filterRows(toMeForm, toMeInput.value);
    }

    // 버튼 클릭 시 검색
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

    // 엔터키로 검색 (폼 submit 막기)
    function handleSearchEnter(e) {
        if (e.key === "Enter") {
            e.preventDefault(); // ❗ 폼 전송 막기 (삭제 submit 방지)

            if (e.target.id === "byMeSearch") {
                filterByMe();
            } else if (e.target.id === "toMeSearch") {
                filterToMe();
            }
        }
    }

    if (byMeInput) {
        byMeInput.addEventListener("keydown", handleSearchEnter);
    }
    if (toMeInput) {
        toMeInput.addEventListener("keydown", handleSearchEnter);
    }
});
