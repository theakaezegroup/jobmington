/**
 * Bump CACHE_VERSION on every deploy. The activate handler deletes any cache
 * whose name doesn't match, so bumping it purges every stale copy — including
 * old ?v= query-string variants, which are separate cache keys and would
 * otherwise accumulate in the cache forever.
 */
const CACHE_VERSION = '2026-08-09';
const CACHE_NAME = `jobmington-pwa-${CACHE_VERSION}`;
const NETWORK_TIMEOUT_MS = 3000;

const APP_SHELL = [
  './',
  './manifest.json',
  './assets/css/brand-platform.css',
  './assets/css/minimal-jobmington.css',
  './assets/fonts/FuturaCyrillicBook.ttf',
  './assets/fonts/FuturaCyrillicDemi.ttf',
  './assets/images/badge.png',
  './assets/images/badge-mark.png',
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

const putInCache = (request, response) => {
  const copy = response.clone();
  caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
  return response;
};

/**
 * Network-first with a timeout, falling back to cache.
 * Used for CSS/JS so a deploy is never a refresh behind, while a slow or
 * offline connection still renders from cache instead of hanging.
 */
const networkFirst = (request) => new Promise((resolve) => {
  let settled = false;
  const fallback = () => {
    if (settled) return;
    settled = true;
    caches.match(request).then((cached) => resolve(cached || fetch(request)));
  };

  const timer = setTimeout(fallback, NETWORK_TIMEOUT_MS);

  fetch(request)
    .then((response) => {
      clearTimeout(timer);
      if (settled) {
        putInCache(request, response);
        return;
      }
      settled = true;
      resolve(putInCache(request, response));
    })
    .catch(() => {
      clearTimeout(timer);
      fallback();
    });
});

/** Cache-first, refreshed in the background. Fine for versioned images/fonts. */
const staleWhileRevalidate = (request) =>
  caches.match(request).then((cached) => {
    const refresh = fetch(request)
      .then((response) => putInCache(request, response))
      .catch(() => cached);
    return cached || refresh;
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
        .then((response) => putInCache(request, response))
        .catch(() => caches.match(request).then((cached) => cached || caches.match('./')))
    );
    return;
  }

  if (url.pathname.match(/\.(?:css|js)$/)) {
    event.respondWith(networkFirst(request));
    return;
  }

  if (url.pathname.match(/\.(?:png|jpg|jpeg|webp|svg|woff2?|ttf|otf)$/)) {
    event.respondWith(staleWhileRevalidate(request));
  }
});
