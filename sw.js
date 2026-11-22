// Service worker de BRDG Seguimiento: permite instalar la app en el celular.
// Privacidad: NUNCA guarda páginas ni respuestas con datos (clientes, salud, pedidos).
// Solo guarda archivos estáticos (estilos, código, íconos). Las páginas siempre se piden al servidor.
const VERSION = 'brdg-estaticos-1';
const ESTATICOS = /\/assets\/.+\.(css|js|png|svg|woff2?)(\?.*)?$/;

self.addEventListener('install', (ev) => { self.skipWaiting(); });

self.addEventListener('activate', (ev) => {
  ev.waitUntil(
    caches.keys()
      .then((claves) => Promise.all(claves.filter((c) => c !== VERSION).map((c) => caches.delete(c))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (ev) => {
  const pedido = ev.request;
  if (pedido.method !== 'GET') { return; }
  const url = new URL(pedido.url);
  if (url.origin !== self.location.origin) { return; }

  // Archivos estáticos: se muestran de la copia y se actualizan por detrás.
  if (ESTATICOS.test(url.pathname)) {
    ev.respondWith(
      caches.open(VERSION).then((cache) =>
        cache.match(pedido).then((guardado) => {
          const red = fetch(pedido).then((resp) => {
            if (resp.ok) { cache.put(pedido, resp.clone()); }
            return resp;
          }).catch(() => guardado);
          return guardado || red;
        })
      )
    );
    return;
  }

  // Páginas: siempre del servidor. Sin conexión, un aviso sencillo (sin datos de nadie).
  if (pedido.mode === 'navigate') {
    ev.respondWith(
      fetch(pedido).catch(() => new Response(
        '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
        '<title>Sin conexión · BRDG</title><body style="margin:0;font:16px/1.5 system-ui,sans-serif;background:#edf8f7;color:#161a2e;' +
        'display:grid;place-items:center;min-height:100vh;text-align:center;padding:24px"><div><h1 style="font-size:22px;margin:0 0 8px">' +
        'Sin conexión</h1><p style="margin:0 0 16px;color:#4c5a63">Revisa tu internet y vuelve a intentarlo.</p>' +
        '<a href="" style="display:inline-block;padding:12px 20px;border-radius:999px;background:#324593;color:#fff;text-decoration:none">' +
        'Reintentar</a></div></body></html>',
        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
      ))
    );
  }
});
