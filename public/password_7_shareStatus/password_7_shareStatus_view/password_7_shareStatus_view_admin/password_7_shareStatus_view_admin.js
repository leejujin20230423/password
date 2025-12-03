// document.addEventListener("DOMContentLoaded", function () {
//     // 기존 폼 참조
//     var byMeForm = document.getElementById("sharedByMeForm");
//     var toMeForm = document.getElementById("sharedToMeForm");
//     var unsharedForm = document.getElementById("unsharedPasswordsForm");  // unsharedForm 정의 추가

//     // 전체 선택 체크박스
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

//     // 삭제 버튼 전 확인
//     if (byMeForm) {
//         byMeForm.addEventListener("submit", function (e) {
//             var checked = byMeForm.querySelectorAll(
//                 'input[name="share_ids[]"]:checked'
//             );
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
//             var checked = toMeForm.querySelectorAll(
//                 'input[name="share_ids[]"]:checked'
//             );
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

//     // 검색 기능
//     var byMeInput = document.getElementById("byMeSearch");
//     var byMeBtn = document.getElementById("byMeSearchBtn");
//     var toMeInput = document.getElementById("toMeSearch");
//     var toMeBtn = document.getElementById("toMeSearchBtn");
//     var unsharedInput = document.getElementById("unsharedSearch");
//     var unsharedBtn = document.getElementById("unsharedSearchBtn");

//     // 공통 필터 함수
//     function filterRows(formElem, keyword) {
//         if (!formElem) return;
//         var rows = formElem.querySelectorAll("tbody tr");
//         var lower = keyword.trim().toLowerCase();

//         rows.forEach(function (row) {
//             var hay = (
//                 row.getAttribute("data-search") ||
//                 row.innerText ||
//                 ""
//             ).toLowerCase();

//             if (!lower || hay.indexOf(lower) !== -1) {
//                 row.style.display = "";
//             } else {
//                 row.style.display = "none";
//             }
//         });
//     }

//     // 개별 필터 함수
//     function filterByMe() {
//         if (!byMeInput || !byMeForm) return;
//         filterRows(byMeForm, byMeInput.value);
//     }

//     function filterToMe() {
//         if (!toMeInput || !toMeForm) return;
//         filterRows(toMeForm, toMeInput.value);
//     }

//     function filterUnshared() {
//         if (!unsharedInput || !unsharedForm) return;  // unsharedForm 확인 추가
//         filterRows(unsharedForm, unsharedInput.value);
//     }

//     // 버튼 클릭 시 검색
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

//     // 엔터키로 검색 (폼 submit 막기)
//     function handleSearchEnter(e) {
//         if (e.key === "Enter") {
//             e.preventDefault(); // 폼 전송 막기 (삭제 submit 방지)

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
// });
