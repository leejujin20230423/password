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
// 2) 비밀번호 복호화 + 로그인 비밀번호 검증 API 경로
//    - 현재 페이지 PHP (view 파일)로 POST 전송
//    - PHP 쪽에서 `if ($_POST['ajax'] === 'decrypt_password')` 처리
// ==========================================
var DECRYPT_URL = window.location.pathname;

// ==========================================
// 3) DOMContentLoaded 이후 이벤트 바인딩
// ==========================================
document.addEventListener("DOMContentLoaded", function () {
    // ---- 아이디 복사 관련 요소 ----
    var loginInput   = document.getElementById("login_id");
    var copyLoginBtn = document.getElementById("copyLoginIdBtn");

    // ---- 비밀번호 토글/복사 관련 요소 ----
    var encInput    = document.getElementById("password_encrypted_view"); // 화면에 보이는 input
    var plainHidden = document.getElementById("password_plain_hidden");   // 숨겨진 평문 (초기엔 비어 있음)
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

            // navigator.clipboard 우선 사용, 안 되면 execCommand로 폴백
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
                // 구형 브라우저용 폴백
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

    // 이 화면이 "보기/수정 모드"가 아닐 수도 있으니, 요소 존재 여부 먼저 체크
    if (encInput && plainHidden && toggleBtn && idHidden) {
        // 암호화 값 (DB에 저장된 값)
        var encryptedVal =
            encInput.getAttribute("data-encrypted") || encInput.value || "";
        var showingPlain = false; // 현재 화면에 평문이 보이는지 여부

        // 초기 상태: 암호화 값 표시
        encInput.value = encryptedVal;
        showingPlain = false;
        toggleBtn.textContent = "암호 보기";

        // ------------------------------------------
        // 내부 헬퍼: 평문 비번을 확보하는 함수
        //  - 이미 hidden에 있으면 그걸 사용
        //  - 없으면 로그인 비밀번호를 물어보고,
        //    PHP에 ajax=decrypt_password 로 요청
        // ------------------------------------------
        function ensurePlainPassword(callback) {
            // 1) 이미 plainHidden 에 값이 있으면 그대로 사용
            var cached = String(plainHidden.value || "").trim();
            if (cached) {
                if (typeof callback === "function") {
                    callback(cached);
                }
                return;
            }

            // 2) 없으면 로그인 비밀번호를 다시 입력받음
            var input = window.prompt(
                "로그인 비밀번호를 다시 입력하세요.\n" +
                "※ 저장된 비밀번호를 보기/복사할 때 한 번 더 확인합니다."
            );
            if (input === null) {
                // 사용자가 취소
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
                credentials: "same-origin" // 세션 유지
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error("HTTP " + res.status);
                    }
                    return res.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) {
                        alert(
                            (data && data.msg) ||
                            "비밀번호 복호화에 실패했습니다."
                        );
                        return;
                    }

                    // 서버에서 받은 평문 비밀번호를 hidden에 캐시
                    var plain = String(data.plain || "");
                    plainHidden.value = plain;

                    if (typeof callback === "function") {
                        callback(plain);
                    }
                })
                .catch(function (err) {
                    console.error("decrypt_password error:", err);
                    alert("서버 통신 중 오류가 발생했습니다.");
                });
        }

        // ▶ "암호 보기" 버튼 클릭
        toggleBtn.addEventListener("click", function () {
            if (!showingPlain) {
                // 아직 평문이 안 보이는 상태 → 평문 확보 후 보여주기
                ensurePlainPassword(function (plain) {
                    encInput.value = plain;
                    showingPlain = true;
                    toggleBtn.textContent = "암호화 값 보기";
                });
            } else {
                // 기존 암호화 값으로 되돌리기
                encInput.value = encryptedVal;
                showingPlain = false;
                toggleBtn.textContent = "암호 보기";
            }
        });

        // ▶ 비밀번호 평문 복사 버튼
        if (copyPwBtn) {
            copyPwBtn.addEventListener("click", function () {
                ensurePlainPassword(function (plain) {
                    var pw = String(plain || "").trim();
                    if (!pw) {
                        alert("복호화된 비밀번호가 없습니다.");
                        return;
                    }

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard
                            .writeText(pw)
                            .then(function () {
                                alert("비밀번호(평문)가 클립보드에 복사되었습니다.");
                            })
                            .catch(function () {
                                alert(
                                    "비밀번호 복사에 실패했습니다. 브라우저 권한을 확인해 주세요."
                                );
                            });
                    } else {
                        // 폴백
                        try {
                            // 임시 textarea 생성해서 복사
                            var textarea = document.createElement("textarea");
                            textarea.value = pw;
                            document.body.appendChild(textarea);
                            textarea.select();
                            var ok = document.execCommand("copy");
                            document.body.removeChild(textarea);

                            if (ok) {
                                alert("비밀번호(평문)가 클립보드에 복사되었습니다.");
                            } else {
                                alert("비밀번호 복사에 실패했습니다.");
                            }
                        } catch (e) {
                            console.error(e);
                            alert("비밀번호 복사에 실패했습니다.");
                        }
                    }
                });
            });
        }
    }

    // ❌ 여기에는 form submit 을 막는 코드는 없음.
    //    → PHP 가 그대로 INSERT / UPDATE 실행.
});
