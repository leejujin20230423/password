// ===============================
// 관리자 비밀번호 변경 페이지 JS
// ===============================

document.addEventListener("DOMContentLoaded", function () {
  // 1) "보기" 버튼 클릭 시 비밀번호 보기/숨기기 토글
  var toggleButtons = document.querySelectorAll(".toggle-password-btn");

  toggleButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var targetId = btn.getAttribute("data-target");
      var input = document.getElementById(targetId);

      if (!input) {
        return;
      }

      if (input.type === "password") {
        input.type = "text";
        btn.textContent = "숨기기";
      } else {
        input.type = "password";
        btn.textContent = "보기";
      }
    });
  });

  // 2) 새 비밀번호 / 확인 비밀번호 일치 여부 실시간 표시
  var newPasswordInput = document.getElementById("new_password");
  var confirmInput = document.getElementById("new_password_confirm");
  var messageSpan = document.getElementById("passwordMatchMessage");

  function checkPasswordMatch() {
    var newVal = newPasswordInput.value;
    var confVal = confirmInput.value;

    // 초기화
    messageSpan.textContent = "";
    messageSpan.classList.remove("match", "mismatch");

    if (newVal === "" && confVal === "") {
      return; // 둘 다 비어 있으면 메시지 안 띄움
    }

    if (newVal === confVal) {
      messageSpan.textContent = "새 비밀번호가 서로 일치합니다.";
      messageSpan.classList.add("match");
    } else {
      messageSpan.textContent = "새 비밀번호가 서로 다릅니다.";
      messageSpan.classList.add("mismatch");
    }
  }

  if (newPasswordInput && confirmInput && messageSpan) {
    newPasswordInput.addEventListener("input", checkPasswordMatch);
    confirmInput.addEventListener("input", checkPasswordMatch);
  }

  // 3) 제출 전에 한 번 더 검사 + 확인 팝업
  var form = document.getElementById("passwordChangeForm");
  if (form) {
    form.addEventListener("submit", function (e) {
      if (!newPasswordInput || !confirmInput) {
        return;
      }

      // (1) 비밀번호 불일치 검사
      if (newPasswordInput.value !== confirmInput.value) {
        alert("새 비밀번호와 확인 비밀번호가 일치하지 않습니다.");
        e.preventDefault();
        confirmInput.focus();
        return;
      }

      // (2) 최종 확인 팝업
      var message =
        "정말 비밀번호를 변경하시겠습니까?\n\n" +
        "변경된 비밀번호는 해시 암호화되어\n" +
        "복구가 절대 불가능합니다.\n" +
        "비밀번호를 꼭 기억해 주세요.";

      if (!window.confirm(message)) {
        // 취소 누르면 전송 막기
        e.preventDefault();
        return;
      }
    });
  }
});
