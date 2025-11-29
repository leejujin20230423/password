<?php

/**
 * ==========================================================
 * 1. 세션 시작 및 로그인 사용자 확인
 * ==========================================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 로그인 여부 체크
if (empty($_SESSION['user_no'])) {
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php');
    exit;
}

$currentUserNo   = (int)($_SESSION['user_no'] ?? 0);
$sessionUsername = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';

/**
 * ==========================================================
 * AES-256-CBC 복호화 헬퍼 함수
 * ==========================================================
 */
if (!function_exists('decryptPasswordForView')) {
    function decryptPasswordForView(?string $cipherText): string
    {
        if ($cipherText === null || $cipherText === '') {
            return '';
        }

        // 키/IV 상수가 정의되어 있지 않으면 그냥 빈 문자열 반환
        if (!defined('PASS_AES_KEY') || !defined('PASS_AES_IV')) {
            return '';
        }

        $keyConfig = constant('PASS_AES_KEY');
        $ivConfig  = constant('PASS_AES_IV');

        // 키/IV가 16진수 문자열이면 hex2bin 처리
        if (is_string($keyConfig) && ctype_xdigit($keyConfig) && (strlen($keyConfig) % 2 === 0)) {
            $key = hex2bin($keyConfig);
        } else {
            $key = $keyConfig;
        }

        if (is_string($ivConfig) && ctype_xdigit($ivConfig) && (strlen($ivConfig) % 2 === 0)) {
            $iv = hex2bin($ivConfig);
        } else {
            $iv = $ivConfig;
        }

        if (!is_string($key) || !is_string($iv)) {
            return '';
        }

        $plain = false;

        // (1) RAW_DATA + base64 인코딩 방식
        $cipherRaw = base64_decode($cipherText, true);
        if ($cipherRaw !== false) {
            $plain = openssl_decrypt(
                $cipherRaw,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
        }

        // (2) 기본 옵션(0) 문자열 방식 (저장 형식이 다를 경우 대비)
        if ($plain === false || $plain === null) {
            $plain = openssl_decrypt(
                $cipherText,
                'AES-256-CBC',
                $key,
                0,
                $iv
            );
        }

        return $plain === false || $plain === null ? '' : $plain;
    }
}

/**
 * ==========================================================
 * 2. DB 연결
 * ==========================================================
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/password_60_CRUD/password_60_CRUD.php';

$dbConnection = new DBConnection();
$pdo          = $dbConnection->getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 헤더용
$listSource    = 'db';
$searchKeyword = '';

/**
 * ==========================================================
 * 3. 내가 "공유해 준" 비밀번호 목록
 * ==========================================================
 */
$sqlSharedByMe = <<<SQL
SELECT
    ps.share_id,
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
 * 4. 내가 "공유받은" 비밀번호 목록
 * ==========================================================
 */
$sqlSharedToMe = <<<SQL
SELECT
    ps.share_id,
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
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>Password 공유현황 (관리자)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- 헤더 / 사이드바 / 레이아웃 CSS -->
    <link rel="stylesheet"
        href="/password_3_header/password_3_header_view/password_3_header_view_admin/password_3_header_view_admin.css">
    <link rel="stylesheet"
        href="/password_4_sidebar/password_4_sidebar_view/password_4_sidebar_view_admin/password_4_sidebar_view_admin.css">
    <link rel="stylesheet"
        href="/password_7_shareStatus/password_7_shareStatus_view/password_7_shareStatus_view_admin/password_7_shareStatus_view_admin.css">
</head>

<body>
    <div class="layout">

        <!-- 상단 헤더 -->
        <?php
        require_once $_SERVER['DOCUMENT_ROOT']
            . '/password_3_header/password_3_header_view/password_3_header_view_admin/password_3_header_view_admin.php';
        ?>

        <div class="main">
            <!-- 좌측 사이드바 -->
            <?php
            require_once $_SERVER['DOCUMENT_ROOT']
                . '/password_4_sidebar/password_4_sidebar_view/password_4_sidebar_view_admin/password_4_sidebar_view_admin.php';
            ?>

            <!-- ========================== 가운데: 내가 공유한 비밀번호 목록 ========================== -->
            <section class="content">
                <form
                    id="sharedByMeForm"
                    method="post"
                    action="/password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_delete_admin.php">

                    <input type="hidden" name="mode" value="by_me">

                    <h2>내가 다른 사람에게 공유한 비밀번호</h2>
                    <p style="margin-top:0; margin-bottom:12px; font-size:13px; color:#6b7280;">
                        현재 계정
                        (<strong><?php echo htmlspecialchars($sessionUsername, ENT_QUOTES, 'UTF-8'); ?></strong>)
                        에서 다른 사용자에게 공유 중인 비밀번호 목록입니다.
                    </p>

                    <!-- 검색 -->
                    <div class="search-box" style="margin-bottom:10px; display:flex; gap:8px;">
                        <input
                            type="text"
                            id="byMeSearch"
                            placeholder="공유 대상, 사이트, 매장명, 메모로 검색"
                            style="flex:1; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:13px;">
                        <button type="button"
                            id="byMeSearchBtn"
                            style="padding:6px 12px; border-radius:4px; border:1px solid #ddd; cursor:pointer; font-size:13px;">
                            검색
                        </button>
                    </div>

                    <div class="table-actions">
                        <button type="submit" class="btn-danger">
                            선택 삭제
                        </button>
                    </div>

                    <div class="table-wrapper">
                        <table class="password-table">
                            <thead>
                                <tr>
                                    <th style="width:40px; text-align:center;">
                                        <input type="checkbox" id="byMeCheckAll">
                                    </th>
                                    <th style="width:50px; text-align:center;">No</th>
                                    <th>공유 대상</th>
                                    <th>연락처</th>
                                    <th>구분</th>
                                    <th>매장명</th>
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
                                        $searchPieces = [
                                            $row['target_username'] ?? '',
                                            $row['storename'] ?? '',
                                            $row['site_url'] ?? '',
                                            $row['share_memo'] ?? '',
                                            $row['password_memo'] ?? '',
                                        ];
                                        $searchText = trim(implode(' ', $searchPieces));

                                        $encryptedPassword = isset($row['encrypted_password'])
                                            ? (string)$row['encrypted_password']
                                            : '';

                                        // 서버에서 미리 복호화
                                        $plainPassword = '';
                                        if ($encryptedPassword !== '') {
                                            $plainPassword = decryptPasswordForView($encryptedPassword);
                                        }

                                        // 복사 버튼에 넣을 값 (복호화 성공 시 평문, 실패 시 암호문)
                                        $copyPassword = $plainPassword !== '' ? $plainPassword : $encryptedPassword;

                                        $siteUrl = isset($row['site_url']) ? (string)$row['site_url'] : '';
                                        ?>
                                        <tr data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>">
                                            <td style="text-align:center;">
                                                <input
                                                    type="checkbox"
                                                    name="share_ids[]"
                                                    value="<?php echo (int)$row['share_id']; ?>">
                                            </td>
                                            <td style="text-align:center;">
                                                <?php echo $idx++; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['target_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['target_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['storename'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>

                                            <!-- 사이트 주소: 텍스트 + 이동 버튼 -->
                                            <td class="site-cell">
                                                <?php if ($siteUrl !== ''): ?>
                                                    <span class="site-url">
                                                        <?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                    <button
                                                        type="button"
                                                        class="btn-go-site"
                                                        onclick="openUrl('<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>');">
                                                        이동
                                                    </button>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($row['login_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>

                                            <!-- 비밀번호: 값이 있으면 *** + 복사 버튼, 없으면 '-' -->
                                            <td>
                                                <?php if ($encryptedPassword !== ''): ?>
                                                    <span class="masked-password">***</span>
                                                    <button type="button"
                                                        class="btn-copy-password"
                                                        data-password="<?php echo htmlspecialchars($copyPassword, ENT_QUOTES, 'UTF-8'); ?>"
                                                        onclick="copyPassword(this);"
                                                        style="margin-left:6px; padding:2px 6px; font-size:11px; border-radius:4px; border:1px solid #ddd; cursor:pointer;">
                                                        복사
                                                    </button>
                                                <?php else: ?>
                                                    <span style="color:#9ca3af; font-size:12px;">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($row['share_memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>보기 전용</td>
                                            <td>
                                                <?php echo htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="12" style="text-align:center;">
                                            현재 다른 사람에게 공유 중인 비밀번호가 없습니다.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </section>

            <!-- ========================== 우측: 내가 공유받은 비밀번호 목록 ========================== -->
            <aside class="list-panel">
                <form
                    id="sharedToMeForm"
                    method="post"
                    action="/password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_delete_admin.php">

                    <input type="hidden" name="mode" value="to_me">

                    <h2>내가 다른 사람에게서 공유받은 비밀번호</h2>
                    <p style="margin-top:0; margin-bottom:12px; font-size:13px; color:#6b7280;">
                        다른 사용자 계정에서 이 계정으로 공유해 준 비밀번호 목록입니다.
                    </p>

                    <div class="search-box" style="margin-bottom:10px; display:flex; gap:8px;">
                        <input
                            type="text"
                            id="toMeSearch"
                            placeholder="공유해 준 사람, 사이트, 매장명, 메모로 검색"
                            style="flex:1; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:13px;">
                        <button type="button"
                            id="toMeSearchBtn"
                            style="padding:6px 12px; border-radius:4px; border:1px solid #ddd; cursor:pointer; font-size:13px;">
                            검색
                        </button>
                    </div>

                    <div class="table-actions">
                        <button type="submit" class="btn-danger">
                            선택 삭제
                        </button>
                    </div>

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
                                    <th>매장명</th>
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
                                        $searchPieces2 = [
                                            $row['owner_username'] ?? '',
                                            $row['storename'] ?? '',
                                            $row['site_url'] ?? '',
                                            $row['share_memo'] ?? '',
                                            $row['password_memo'] ?? '',
                                        ];
                                        $searchText2 = trim(implode(' ', $searchPieces2));

                                        $encryptedPassword2 = isset($row['encrypted_password'])
                                            ? (string)$row['encrypted_password']
                                            : '';

                                        $plainPassword2 = '';
                                        if ($encryptedPassword2 !== '') {
                                            $plainPassword2 = decryptPasswordForView($encryptedPassword2);
                                        }

                                        $copyPassword2 = $plainPassword2 !== '' ? $plainPassword2 : $encryptedPassword2;

                                        $siteUrl2 = isset($row['site_url']) ? (string)$row['site_url'] : '';
                                        ?>
                                        <tr data-search="<?php echo htmlspecialchars($searchText2, ENT_QUOTES, 'UTF-8'); ?>">
                                            <td style="text-align:center;">
                                                <input
                                                    type="checkbox"
                                                    name="share_ids[]"
                                                    value="<?php echo (int)$row['share_id']; ?>">
                                            </td>
                                            <td style="text-align:center;">
                                                <?php echo $idx2++; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['owner_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['owner_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['storename'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>

                                            <td class="site-cell">
                                                <?php if ($siteUrl2 !== ''): ?>
                                                    <span class="site-url">
                                                        <?php echo htmlspecialchars($siteUrl2, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                    <button
                                                        type="button"
                                                        class="btn-go-site"
                                                        onclick="openUrl('<?php echo htmlspecialchars($siteUrl2, ENT_QUOTES, 'UTF-8'); ?>');">
                                                        이동
                                                    </button>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($row['login_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>

                                            <td>
                                                <?php if ($encryptedPassword2 !== ''): ?>
                                                    <span class="masked-password">***</span>
                                                    <button type="button"
                                                        class="btn-copy-password"
                                                        data-password="<?php echo htmlspecialchars($copyPassword2, ENT_QUOTES, 'UTF-8'); ?>"
                                                        onclick="copyPassword(this);"
                                                        style="margin-left:6px; padding:2px 6px; font-size:11px; border-radius:4px; border:1px solid #ddd; cursor:pointer;">
                                                        복사
                                                    </button>
                                                <?php else: ?>
                                                    <span style="color:#9ca3af; font-size:12px;">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($row['share_memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>보기 전용</td>
                                            <td>
                                                <?php echo htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="12" style="text-align:center;">
                                            현재 다른 사람으로부터 공유받은 비밀번호가 없습니다.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </aside>
        </div>
    </div>

    <script src="/password_7_shareStatus/password_7_shareStatus_view/password_7_shareStatus_view_admin/password_7_shareStatus_view_admin.js"></script>
</body>

</html>
