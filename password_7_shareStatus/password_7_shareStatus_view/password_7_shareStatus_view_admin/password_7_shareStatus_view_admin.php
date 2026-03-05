<?php

/**
 * ==========================================================
 * 1. 세션 시작 및 로그인 사용자 확인
 * ==========================================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 브라우저 캐시로 인해 최신 CSS/JS가 반영되지 않는 문제 방지
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// 로그인 여부 체크
if (empty($_SESSION['user_no'])) {
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php');
    exit;
}

$currentUserNo   = (int)($_SESSION['user_no'] ?? 0);
$sessionUsername = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';

/**
 * ==========================================================
 * 2. AES-256-CBC 복호화 설정
 *    - 비밀번호 등록 페이지와 동일한 키/IV 사용
 *    - DB의 password.encrypted_password 를 복호화해서
 *      화면에는 ●●●● 로, 복사 버튼에는 평문으로 사용
 * ==========================================================
 */

// 등록 페이지에서 사용하던 상수랑 맞춰야 함
if (!defined('PASSWORD_CIPHER_METHOD')) {
    define('PASSWORD_CIPHER_METHOD', 'AES-256-CBC');
}
if (!defined('PASSWORD_SECRET_KEY')) {
    // ✅ 비밀번호 등록 페이지에 정의한 값과 반드시 동일해야 함
    define('PASSWORD_SECRET_KEY', 'change-this-to-your-own-strong-secret-key-32byte');
}
if (!defined('PASSWORD_SECRET_IV')) {
    // ✅ 비밀번호 등록 페이지에 정의한 값과 반드시 동일해야 함
    define('PASSWORD_SECRET_IV', 'change-this-iv-16b');
}

/**
 * 암호화된 비밀번호(base64) → 평문 비밀번호
 */
