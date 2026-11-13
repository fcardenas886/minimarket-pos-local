<?php
// Pantalla de cocina (tablet o PC): comandas pendientes en tiempo real. Módulo "Mesas y garzones".
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/app_instalable.php';
$sesion = $_SESSION['garzon'] ?? ((acceso_es_local() && !empty($_SESSION['usuario'])) ? $_SESSION['usuario'] : null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
<?php app_instalable_head('cocina', 'Cocina', '#14171e'); ?>
<title>Cocina · Comandas</title>
<style>
  :root { --bg:#14171e; --card:#1e2430; --text:#eef1f6; --muted:#9aa3b5; --border:#2e3646; --primary:#818cf8;
          --verde:#3ecf8e; --amarillo:#f5b942; --rojo:#f2685c; }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.35 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  header { display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: var(--bg); z-index: 3; }
  header h1 { font-size: 1.2rem; margin: 0; flex: 1; }
  header button { background: var(--card); color: var(--text); border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; font: inherit; font-size: 0.9rem; cursor: pointer; }
  .reloj { font-variant-numeric: tabular-nums; color: var(--muted); }
  main { padding: 16px 18px 40px; }
  .tablero { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; align-items: start; }
  .comanda { background: var(--card); border: 1px solid var(--border); border-top: 6px solid var(--verde); border-radius: 12px; padding: 14px; }
  .comanda.amarilla { border-top-color: var(--amarillo); }
  .comanda.roja { border-top-color: var(--rojo); }
  .comanda.nueva { animation: brillo 1.2s ease 3; }
  @keyframes brillo { 50% { box-shadow: 0 0 0 4px var(--primary); } }
  .cab { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
  .mesa { font-size: 1.35rem; font-weight: 800; }
  .tiempo { font-weight: 700; font-variant-numeric: tabular-nums; }
  .meta { color: var(--muted); font-size: 0.85rem; margin: 2px 0 10px; }
  ul { list-style: none; margin: 0 0 12px; padding: 0; }
  li { padding: 6px 0; border-bottom: 1px dashed var(--border); font-size: 1.1rem; }
  li:last-child { border-bottom: 0; }
  li b { display: inline-block; min-width: 2.2em; }
  .nota { display: block; color: var(--amarillo); font-size: 0.95rem; margin-left: 2.2em; }
  .lista { width: 100%; padding: 13px; border: 0; border-radius: 10px; background: var(--verde); color: #08281a; font: inherit; font-weight: 800; font-size: 1.05rem; cursor: pointer; }
  .vacio { color: var(--muted); text-align: center; padding: 60px 0; font-size: 1.1rem; }
  h2 { font-size: 0.85rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin: 28px 0 10px; }
  .hechas { display: flex; flex-wrap: wrap; gap: 8px; }
  .hecha { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 6px 10px; font-size: 0.9rem; color: var(--muted); }
  .hecha button { background: none; border: 0; color: var(--primary); cursor: pointer; font: inherit; font-size: 0.85rem; }
  .pin { max-width: 320px; margin: 60px auto; text-align: center; }
  .pin input { width: 100%; padding: 14px; font-size: 1.4rem; text-align: center; letter-spacing: 0.3em; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); }
  .pin button { margin-top: 12px; width: 100%; padding: 14px; border-radius: 10px; border: 0; background: var(--primary); color: #11141b; font: inherit; font-weight: 700; }
  .error { color: var(--rojo); margin-top: 10px; min-height: 1.3em; }
</style>
</head>
<body>
<?php app_instalable_aviso('cocina'); ?>
<?php if (!$sesion): ?>
  <form class="pin" onsubmit="entrar(event)">
    <h1 style="font-size: 1.3rem;">Cocina</h1>
    <p style="color: var(--muted);">Ingresa un PIN para ver las comandas</p>
    <input type="password" inputmode="numeric" id="pin" autocomplete="off" autofocus>
    <button type="submit">Entrar</button>
    <div class="error" id="err"></div>
  </form>
  <script>
    async function entrar(e) {
      e.preventDefault();
      const d = await (await fetch('api/mesas.php?action=login_pin', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ pin: document.getElementById('pin').value }) })).json();
      if (d.success) location.reload(); else document.getElementById('err').textContent = d.error || 'PIN incorrecto';
    }
  </script>
<?php else: ?>
  <header>
    <h1>Comandas <span id="contador" style="color: var(--muted); font-weight: 400;"></span></h1>
    <span class="reloj" id="reloj"></span>
    <button type="button" id="btnSonido" onclick="activarSonido()">🔔 Activar sonido</button>
    <button type="button" onclick="pantallaCompleta()">⛶</button>
  </header>
  <main>
    <div class="tablero" id="tablero"><div class="vacio">Cargando…</div></div>
    <h2>Listas recientemente</h2>
    <div class="hechas" id="hechas"></div>
  </main>
  <script>
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let conocidas = null, sonido = false, desfase = 0, audio = null, ultimas = [];

    function activarSonido() {
      // Los navegadores solo permiten sonido después de un toque del usuario.
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      sonido = true; pitido();
      document.getElementById('btnSonido').textContent = '🔔 Sonido activo';
    }
    function pitido() {
      if (!sonido || !audio) return;
      [0, 0.25].forEach(t => {
        const o = audio.createOscillator(), g = audio.createGain();
        o.frequency.value = 880; o.connect(g); g.connect(audio.destination);
        g.gain.setValueAtTime(0.25, audio.currentTime + t);
        o.start(audio.currentTime + t); o.stop(audio.currentTime + t + 0.18);
      });
    }
    function pantallaCompleta() { document.documentElement.requestFullscreen?.(); }
    function minutos(fecha) { return Math.max(0, Math.floor((Date.now() + desfase - new Date(fecha.replace(' ', 'T')).getTime()) / 60000)); }

    function pintar() {
      const t = document.getElementById('tablero');
      document.getElementById('contador').textContent = ultimas.length ? `(${ultimas.length})` : '';
      if (!ultimas.length) { t.innerHTML = '<div class="vacio">Sin comandas pendientes 👍</div>'; return; }
      t.innerHTML = ultimas.map(c => {
        const m = minutos(c.Fecha);
        const color = m >= 20 ? 'roja' : (m >= 10 ? 'amarilla' : '');
        return `<div class="comanda ${color} ${c._nueva ? 'nueva' : ''}">
          <div class="cab"><span class="mesa">${esc(c.Mesa)}</span><span class="tiempo">${m} min</span></div>
          <div class="meta">${esc(c.Zona)} · ${c.Fecha.slice(11, 16)} · ${esc(c.Garzon)} · #${c.ComandaID}</div>
          <ul>${c.items.map(i => `<li><b>${+i.cantidad}×</b>${esc(i.nombre)}${i.nota ? `<span class="nota">↳ ${esc(i.nota)}</span>` : ''}</li>`).join('')}</ul>
          <button class="lista" onclick="marcar(${c.ComandaID})">✓ Lista</button>
        </div>`;
      }).join('');
    }

    async function cargar() {
      try {
        const res = await fetch('api/mesas.php?action=comandas');
        const d = await res.json();
        if (d.sin_sesion) return location.reload();
        if (!d.success) throw new Error(d.error);
        desfase = new Date(d.ahora.replace(' ', 'T')).getTime() - Date.now();
        const ids = new Set(d.pendientes.map(c => c.ComandaID));
        let hayNuevas = false;
        d.pendientes.forEach(c => { c._nueva = conocidas !== null && !conocidas.has(c.ComandaID); if (c._nueva) hayNuevas = true; });
        conocidas = ids;
        ultimas = d.pendientes;
        pintar();
        if (hayNuevas) pitido();
        document.getElementById('hechas').innerHTML = d.listas.map(c =>
          `<span class="hecha">${esc(c.Mesa)} · ${c.FechaLista.slice(11, 16)} <button onclick="marcar(${c.ComandaID}, true)">deshacer</button></span>`).join('')
          || '<span style="color: var(--muted);">—</span>';
      } catch (e) {
        document.getElementById('tablero').innerHTML = `<div class="vacio">Sin conexión con la caja: ${esc(e.message)}. Reintentando…</div>`;
      }
    }

    async function marcar(id, deshacer = false) {
      await fetch('api/mesas.php?action=comanda_lista', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify({ comanda_id: id, deshacer }) });
      cargar();
    }

    setInterval(() => { document.getElementById('reloj').textContent = new Date(Date.now() + desfase).toTimeString().slice(0, 5); pintar(); }, 30000);
    document.getElementById('reloj').textContent = new Date().toTimeString().slice(0, 5);
    cargar();
    setInterval(cargar, 5000);
  </script>
<?php endif; ?>
</body>
</html>
