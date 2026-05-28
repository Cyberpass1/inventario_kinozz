// Service Worker para Kinozz PWA
// Estrategias:
// - Navegacion (HTML): network-first con fallback al cache (modo offline)
// - Assets estaticos (css/js/img/fuentes): stale-while-revalidate
// - Peticiones POST y de API: siempre network (no se cachean)

const CACHE_VERSION = "kinozz-v1";
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

// Resuelve rutas relativas al scope del SW (funciona aunque la app este montada bajo un sub-path)
const swScope = new URL(self.registration ? self.registration.scope : self.location.href);
const scopePath = swScope.pathname.endsWith("/") ? swScope.pathname : swScope.pathname + "/";

const PRECACHE_URLS = [
    scopePath,
    scopePath + "assets/css/app.css",
    scopePath + "assets/js/app.js",
    scopePath + "assets/img/Logo_System.png",
    scopePath + "manifest.webmanifest",
];

self.addEventListener("install", (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) =>
            cache.addAll(PRECACHE_URLS).catch(() => {
                // Si algun recurso falla (por ej. la pagina home no esta autenticada) seguimos igual
                return Promise.resolve();
            })
        )
    );
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => !key.startsWith(CACHE_VERSION))
                    .map((key) => caches.delete(key))
            )
        ).then(() => self.clients.claim())
    );
});

const isSameOrigin = (url) => url.origin === self.location.origin;
const isStaticAsset = (url) => /\.(?:css|js|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf|eot)$/i.test(url.pathname);
const isHtmlRequest = (request) => request.mode === "navigate"
    || (request.method === "GET" && (request.headers.get("accept") || "").includes("text/html"));

self.addEventListener("fetch", (event) => {
    const { request } = event;

    // Nunca interceptamos metodos distintos a GET
    if (request.method !== "GET") {
        return;
    }

    const url = new URL(request.url);

    // CDN externos (sweetalert, bootstrap-icons, xlsx) → cache stale-while-revalidate
    if (!isSameOrigin(url)) {
        event.respondWith(staleWhileRevalidate(request, RUNTIME_CACHE));
        return;
    }

    // Navegacion: network-first con fallback offline
    if (isHtmlRequest(request)) {
        event.respondWith(networkFirst(request, RUNTIME_CACHE));
        return;
    }

    // Assets estaticos
    if (isStaticAsset(url)) {
        event.respondWith(staleWhileRevalidate(request, STATIC_CACHE));
        return;
    }

    // Por defecto: network con fallback al cache
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});

async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    try {
        const response = await fetch(request);
        if (response && response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }
        // Fallback al shell raiz si esta cacheado
        const shell = await caches.match(scopePath);
        if (shell) {
            return shell;
        }
        return new Response(
            "<h1>Sin conexion</h1><p>No pudimos cargar esta vista y no esta disponible offline.</p>",
            { status: 503, headers: { "Content-Type": "text/html; charset=utf-8" } }
        );
    }
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const networkPromise = fetch(request)
        .then((response) => {
            if (response && response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch(() => null);

    return cached || (await networkPromise) || new Response("", { status: 504 });
}

self.addEventListener("message", (event) => {
    if (event.data === "SKIP_WAITING") {
        self.skipWaiting();
    }
});