if (!function_exists('decryptPasswordAES')) {
    function decryptPasswordAES(?string $encryptedBase64): string
    {
        if ($encryptedBase64 === null || $encryptedBase64 === '') {
            return '';
        }

        // base64 → raw binary
        $cipherRaw = base64_decode($encryptedBase64, true);
        if ($cipherRaw === false) {
            return '';
        }

        // 등록 페이지와 동일한 방식으로 key/iv 생성
        $key = hash('sha256', PASSWORD_SECRET_KEY, true);               // 32 bytes
        $iv  = substr(hash('sha256', PASSWORD_SECRET_IV, true), 0, 16); // 16 bytes

        $plain = openssl_decrypt(
            $cipherRaw,
            PASSWORD_CIPHER_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plain === false ? '' : $plain;
    }
}

/**
 * ==========================================================
 * 3. DB 연결 (password_60_CRUD 에서 DBConnection 사용)
 * ==========================================================
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/password_60_CRUD/password_60_CRUD.php';

$dbConnection = new DBConnection();
$pdo          = $dbConnection->getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ✅ 헤더에서 사용할 리스트 소스 / 검색어 변수
$listSource    = 'db';     // header 에서 DBQuery 로 표시
$searchKeyword = '';       // 이 화면은 별도 검색 없음

/**
 * ==========================================================
 * 4. 내가 "공유해 준" 비밀번호 목록
 *    - password_share.owner_user_no_Fk = 현재 로그인 user_no
 * ==========================================================
 */
$sqlSharedByMe = <<<SQL
SELECT
    ps.share_id,                    -- 공유 PK
    ps.owner_user_no_Fk,
    ps.target_user_no_Fk,
    ps.password_idno_Fk,
    ps.share_memo,
    ps.created_at,

    u.username       AS target_username,
    u.phone          AS target_phone,

    p.category,
    p.storename,
    p.site_url,
    p.login_id,
    p.encrypted_password AS encrypted_password,
    p.memo           AS password_memo
FROM password_share ps
LEFT JOIN users u
    ON ps.target_user_no_Fk = u.user_no
LEFT JOIN password p
    ON ps.password_idno_Fk = p.password_idno
WHERE ps.owner_user_no_Fk = :currentUserNo
ORDER BY ps.created_at DESC, ps.share_id DESC
SQL;

$stmt = $pdo->prepare($sqlSharedByMe);
$stmt->bindValue(':currentUserNo', $currentUserNo, PDO::PARAM_INT);
$stmt->execute();
$sharedByMeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ==========================================================
 * 5. 내가 "공유받은" 비밀번호 목록
 *    - password_share.target_user_no_Fk = 현재 로그인 user_no
 * ==========================================================
 */
$sqlSharedToMe = <<<SQL
SELECT
    ps.share_id,                    -- 공유 PK
    ps.owner_user_no_Fk,
    ps.target_user_no_Fk,
    ps.password_idno_Fk,
    ps.share_memo,
    ps.created_at,

    u.username       AS owner_username,
    u.phone          AS owner_phone,

    p.category,
    p.storename,
    p.site_url,
    p.login_id,
    p.encrypted_password AS encrypted_password,
    p.memo           AS password_memo
FROM password_share ps
LEFT JOIN users u
    ON ps.owner_user_no_Fk = u.user_no
LEFT JOIN password p
    ON ps.password_idno_Fk = p.password_idno
WHERE ps.target_user_no_Fk = :currentUserNo
ORDER BY ps.created_at DESC, ps.share_id DESC
SQL;

$stmt2 = $pdo->prepare($sqlSharedToMe);
$stmt2->bindValue(':currentUserNo', $currentUserNo, PDO::PARAM_INT);
$stmt2->execute();
$sharedToMeRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

/**
 * ==========================================================
 * 6. 내가 "공유하지 않은" 비밀번호 목록
 *    - password_share.share_id IS NULL (공유되지 않은 비밀번호만)
 * ==========================================================
 */
$sqlUnsharedPasswords = <<<SQL
SELECT 
    p.password_idno,
    p.category,
    p.storename,
    p.site_url,
    p.login_id,
    p.encrypted_password AS encrypted_password,  -- ✅ 복호화를 위해 추가
    p.memo,
    u.username AS owner_username,
    u.phone AS owner_phone
FROM password p
LEFT JOIN password_share ps
    ON p.password_idno = ps.password_idno_Fk
LEFT JOIN users u
    ON p.user_no_Fk = u.user_no
WHERE ps.share_id IS NULL
  AND p.user_no_Fk = :currentUserNo
ORDER BY p.created_at DESC
SQL;

$stmtUnshared = $pdo->prepare($sqlUnsharedPasswords);
$stmtUnshared->bindValue(':currentUserNo', $currentUserNo, PDO::PARAM_INT);
$stmtUnshared->execute();
$unsharedPasswordsRows = $stmtUnshared->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>Password 공유현황 (관리자)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<?php
    // 세션이 아직 시작 안 되었다면 시작
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 캐시 이슈 방지: 강제 캐시 버스트
    $cacheBust = (string) time();
    $cssVersion = $cacheBust;
    $jsVersion  = $cacheBust;
    ?>

    <!-- ✅ 공유현황 전용 레이아웃 CSS -->
    <link rel="stylesheet"
          href="/password_7_shareStatus/password_7_shareStatus_view/password_7_shareStatus_view_admin/password_7_shareStatus_view_admin.css?v=<?php echo htmlspecialchars($cssVersion, ENT_QUOTES, 'UTF-8'); ?>">

</head>


<body id="page-share-status" class="page-share-status">
<div class="layout">

    <!-- ========================== 상단 헤더 include ========================== -->
    <?php
    require_once $_SERVER['DOCUMENT_ROOT']
        . '/password_3_header/password_3_header_view/password_3_header_view_admin/password_3_header_view_admin.php';
    ?>

    <div class="global-filter-bar">
        <div class="global-search-shell" id="globalSearchShell">
            <svg class="global-search-led" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                <rect class="global-search-led-base" x="1.4" y="1.4" width="97.2" height="97.2" rx="12" ry="12"></rect>
                <rect class="global-search-led-trail" x="1.4" y="1.4" width="97.2" height="97.2" rx="12" ry="12"></rect>
            </svg>
            <input
                type="text"
                id="globalShareSearch"
                placeholder="공유 대상, 공유해 준 사람, 구분, 항목, 사이트 주소, 아이디, 메모, 연락처에서 통합 검색">
        </div>
    </div>

    <div class="main">
        <!-- ========================== 좌측 사이드바 include ========================== -->
        <?php
        require_once $_SERVER['DOCUMENT_ROOT']
            . '/password_4_sidebar/password_4_sidebar_view/password_4_sidebar_view_admin/password_4_sidebar_view_admin.php';
        ?>

        <!-- ✅ 가운데 + 아래를 위/아래로 쌓는 컨테이너 -->
        <div class="share-container">

            <!-- ========================== 위: 내가 공유한 비밀번호 목록 ========================== -->
            <section class="content">
  <div class="container">
                <form
                    id="sharedByMeForm"
                    method="post"
                    action="/password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_delete_admin.php">

                    <input type="hidden" name="mode" value="by_me">

                    <h4 class="status-moving-title">공유해준 비밀번호</h4>
                    <p style="margin-top:0; margin-bottom:12px; font-size:13px; color:#6b7280;">
                        현재 계정
                        (<strong><?php echo htmlspecialchars($sessionUsername, ENT_QUOTES, 'UTF-8'); ?></strong>)
                        에서 다른 사용자에게 공유 중인 비밀번호 목록입니다.
                    </p>

                    <!-- ✅ 삭제 버튼 -->
                    <div class="table-actions">
                        <button type="submit" class="btn-danger">
                            공유 끊기
                        </button>(공유를 해제할 대상을 선택한후 버튼을 눌러주세요)
                    </div>

                    <!-- 테이블 목록 -->
                    <div class="table-wrapper">
                        <table class="password-table">
                            <thead>
                            <tr>
                                <th style="width:40px; text-align:center;">
                                    <input type="checkbox" id="byMeCheckAll">
                                </th>
                                <th>No</th>
                                <th>공유 대상</th>
                                <th>연락처</th>
                                <th>구분</th>
                                <th>항목</th>
                                <th>사이트 주소</th>
                                <th>아이디</th>
                                <th>비밀번호</th>
                                <th>공유 메모</th>
                                <th>권한</th>
                                <th>공유일</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($sharedByMeRows)): ?>
                                <?php $idx = 1; ?>
                                <?php foreach ($sharedByMeRows as $row): ?>
                                    <?php
                                    $plainPw = '';
                                    if (isset($row['encrypted_password'])) {
                                        $plainPw = decryptPasswordAES((string)$row['encrypted_password']);
                                    }
                                    $siteUrl = (string)($row['site_url'] ?? '');
                                    $loginId = (string)($row['login_id'] ?? '');
                                    ?>
                                    <tr>
                                        <!-- 체크박스 -->
                                        <td style="text-align:center;">
                                            <input type="checkbox"
                                                   name="share_ids[]"
                                                   value="<?php echo (int)$row['share_id']; ?>">
                                        </td>

                                        <!-- No -->
                                        <td style="text-align:center;"><?php echo $idx++; ?></td>

                                        <!-- 공유 대상 / 연락처 -->
                                        <td><?php echo htmlspecialchars($row['target_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['target_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- 구분 / 항목 -->
                                        <td><?php echo htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['storename'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- 사이트 주소 + 이동 버튼 -->
                                        <td class="cell-site-url">
                                            <div class="site-cell">
                                                <span class="site-url-text">
                                                    <?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <button type="button"
                                                        class="btn-open-url"
                                                        data-url="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                    이동
                                                </button>
                                            </div>
                                        </td>

                                        <!-- 아이디 + 복사 버튼 -->
                                        <td>
                                            <div class="login-cell">
                                                <span class="login-id-text">
                                                    <?php echo htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <button type="button"
                                                        class="btn-copy-login"
                                                        data-login="<?php echo htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8'); ?>">
                                                    복사
                                                </button>
                                            </div>
                                        </td>

                                        <!-- 비밀번호 + 복사 버튼 -->
                                        <td>
                                            <div class="password-cell">
                                                <input type="password"
                                                       class="password-view-input"
                                                       value="<?php echo htmlspecialchars($plainPw, ENT_QUOTES, 'UTF-8'); ?>"
                                                       readonly>
                                                <button type="button"
                                                        class="btn-copy-password"
                                                        data-password="<?php echo htmlspecialchars($plainPw, ENT_QUOTES, 'UTF-8'); ?>">
                                                    복사
                                                </button>
                                            </div>
                                        </td>

                                        <!-- 공유 메모 -->
                                        <td class="cell-memo">
                                            <div class="memo-scroll-text">
                                                <?php echo htmlspecialchars($row['share_memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        </td>

                                        <!-- 권한 / 공유일 -->
                                        <td>보기 전용</td>
                                        <td><?php echo htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" style="text-align:center;">공유한 비밀번호가 없습니다.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div><!-- /.table-wrapper -->
                </form>
              </div>
</section>

            <!-- ========================== 중간: 내가 공유받은 비밀번호 목록 ========================== -->
            <section class="content">
                <form
                    id="sharedToMeForm"
                    method="post"
                    action="/password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_delete_admin.php">

                    <input type="hidden" name="mode" value="to_me">

                    <h4 class="status-moving-title">공유받은 비밀번호</h4>
                    <p style="margin-top:0; margin-bottom:12px; font-size:13px; color:#6b7280;">
                        다른 사용자 계정에서 이 계정으로 공유해 준 비밀번호 목록입니다.
                    </p>

                    <!-- ✅ 삭제 버튼 -->
                    <div class="table-actions">
                        <button type="submit" class="btn-danger">
                            공유 끊기
                        </button>(공유를 해제할 대상을 선택한후 버튼을 눌러주세요)
                    </div>

                    <!-- 테이블 목록 -->
                    <div class="table-wrapper">
                        <table class="password-table">
                            <thead>
                            <tr>
                                <th style="width:40px; text-align:center;">
                                    <input type="checkbox" id="toMeCheckAll">
                                </th>
                                <th style="width:50px; text-align:center;">No</th>
                                <th>공유해 준 사람</th>
                                <th>연락처</th>
                                <th>구분</th>
                                <th>항목</th>
                                <th>사이트 주소</th>
                                <th>아이디</th>
                                <th>비밀번호</th>
                                <th>공유 메모</th>
                                <th>권한</th>
                                <th>공유일</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($sharedToMeRows)): ?>
                                <?php $idx2 = 1; ?>
                                <?php foreach ($sharedToMeRows as $row): ?>
                                    <?php
                                    $plainPw = '';
                                    if (isset($row['encrypted_password'])) {
                                        $plainPw = decryptPasswordAES((string)$row['encrypted_password']);
                                    }
                                    $siteUrl = (string)($row['site_url'] ?? '');
                                    $loginId = (string)($row['login_id'] ?? '');
                                    ?>
                                    <tr>
                                        <!-- 체크박스 -->
                                        <td style="text-align:center;">
                                            <input type="checkbox"
                                                   name="share_ids[]"
                                                   value="<?php echo (int)$row['share_id']; ?>">
                                        </td>

                                        <!-- No -->
                                        <td style="text-align:center;"><?php echo $idx2++; ?></td>

                                        <!-- 공유해 준 사람 / 연락처 -->
                                        <td><?php echo htmlspecialchars($row['owner_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['owner_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- 구분 / 항목 -->
                                        <td><?php echo htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['storename'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- 사이트 주소 + 이동 버튼 -->
                                        <td class="cell-site-url">
                                            <div class="site-cell">
                                                <span class="site-url-text">
                                                    <?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <button type="button"
                                                        class="btn-open-url"
                                                        data-url="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                    이동
                                                </button>
                                            </div>
                                        </td>

                                        <!-- 아이디 + 복사 버튼 -->
                                        <td>
                                            <div class="login-cell">
                                                <span class="login-id-text">
                                                    <?php echo htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <button type="button"
                                                        class="btn-copy-login"
                                                        data-login="<?php echo htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8'); ?>">
                                                    복사
                                                </button>
                                            </div>
                                        </td>

                                        <!-- 비밀번호 + 복사 버튼 -->
                                        <td>
                                            <div class="password-cell">
                                                <input type="password"
                                                       class="password-view-input"
                                                       value="<?php echo htmlspecialchars($plainPw, ENT_QUOTES, 'UTF-8'); ?>"
                                                       readonly>
                                                <button type="button"
                                                        class="btn-copy-password"
                                                        data-password="<?php echo htmlspecialchars($plainPw, ENT_QUOTES, 'UTF-8'); ?>">
                                                    복사
                                                </button>
                                            </div>
                                        </td>

                                        <!-- 공유 메모 -->
                                        <td class="cell-memo">
                                            <div class="memo-scroll-text">
                                                <?php echo htmlspecialchars($row['share_memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        </td>

                                        <!-- 권한 / 공유일 -->
                                        <td>보기 전용</td>
                                        <td><?php echo htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" style="text-align:center;">공유받은 비밀번호가 없습니다.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div><!-- /.table-wrapper -->
                </form>
            </section>

            <!-- ========================== 아래: 내가 공유하지 않은 비밀번호 목록 ========================== -->
            <section class="content">
                <form
                    id="unsharedPasswordsForm"
                    method="post"
                    action="/password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_delete_admin.php">

                    <input type="hidden" name="mode" value="unshared">

                    <h4 class="status-moving-title">나만의 비밀번호</h4>
                    <p style="margin-top:0; margin-bottom:12px; font-size:13px; color:#6b7280;">
                        현재 계정에 대해 공유하지 않은 비밀번호 목록입니다.
                    </p>

                    <!-- 테이블 목록 -->
                    <div class="table-wrapper">
                        <table class="password-table">
                            <thead>
                            <tr>
                                <!-- <th style="width:40px; text-align:center;">
                                    <input type="checkbox" id="unsharedCheckAll">
                                </th> -->
                                <th style="width:50px; text-align:center;">No</th>
                                <th>구분</th>
                                <th>항목</th>
                                <th>사이트 주소</th>
                                <th>아이디</th>
                                <th>비밀번호</th>
                                <th>메모</th>
                                <th>소유자</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($unsharedPasswordsRows)): ?>
                                <?php $idx3 = 1; ?>
                                <?php foreach ($unsharedPasswordsRows as $row): ?>
                                    <?php
                                    $plainPw = '';
                                    if (isset($row['encrypted_password'])) {
                                        $plainPw = decryptPasswordAES((string)$row['encrypted_password']);
                                    }
                                    $siteUrl = (string)($row['site_url'] ?? '');
                                    $loginId = (string)($row['login_id'] ?? '');
                                    ?>
                                    <tr>
                                        <!-- No -->
                                        <td style="text-align:center;"><?php echo $idx3++; ?></td>

                                        <!-- 구분 / 항목 -->
                                        <td><?php echo htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['storename'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- 사이트 주소 + 이동 버튼 -->
                                        <td class="cell-site-url">
                                            <div class="site-cell">
                                                <span class="site-url-text">
                                                    <?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <button type="button"
                                                        class="btn-open-url"
                                                        data-url="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                    이동
                                                </button>
                                            </div>
                                        </td>

                                        <!-- 아이디 + 복사 버튼 -->
                                        <td>
                                            <div class="login-cell">
                                                <span class="login-id-text">
                                                    <?php echo htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <button type="button"
                                                        class="btn-copy-login"
                                                        data-login="<?php echo htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8'); ?>">
                                                    복사
                                                </button>
                                            </div>
                                        </td>

                                        <!-- 비밀번호 + 복사 버튼 -->
                                        <td>
                                            <div class="password-cell">
                                                <input type="password"
                                                       class="password-view-input"
                                                       value="<?php echo htmlspecialchars($plainPw, ENT_QUOTES, 'UTF-8'); ?>"
                                                       readonly>
                                                <button type="button"
                                                        class="btn-copy-password"
                                                        data-password="<?php echo htmlspecialchars($plainPw, ENT_QUOTES, 'UTF-8'); ?>">
                                                    복사
                                                </button>
                                            </div>
                                        </td>

                                        <!-- 메모 / 소유자 -->
                                        <td class="cell-memo">
                                            <div class="memo-scroll-text">
                                                <?php echo htmlspecialchars($row['memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['owner_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align:center;">공유하지 않은 비밀번호가 없습니다.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div><!-- /.table-wrapper -->
                </form>
            </section>

        </div><!-- /.share-container -->
    </div><!-- /.main -->

</div><!-- /.layout -->

<script src="/password_7_shareStatus/password_7_shareStatus_view/password_7_shareStatus_view_admin/password_7_shareStatus_view_admin.js?v=<?php echo htmlspecialchars($jsVersion, ENT_QUOTES, 'UTF-8'); ?>"></script>


</body>
</html>
