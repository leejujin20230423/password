<?php
/**
 * redis_stubs.php
 *
 * 역할:
 *  - 개발 환경 / IDE 인텔리센스를 위한 Redis 가짜 클래스 정의
 *  - 실제 서버에 phpredis 확장이 설치되어 있으면
 *    class_exists('Redis') 가 true 이므로 이 클래스는 선언되지 않음.
 *  - 확장이 없는 환경에서는 이 가짜 클래스로 인해
 *    코드가 "캐시 없이 DB만 쓰는" 흐름으로 자연스럽게 동작하도록 함.
 */

if (!class_exists('Redis')) {

    /**
     * @internal IDE / 개발용 Redis Stub 클래스
     *
     * - 실제 Redis 서버에 연결되지는 않음
     * - 메서드들은 대부분 true/false 정도만 돌려줘서
     *   상위 로직이 에러 없이 흘러가게만 해준다.
     *
     * ⚠ 실제 phpredis 메서드 시그니처와 100% 일치하지는 않을 수 있음.
     *   필요한 메서드는 프로젝트에 맞게 추가/수정해서 사용하면 된다.
     */
    class Redis
    {
        /* =========================
         * 연결 / 인증 / 기본 설정
         * ========================= */

        public function connect(string $host, int $port = 6379, float $timeout = 0.0)
        {
            return true;
        }

        public function pconnect(string $host, int $port = 6379, float $timeout = 0.0, string $persistent_id = '')
        {
            return true;
        }

        public function close()
        {
            return true;
        }

        public function auth(string $password)
        {
            return true;
        }

        public function select(int $db)
        {
            return true;
        }

        public function ping()
        {
            return true;
        }

        public function info(string $option = null)
        {
            return [];
        }

        public function dbSize()
        {
            return 0;
        }

        public function flushDB()
        {
            return true;
        }

        public function flushAll()
        {
            return true;
        }

        /* =========================
         * 문자열 (String)
         * ========================= */

        public function get(string $key)
        {
            // 캐시에 값이 없다고 가정 (DB 조회로 넘어가게 하기 위함)
            return false;
        }

        public function set(string $key, string $value, int $timeout = 0)
        {
            return true;
        }

        public function setex(string $key, int $ttl, string $value)
        {
            return true;
        }

        public function setnx(string $key, string $value)
        {
            return true;
        }

        public function mGet(array $keys)
        {
            return [];
        }

        public function mSet(array $pairs)
        {
            return true;
        }

        public function incr(string $key)
        {
            return 1;
        }

        public function incrBy(string $key, int $value)
        {
            return $value;
        }

        public function decr(string $key)
        {
            return -1;
        }

        public function decrBy(string $key, int $value)
        {
            return -$value;
        }

        /* =========================
         * 키 관련 (Key ops)
         * ========================= */

        public function exists(string $key)
        {
            return false;
        }

        /**
         * del
         * - 실제 phpredis 는 string ...$keys 이지만
         *   여기서는 배열도 받을 수 있게 느슨하게 처리
         */
        public function del($keys)
        {
            // $keys 가 배열이면 그 길이, 문자열이면 1 삭제했다고 가정
            if (is_array($keys)) {
                return count($keys);
            }
            return 1;
        }

        public function expire(string $key, int $seconds)
        {
            return true;
        }

        public function expireAt(string $key, int $timestamp)
        {
            return true;
        }

        public function ttl(string $key)
        {
            return -1;
        }

        /**
         * keys
         * - 패턴에 맞는 키 목록을 리턴해야 하지만,
         *   stub 에서는 항상 빈 배열 리턴
         */
        public function keys(string $pattern)
        {
            return [];
        }

        /* =========================
         * 리스트 (List)
         * ========================= */

        public function lPush(string $key, string ...$values)
        {
            return count($values);
        }

        public function rPush(string $key, string ...$values)
        {
            return count($values);
        }

        public function lPop(string $key)
        {
            return null;
        }

        public function rPop(string $key)
        {
            return null;
        }

        public function bLPop(array $keys, int $timeout)
        {
            return null;
        }

        public function bRPop(array $keys, int $timeout)
        {
            return null;
        }

        public function lLen(string $key)
        {
            return 0;
        }

        public function lRange(string $key, int $start, int $stop)
        {
            return [];
        }

        /* =========================
         * 집합 (Set)
         * ========================= */

        public function sAdd(string $key, string ...$members)
        {
            return count($members);
        }

        public function sRem(string $key, string ...$members)
        {
            return count($members);
        }

        public function sMembers(string $key)
        {
            return [];
        }

        public function sIsMember(string $key, string $member)
        {
            return false;
        }

        public function sCard(string $key)
        {
            return 0;
        }

        /* =========================
         * 해시 (Hash)
         * ========================= */

        public function hGet(string $key, string $field)
        {
            return null;
        }

        public function hSet(string $key, string $field, string $value)
        {
            return true;
        }

        public function hDel(string $key, string ...$fields)
        {
            return count($fields);
        }

        public function hExists(string $key, string $field)
        {
            return false;
        }

        public function hLen(string $key)
        {
            return 0;
        }

        public function hKeys(string $key)
        {
            return [];
        }

        public function hVals(string $key)
        {
            return [];
        }

        public function hGetAll(string $key)
        {
            return [];
        }

        public function hMGet(string $key, array $fields)
        {
            return [];
        }

        public function hMSet(string $key, array $pairs)
        {
            return true;
        }

        /* =========================
         * 정렬된 집합 (Sorted Set)
         * ========================= */

        public function zAdd(string $key, float $score, string $member)
        {
            return 1;
        }

        public function zRem(string $key, string ...$members)
        {
            return count($members);
        }

        public function zRange(string $key, int $start, int $stop, bool $withscores = false)
        {
            return [];
        }

        public function zRevRange(string $key, int $start, int $stop, bool $withscores = false)
        {
            return [];
        }

        public function zRangeByScore(string $key, $min, $max, array $options = [])
        {
            return [];
        }

        public function zCard(string $key)
        {
            return 0;
        }

        public function zScore(string $key, string $member)
        {
            return null;
        }

        /* =========================
         * Pub/Sub
         * ========================= */

        public function publish(string $channel, string $message)
        {
            return 1;
        }

        public function subscribe(array $channels, callable $callback)
        {
            return true;
        }

        public function pSubscribe(array $patterns, callable $callback)
        {
            return true;
        }

        public function unsubscribe(array $channels = null)
        {
            return true;
        }

        public function pUnsubscribe(array $patterns = null)
        {
            return true;
        }

        /* =========================
         * 트랜잭션 / 파이프라인
         * ========================= */

        public function multi()
        {
            return $this;
        }

        public function exec()
        {
            return [];
        }

        public function discard()
        {
            return true;
        }

        public function watch(string ...$keys)
        {
            return true;
        }

        public function unwatch()
        {
            return true;
        }

        /* =========================
         * 스크립트 (Eval)
         * ========================= */

        public function eval(string $script, array $args = [], int $num_keys = 0)
        {
            return null;
        }

        public function evalSha(string $sha, array $args = [], int $num_keys = 0)
        {
            return null;
        }
    }
}
