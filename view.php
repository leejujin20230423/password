<?php
require_once "db.php";
require_once "crypto.php";
require_once "auth_check.php";


echo "aaa --- IGNORE ---";

// DB 연결
$db = new Database();
$pdo = $db->connect();

// 암호화 객체
$crypto = new PasswordCrypto();

// id 체크
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("잘못된 접근입니다.");
}

$id = $_GET['id'];

// 데이터 조회
$sql = "SELECT * FROM password WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// 데이터 없으면
if (!$row) {
    die("데이터를 찾을 수 없습니다.");
}

// 비밀번호 복호화
$decrypted_password = $crypto->decrypt($row['encrypted_password']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>비밀번호 상세</title>
</head>
<body>

<h2>비밀번호 상세 정보</h2>

<p><b>구분:</b> <?php echo htmlspecialchars($row['category']); ?></p>
<p>
    <b>사이트 주소:</b>
    <?php echo htmlspecialchars($row['site_url']); ?>

    <?php if (!empty($row['site_url'])): ?>
        <a href="<?php echo (strpos($row['site_url'], 'http') === 0 ? $row['site_url'] : 'https://' . $row['site_url']); ?>"
           target="_blank"
           style="padding: 4px 10px; background:#007bff; color:#fff;
                  border-radius:5px; text-decoration:none; margin-left:10px;">
            이동하기
        </a>
    <?php endif; ?>
</p>

<p><b>아이디:</b> <?php echo htmlspecialchars($row['login_id']); ?></p>
<p><b>비밀번호:</b> <?php echo htmlspecialchars($decrypted_password); ?></p>
<p><b>메모:</b> <?php echo nl2br(htmlspecialchars($row['memo'])); ?></p>
<p><b>등록일:</b> <?php echo $row['created_at']; ?></p>

<br>
<a href="index.php">← 목록으로</a>

</body>
</html>
