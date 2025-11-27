

// ✅ 1) 전역 함수: URL 열기 (HTML onclick 에서 직접 호출)
function openUrl(raw) {
    if (!raw) return;
    var url = raw.trim();
    if (!url) return;

    // http/https 없으면 https:// 자동 붙이기
    if (!/^https?:\/\//i.test(url)) {
        url = 'https://' + url;
    }
    window.open(url, '_blank');
}

// ✅ 2) DOMContentLoaded 안에서 복사/토글 처리
document.addEventListener('DOMContentLoaded', function () {
    // ---- 아이디 복사 관련 요소 ----
    const loginInput   = document.getElementById('login_id');
    const copyLoginBtn = document.getElementById('copyLoginIdBtn');

    // ---- 비밀번호 토글/복사 관련 요소 ----
    const encInput    = document.getElementById('password_encrypted_view');  // 화면에 보여지는 input
    const plainHidden = document.getElementById('password_plain_hidden');    // hidden 에 담긴 평문
    const toggleBtn   = document.getElementById('togglePasswordView');       // "암호 보기" 버튼
    const copyPwBtn   = document.getElementById('copyPasswordBtn');          // "복사" 버튼

    // ============================
    // 1. 아이디 복사
    // ============================
    if (loginInput && copyLoginBtn && navigator.clipboard) {
        copyLoginBtn.addEventListener('click', function () {
            const text = loginInput.value.trim();
            if (!text) {
                alert('복사할 아이디가 없습니다.');
                return;
            }

            navigator.clipboard.writeText(text)
                .then(function () {
                    alert('아이디가 클립보드에 복사되었습니다.');
                })
                .catch(function () {
                    alert('아이디 복사에 실패했습니다. 브라우저 권한을 확인해 주세요.');
                });
        });
    }

    // ============================
    // 2. 비밀번호 암호/평문 토글 + 복사
    // ============================
    if (encInput && plainHidden && toggleBtn) {
        let showingPlain = false;

        // data-encrypted 에 암호문이 들어있으면 그걸 우선 사용
        const encryptedVal = encInput.dataset.encrypted || encInput.value;
        const plainVal     = plainHidden.value || '';

        // 초기 상태: 암호화된 값 표시
        encInput.value = encryptedVal;

        // ▶ 버튼 눌러서 암호/평문 토글
        toggleBtn.addEventListener('click', function () {
            if (!showingPlain) {
                encInput.value = plainVal;
                showingPlain = true;
                toggleBtn.textContent = '암호화 값 보기';
            } else {
                encInput.value = encryptedVal;
                showingPlain = false;
                toggleBtn.textContent = '암호 보기';
            }
        });

        // ▶ 평문 비밀번호 복사
        if (copyPwBtn && navigator.clipboard) {
            copyPwBtn.addEventListener('click', function () {
                const pw = plainVal.trim();
                if (!pw) {
                    alert('복호화된 비밀번호가 없습니다.');
                    return;
                }

                navigator.clipboard.writeText(pw)
                    .then(function () {
                        alert('비밀번호(평문)가 클립보드에 복사되었습니다.');
                    })
                    .catch(function () {
                        alert('비밀번호 복사에 실패했습니다. 브라우저 권한을 확인해 주세요.');
                    });
            });
        }
    }

    // ❌ form submit 막는 코드는 없음.
    //    → PHP 가 그대로 INSERT / UPDATE 실행하도록 둠.
});
