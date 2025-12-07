// /**
//  * ==========================================================
//  * Password 공유현황 (관리자) 전용 JS
//  *  - 1) 전체 선택 체크박스
//  *  - 2) 삭제 버튼 클릭 시 선택 여부 / 확인창
//  *  - 3) 검색 (엔터, 버튼 클릭, 실시간 입력)
//  * ==========================================================
//  */

// document.addEventListener("DOMContentLoaded", function () {
//     // -------------------------------
//     // 1. 폼 엘리먼트 가져오기
//     // -------------------------------
//     var byMeForm      = document.getElementById("sharedByMeForm");          // 내가 공유한 목록
//     var toMeForm      = document.getElementById("sharedToMeForm");          // 내가 공유받은 목록
//     var unsharedForm  = document.getElementById("unsharedPasswordsForm");   // 공유하지 않은 목록

//     // -------------------------------
//     // 2. 전체 선택 체크박스
//     // -------------------------------
//     var byMeCheckAll = document.getElementById("byMeCheckAll");
//     if (byMeCheckAll && byMeForm) {
//         byMeCheckAll.addEventListener("change", function () {
//             var boxes = byMeForm.querySelectorAll('input[name="share_ids[]"]');
//             boxes.forEach(function (cb) {
//                 cb.checked = byMeCheckAll.checked;
//             });
//         });
//     }

//     var toMeCheckAll = document.getElementById("toMeCheckAll");
//     if (toMeCheckAll && toMeForm) {
//         toMeCheckAll.addEventListener("change", function () {
//             var boxes = toMeForm.querySelectorAll('input[name="share_ids[]"]');
//             boxes.forEach(function (cb) {
//                 cb.checked = toMeCheckAll.checked;
//             });
//         });
//     }

//     // (공유하지 않은 목록은 체크박스가 없으므로 전체선택 없음)

//     // -------------------------------
//     // 3. 삭제 버튼 submit 전 확인
//     // -------------------------------
//     if (byMeForm) {
//         byMeForm.addEventListener("submit", function (e) {
//             var checked = byMeForm.querySelectorAll('input[name="share_ids[]"]:checked');
//             if (checked.length === 0) {
//                 e.preventDefault();
//                 alert("삭제할 항목을 하나 이상 선택해 주세요.");
//                 return;
//             }
//             if (!confirm("선택한 공유 설정을 정말 삭제하시겠습니까?")) {
//                 e.preventDefault();
//             }
//         });
//     }

//     if (toMeForm) {
//         toMeForm.addEventListener("submit", function (e) {
//             var checked = toMeForm.querySelectorAll('input[name="share_ids[]"]:checked');
//             if (checked.length === 0) {
//                 e.preventDefault();
//                 alert("삭제할 항목을 하나 이상 선택해 주세요.");
//                 return;
//             }
//             if (!confirm("선택한 공유 설정을 정말 삭제하시겠습니까?")) {
//                 e.preventDefault();
//             }
//         });
//     }

//     // -------------------------------
//     // 4. 검색 관련 엘리먼트
//     // -------------------------------
//     var byMeInput     = document.getElementById("byMeSearch");
//     var byMeBtn       = document.getElementById("byMeSearchBtn");

//     var toMeInput     = document.getElementById("toMeSearch");
//     var toMeBtn       = document.getElementById("toMeSearchBtn");

//     var unsharedInput = document.getElementById("unsharedSearch");
//     var unsharedBtn   = document.getElementById("unsharedSearchBtn");

//     // -------------------------------
//     // 5. 공통 필터 함수
//     //    - formElem: 각 섹션 form
//     //    - keyword : 검색어
//     // -------------------------------
//     function filterRows(formElem, keyword) {
//         if (!formElem) return;

//         var rows = formElem.querySelectorAll("tbody tr");
//         var lower = (keyword || "").trim().toLowerCase();

//         rows.forEach(function (row) {
//             // "데이터 없음" 한 줄도 같이 tr 이라서 함께 처리됨
//             var hay = (
//                 row.getAttribute("data-search") ||
//                 row.innerText ||
//                 ""
//             ).toLowerCase();

//             // 검색어 없으면 모두 보이기
//             if (!lower || hay.indexOf(lower) !== -1) {
//                 row.style.display = "";
//             } else {
//                 row.style.display = "none";
//             }
//         });
//     }

//     // -------------------------------
//     // 6. 섹션별 필터 함수
//     // -------------------------------
//     function filterByMe() {
//         if (!byMeForm || !byMeInput) return;
//         filterRows(byMeForm, byMeInput.value);
//     }

//     function filterToMe() {
//         if (!toMeForm || !toMeInput) return;
//         filterRows(toMeForm, toMeInput.value);
//     }

//     function filterUnshared() {
//         if (!unsharedForm || !unsharedInput) return;
//         filterRows(unsharedForm, unsharedInput.value);
//     }

//     // -------------------------------
//     // 7. 검색 버튼 클릭 이벤트
//     // -------------------------------
//     if (byMeBtn) {
//         byMeBtn.addEventListener("click", function () {
//             filterByMe();
//         });
//     }
//     if (toMeBtn) {
//         toMeBtn.addEventListener("click", function () {
//             filterToMe();
//         });
//     }
//     if (unsharedBtn) {
//         unsharedBtn.addEventListener("click", function () {
//             filterUnshared();
//         });
//     }

//     // -------------------------------
//     // 8. 엔터키로 검색 (폼 submit 방지)
//     // -------------------------------
//     function handleSearchEnter(e) {
//         if (e.key === "Enter") {
//             e.preventDefault();  // 폼 submit 막기 (삭제/새로고침 방지)

//             if (e.target.id === "byMeSearch") {
//                 filterByMe();
//             } else if (e.target.id === "toMeSearch") {
//                 filterToMe();
//             } else if (e.target.id === "unsharedSearch") {
//                 filterUnshared();
//             }
//         }
//     }

//     if (byMeInput) {
//         byMeInput.addEventListener("keydown", handleSearchEnter);
//     }
//     if (toMeInput) {
//         toMeInput.addEventListener("keydown", handleSearchEnter);
//     }
//     if (unsharedInput) {
//         unsharedInput.addEventListener("keydown", handleSearchEnter);
//     }

//     // -------------------------------
//     // 9. 입력할 때마다 실시간 필터 (선택 사항이지만 UX 좋아짐)
//     // -------------------------------
//     if (byMeInput) {
//         byMeInput.addEventListener("input", filterByMe);
//     }
//     if (toMeInput) {
//         toMeInput.addEventListener("input", filterToMe);
//     }
//     if (unsharedInput) {
//         unsharedInput.addEventListener("input", filterUnshared);
//     }
// });
