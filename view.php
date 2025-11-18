<?php
require_once "db.php";
require_once "auth_check.php";
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$db = new Database();
$pdo = $db->connect();

// 현재 로그인한 유저의 등록 항목 조회
$stmt = $pdo->prepare("SELECT * FROM pass_items WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$items = $stmt->fetchAll();
?>

<h2>등록 화면</h2>
<p>안녕하세요, <?php echo htmlspecialchars($_SESSION['username']); ?>님!</p>

<!-- 등록 폼 -->
<form method="POST" action="register_item.php">
    <p>등록 항목: <input type="text" name="item_name" required></p>
    <button type="submit">등록</button>
</form>

<h3>등록 목록</h3>
<ul>
<?php foreach($items as $item): ?>
    <li>
        <?php echo htmlspecialchars($item['item_name']); ?> 
        [<a href="view_detail.php?id=<?php echo $item['id']; ?>">상세보기</a>]
    </li>
<?php endforeach; ?>
</ul>

<a href="logout.php">로그아웃</a>
