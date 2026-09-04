/*
 * Service worker for Prativa Stock & Procurement (Laravel PWA).
 */

// Bump this whenever the caching rules change. The activate handler deletes
// every cache whose name does not end with the current version, which is what
// makes an existing browser let go of what it stored under the old rules —
// including, until now, a Livewire runtime it should never have kept.
const VERSION = 'v2';
const SHELL = `pss-shell-${VERSION}`;
const ASSETS = `pss-assets-${VERSION}`;

const SHELL_URLS = [
  '/',
  '/offline.html',
  '/manifest.webmanifest',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL)
      .then((c) => c.addAll(SHELL_URLS))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => k.startsWith('pss-') && !k.endsWith(VERSION)).map((k) => caches.delete(k)),
      ))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests and cross-origin analytics/fonts
  if (request.method !== 'GET') return;
  if (url.origin !== self.location.origin && !url.hostname.includes('fonts.googleapis.com') && !url.hostname.includes('fonts.gstatic.com')) {
    return;
  }

  // 1. HTML Navigations (Network first -> Cache / Offline fallback)
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((res) => {
          if (res.ok && res.status === 200) {
            const copy = res.clone();
            caches.open(SHELL).then((c) => c.put(request, copy));
          }
          return res;
        })
        .catch(async () => {
          return (await caches.match(request)) || (await caches.match('/offline.html'));
        }),
    );
    return;
  }

  // Livewire's runtime is NOT a static asset, whatever its extension.
  //
  // Its path carries a per-installation prefix and a build id, and the client
  // it hands back has the update endpoint baked in. Served cache-first it can
  // outlive a deploy or a key change and go on posting to an endpoint that no
  // longer exists — every button on the site would then do nothing at all,
  // with no error to show for it. Same for uploads and file previews.
  if (url.pathname.includes('/livewire')) {
    return;
  }

  // 2. Static Assets (Vite build chunks, images, icons, fonts) -> Cache first with network fallback
  //
  // Safe because Vite fingerprints these filenames: a new build is a new URL,
  // so a stale entry is never served for fresh content.
  if (
    url.pathname.startsWith('/build/') ||
    url.pathname.startsWith('/icons/') ||
    url.pathname.endsWith('.woff2') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.svg') ||
    url.hostname.includes('fonts.gstatic.com')
  ) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((res) => {
          if (res.ok) {
            const copy = res.clone();
            caches.open(ASSETS).then((c) => c.put(request, copy));
          }
          return res;
        }).catch(() => cached);
      }),
    );
    return;
  }

  // 3. Default network fetch
  event.respondWith(
    fetch(request).catch(async () => {
      return await caches.match(request);
    }),
  );
});
