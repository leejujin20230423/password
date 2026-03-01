<?php
declare(strict_types=1);

/**
 * PASS One-Page App
 * - Single file (PHP + HTML + CSS + JS)
 * - Login / Logout
 * - Password CRUD (per logged-in user)
 * - AES encrypt/decrypt for vault password
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mb_internal_encoding('UTF-8');

function env_load(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        $val = trim($val, "\"'");

        if ($key === '') {
            continue;
        }

        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
        putenv($key . '=' . $val);
    }
}

function env_get(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($v === false || $v === null || $v === '') {
        return $default;
    }
    return (string)$v;
}

env_load(__DIR__ . '/../.env');
env_load(__DIR__ . '/.env');

function pdo_connect(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_get('DB_HOST', '127.0.0.1');
    $port = env_get('DB_PORT', '3306');
    $db   = env_get('DB_NAME', 'pass');
    $user = env_get('DB_USER', 'root');
    $pass = env_get('DB_PASS', '');
    $charset = env_get('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool
{
    $saved = (string)($_SESSION['csrf_token'] ?? '');
    return $saved !== '' && is_string($token) && hash_equals($saved, $token);
}

function aes_encrypt(string $plain): string
{
    if ($plain === '') {
        return '';
    }

    $method = 'AES-256-CBC';
    $keySrc = env_get('PASS_SECRET_KEY', 'change-this-to-your-own-strong-secret-key-32byte');
    $ivSrc  = env_get('PASS_SECRET_IV', 'change-this-iv-16b');

    $key = hash('sha256', $keySrc, true);
    $iv  = substr(hash('sha256', $ivSrc, true), 0, 16);

    $raw = openssl_encrypt($plain, $method, $key, OPENSSL_RAW_DATA, $iv);
    if ($raw === false) {
        return '';
    }

    return base64_encode($raw);
}

function aes_decrypt(string $encoded): string
{
    if ($encoded === '') {
        return '';
    }

    $method = 'AES-256-CBC';
    $keySrc = env_get('PASS_SECRET_KEY', 'change-this-to-your-own-strong-secret-key-32byte');
    $ivSrc  = env_get('PASS_SECRET_IV', 'change-this-iv-16b');

    $key = hash('sha256', $keySrc, true);
    $iv  = substr(hash('sha256', $ivSrc, true), 0, 16);

    $raw = base64_decode($encoded, true);
    if ($raw === false) {
        return '';
    }

    $plain = openssl_decrypt($raw, $method, $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_no']);
}

function login_attempt(PDO $pdo, string $userid, string $plainPw): bool
{
    $userid = trim($userid);
    if ($userid === '' || $plainPw === '') {
        return false;
    }

    $sql = 'SELECT user_no, userid, username, password, user_type, status FROM users WHERE userid = :uid LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userid]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    $stored = (string)($row['password'] ?? '');
    if ($stored === '') {
        return false;
    }

    $okHash = password_verify($plainPw, $stored);
    $okPlain = hash_equals($stored, $plainPw);

    if (!$okHash && !$okPlain) {
        return false;
    }

    if (!$okHash && $okPlain) {
        $up = $pdo->prepare('UPDATE users SET password = :hpw, updated_at = CURRENT_TIMESTAMP WHERE user_no = :uno');
        $up->execute([
            ':hpw' => password_hash($plainPw, PASSWORD_DEFAULT),
            ':uno' => (int)$row['user_no'],
        ]);
    }

    $_SESSION['user_no'] = (int)$row['user_no'];
    $_SESSION['userid'] = (string)$row['userid'];
    $_SESSION['username'] = (string)($row['username'] ?? '');
    $_SESSION['user_type'] = (string)($row['user_type'] ?? 'user');

    return true;
}

function logout_all(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'] ?? '/', $p['domain'] ?? '', (bool)($p['secure'] ?? false), (bool)($p['httponly'] ?? true));
    }
    session_destroy();
}

$pdo = null;
$dbError = '';

try {
    $pdo = pdo_connect();
} catch (Throwable $e) {
    $dbError = 'DB 연결 실패: ' . $e->getMessage();
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $err = '보안 토큰이 유효하지 않습니다. 새로고침 후 다시 시도하세요.';
    } elseif ($dbError !== '') {
        $err = 'DB 연결이 없어 요청을 처리할 수 없습니다.';
    } elseif ($pdo instanceof PDO) {
        try {
            if ($action === 'login') {
                $uid = (string)($_POST['userid'] ?? '');
                $pw  = (string)($_POST['password'] ?? '');

                if (login_attempt($pdo, $uid, $pw)) {
                    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                    exit;
                }

                $err = '아이디 또는 비밀번호가 잘못되었습니다.';
            }

            if ($action === 'logout') {
                logout_all();
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }

            if (is_logged_in() && $action === 'create') {
                $site = trim((string)($_POST['site_url'] ?? ''));
                $siteUser = trim((string)($_POST['site_userid'] ?? ''));
                $plain = (string)($_POST['site_password'] ?? '');
                $memo = trim((string)($_POST['memo'] ?? ''));

                if ($site === '' || $siteUser === '' || $plain === '') {
                    $err = '사이트 주소, 아이디, 비밀번호를 입력하세요.';
                } else {
                    $enc = aes_encrypt($plain);
                    $sql = 'INSERT INTO password (user_no_Fk, site_url, site_userid, encrypted_password, memo, created_at, updated_at) VALUES (:uno, :url, :uid, :epw, :memo, NOW(), NOW())';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':uno' => (int)$_SESSION['user_no'],
                        ':url' => $site,
                        ':uid' => $siteUser,
                        ':epw' => $enc,
                        ':memo' => $memo,
                    ]);
                    $msg = '비밀번호가 등록되었습니다.';
                }
            }

            if (is_logged_in() && $action === 'update') {
                $id = (int)($_POST['password_idno'] ?? 0);
                $site = trim((string)($_POST['site_url'] ?? ''));
                $siteUser = trim((string)($_POST['site_userid'] ?? ''));
                $plain = (string)($_POST['site_password'] ?? '');
                $memo = trim((string)($_POST['memo'] ?? ''));

                if ($id <= 0 || $site === '' || $siteUser === '' || $plain === '') {
                    $err = '수정 데이터가 올바르지 않습니다.';
                } else {
                    $enc = aes_encrypt($plain);
                    $sql = 'UPDATE password SET site_url=:url, site_userid=:uid, encrypted_password=:epw, memo=:memo, updated_at=NOW() WHERE password_idno=:id AND user_no_Fk=:uno';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':url' => $site,
                        ':uid' => $siteUser,
                        ':epw' => $enc,
                        ':memo' => $memo,
                        ':id' => $id,
                        ':uno' => (int)$_SESSION['user_no'],
                    ]);
                    $msg = '비밀번호가 수정되었습니다.';
                }
            }

            if (is_logged_in() && $action === 'delete') {
                $id = (int)($_POST['password_idno'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare('DELETE FROM password WHERE password_idno = :id AND user_no_Fk = :uno');
                    $stmt->execute([
                        ':id' => $id,
                        ':uno' => (int)$_SESSION['user_no'],
                    ]);
                    $msg = '비밀번호가 삭제되었습니다.';
                }
            }

            if (is_logged_in() && $action === 'decrypt') {
                $id = (int)($_POST['password_idno'] ?? 0);
                $stmt = $pdo->prepare('SELECT encrypted_password FROM password WHERE password_idno = :id AND user_no_Fk = :uno LIMIT 1');
                $stmt->execute([
                    ':id' => $id,
                    ':uno' => (int)$_SESSION['user_no'],
                ]);
                $row = $stmt->fetch();
                if ($row) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => true, 'plain' => aes_decrypt((string)$row['encrypted_password'])], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => '레코드를 찾지 못했습니다.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (Throwable $e) {
            $err = '요청 처리 중 오류: ' . $e->getMessage();
        }
    }
}

$rows = [];
$edit = null;

if (is_logged_in() && $pdo instanceof PDO) {
    $q = trim((string)($_GET['q'] ?? ''));
    $editId = (int)($_GET['edit'] ?? 0);

    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT password_idno, site_url, site_userid, encrypted_password, memo FROM password WHERE password_idno = :id AND user_no_Fk = :uno LIMIT 1');
        $stmt->execute([
            ':id' => $editId,
            ':uno' => (int)$_SESSION['user_no'],
        ]);
        $edit = $stmt->fetch() ?: null;
        if ($edit) {
            $edit['plain_password'] = aes_decrypt((string)$edit['encrypted_password']);
        }
    }

    if ($q !== '') {
        $stmt = $pdo->prepare('SELECT password_idno, site_url, site_userid, encrypted_password, memo, created_at, updated_at FROM password WHERE user_no_Fk = :uno AND (site_url LIKE :q OR memo LIKE :q OR site_userid LIKE :q) ORDER BY password_idno DESC');
        $stmt->execute([
            ':uno' => (int)$_SESSION['user_no'],
            ':q' => '%' . $q . '%',
        ]);
        $rows = $stmt->fetchAll() ?: [];
    } else {
        $stmt = $pdo->prepare('SELECT password_idno, site_url, site_userid, encrypted_password, memo, created_at, updated_at FROM password WHERE user_no_Fk = :uno ORDER BY password_idno DESC');
        $stmt->execute([':uno' => (int)$_SESSION['user_no']]);
        $rows = $stmt->fetchAll() ?: [];
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PASS One-Page</title>
  <style>
    :root {
      --bg: #f4f6fb;
      --panel: #ffffff;
      --text: #1f2937;
      --sub: #6b7280;
      --line: #dbe3f0;
      --pri: #0f766e;
      --pri2: #115e59;
      --danger: #b91c1c;
      --ok: #065f46;
      --shadow: 0 10px 30px rgba(17, 24, 39, 0.08);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Pretendard", "Noto Sans KR", sans-serif;
      background: radial-gradient(1200px 600px at 15% -10%, #d6f3ee 0%, transparent 55%), var(--bg);
      color: var(--text);
    }
    .wrap {
      width: min(1100px, 94vw);
      margin: 28px auto;
      display: grid;
      gap: 16px;
    }
    .card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 16px;
      box-shadow: var(--shadow);
      padding: 18px;
    }
    h1 {
      margin: 0;
      font-size: 28px;
      letter-spacing: -0.02em;
    }
    .sub {
      margin-top: 6px;
      color: var(--sub);
      font-size: 14px;
    }
    .row {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 10px;
    }
    .c3 { grid-column: span 3; }
    .c4 { grid-column: span 4; }
    .c6 { grid-column: span 6; }
    .c12 { grid-column: span 12; }
    label {
      display: block;
      margin-bottom: 6px;
      font-size: 13px;
      color: var(--sub);
    }
    input, textarea, button {
      width: 100%;
      border-radius: 10px;
      border: 1px solid var(--line);
      padding: 11px 12px;
      font-size: 14px;
    }
    textarea { min-height: 90px; resize: vertical; }
    button {
      background: var(--pri);
      color: #fff;
      border-color: var(--pri);
      cursor: pointer;
      transition: .15s ease;
    }
    button:hover { background: var(--pri2); border-color: var(--pri2); }
    .btn-gray { background: #374151; border-color: #374151; }
    .btn-red { background: var(--danger); border-color: var(--danger); }
    .btn-line { background: #fff; color: var(--text); }
    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .topbar .who { color: var(--sub); font-size: 14px; }
    .alert { padding: 10px 12px; border-radius: 10px; font-size: 14px; }
    .alert.ok { background: #ecfdf5; color: var(--ok); border: 1px solid #a7f3d0; }
    .alert.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }
    .table th, .table td {
      border-bottom: 1px solid var(--line);
      padding: 10px 8px;
      text-align: left;
      vertical-align: top;
    }
    .table th { color: var(--sub); font-weight: 600; }
    .actions {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .actions form { margin: 0; }
    .pw-box {
      display: inline-flex;
      gap: 6px;
      align-items: center;
    }
    .pw-value {
      font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
      background: #f9fafb;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 6px 8px;
      min-width: 110px;
      text-align: center;
    }
    .login-wrap {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 20px;
    }
    .login-card {
      width: min(460px, 96vw);
    }
    .mt8 { margin-top: 8px; }
    .mt12 { margin-top: 12px; }
    .mt16 { margin-top: 16px; }
    @media (max-width: 900px) {
      .c3, .c4, .c6 { grid-column: span 12; }
      .table { font-size: 13px; }
    }
  </style>
</head>
<body>
<?php if (!is_logged_in()): ?>
  <div class="login-wrap">
    <div class="card login-card">
      <h1>PASS</h1>
      <div class="sub">원페이지 통합 로그인</div>

      <?php if ($dbError !== ''): ?>
        <div class="alert err mt12"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($err !== ''): ?>
        <div class="alert err mt12"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" class="mt16">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <label for="userid">아이디</label>
        <input id="userid" name="userid" required>

        <label for="password" class="mt12">비밀번호</label>
        <input id="password" name="password" type="password" required>

        <button class="mt16" type="submit">로그인</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="wrap">
    <div class="card">
      <div class="topbar">
        <div>
          <h1>PASS Vault</h1>
          <div class="who"><?= htmlspecialchars((string)($_SESSION['username'] ?: $_SESSION['userid']), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$_SESSION['user_type'], ENT_QUOTES, 'UTF-8') ?>)</div>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="logout">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn-gray" type="submit">로그아웃</button>
        </form>
      </div>

      <?php if ($msg !== ''): ?>
        <div class="alert ok mt12"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($err !== ''): ?>
        <div class="alert err mt12"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($dbError !== ''): ?>
        <div class="alert err mt12"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="margin:0; font-size:20px;">
        <?= $edit ? '비밀번호 수정' : '비밀번호 등록' ?>
      </h2>
      <form method="post" class="mt12">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="password_idno" value="<?= (int)($edit['password_idno'] ?? 0) ?>">

        <div class="row">
          <div class="c4">
            <label for="site_url">사이트 주소</label>
            <input id="site_url" name="site_url" required value="<?= htmlspecialchars((string)($edit['site_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="c4">
            <label for="site_userid">사이트 아이디</label>
            <input id="site_userid" name="site_userid" required value="<?= htmlspecialchars((string)($edit['site_userid'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="c4">
            <label for="site_password">사이트 비밀번호</label>
            <input id="site_password" name="site_password" required value="<?= htmlspecialchars((string)($edit['plain_password'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="c12">
            <label for="memo">메모</label>
            <textarea id="memo" name="memo"><?= htmlspecialchars((string)($edit['memo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
          <div class="c6">
            <button type="submit"><?= $edit ? '수정 저장' : '신규 등록' ?></button>
          </div>
          <div class="c6">
            <a href="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>" style="text-decoration:none;">
              <button type="button" class="btn-line">초기화</button>
            </a>
          </div>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="topbar">
        <h2 style="margin:0; font-size:20px;">등록 목록</h2>
        <form method="get" style="display:flex; gap:8px;">
          <input name="q" placeholder="사이트/아이디/메모 검색" value="<?= htmlspecialchars((string)($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit">검색</button>
        </form>
      </div>

      <div class="mt12" style="overflow:auto;">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>사이트</th>
              <th>아이디</th>
              <th>비밀번호</th>
              <th>메모</th>
              <th>수정일</th>
              <th>작업</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="7" style="text-align:center; color:var(--sub);">데이터가 없습니다.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['password_idno'] ?></td>
                <td><?= htmlspecialchars((string)$r['site_url'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$r['site_userid'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <div class="pw-box">
                    <span class="pw-value" id="pw_<?= (int)$r['password_idno'] ?>">••••••••</span>
                    <button type="button" class="btn-line" onclick="revealPassword(<?= (int)$r['password_idno'] ?>)">보기</button>
                    <button type="button" class="btn-line" onclick="copyPassword(<?= (int)$r['password_idno'] ?>)">복사</button>
                  </div>
                </td>
                <td><?= nl2br(htmlspecialchars((string)$r['memo'], ENT_QUOTES, 'UTF-8')) ?></td>
                <td><?= htmlspecialchars((string)$r['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <div class="actions">
                    <a href="?edit=<?= (int)$r['password_idno'] ?><?= isset($_GET['q']) ? '&q=' . urlencode((string)$_GET['q']) : '' ?>">
                      <button type="button" class="btn-gray">수정</button>
                    </a>
                    <form method="post" onsubmit="return confirm('정말 삭제할까요?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="password_idno" value="<?= (int)$r['password_idno'] ?>">
                      <button type="submit" class="btn-red">삭제</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    async function postDecrypt(id) {
      const formData = new FormData();
      formData.append('action', 'decrypt');
      formData.append('password_idno', String(id));
      formData.append('csrf_token', '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>');

      const res = await fetch('<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });
      return res.json();
    }

    async function revealPassword(id) {
      try {
        const j = await postDecrypt(id);
        if (!j.ok) {
          alert(j.msg || '복호화 실패');
          return;
        }
        const el = document.getElementById('pw_' + id);
        if (el) el.textContent = j.plain || '(empty)';
      } catch (e) {
        alert('요청 실패');
      }
    }

    async function copyPassword(id) {
      try {
        const j = await postDecrypt(id);
        if (!j.ok) {
          alert(j.msg || '복호화 실패');
          return;
        }
        await navigator.clipboard.writeText(j.plain || '');
        alert('복사 완료');
      } catch (e) {
        alert('복사 실패');
      }
    }
  </script>
<?php endif; ?>
</body>
</html>
