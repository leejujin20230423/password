<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================================================
 * 0. AES 암호화/복호화 설정
 * ========================================================== */
const PASSWORD_CIPHER_METHOD = 'AES-256-CBC';

// ⚠ 실제 서비스에서는 .env / config 로 빼는 게 좋음
const PASSWORD_SECRET_KEY = 'change-this-to-your-own-strong-secret-key-32byte';
const PASSWORD_SECRET_IV  = 'change-this-iv-16b';

/**
 * 비밀번호 암호화 (평문 → 암호문 base64)
 */
function encryptPasswordAES(string $plain): string
{
    if ($plain === '') {
        return '';
    }

    $key = hash('sha256', PASSWORD_SECRET_KEY, true);               // 32 bytes
    $iv  = substr(hash('sha256', PASSWORD_SECRET_IV, true), 0, 16); // 16 bytes

    $cipherRaw = openssl_encrypt(
        $plain,
        PASSWORD_CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($cipherRaw === false) {
        return '';
    }

    return base64_encode($cipherRaw);
}

/**
 * 비밀번호 복호화 (암호문 base64 → 평문)
 */
function decryptPasswordAES(string $encryptedBase64): string
{
    if ($encryptedBase64 === '') {
        return '';
    }

    $cipherRaw = base64_decode($encryptedBase64, true);
    if ($cipherRaw === false) {
        return '';
    }

    $key = hash('sha256', PASSWORD_SECRET_KEY, true);
    $iv  = substr(hash('sha256', PASSWORD_SECRET_IV, true), 0, 16);

    $plain = openssl_decrypt(
        $cipherRaw,
        PASSWORD_CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return $plain === false ? '' : $plain;
}

/* ==========================================================
 * 1. 이 페이지에서 사용할 테이블명
 * ========================================================== */
$tableName = 'password';

/* ==========================================================
 * 2. Generic CRUD 라이브러리 로드
 * ========================================================== */
require_once __DIR__ . '/../../../password_60_CRUD/password_60_CRUD.php';

/* ==========================================================
 * 3. DB & Redis & 스키마 로더 생성
 * ========================================================== */
$dbConnection = new DBConnection();
$pdo          = $dbConnection->getDB();

// Redis (선택)
$redis = null;
try {
    if (class_exists('Redis')) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379, 0.5);
        // $redis->auth('your_redis_password');
        // $redis->select(0);
    }
} catch (Exception $e) {
    $redis = null;
}

// 스키마 로더
$schemaLoader = new GetAllTableNameAutoload($pdo, 'user_id', $redis);

// CRUD 인스턴스
$crud = new GenericCrud($pdo, $schemaLoader, $tableName, $redis);

/* ==========================================================
 * 4. 화면에서 사용할 편집용 변수 + 검색어
 * ========================================================== */
$editRow           = null;  // 보기(view) 눌렀을 때 읽어온 한 행
$decryptedPassword = '';    // 복호화된 평문 비밀번호

// 검색어 (사이트 주소 + 메모)
$searchKeyword = trim($_GET['q'] ?? '');

/* ==========================================================
 * 5. POST 처리 (등록/수정/삭제/보기)
 * ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {

        $plainPassword = $_POST['encrypted_password'] ?? '';
        $encrypted     = encryptPasswordAES($plainPassword);

        $data = [
            'category'           => $_POST['category'] ?? '',
            'site_url'           => $_POST['site_url'] ?? '',
            'login_id'           => $_POST['login_id'] ?? '',
            'encrypted_password' => $encrypted,
            'memo'               => $_POST['memo'] ?? '',
        ];

        $crud->insert($data);

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;

    } elseif ($action === 'update') {

        $id = $_POST['password_idno'] ?? null;

        if ($id !== null && $id !== '') {
            $data = [
                'category' => $_POST['category'] ?? '',
                'site_url' => $_POST['site_url'] ?? '',
                'login_id' => $_POST['login_id'] ?? '',
                'memo'     => $_POST['memo'] ?? '',
            ];

            // 새 비밀번호 입력값
            $newPlain = $_POST['encrypted_password'] ?? '';

            // 새 비번이 있으면 암호화해서 교체, 없으면 기존 유지
            if (trim($newPlain) !== '') {
                $data['encrypted_password'] = encryptPasswordAES($newPlain);
            }

            $crud->update($id, $data);
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;

    } elseif ($action === 'delete') {

        $id = $_POST['password_idno'] ?? null;
        if ($id !== null && $id !== '') {
            $crud->delete($id);
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;

    } elseif ($action === 'view') {

        $id = $_POST['password_idno'] ?? null;
        if ($id !== null && $id !== '') {
            $editRow = $crud->getById($id);

            if (!empty($editRow) && isset($editRow['encrypted_password'])) {
                $decryptedPassword = decryptPasswordAES($editRow['encrypted_password']);
            }
        }
        // view 는 리다이렉트 없이 그대로 렌더링
    }
}

/* ==========================================================
 * 6. 화면에 뿌릴 리스트 데이터
 *    - 검색어가 있으면 site_url, memo LIKE 검색
 *    - 없으면 category 기준 정렬 + 캐시 사용
 * ========================================================== */
