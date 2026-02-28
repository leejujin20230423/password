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
 * 2. DB / GenericCrud 로드 (password 테이블 사용)
 * ==========================================================
 */
$tableName = 'password';

require_once $_SERVER['DOCUMENT_ROOT'] . '/password_60_CRUD/password_60_CRUD.php';

$dbConnection = new DBConnection();
$pdo          = $dbConnection->getDB();

// Redis (있으면 쓰고, 없어도 null 처리)
$redis = null;
try {
    if (class_exists('Redis')) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379, 0.5);
    }
} catch (Exception $e) {
    $redis = null;
}

$schemaLoader = new GetAllTableNameAutoload($pdo, 'user_no', $redis);
$crud         = new GenericCrud($pdo, $schemaLoader, $tableName, $redis);

/**
 * ==========================================================
 * 3. 현재 로그인 사용자의 비밀번호 리스트 조회
 *    - 공유할 때 가운데 패널에서 체크박스로 선택
 * ==========================================================
 */
$pk         = $crud->getPrimaryKey(); // 예: password_idno
$orderBy    = 'category ASC' . ($pk ? ', ' . $pk . ' DESC' : '');
$conditions = ['user_no_Fk' => $currentUserNo];

$myPasswordRows = $crud->getList($conditions, $orderBy);

/**
 * ==========================================================
 * 4. 테이블에 보여줄 컬럼 리스트/라벨 준비
 *    - 첫 번째 행의 컬럼명을 기준으로 전체 컬럼 자동 표시
 *    - 헤더는 라벨 매핑으로 한글/가독성 있게 표시
 * ==========================================================
 */
$columns = [];
if (!empty($myPasswordRows) && is_array($myPasswordRows[0])) {
    $allColumns = array_keys($myPasswordRows[0]);

    // ❌ 화면에서 빼고 싶은 컬럼들
    $hiddenColumns = ['user_no_Fk', 'password_idno', 'encrypted_password'];

    // ✅ 실제로 테이블에 표시할 컬럼 목록 (숨기고 싶은 컬럼 제외)
    $columns = array_values(array_filter(
        $allColumns,
        function ($col) use ($hiddenColumns) {
            return !in_array($col, $hiddenColumns, true);
        }
    ));
}

// 컬럼 라벨 매핑 (없는 것은 컬럼명 그대로 사용)
$columnLabels = [
    'password_idno'      => '번호',
    'user_no_Fk'         => '사용자번호',
    'category'           => '구분',
    'storename'          => '매장명',
    'site_url'           => '사이트 주소',
    'login_id'           => '아이디',
    'encrypted_password' => '암호화 비밀번호',
    'contact_phone'      => '연락처',
    'memo'               => '메모',
    'created_at'         => '등록일',
    'updated_at'         => '수정일',
];
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>Password 공유 설정 (관리자)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    
    <link rel="stylesheet" href="/assets/app.css">
<?php
    // 세션이 아직 시작 안 되었으면 시작
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 로그인 시 세팅해 둔 asset_version 사용, 없으면 기본값
    $assetVersion = isset($_SESSION['asset_version'])
        ? $_SESSION['asset_version']
        : '20251206_01'; // 첫 접속/비로그인용 기본 버전
    ?>

    <!-- ✅ 헤더 전용 CSS -->
    <link rel="stylesheet"
          href="/password_3_header/password_3_header_view/password_3_header_view_admin/password_3_header_view_admin.css?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ✅ 사이드바 전용 CSS -->
    <link rel="stylesheet"
          href="/password_4_sidebar/password_4_sidebar_view/password_4_sidebar_view_admin/password_4_sidebar_view_admin.css?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ✅ 비밀번호 공유 화면 전용 CSS -->
    <link rel="stylesheet"
          href="/password_6_share/password_6_share_view/password_6_share_view_admin/password_6_share_view_admin.css?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>


