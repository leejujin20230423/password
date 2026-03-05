// ==========================================
// 1) 전역 함수: URL 열기 (HTML onclick 에서 직접 호출)
// ==========================================
function openUrl(raw) {
  if (!raw) return;
  var url = String(raw).trim();
  if (!url) return;

  // http/https 없으면 https:// 자동 붙이기
  if (!/^https?:\/\//i.test(url)) {
    url = "https://" + url;
  }
  window.open(url, "_blank");
}

// ==========================================
// 2) 비밀번호 복호화 API 경로
//    - 현재 페이지 PHP 로 POST 전송
// ==========================================
var DECRYPT_URL = window.location.pathname;

// ==========================================
// 3) DOMContentLoaded 이후 이벤트 바인딩
// ==========================================
document.addEventListener("DOMContentLoaded", function () {
  var contactPhoneInput = document.getElementById("contact_phone");
  if (contactPhoneInput) {
    contactPhoneInput.addEventListener("input", function (e) {
      e.target.value = formatPhoneNumber(e.target.value);
    });
  }

  // ---- 아이디 복사 관련 요소 ----
  var loginInput   = document.getElementById("login_id");
  var copyLoginBtn = document.getElementById("copyLoginIdBtn");

  // ---- 비밀번호 토글/복사 관련 요소 ----
  var encInput    = document.getElementById("password_encrypted_view"); // 화면에 보이는 input
  var plainHidden = document.getElementById("password_plain_hidden");   // 숨겨진 평문(옵션)
  var toggleBtn   = document.getElementById("togglePasswordView");      // "암호 보기" 버튼
  var copyPwBtn   = document.getElementById("copyPasswordBtn");         // "복사" 버튼
  var idHidden    = document.getElementById("password_idno");           // 현재 레코드 PK

  // ============================
  // 1. 아이디 복사
  // ============================
  if (loginInput && copyLoginBtn) {
    copyLoginBtn.addEventListener("click", function () {
      var text = String(loginInput.value || "").trim();
      if (!text) {
        alert("복사할 아이디가 없습니다.");
        return;
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
          .writeText(text)
          .then(function () {
            alert("아이디가 클립보드에 복사되었습니다.");
          })
          .catch(function () {
            alert("아이디 복사에 실패했습니다. 브라우저 권한을 확인해 주세요.");
          });
      } else {
        try {
          loginInput.focus();
          loginInput.select();
          var ok = document.execCommand("copy");
          if (ok) {
            alert("아이디가 클립보드에 복사되었습니다.");
          } else {
            alert("아이디 복사에 실패했습니다.");
          }
        } catch (e) {
          console.error(e);
          alert("아이디 복사에 실패했습니다.");
        }
      }
    });
  }

  // ============================
  // 2. 비밀번호 암호/평문 토글 + 복사
  // ============================
  if (encInput && toggleBtn && idHidden) {
    var encryptedVal =
      encInput.getAttribute("data-encrypted") || encInput.value || "";
    var showingPlain = false; // 현재 평문 표시 여부

    // ✅ 초기 상태: 암호화 값 + password 타입으로 숨김
    encInput.type = "password";
    encInput.value = encryptedVal;
    toggleBtn.textContent = "암호 보기";

    // ------------------------------------------
    // 🔵 암호 보기용: 로그인 비밀번호를 "항상" 다시 물어보고 복호화
    //  - 여기서는 캐시 안 쓰고, 누를 때마다 로그인 비번 물어봄
    // ------------------------------------------
    function decryptWithLogin(callback) {
      // 1) 로그인 비밀번호 입력
      var input = window.prompt(
        "로그인 비밀번호를 다시 입력하세요.\n" +
          "※ 저장된 비밀번호를 보기 전에 한 번 더 확인합니다."
      );
      if (input === null) {
        // 취소
        return;
      }

      var loginPw = String(input).trim();
      if (!loginPw) {
        alert("로그인 비밀번호를 입력해 주세요.");
        return;
      }

      if (typeof fetch === "undefined") {
        alert("이 브라우저에서는 추가 검증을 지원하지 않습니다.");
        return;
      }

      var pwId = String(idHidden.value || "").trim();
      if (!pwId) {
        alert("선택된 비밀번호 레코드가 없습니다.");
        return;
      }

      var formData = new FormData();
      formData.append("ajax", "decrypt_password");
      formData.append("login_password", loginPw);
      formData.append("password_idno", pwId);

      fetch(DECRYPT_URL, {
        method: "POST",
        body: formData,
        credentials: "same-origin"
      })
        .then(function (res) {
          if (!res.ok) {
            throw new Error("HTTP " + res.status);
          }
          return res.json();
        })
        .then(function (data) {
          if (!data || !data.ok) {
            alert((data && data.msg) || "비밀번호 복호화에 실패했습니다.");
            return;
          }

          var plain = String(data.plain || "");

          // 옵션: 서버 응답 평문을 hidden에 저장할 수는 있음(다른 용도)
          if (plainHidden) {
            plainHidden.value = plain;
          }

          if (typeof callback === "function") {
            callback(plain);
          }
        })
        .catch(function (err) {
          console.error("decrypt_password error:", err);
          alert("서버 통신 중 오류가 발생했습니다.");
        });
    }

    // 🔵 "암호 보기" 버튼: 항상 decryptWithLogin 호출 + type 토글
    toggleBtn.addEventListener("click", function () {
      if (!showingPlain) {
        // 평문을 보고 싶을 때 → 로그인 비번 재입력 + 복호화
        decryptWithLogin(function (plain) {
          encInput.type = "text";       // 👈 평문 보이게
          encInput.value = plain;
          showingPlain = true;
          toggleBtn.textContent = "암호화 값 보기";
        });
      } else {
        // 다시 암호화 값으로
        encInput.type = "password";     // 👈 다시 가리기
        encInput.value = encryptedVal;
        showingPlain = false;
        toggleBtn.textContent = "암호 보기";
      }
    });
  }

  function requestPasswordCopy(passwordId, onSuccess) {
    if (typeof fetch === "undefined") {
      alert("이 브라우저에서는 복호화 복사를 지원하지 않습니다.");
      return;
    }

    var formData = new FormData();
    formData.append("ajax", "decrypt_password_copy");
    formData.append("password_idno", passwordId);

    fetch(DECRYPT_URL, {
      method: "POST",
      body: formData,
      credentials: "same-origin"
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error("HTTP " + res.status);
        }
        return res.json();
      })
      .then(function (data) {
        if (!data || !data.ok) {
          alert((data && data.msg) || "비밀번호 복호화에 실패했습니다.");
          return;
        }

        if (typeof onSuccess === "function") {
          onSuccess(String(data.plain || ""));
        }
      })
      .catch(function (err) {
        console.error("decrypt_password_copy error:", err);
        alert("서버 통신 중 오류가 발생했습니다.");
      });
  }

  // 🟢 상세 패널 비밀번호 복사
  if (copyPwBtn && idHidden) {
    copyPwBtn.addEventListener("click", function () {
      var pwId = String(idHidden.value || "").trim();
      if (!pwId) {
        alert("선택된 비밀번호 레코드가 없습니다.");
        return;
      }
      requestPasswordCopy(pwId, function (plain) {
        copyToClipboard(plain);
      });
    });
  }

  // 🟢 리스트 테이블 비밀번호 복사
  var tableCopyButtons = document.querySelectorAll(".copy-password-row-btn");
  tableCopyButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var pwId = String(btn.getAttribute("data-password-id") || "").trim();
      if (!pwId) {
        alert("비밀번호 ID를 찾을 수 없습니다.");
        return;
      }
      requestPasswordCopy(pwId, function (plain) {
        copyToClipboard(plain);
      });
    });
  });

  // ------------------------------------------
  // 공통: 클립보드 복사 함수
  // ------------------------------------------
  function copyToClipboard(pw) {
    var text = String(pw || "").trim();
    if (!text) {
      alert("복사할 비밀번호가 없습니다.");
      return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard
        .writeText(text)
        .then(function () {
          // 조용히 복사만; 필요하면 alert 추가 가능
          alert("비밀번호가 복사되었습니다.");
        })
        .catch(function () {
          alert("비밀번호 복사에 실패했습니다. 브라우저 권한을 확인해 주세요.");
        });
    } else {
      try {
        var textarea = document.createElement("textarea");
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        var ok = document.execCommand("copy");
        document.body.removeChild(textarea);

        if (!ok) {
          alert("비밀번호 복사에 실패했습니다.");
        }
      } catch (e) {
        console.error(e);
        alert("비밀번호 복사에 실패했습니다.");
      }
    }
  }

  // ❌ 여기에는 form submit 막는 코드 없음.
  //    → PHP가 그대로 INSERT / UPDATE 실행.
});

