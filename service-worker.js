const CACHE_NAME = 'jobmington-pwa-v2026-05-17-clean-urls';
const APP_SHELL = [
  './',
  './manifest.json',
  './assets/css/brand-platform.css',
  './assets/css/minimal-jobmington.css',
  './assets/fonts/FuturaCyrillicBook.ttf',
  './assets/fonts/FuturaCyrillicDemi.ttf',
  './assets/images/badge.png',
  './assets/images/heroo.png',
  './assets/images/hero2.png',
  './assets/images/pwa-icon-192.png',
  './assets/images/pwa-icon-512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(APP_SHELL))
      .catch(() => undefined)
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || caches.match('./')))
    );
    return;
  }

  if (url.pathname.match(/\.(?:css|js|png|jpg|jpeg|webp|svg|woff2?|ttf|otf)$/)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const refresh = fetch(request).then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        }).catch(() => cached);
        return cached || refresh;
      })
    );
  }
});
