<?php
/**
 * app_bootstrap.php
 * ------------------------------------------------------------
 * 목표:
 *  - 프로젝트 어디에서 실행되든(루트/서브디렉토리) loader.php 경로를 안정적으로 찾기
 *  - 치명적 require 실패로 500이 나지 않도록, 실패 시 명확한 메시지 제공
 *
 * 주의:
 *  - URL/라우트 경로는 건드리지 않습니다.
 *  - DBConnection/RedisConnection 정의는 기존 loader.php 책임입니다.
 */

declare(strict_types=1);

/**
 * 프로젝트 루트 경로 반환
 */
function pass_project_root_dir(): string
{
    return __DIR__;
}

/**
 * loader.php 후보 경로 목록
 * - 배포 환경에 따라 위치가 다를 수 있어서 여러 후보를 두고 탐색합니다.
 */
function pass_loader_candidate_paths(): array
{
    $root = pass_project_root_dir();

    return [
        // 1) 프로젝트 내부에 connection/loader.php가 있을 때(권장)
        $root . '/connection/loader.php',

        // 2) 현재 파일이 public 하위로 옮겨진 환경 대비
        dirname($root) . '/connection/loader.php',
        dirname($root, 2) . '/connection/loader.php',

        // 3) 서버에서 /var/www/connection 같은 공용 경로를 쓰는 환경 대비
        '/var/www/connection/loader.php',
        '/var/www/pass/connection/loader.php',
    ];
}

/**
 * loader.php를 찾으면 require_once, 없으면 500으로 종료
 */
function pass_require_loader_or_die(): void
{
    foreach (pass_loader_candidate_paths() as $candidatePath) {
        if (is_string($candidatePath) && $candidatePath !== '' && file_exists($candidatePath)) {
            require_once $candidatePath;
            return;
        }
    }

    // loader.php가 없으면 DB/Redis 클래스가 없어 실행 불가 -> 명확하게 종료
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');

    $searched = implode("\n", pass_loader_candidate_paths());
    echo "[PASS] loader.php 를 찾지 못했습니다.\n\n검색한 경로:\n" . $searched . "\n\n"
        . "해결 방법:\n"
        . "1) 프로젝트 루트에 connection/loader.php 를 배치하거나\n"
        . "2) 서버에 /var/www/connection/loader.php 를 배치해주세요.\n";
    exit;
}
