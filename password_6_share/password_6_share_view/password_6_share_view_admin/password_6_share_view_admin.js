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
    phoneInput.addEventListener("input", function (e) {
      e.target.value = formatPhoneNumber(e.target.value);
    });
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
  var searchBtn = document.getElementById("passwordListSearchBtn");

  var tbody = document.querySelector(".password-table tbody");
  if (!tbody) return;

  var rows = Array.prototype.slice.call(tbody.querySelectorAll("tr"));

  function runFilter() {
    var keyword = input.value.trim().toLowerCase();

    rows.forEach(function (tr) {
      var searchText = (
        tr.getAttribute("data-search") ||
        tr.textContent ||
        ""
      ).toLowerCase();

      if (!keyword) {
        tr.style.display = "";
      } else if (searchText.indexOf(keyword) !== -1) {
        tr.style.display = "";
      } else {
        tr.style.display = "none";
      }
    });
  }

  var debouncedRunFilter = debounce(runFilter, 160);
  input.addEventListener("input", debouncedRunFilter);

  if (searchBtn) {
    searchBtn.addEventListener("click", runFilter);
  }

  input.addEventListener("keydown", function (e) {
    if (e.key === "Enter" || e.keyCode === 13) {
      e.preventDefault();
      runFilter();
    }
  });
}

function parseJsonWithFallback(rawText) {
  var text = String(rawText || "").trim();
  if (!text) return null;

  try {
    return JSON.parse(text);
  } catch (e) {
    // PHP warning/notice가 JSON 앞뒤에 섞인 경우를 대비
    var start = text.indexOf("{");
    var end = text.lastIndexOf("}");
    if (start !== -1 && end !== -1 && end > start) {
      var candidate = text.slice(start, end + 1);
      try {
        return JSON.parse(candidate);
      } catch (ignored) {
        return null;
      }
    }
    return null;
  }
}

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
      // 요청이 끝났을 때
      if (xhr.status === 200) {
        // 서버 응답 상태 200이면
        var res = parseJsonWithFallback(xhr.responseText);
        if (!res) {
          resultBox.textContent =
            "응답 처리 중 오류가 발생했습니다. 다시 로그인 후 시도해 주세요.";
          console.error("raw response:", xhr.responseText);
          return;
        }

        var users = [];
        if (res.ok && Array.isArray(res.users)) {
          users = res.users.slice();
        } else if (res.ok && res.user) {
          users = [res.user];
        } else if (res.ok && Array.isArray(res.data)) {
          users = res.data.slice();
        }

        if (users.length > 0) {
          var currentUserNo = parseInt(window.PASS_USER_NO || 0, 10) || 0;
          var filtered = users.filter(function (u) {
            return !(currentUserNo && parseInt(u.user_no, 10) === currentUserNo);
          });

          if (filtered.length === 0) {
            resultBox.innerHTML =
              '<span class="error-text">본인 번호는 공유 대상에 추가할 수 없습니다.</span>';
            phoneInput.focus();
            return;
          }

          renderSearchResultUsers(resultBox, filtered);
          return;
        }

        var msg =
          res && res.msg ? res.msg : "해당 전화번호로 등록된 회원이 없습니다.";
        resultBox.innerHTML =
          '<span class="error-text">' + escapeHtml(msg) + "</span>";
      } else {
        resultBox.textContent =
          "서버 통신 오류입니다. 잠시 후 다시 시도해 주세요.";
      }
    }
  };

  xhr.send(params); // 서버로 요청 보내기
}

function renderSearchResultUsers(resultBox, users) {
  if (!resultBox) return;

  var html = '<div class="search-users-title">검색된 회원 목록</div>';
  html += '<ul class="search-user-list">';

  users.forEach(function (u) {
    var userNo = parseInt(u.user_no, 10) || 0;
    if (!userNo) return;

    var username = escapeHtml(u.username || "");
    var phone = escapeHtml(u.phone || "");
    var desc = phone ? username + " (" + phone + ")" : username;

    html +=
      '<li class="search-user-item">' +
      '<span class="search-user-name">' +
      desc +
      "</span>" +
      '<button type="button" class="btn-add-target" data-user-no="' +
      userNo +
      '" data-username="' +
      username +
      '" data-phone="' +
      phone +
      '">추가</button>' +
      "</li>";
  });

  html += "</ul>";
  resultBox.innerHTML = html;

  var addButtons = resultBox.querySelectorAll(".btn-add-target");
  addButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var userNo = btn.getAttribute("data-user-no");
      var username = btn.getAttribute("data-username");
      var phone = btn.getAttribute("data-phone");
      addTarget(userNo, username, phone);
    });
  });
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
  li.className = "target-item";

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

function formatPhoneNumber(input) {
  var digits = String(input || "").replace(/\D+/g, "");
  if (digits.length <= 3) return digits;
  if (digits.length <= 7) return digits.replace(/(\d{3})(\d+)/, "$1-$2");
  if (digits.length <= 11) return digits.replace(/(\d{3})(\d{3,4})(\d+)/, "$1-$2-$3");
  return digits.slice(0, 11).replace(/(\d{3})(\d{4})(\d{4})/, "$1-$2-$3");
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
