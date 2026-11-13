// Service worker mínimo para que las pantallas de garzón y cocina se puedan instalar como app.
// No guarda nada en caché: el sistema siempre necesita datos frescos de la caja.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (e) => {
  if (e.request.mode !== 'navigate') return; // el resto lo maneja el navegador normalmente
  e.respondWith(fetch(e.request).catch(() => new Response(
    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
    '<body style="font-family:system-ui;text-align:center;padding:40px 20px"><h2>Sin conexión con la caja</h2>' +
    '<p>Revisa que este equipo esté conectado al WiFi del local y que el computador de caja esté encendido.</p>' +
    '<button onclick="location.reload()" style="padding:12px 20px;font-size:1rem">Reintentar</button></body>',
    { headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
});
