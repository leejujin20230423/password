// sw.js
const CACHE_NAME = 'pass-pw-cache-v1';

// 오프라인에서도 열어주고 싶은 기본 파일들
const URLS_TO_CACHE = [
  '/',
  '/password_0_login/password_0_login_View/password_0_login_View.php',
  '/password_5_passwordRegister/password_5_passwordRegister_View/password_5_passwordRegister_View_admin.php',
  '/password_5_passwordRegister/password_5_passwordRegister_View/password_5_passwordRegister_View_admin.css',
  '/password_5_passwordRegister/password_5_passwordRegister_View/password_5_passwordRegister_View_admin.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(URLS_TO_CACHE);
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      // 캐시에 있으면 캐시 응답, 없으면 네트워크
      return response || fetch(event.request);
    })
  );
});