<body>
<div class="layout">

    <!-- ========================== 상단 헤더 include ========================== -->
    <?php
    require_once $_SERVER['DOCUMENT_ROOT']
        . '/password_3_header/password_3_header_view/password_3_header_view_admin/password_3_header_view_admin.php';
    ?>

    <div class="main">
        <!-- ========================== 좌측 사이드바 include ========================== -->
        <?php
        require_once $_SERVER['DOCUMENT_ROOT']
            . '/password_4_sidebar/password_4_sidebar_view/password_4_sidebar_view_admin/password_4_sidebar_view_admin.php';
        ?>

        <!--
            폼이 가운데 content + 우측 list-panel 전체를 감싸도록
            - password_ids[] (체크박스)
            - target_user_ids[] (JS에서 hidden input 생성)
            모두 한 번에 POST
        -->
        <form id="shareForm"
              method="post"
              action="/password_6_share/password_6_share_route/password_6_share_route_admin.php">
            <input type="hidden" name="action" value="save_share">

            <!-- ========================== 가운데: 내 비밀번호 리스트 (체크박스) ========================== -->
            <section class="content">
  <div class="container">
                <h2>공유할 비밀번호 선택</h2>

                <!-- 🔍 사이트/매장명/메모 검색 박스 (입력 + 버튼) -->
                <div class="search-box">
                    <input
                        type="text"
                        id="passwordListSearch"
                        placeholder="사이트 주소, 매장명, 메모에서 검색">
                    <button type="button" id="passwordListSearchBtn">검색</button>
                </div>

                <div class="table-wrapper">
                    <table class="password-table">
                        <thead>
                        <tr>
                            <!-- ✅ 전체 선택 체크박스 -->
                            <th style="width:40px; text-align:center;">
                                <input type="checkbox" id="checkAll">
                            </th>

                            <!-- ✅ 순번 컬럼 -->
                            <th style="width:50px; text-align:center;">
                                No
                            </th>

                            <!-- ✅ password 테이블에서 선택된 컬럼 헤더 (user_no_Fk, password_idno, encrypted_password 제외) -->
                            <?php if (!empty($columns)): ?>
                                <?php foreach ($columns as $colName): ?>
                                    <th>
                                        <?php
                                        $label = isset($columnLabels[$colName]) ? $columnLabels[$colName] : $colName;
                                        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($myPasswordRows)): ?>
                            <?php $rowNo = 1; ?>
                            <?php foreach ($myPasswordRows as $row): ?>
                                <?php
                                // 🔍 검색 대상 텍스트 (사이트 + 매장명 + 메모)
                                $searchPieces = [];
                                if (isset($row['site_url'])) {
                                    $searchPieces[] = (string)$row['site_url'];
                                }
                                if (isset($row['storename'])) {
                                    $searchPieces[] = (string)$row['storename'];
                                }
                                if (isset($row['memo'])) {
                                    $searchPieces[] = (string)$row['memo'];
                                }
                                $searchText = htmlspecialchars(implode(' ', $searchPieces), ENT_QUOTES, 'UTF-8');

                                // PK 값 (password_idno)
                                $pkValue = 0;
                                if ($pk && isset($row[$pk])) {
                                    $pkValue = (int)$row[$pk];
                                } elseif (isset($row['password_idno'])) {
                                    $pkValue = (int)$row['password_idno'];
                                }
                                ?>
                                <tr data-search="<?php echo $searchText; ?>">
                                    <!-- ✅ 체크박스 -->
                                    <td style="text-align:center;">
                                        <input type="checkbox"
                                               name="password_ids[]"
                                               value="<?php echo $pkValue; ?>">
                                    </td>

                                    <!-- ✅ 순번 -->
                                    <td style="text-align:center;">
                                        <?php echo $rowNo++; ?>
                                    </td>

                                    <!-- ✅ 화면에 보여줄 컬럼들만 출력 (user_no_Fk, password_idno, encrypted_password 제외) -->
                                    <?php foreach ($columns as $colName): ?>
                                        <td>
                                            <?php
                                            $value = isset($row[$colName]) ? (string)$row[$colName] : '';
                                            echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <!-- 체크박스 + No + 나머지 컬럼 개수 -->
                                <td colspan="<?php echo 2 + count($columns); ?>" style="text-align:center;">
                                    등록된 비밀번호가 없습니다.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div><!-- /.table-wrapper -->
              </div>
</section>

            <!-- ========================== 우측: 공유 대상 선택 영역 ========================== -->
            <aside class="list-panel">
                <h2>공유 대상 설정</h2>

                <!-- 1) 전화번호로 회원 검색 -->
                <div class="form-group">
                    <label for="search_phone">전화번호로 회원 검색</label>
                    <div style="display:flex; gap:8px;">
                        <input type="tel"
                               id="search_phone"
                               placeholder="예: 010-1234-5678 또는 숫자만"
                               style="flex:1;">
                        <button type="button" onclick="searchUserByPhone();">검색</button>
                    </div>
                    <!-- ✅ 안내 문구 수정 -->
                    <small style="color:#666; display:block; margin-top:4px;">
                        회원으로 등록된 사용자만 검색됩니다. 상대방이 등록하지 않았다면 초대하세요.
                    </small>
                </div>

                <!-- 2) 검색 결과 표시 영역 -->
                <div id="searchResult"
                     style="margin-top:10px; font-size:14px; min-height:24px;"></div>

                <!-- 3) 선택된 공유 대상 목록 -->
                <div style="margin-top:16px;">
                    <h3 style="margin:0 0 8px 0; font-size:15px;">선택된 공유 대상</h3>
                    <ul id="selectedTargets"
                        style="list-style:none; padding:0; margin:0; font-size:14px;">
                        <!-- JS에서 li + hidden input 동적으로 추가 -->
                    </ul>
                </div>

                <!-- 4) 공유 저장 버튼 -->
                <div style="margin-top:20px; text-align:right;">
                    <button type="button"
                            onclick="submitShareForm();"
                            class="btn-primary">
                        공유 설정 저장
                    </button>
                </div>
            </aside>
        </form> <!-- /#shareForm -->

    </div><!-- /.main -->
</div><!-- /.layout -->

<!-- ✅ 비밀번호 공유 화면용 JS -->
<script src="/password_6_share/password_6_share_view/password_6_share_view_admin/password_6_share_view_admin.js?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>"></script>

<script>
    // 초대 메시지에 쓸 발신자 이름
    window.PASS_SENDER_NAME = '<?php echo htmlspecialchars($sessionUsername, ENT_QUOTES, "UTF-8"); ?>';
    // 로그인한 사용자 번호 (본인 체크용)
    window.PASS_USER_NO = <?php echo (int)$currentUserNo; ?>;
</script>

</body>
</html>