$pk      = $crud->getPrimaryKey();  // password_idno
$listSource = null;
$passwordRows = [];

if ($searchKeyword !== '') {
    // 🔎 검색 모드: site_url / memo 에 검색어 포함
    $like = '%' . $searchKeyword . '%';

    $sql = "SELECT *
            FROM `{$tableName}`
            WHERE site_url LIKE :kw
               OR memo     LIKE :kw
            ORDER BY category ASC, {$pk} DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':kw', $like, PDO::PARAM_STR);
    $stmt->execute();
    $passwordRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $listSource = 'db-search';
} else {
    // 기본 정렬: 구분(category) 기준 정렬, 같은 구분 안에선 최신순
    $orderBy      = 'category ASC' . ($pk ? ', ' . $pk . ' DESC' : '');
    $passwordRows = $crud->getListCached([], $orderBy);
    $listSource   = $crud->getLastListSource();
}

// 폼이 수정 모드인지 여부
$isEdit = !empty($editRow);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Password 등록</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="password_0_register_View_admin.css">
    <style>
        /* 검색박스 살짝만 스타일 */
        .search-box {
            padding: 8px 0 12px 0;
        }
        .search-box form {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .search-box input[type="text"] {
            flex: 1;
            padding: 6px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .search-box button {
            padding: 6px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .search-reset-btn {
            background: #eee;
        }
    </style>
</head>
<body>
<div class="layout">

    <!-- 상단 헤더 -->
    <header class="header">
        <h1>Password 관리 시스템</h1>
        <div class="header-right">
            <span class="user-info">관리자</span>

            <button type="button"
                    class="logout-button"
                    onclick="window.location.href='/password_9_logout/password_9_logout_Route/password_9_logout_Route.php';">
                로그아웃
            </button>
        </div>
    </header>

    <!-- (디버깅용) 리스트 데이터 출처 표시 -->
    <div style="padding:8px 16px; font-size:12px; color:#555;">
        List Source:
        <strong>
            <?php
            if ($searchKeyword !== '') {
                echo 'Database search';
            } elseif ($listSource === 'redis') {
                echo 'Redis cache';
            } elseif ($listSource === 'db') {
                echo 'Database query';
            } else {
                echo 'Unknown';
            }
            ?>
        </strong>
    </div>

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

        <!-- 가운데 등록 / 수정 폼 -->
        <section class="content">
            <h2>비밀번호 <?php echo $isEdit ? '수정' : '등록'; ?></h2>

            <form id="passwordForm" method="post" action="">
                <!-- 모드: create / update -->
                <input type="hidden" name="action"
                       value="<?php echo $isEdit ? 'update' : 'create'; ?>">

                <!-- PK -->
                <input type="hidden" name="password_idno" id="password_idno"
                       value="<?php echo htmlspecialchars($editRow['password_idno'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="category">구분</label>
                    <input type="text"
                           id="category"
                           name="category"
                           value="<?php echo htmlspecialchars($editRow['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="site_url">사이트 주소</label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text"
                               id="site_url"
                               name="site_url"
                               style="flex:1;"
                               value="<?php echo htmlspecialchars($editRow['site_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               required>
                        <!-- ✅ 폼에서도 URL 이동 버튼 -->
                        <button type="button"
                                onclick="openUrl(document.getElementById('site_url').value);">
                            이동
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="login_id">아이디</label>
                    <input type="text"
                           id="login_id"
                           name="login_id"
                           value="<?php echo htmlspecialchars($editRow['login_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           required>
                </div>

                <?php if ($isEdit && !empty($editRow)): ?>
                    <!-- 저장된 비밀번호 (암호화 값 / 평문 토글용) -->
                    <div class="form-group">
                        <label for="password_encrypted_view">
                            저장된 비밀번호
                            <span style="font-size:11px; color:#888;">
                                (기본은 암호화된 값, 버튼으로 평문 보기)
                            </span>
                        </label>

                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="text"
                                   id="password_encrypted_view"
                                   readonly
                                   data-encrypted="<?php echo htmlspecialchars($editRow['encrypted_password'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   value="<?php echo htmlspecialchars($editRow['encrypted_password'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   style="flex:1;">
                            <button type="button" id="togglePasswordView">
                                암호 보기
                            </button>
                        </div>

                        <!-- 평문 비밀번호는 hidden에 숨겨두고 JS에서만 사용 -->
                        <input type="hidden"
                               id="password_plain_hidden"
                               value="<?php echo htmlspecialchars($decryptedPassword, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="encrypted_password">
                        <?php echo $isEdit ? '새 비밀번호' : '비밀번호'; ?>
                        <?php if ($isEdit): ?>
                            <span style="font-size:11px; color:#888;">
                                (변경 시에만 새 비밀번호를 입력하세요)
                            </span>
                        <?php endif; ?>
                    </label>
                    <!-- 새 비밀번호 입력칸: 평문 입력 → 서버에서 AES 암호화 후 저장 -->
                    <input type="password"
                           id="encrypted_password"
                           name="encrypted_password"
                           value="">
                </div>

                <div class="form-group">
                    <label for="memo">메모</label>
                    <textarea id="memo" name="memo" rows="4"><?php
                        echo htmlspecialchars($editRow['memo'] ?? '', ENT_QUOTES, 'UTF-8');
                    ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit">
                        <?php echo $isEdit ? '수정하기' : '등록'; ?>
                    </button>

                    <button type="button" class="btn-secondary"
                            onclick="window.location.href='<?php echo htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ENT_QUOTES, 'UTF-8'); ?>';">
                        새로 작성
                    </button>
                </div>
            </form>
        </section>

        <!-- 우측 리스트 -->
        <aside class="list-panel">
            <h2>등록된 비밀번호 목록</h2>

            <!-- 🔎 검색 박스: 사이트 주소 / 메모 검색 -->
            <div class="search-box">
                <form method="get" action="">
                    <input type="text"
                           name="q"
                           placeholder="사이트 주소 또는 메모에서 검색"
                           value="<?php echo htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit">검색</button>
                    <?php if ($searchKeyword !== ''): ?>
                        <button type="button"
                                class="search-reset-btn"
                                onclick="window.location.href='<?php echo htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ENT_QUOTES, 'UTF-8'); ?>';">
                            초기화
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <table class="password-table" id="passwordTable">
                <thead>
                <tr>
                    <th>순번</th>
                    <th>구분</th>
                    <th>사이트 주소</th>
                    <th>아이디</th>
                    <th>메모</th>
                    <th>동작</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($passwordRows)): ?>
                    <?php $seq = 1; ?>
                    <?php foreach ($passwordRows as $row): ?>
                        <tr>
                            <!-- ✅ 순번 (현재 정렬 기준에 따른 1,2,3...) -->
                            <td><?php echo $seq++; ?></td>

                            <td><?php echo htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8'); ?></td>

                            <td>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        <?php echo htmlspecialchars($row['site_url'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <!-- URL 이동 버튼 -->
                                    <button type="button"
                                            onclick="openUrl('<?php echo htmlspecialchars($row['site_url'], ENT_QUOTES, 'UTF-8'); ?>');">
                                        이동
                                    </button>
                                </div>
                            </td>

                            <td><?php echo htmlspecialchars($row['login_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['memo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <!-- 보기 (폼에 값 채우기) -->
                                <form method="post" action="" style="display:inline;">
                                    <input type="hidden" name="action" value="view">
                                    <input type="hidden" name="password_idno"
                                           value="<?php echo (int)$row['password_idno']; ?>">
                                    <button type="submit">보기</button>
                                </form>

                                <!-- 삭제 -->
                                <form method="post" action="" style="display:inline; margin-left:4px;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="password_idno"
                                           value="<?php echo (int)$row['password_idno']; ?>">
                                    <button type="submit" onclick="return confirm('정말 삭제할까요?');">
                                        삭제
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">등록된 비밀번호가 없습니다.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </aside>

    </div><!-- /.main -->
</div><!-- /.layout -->

<script>
// ✅ URL 열기 공용 함수
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

// 암호화 값 / 평문 토글
document.addEventListener('DOMContentLoaded', function () {
    const encInput    = document.getElementById('password_encrypted_view');
    const plainHidden = document.getElementById('password_plain_hidden');
    const toggleBtn   = document.getElementById('togglePasswordView');

    if (encInput && plainHidden && toggleBtn) {
        let showingPlain = false;
        const encryptedVal = encInput.dataset.encrypted || encInput.value;
        const plainVal     = plainHidden.value || '';

        // 초기값: 암호화된 값
        encInput.value = encryptedVal;

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
    }
});
</script>

</body>
</html>
