<?php
declare(strict_types=1);

namespace PassApp\Controller;

use PassApp\Core\DbHub;
use PassApp\Core\SessionVault;
use PassApp\Security\CsrfShield;
use PassApp\Vault\ShareRepo;
use Throwable;

final class ShareController
{
    public function ajaxAdmin(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        // AJAX에서는 HTML redirect 대신 JSON 에러를 반환
        if (!SessionVault::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'msg' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $action = (string)($_POST['action'] ?? '');
        $repo = new ShareRepo();

        if ($action === 'search_user') {
            // Legacy JS 호환: phone 파라미터 우선 사용, 없으면 keyword 사용
            $rawPhone = trim((string)($_POST['phone'] ?? ''));
            $keyword  = trim((string)($_POST['keyword'] ?? ''));

            if ($rawPhone === '' && $keyword === '') {
                echo json_encode(['ok' => false, 'msg' => '전화번호를 입력해 주세요.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            try {
                $pdo = DbHub::pdo();

                // 전화번호 검색: 하이픈/공백 제거 후 비교
                if ($rawPhone !== '') {
                    $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
                    if ($digits === '') {
                        echo json_encode(['ok' => false, 'msg' => '전화번호 형식이 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }

                    $stmt = $pdo->prepare(
                        "SELECT user_no, username, phone
                         FROM users
                         WHERE REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '.', '') = :digits
                         ORDER BY user_no DESC
                         LIMIT 30"
                    );
                    $stmt->execute([':digits' => $digits]);
                    $users = $stmt->fetchAll() ?: [];

                    if (!empty($users)) {
                        // 하위 호환: 기존 JS가 user 단일 필드를 읽어도 동작하도록 첫 행을 함께 전달
                        echo json_encode(
                            [
                                'ok' => true,
                                'user' => $users[0],
                                'users' => $users,
                            ],
                            JSON_UNESCAPED_UNICODE
                        );
                    } else {
                        echo json_encode(['ok' => false, 'msg' => '해당 전화번호로 등록된 회원이 없습니다.'], JSON_UNESCAPED_UNICODE);
                    }
                    exit;
                }

                // fallback: keyword 검색 (신규 API 호환)
                $rows = $repo->searchUser($keyword);
                echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
                exit;
            } catch (Throwable $e) {
                echo json_encode(['ok' => false, 'msg' => '검색 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        if ($action === 'grant_share') {
            CsrfShield::assert($_POST['csrf_token'] ?? null);
            $pid = (int)($_POST['password_idno'] ?? 0);
            $target = (int)($_POST['user_no'] ?? 0);
            $by = (int)SessionVault::pull('user_no', 0);
            if ($pid<=0 || $target<=0 || $by<=0) {
                echo json_encode(['ok'=>false,'msg'=>'파라미터 오류'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $repo->grant($pid, $target, $by);
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'revoke_share') {
            CsrfShield::assert($_POST['csrf_token'] ?? null);
            $sid = (int)($_POST['password_share_idno'] ?? 0);
            if ($sid<=0) {
                echo json_encode(['ok'=>false,'msg'=>'파라미터 오류'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $repo->revoke($sid);
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok'=>false,'msg'=>'지원하지 않는 action'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
