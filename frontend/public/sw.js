/* XCOMIX service worker — lightweight app-shell cache for PWA installability and
   faster repeat loads. Network-first for navigations (always try fresh HTML,
   fall back to cache/offline), cache-first for static build assets. API and
   image-proxy requests are never cached here (handled by HTTP cache headers). */
const CACHE = "xcomix-shell-v2";
const SHELL = [
  "/",
  "/home/",
  "/browse/",
  "/library/",
  "/offline.html",
  "/manifest.webmanifest",
  "/favicon.png",
  "/icon-192.png",
  "/icon-512.png",
  "/icon-maskable-512.png",
];

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL).catch(() => {})).then(() => self.skipWaiting()));
});

self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (e) => {
  const { request } = e;
  if (request.method !== "GET") return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return; // skip API + image proxy (cross-origin)

  // Static build assets: cache-first.
  if (url.pathname.startsWith("/_next/") || /\.(?:js|css|woff2?|png|jpg|jpeg|webp|svg|ico)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(request).then((hit) => hit || fetch(request).then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(request, copy)).catch(() => {});
        return res;
      }).catch(() => hit))
    );
    return;
  }

  // Navigations / HTML: network-first, fall back to cache then offline page.
  if (request.mode === "navigate") {
    e.respondWith(
      fetch(request).then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(request, copy)).catch(() => {});
        return res;
      }).catch(() => caches.match(request).then((hit) => hit || caches.match("/offline.html") || caches.match("/home/")))
    );
  }
});
