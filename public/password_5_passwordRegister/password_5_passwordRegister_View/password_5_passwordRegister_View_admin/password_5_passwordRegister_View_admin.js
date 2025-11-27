document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("passwordForm");
    const tableBody = document.querySelector("#passwordTable tbody");

    form.addEventListener("submit", function (e) {
        e.preventDefault(); // 기본 form submit 막기

        const category = document.getElementById("category").value.trim();
        const siteUrl  = document.getElementById("site_url").value.trim();
        const loginId  = document.getElementById("login_id").value.trim();
        const password = document.getElementById("password").value.trim();
        const memo     = document.getElementById("memo").value.trim();

        if (!category || !siteUrl || !loginId || !password) {
            alert("구분, 사이트 주소, 아이디, 비밀번호는 필수입니다.");
            return;
        }

        // 테이블에 행 추가 (나중에 서버 저장 후 리로드 방식으로 바꿔도 됨)
        const tr = document.createElement("tr");

        tr.innerHTML = `
            <td>${category}</td>
            <td>${siteUrl}</td>
            <td>${loginId}</td>
            <td>${memo}</td>
        `;

        tableBody.appendChild(tr);

        // 폼 초기화
        form.reset();
    });
});
