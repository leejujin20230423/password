// ================================================
// 1. DOMContentLoaded 이후 기본 이벤트 바인딩
//    - 전체 선택 체크박스 기능
//    - 비밀번호 리스트 검색 기능
//    - 전화번호 입력창 엔터 검색 기능
// ================================================
document.addEventListener("DOMContentLoaded", function () {
  // 1) "전체 선택" 체크박스
  var checkAll = document.getElementById("checkAll");

  if (checkAll) {
    checkAll.addEventListener("change", function () {
      var boxes = document.querySelectorAll('input[name="password_ids[]"]');
      boxes.forEach(function (cb) {
        cb.checked = checkAll.checked;
      });
    });
  }

  // 2) 비밀번호 리스트 검색 초기화
  initPasswordListSearch();

  // 3) 전화번호 입력창에서 엔터만 쳐도 검색되도록 처리
  var phoneInput = document.getElementById("search_phone");
  if (phoneInput) {
    phoneInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.keyCode === 13) {
        e.preventDefault(); // 폼 submit 막기
        searchUserByPhone(); // 검색 + 바로 공유대상 추가
      }
    });
  }
});

// ================================================
// 1-1. 비밀번호 리스트 검색 초기화
// ================================================
function initPasswordListSearch() {
  var input = document.getElementById("passwordListSearch");
  if (!input) return;

  var tbody = document.querySelector(".password-table tbody");
  if (!tbody) return;

  var rows = Array.prototype.slice.call(tbody.querySelectorAll("tr"));

  input.addEventListener("input", function () {
    var keyword = input.value.trim().toLowerCase();

    rows.forEach(function (tr) {
      var searchText = (tr.getAttribute("data-search") || "").toLowerCase();

      if (!keyword) {
        tr.style.display = "";
      } else if (searchText.indexOf(keyword) !== -1) {
        tr.style.display = "";
      } else {
        tr.style.display = "none";
      }
    });
  });
}

// ================================================
// 2. 전화번호로 회원 검색
//    - 서버에 AJAX 요청 → users 테이블에서 검색
//    - 성공 시: "선택된 공유대상" 리스트에 바로 추가
//    - 실패 시: 문자/카톡으로 초대 안내
//    - ⚠️ 로그인한 본인 번호면 공유 대상에서 제외
// ================================================
function searchUserByPhone() {
  var phoneInput = document.getElementById("search_phone");
  var resultBox = document.getElementById("searchResult");

  if (!phoneInput || !resultBox) return;

  var raw = phoneInput.value.trim();
  if (!raw) {
    alert("전화번호를 입력하세요.");
    return;
  }

  var url =
    "/password_6_share/password_6_share_route/password_6_share_ajax_admin.php";

  var params = "action=search_user" + "&phone=" + encodeURIComponent(raw);

  var xhr = new XMLHttpRequest();
  xhr.open("POST", url, true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        try {
          var res = JSON.parse(xhr.responseText);

          // ✅ 회원 존재
          if (res.ok && res.user) {
            var u = res.user; // { user_no, username, phone }

            // 🔹 로그인한 본인 번호인지 확인
            var currentUserNo = 0;
            if (typeof window.PASS_USER_NO !== "undefined") {
              currentUserNo = parseInt(window.PASS_USER_NO, 10) || 0;
            }

            if (currentUserNo && parseInt(u.user_no, 10) === currentUserNo) {
              alert(
                "본인 전화번호는 공유 대상으로 선택할 수 없습니다.\n다른 사용자의 전화번호를 검색해 주세요."
              );
              resultBox.innerHTML =
                '<span style="color:#d9534f;">본인 번호는 공유 대상에 추가할 수 없습니다.</span>';
              phoneInput.focus();
              return;
            }

            // 🔹 정상적인 다른 회원인 경우:
            //    - 선택된 공유대상 리스트에 바로 추가
            addTarget(u.user_no, u.username, u.phone || "");

            //    - 결과 영역은 안내 문구
            resultBox.textContent =
              "공유 대상에 추가되었습니다. 여러 명을 추가할 수 있습니다.";
          }
          // ❌ 회원 없음 (가입 유도)
          else {
            resultBox.innerHTML =
              '<span style="color:#d9534f;">해당 전화번호로 등록된 회원이 없습니다.</span><br>' +
              '<span style="font-size:12px; color:#6b7280;">회원으로 등록된 사용자만 검색됩니다. 상대방이 등록하지 않았다면 로그인 화면에서 카카오톡/문자로 초대해 주세요.</span><br>' +
              '<button type="button" onclick="inviteBySms();">카카오톡/문자로 초대하기</button>';
          }

          // 검색 처리 후 입력창 비우기
          phoneInput.value = "";
        } catch (e) {
          console.error(e);
          resultBox.textContent = "응답 처리 중 오류가 발생했습니다.";
        }
      } else {
        resultBox.textContent =
          "서버 통신 오류입니다. 잠시 후 다시 시도해 주세요.";
      }
    }
  };

  xhr.send(params);
}

