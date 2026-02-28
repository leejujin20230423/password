<?php

// // DB 연결
// require_once $_SERVER['DOCUMENT_ROOT'] . '/password_60_CRUD/password_60_CRUD.php';

// $dbConnection = new DBConnection();
// $pdo = $dbConnection->getDB();
// $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// // 삭제할 비밀번호 ID
// $password_idno = $_POST['password_idno']; // 예시로 POST로 전달된 password_idno

// try {
//     // 트랜잭션 시작
//     $pdo->beginTransaction();

//     // 1. password_share 테이블에서 해당 비밀번호와 연결된 공유 기록 삭제
//     $deleteShareQuery = "DELETE FROM password_share WHERE password_idno_Fk = :password_idno_Fk";
//     $stmt2 = $pdo->prepare($deleteShareQuery);
//     $stmt2->bindValue(':password_idno_Fk', $password_idno, PDO::PARAM_INT);
//     $stmt2->execute();

//     // 삭제된 공유 기록이 있는지 확인
//     if ($stmt2->rowCount() === 0) {
//         throw new Exception("공유 기록 삭제 실패: 해당 비밀번호와 연결된 공유 기록이 없습니다.");
//     }

//     // 2. password 테이블에서 해당 비밀번호 삭제
//     $deletePasswordQuery = "DELETE FROM password WHERE password_idno = :password_idno";
//     $stmt1 = $pdo->prepare($deletePasswordQuery);
//     $stmt1->bindValue(':password_idno', $password_idno, PDO::PARAM_INT);
//     $stmt1->execute();

//     // 삭제된 비밀번호가 있는지 확인 (디버깅 용도)
//     if ($stmt1->rowCount() === 0) {
//         throw new Exception("비밀번호 삭제 실패: 해당 비밀번호가 존재하지 않거나 이미 삭제되었습니다.");
//     }

//     // 트랜잭션 커밋
//     $pdo->commit();
    
//     echo "비밀번호와 관련된 공유 기록이 삭제되었습니다.";
// } catch (Exception $e) {
//     // 트랜잭션 롤백
//     $pdo->rollBack();
//     echo "오류 발생: " . $e->getMessage();
// }

?>
