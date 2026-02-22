const CACHE_NAME = 'form-builder-v1';
const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/assets/css/styles.css',
  '/assets/js/pwa.js',
  '/assets/images/icon-192x192.png',
  '/assets/images/icon-512x512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(ASSETS_TO_CACHE))
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => response || fetch(event.request))
  );
});