function formatPhoneNumber(input) {
  var digits = String(input || "").replace(/\D+/g, "");
  if (digits.length <= 3) return digits;
  if (digits.length <= 7) return digits.replace(/(\d{3})(\d+)/, "$1-$2");
  if (digits.length <= 11) return digits.replace(/(\d{3})(\d{3,4})(\d+)/, "$1-$2-$3");
  return digits.slice(0, 11).replace(/(\d{3})(\d{4})(\d{4})/, "$1-$2-$3");
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

document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById("pw5TableSearchInput");
    var searchBtn = document.getElementById("pw5TableSearchBtn");
    var tableBody = document.querySelector("#passwordTable tbody");

    if (searchInput && tableBody) {
      var rows = Array.prototype.slice.call(tableBody.querySelectorAll("tr"));

      function runTableFilter() {
        var keyword = searchInput.value.trim().toLowerCase();
        rows.forEach(function (row) {
          var haystack = (row.textContent || "").toLowerCase();
          if (!keyword || haystack.indexOf(keyword) !== -1) {
            row.style.display = "";
          } else {
            row.style.display = "none";
          }
        });
      }

      var debouncedRunTableFilter = debounce(runTableFilter, 160);
      searchInput.addEventListener("input", debouncedRunTableFilter);

      if (searchBtn) {
        searchBtn.addEventListener("click", runTableFilter);
      }

      searchInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.keyCode === 13) {
          e.preventDefault();
          runTableFilter();
        }
      });
    }

    const categoryInput = document.getElementById('category');        // 구분 input
    const storeLabel    = document.getElementById('storenameLabel');  // 매장명 레이블

    if (!categoryInput || !storeLabel) {
        return;
    }

    // 레이블 기본값 (예: "항목")
    const defaultLabel = storeLabel.textContent.trim() || '항목';

    function updateStoreLabel() {
        const value = categoryInput.value.trim();

        // 1) 값이 비어 있으면 기본값("항목")으로
        if (!value) {
            storeLabel.textContent = defaultLabel;
            return;
        }

        // 2) "매장관리"인 경우 → "매장명"
        if (value === '매장관리') {
            storeLabel.textContent = '매장명';
        } 
        // 3) 그 외는 선택한 구분 이름 그대로 사용 (개발용, 세무관리, 업무용, 개인용 등)
        else {
            storeLabel.textContent = value;
        }
    }

    // 구분 값이 바뀔 때마다 레이블 업데이트
    categoryInput.addEventListener('input', updateStoreLabel);
    categoryInput.addEventListener('change', updateStoreLabel);

    // 페이지 처음 로드될 때도 한 번 반영
    updateStoreLabel();
});
