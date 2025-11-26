<?php

/**
 * SessionManager.php
 *
 * 역할:
 *  - 세션 시작/저장 위치를 한 곳에서 통일 관리
 *  - 로그인 성공 시 사용자 정보를 세션에 저장
 *  - 로그인 여부/권한 확인
 *  - 로그아웃 (세션 + 쿠키 정리)
 *  - 옵션: Redis 전체 초기화(flushAll)
 *
 * 위치:
 *  - /PASS/SessionManager/SessionManager.php
 */

class SessionManager
{
    /**
     * @var bool 세션이 이미 시작되었는지 여부 (중복 start 방지용)
     */
    private static $started = false;

    /**
     * 세션 시작 + 세션 파일 저장 경로 지정
     *
     * - 항상 이 메서드로만 세션을 시작하도록 통일
     * - /PASS/session 폴더를 세션 저장소로 사용
     */
    public static function start()
    {
        // 이미 한 번 시작했다면 다시 시작할 필요 없음
        if (self::$started === true) {
            return;
        }

        // PHP 내부적으로 아직 세션이 시작 안 된 상태일 때만 처리
        if (session_status() === PHP_SESSION_NONE) {

            // 1) 세션 파일을 저장할 폴더 경로 지정
            //
            // __DIR__             = /PASS/SessionManager
            // dirname(__DIR__)    = /PASS
            // 세션 폴더           = /PASS/session
            $sessionSavePath = dirname(__DIR__) . '/session';

            // 세션 폴더가 없다면 생성 (권한 0777, 하위폴더 자동 생성)
            if (!is_dir($sessionSavePath)) {
                mkdir($sessionSavePath, 0777, true);
            }

            // PHP에게 "세션 파일은 여기 저장해라"라고 알려줌
            session_save_path($sessionSavePath);

            // 2) 실제로 세션 시작
            session_start();
        }

        self::$started = true;
    }

    /**
     * 로그인 성공 시, users 테이블 1행 정보를 세션에 저장
     *
     * @param array $userRow
     *  예: [
     *        'user_no'        => 1,
     *        'userid'    => 'lokiaadmin',
     *        'username'  => '이주진',
     *        'user_type' => 'admin',
     *        ...
     *      ]
     */
    public static function setLoginUser(array $userRow)
    {
        // 세션이 시작되어 있는지 보장
        self::start();

        // 필요한 값들을 세션에 저장
        $_SESSION['user_no']   = isset($userRow['user_no'])   ? $userRow['user_no']   : null;
        $_SESSION['userid']    = isset($userRow['userid'])    ? $userRow['userid']    : null;
        $_SESSION['username']  = isset($userRow['username'])  ? $userRow['username']  : null;
        $_SESSION['user_type'] = isset($userRow['user_type']) ? $userRow['user_type'] : null;

        // 선택: 로그인 시각
        $_SESSION['login_at']  = time();
    }

    /**
     * 현재 로그인 상태인지 여부
     *  - 기준: 세션에 user_no 가 있으면 "로그인"으로 판단
     *
     * @return bool
     */
    public static function isLoggedIn()
    {
        self::start();

        return isset($_SESSION['user_no']) && $_SESSION['user_no'] !== null;
    }

    /**
     * 현재 로그인한 사용자 user_type (권한) 가져오기
     *
     * @return string|null  예: 'admin', 'user', 'master' / 없으면 null
     */
    public static function getUserType()
    {
        self::start();

        return isset($_SESSION['user_type']) && $_SESSION['user_type'] !== ''
            ? $_SESSION['user_type']
            : null;
    }

    /**
     * 현재 로그인한 사용자 PK (user_no) 가져오기
     *
     * @return int|null
     */
    public static function getUserId()
    {
        self::start();

        return isset($_SESSION['user_no'])
            ? (int) $_SESSION['user_no']
            : null;
    }

    /**
     * 세션에 임의 값 쓰기 (일반적인 key/value)
     */
    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * 세션에서 값 가져오기 (없으면 $default 반환)
     */
    public static function get($key, $default = null)
    {
        self::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    /**
     * 순수 세션 정리 (Redis는 건드리지 않음)
     *
     * - 세션 변수 비우기
     * - 세션 쿠키 제거
     * - 서버 측 세션 파일 삭제
     */
    public static function clearSession()
    {
        self::start();

        // 1) 모든 세션 변수 제거
        $_SESSION = array();

        // 2) 세션 쿠키 삭제
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // 3) 서버 측 세션 파일 삭제
        session_destroy();

        self::$started = false;
    }

    /**
     * Redis 전체 포맷 (flushAll)
     *
     * - phpredis 확장이 있거나, redis_stubs.php 가 정의되어 있으면 동작
     * - 모든 DB, 모든 키가 삭제되므로 주의해서 사용
     */
    public static function flushRedisAll()
    {
        // /PASS/connection/redis_stubs.php 로 가정
        require_once dirname(__DIR__) . '/connection/redis_stubs.php';

        try {
            if (class_exists('Redis')) {
                $redis = new Redis();

                // 네가 사용 중인 Redis 설정과 맞춰주면 됨
                $redis->connect('127.0.0.1', 6379, 0.5);
                // $redis->auth('your_redis_password');
                // $redis->select(0);

                $redis->flushAll();

                error_log('[SessionManager] Redis flushAll() called');
            } else {
                error_log('[SessionManager] Redis class not found');
            }
        } catch (Exception $e) {
            error_log('[SessionManager] Redis flushAll error: ' . $e->getMessage());
        }
    }

    /**
     * 로그아웃 처리 (세션 + 옵션에 따라 Redis 전체 초기화)
     *
     * @param bool $flushRedis true 이면 Redis flushAll 까지 같이 수행
     */
    public static function logout($flushRedis = false)
    {
        if ($flushRedis) {
            self::flushRedisAll();
        }

        self::clearSession();
    }
}
