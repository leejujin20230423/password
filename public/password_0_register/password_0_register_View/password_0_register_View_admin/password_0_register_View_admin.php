<?php
// 추후 세션 체크, 공통 헤더 include 등을 여기서 처리하면 됨.
// require_once __DIR__ . '/../../../connection/loader.php';
// require_once __DIR__ . '/../some_header.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Password 등록</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- 이 파일과 같은 폴더에 css 둔다고 가정 -->
    <link rel="stylesheet" href="password_0_register_View_admin.css">
</head>
<body>
<div class="layout">

    <!-- 상단 헤더 -->
    <header class="header">
        <h1>Password 관리 시스템</h1>
        <div class="header-right">
            <!-- 나중에 로그인 사용자명, 로그아웃 버튼 등 배치 -->
            <span class="user-info">관리자</span>

            <!-- ✅ 로그아웃 버튼 추가 -->
            <!-- 실제 로그아웃 처리 PHP 경로에 맞게 href/onclick 수정해서 사용하세요 -->
            <button type="button"
                    class="logout-button"
                    onclick="window.location.href='/password_9_logout/password_9_logout_Route/password_9_logout_Route.php';">
                로그아웃
            </button>
        </div>
    </header>

    <!-- 본문 레이아웃 (좌:사이드, 중:등록폼, 우:리스트) -->
    <div class="main">

        <!-- 좌측 사이드바 -->
        <aside class="sidebar">
            <nav>
                <ul>
                    <li class="active">비밀번호 등록</li>
                    <li>비밀번호 검색</li>
                    <li>환경 설정</li>
                </ul>
            </nav>
        </aside>

        <!-- 가운데 등록 폼 -->
        <section class="content">
            <h2>비밀번호 등록</h2>

            <form id="passwordForm">
                <div class="form-group">
                    <label for="category">구분</label>
                    <input type="text" id="category" name="category" required>
                </div>

                <div class="form-group">
                    <label for="site_url">사이트 주소</label>
                    <input type="text" id="site_url" name="site_url" required>
                </div>

                <div class="form-group">
                    <label for="login_id">아이디</label>
                    <input type="text" id="login_id" name="login_id" required>
                </div>

                <div class="form-group">
                    <label for="password">비밀번호</label>
                    <input type="text" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="memo">메모</label>
                    <textarea id="memo" name="memo" rows="4"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit">등록</button>
                    <button type="reset" class="btn-secondary">초기화</button>
                </div>
            </form>
        </section>

        <!-- 우측 리스트 -->
        <aside class="list-panel">
            <h2>등록된 비밀번호 목록</h2>

            <!-- JS에서 행을 추가해 줄 테이블 -->
            <table class="password-table" id="passwordTable">
                <thead>
                <tr>
                    <th>구분</th>
                    <th>사이트 주소</th>
                    <th>아이디</th>
                    <th>메모</th>
                    <!-- ✅ 보기/삭제 버튼용 동작 컬럼 추가 -->
                    <th>동작</th>
                </tr>
                </thead>
                <tbody>
                <!--
                    처음에는 비어 있고, JS가 채움.
                    예시) JS에서 이런 형태로 만들어 주면 됩니다.

                    <tr>
                        <td>은행</td>
                        <td>https://bank.example.com</td>
                        <td>myid</td>
                        <td>주계좌</td>
                        <td>
                            <button type="button" class="btn-view">보기</button>
                            <button type="button" class="btn-delete">삭제</button>
                        </td>
                    </tr>
                -->
                </tbody>
            </table>
        </aside>

    </div><!-- /.main -->
</div><!-- /.layout -->

<script src="password_0_register_View_admin.js"></script>
</body>
</html>
