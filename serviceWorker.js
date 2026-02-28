// /public/serviceWorker.js

const CACHE_NAME = 'pass-pw-cache-v3';

// ⚠ 여기에는 "정상 응답(200)"이 확실한 파일만 넣어야 함
const URLS_TO_CACHE = [
  '/', // 루트
  '/password_0_login/password_0_login_View/password_0_login_View.php',
  '/password_0_login/password_0_login_View/password_0_login_View.css'
];

// Service Worker 설치 단계: 기본 파일들 캐시에 저장
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(URLS_TO_CACHE);
    })
  );
});

// 활성화 단계: 이전 버전 캐시 삭제
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      );
    })
  );
});

// fetch 가로채기
// 👉 "네트워크 우선, 실패하면 캐시" 방식으로 단순하게 처리
self.addEventListener('fetch', (event) => {
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});