// ================================================
// 3. XSS 방지용 문자열 이스케이프
// ================================================
function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

// ================================================
// 4. 공유 대상 목록에 사용자 추가
// ================================================
function addTarget(userNo, username, phone) {
  var list = document.getElementById("selectedTargets");
  if (!list) return;

  // 이미 추가된 사용자면 막기
  var exists = list.querySelector('li[data-user-no="' + userNo + '"]');
  if (exists) {
    alert("이미 공유 대상에 추가된 사용자입니다.");
    return;
  }

  var li = document.createElement("li");
  li.setAttribute("data-user-no", userNo);

  var phoneText = phone ? " (" + escapeHtml(phone) + ")" : "";

  li.innerHTML =
    "" +
    '<span class="target-name">' +
    escapeHtml(username) +
    phoneText +
    "</span>" +
    '<button type="button"' +
    '        class="btn-remove-target"' +
    '        onclick="removeTarget(this);">' +
    "    삭제" +
    "</button>" +
    '<input type="hidden" name="target_user_ids[]" value="' +
    userNo +
    '">';

  list.appendChild(li);
}

// ================================================
// 5. 공유 대상 목록에서 사용자 제거
// ================================================
function removeTarget(btn) {
  var li = btn.closest("li");
  if (li) {
    li.remove();
  }
}

// ================================================
// 6. 회원이 아닐 때: 문자/카톡으로 초대
// ================================================
function inviteBySms() {
  var siteUrl = "https://pass.bizstore.co.kr";

  var senderName =
    typeof window.PASS_SENDER_NAME === "string" &&
    window.PASS_SENDER_NAME.trim() !== ""
      ? window.PASS_SENDER_NAME
      : "지인";

  var text =
    senderName +
    "이 PASS 비밀번호 관리 가입을 요청합니다.\n" +
    "PASS에 가입하고 효율적으로 비밀번호를 관리해 보세요.\n" +
    siteUrl;

  if (navigator.share) {
    navigator
      .share({
        title: "PASS 비밀번호 관리 초대",
        text: text,
        url: siteUrl,
      })
      .catch(function (err) {
        console.log("공유 취소 또는 실패:", err);
      });
    return;
  }

  var smsBody = encodeURIComponent(text);
  window.location.href = "sms:?body=" + smsBody;
}

// ================================================
// 7. 공유 설정 저장
// ================================================
const shareForm = document.getElementById("shareForm");

function submitShareForm() {
  const checkedPasswords = document.querySelectorAll(
    'input[name="password_ids[]"]:checked'
  );
  if (checkedPasswords.length === 0) {
    alert("공유할 비밀번호를 하나 이상 선택해 주세요.");
    return;
  }

  const targetInputs = document.querySelectorAll(
    'input[name="target_user_ids[]"]'
  );

  if (targetInputs.length === 0) {
    alert("공유 대상 사용자를 하나 이상 추가해 주세요.");
    return;
  }

  if (shareForm) {
    shareForm.submit();
  } else {
    alert("공유 설정 폼을 찾을 수 없습니다.");
  }
}
