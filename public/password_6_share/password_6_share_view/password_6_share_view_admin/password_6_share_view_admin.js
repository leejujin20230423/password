// ================================================
// 1. DOMContentLoaded 이후 기본 이벤트 바인딩
//    - 전체 선택 체크박스 기능
// ================================================
document.addEventListener('DOMContentLoaded', function () {
    var checkAll = document.getElementById('checkAll');

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            var boxes = document.querySelectorAll('input[name="password_ids[]"]');
            boxes.forEach(function (cb) {
                cb.checked = checkAll.checked;
            });
        });
    }
});

// ================================================
// 2. 전화번호로 회원 검색
//    - 서버에 AJAX 요청 → users 테이블에서 검색
//    - 성공 시: "공유 대상 추가" 버튼 표시
//    - 실패 시: 문자/카톡으로 초대 안내
// ================================================
function searchUserByPhone() {
    var phoneInput = document.getElementById('search_phone');
    var resultBox  = document.getElementById('searchResult');

    if (!phoneInput || !resultBox) return;

    var raw = phoneInput.value.trim();
    if (!raw) {
        alert('전화번호를 입력하세요.');
        return;
    }

    // ✅ 검색 후 입력창 비우기
    phoneInput.value = '';

    // AJAX 요청 보낼 URL
    var url = '/password_6_share/password_6_share_route/password_6_share_ajax_admin.php';

    // x-www-form-urlencoded 형식으로 전송
    var params = 'action=search_user'
        + '&phone=' + encodeURIComponent(raw);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {

            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);

                    // ✅ 회원 존재
                    if (res.ok && res.user) {
                        var u = res.user; // { user_no, username, phone } 라고 가정 (phone 없으면 '')

                        resultBox.innerHTML = `
                            회원: ${escapeHtml(u.username)} (${escapeHtml(u.phone || '')})
                            <button type="button"
                                onclick="addTarget(${u.user_no}, '${escapeHtml(u.username)}', '${escapeHtml(u.phone || '')}');">
                                공유 대상 추가
                            </button>
                        `;
                    }
                    // ❌ 회원 없음 (가입 유도)
                    else {
                        resultBox.innerHTML =
                            '<span style="color:#d9534f;">해당 전화번호로 등록된 회원이 없습니다.</span><br>' +
                            '<button type="button" onclick="inviteBySms();">문자로 가입 안내 보내기</button>';
                    }
                } catch (e) {
                    console.error(e);
                    resultBox.textContent = '응답 처리 중 오류가 발생했습니다.';
                }
            } else {
                resultBox.textContent = '서버 통신 오류입니다. 잠시 후 다시 시도해 주세요.';
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
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// ================================================
// 4. 공유 대상 목록에 사용자 추가
//    - <ul id="selectedTargets">에 <li> 추가
//    - 같은 user_no가 중복 추가되지 않도록 체크
//    - ❗ 여기서 hidden input name="target_user_ids[]" 를 꼭 만든다
// ================================================
function addTarget(userNo, username, phone) {
    var list = document.getElementById('selectedTargets');
    if (!list) return;

    // 이미 추가된 사용자면 막기
    var exists = list.querySelector('li[data-user-no="' + userNo + '"]');
    if (exists) {
        alert('이미 공유 대상에 추가된 사용자입니다.');
        return;
    }

    var li = document.createElement('li');
    li.setAttribute('data-user-no', userNo);

    var phoneText = phone ? ' (' + escapeHtml(phone) + ')' : '';

    // ✅ 여기서 hidden input name="target_user_ids[]" 를 만든다
    li.innerHTML = `
        <span class="target-name">${escapeHtml(username)}${phoneText}</span>
        <button type="button"
                class="btn-remove-target"
                onclick="removeTarget(this);">
            삭제
        </button>
        <input type="hidden" name="target_user_ids[]" value="${userNo}">
    `;

    list.appendChild(li);

    // 검색 결과 영역은 메시지로 변경
    var resultBox = document.getElementById('searchResult');
    if (resultBox) {
        resultBox.textContent = '공유 대상에 추가되었습니다.';
    }
}

// ================================================
// 5. 공유 대상 목록에서 사용자 제거
// ================================================
function removeTarget(btn) {
    var li = btn.closest('li');
    if (li) {
        li.remove();
    }
}

// ================================================
// 6. 회원이 아닐 때: 문자/카톡으로 초대
//    - Web Share API 지원 시: share()
//    - 아니면 sms:?body= 를 사용해 문자 앱으로 이동 시도
// ================================================
function inviteBySms() {
    var siteUrl = 'https://pass.bizstore.co.kr';
    var text    = 'PASS 비밀번호 관리에 가입하고 비밀번호를 공유 받아보세요.\n' + siteUrl;

    // Web Share API 지원 (모바일 브라우저 대부분)
    if (navigator.share) {
        navigator.share({
            title: 'PASS 비밀번호 관리 초대',
            text: text,
            url: siteUrl
        }).catch(function (err) {
            console.log('공유 취소 또는 실패:', err);
        });
        return;
    }

    // Web Share 미지원 → 문자 앱으로 이동
    var smsBody = encodeURIComponent(text);
    window.location.href = 'sms:?body=' + smsBody;
}

// ================================================
// 7. 공유 설정 저장
//    - 선택된 비밀번호 + 선택된 대상이 있어야 전송
//    - 최종적으로 shareForm.submit() 호출
// ================================================
const shareForm = document.getElementById('shareForm');

function submitShareForm() {
    // 1) 체크된 비밀번호 개수 확인
    const checkedPasswords = document.querySelectorAll('input[name="password_ids[]"]:checked');
    if (checkedPasswords.length === 0) {
        alert('공유할 비밀번호를 하나 이상 선택해 주세요.');
        return;
    }

    // 2) 선택된 공유 대상(hidden input) 개수 확인
    const targetInputs = document.querySelectorAll('input[name="target_user_ids[]"]');

    console.log('선택된 공유 대상 수:', targetInputs.length); // 디버깅용

    if (targetInputs.length === 0) {
        alert('공유 대상 사용자를 하나 이상 추가해 주세요.');
        return;
    }

    // 3) 검증 통과 → 폼 제출
    if (shareForm) {
        shareForm.submit();
    } else {
        alert('공유 설정 폼을 찾을 수 없습니다.');
    }
}
