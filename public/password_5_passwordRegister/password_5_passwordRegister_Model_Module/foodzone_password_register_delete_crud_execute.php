<?php
// DB 연결 객체가 이미 다른 파일에서 생성되었다고 가정하고, 해당 연결을 사용합니다.
require_once __DIR__ . '/../../foodzone_password_register_crud.php'; // 상대 경로로 변경 (필요에 따라 수정)

// DB 연결 객체가 이미 세션에서, 혹은 다른 방식으로 생성되어 있다고 가정
// 예시: require_once 'db_connection.php'; // DB 연결 코드

// 비밀번호 삭제 요청 처리
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['password_id'])) {
    $passwordId = $_POST['password_id']; // 삭제할 비밀번호의 ID

    // DB 연결을 전달하는 방식 (DB 연결 객체가 필요함)
    $passwordRegister = new PasswordRegister($dbConnection); // DB 연결 객체 전달

    // 비밀번호 삭제
    $isDeleted = $passwordRegister->deletePassword($passwordId);

    if ($isDeleted) {
        // 삭제 성공 시 처리
        echo "비밀번호가 성공적으로 삭제되었습니다.";
        // 삭제 후 리다이렉트 등 추가할 수 있음
    } else {
        // 삭제 실패 시 처리
        echo "비밀번호 삭제에 실패했습니다.";
    }
}
?>
