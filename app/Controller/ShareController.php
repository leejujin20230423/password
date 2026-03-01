<?php
declare(strict_types=1);

namespace PassApp\Controller;

use PassApp\Auth\AuthGate;
use PassApp\Core\SessionVault;
use PassApp\Security\CsrfShield;
use PassApp\Vault\ShareRepo;

final class ShareController
{
    public function ajaxAdmin(): void
    {
        (new AuthGate())->requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $action = (string)($_POST['action'] ?? '');
        $repo = new ShareRepo();

        if ($action === 'search_user') {
            $keyword = trim((string)($_POST['keyword'] ?? ''));
            if ($keyword === '') {
                echo json_encode(['ok'=>true,'data'=>[]], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(['ok'=>true,'data'=>$repo->searchUser($keyword)], JSON_UNESCAPED_UNICODE);
            exit;
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
