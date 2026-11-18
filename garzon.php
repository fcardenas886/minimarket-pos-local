<?php
// Pantalla del garzón (celular o tablet): toma de pedidos por mesa. Módulo "Mesas y garzones".
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/app_instalable.php';
$garzon = $_SESSION['garzon'] ?? null;
$negocio = '';
try {
    $negocio = (string)getDB()->query("SELECT Valor FROM configuraciones WHERE Clave = 'MINIMARKET_NOMBRE'")->fetchColumn();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<?php app_instalable_head('garzon', 'Garzón', '#4f46e5'); ?>
<meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
<title>Garzón · <?= htmlspecialchars($negocio ?: 'Minimarket POS') ?></title>
<style>
  :root { --bg:#f3f4f8; --card:#fff; --text:#1c2230; --muted:#6b7385; --border:#e1e4ec; --primary:#4f46e5; --primary-text:#fff;
          --libre:#e8f7ee; --libre-text:#0f6b3f; --ocupada:#fff1e0; --ocupada-text:#8a4b00; --cocina:#fde8e8; --cocina-text:#a12a1f;
          --precuenta:#fef3c7; --precuenta-text:#92400e; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#11141b; --card:#1b2029; --text:#e8ebf1; --muted:#9aa3b5; --border:#2c3342; --primary:#818cf8; --primary-text:#11141b;
            --libre:#143323; --libre-text:#7fe0a9; --ocupada:#3a2a12; --ocupada-text:#f5bf6b; --cocina:#3d1a17; --cocina-text:#f4978e;
            --precuenta:#3d3314; --precuenta-text:#fcd34d; }
  }
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  header { position: sticky; top: 0; z-index: 5; background: var(--primary); color: var(--primary-text); padding: 12px 16px; display: flex; align-items: center; gap: 10px; }
  header h1 { font-size: 1.05rem; margin: 0; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  header button { background: rgba(255,255,255,0.18); color: inherit; border: 0; border-radius: 8px; padding: 8px 12px; font: inherit; font-size: 0.9rem; }
  main { padding: 14px 16px 120px; max-width: 900px; margin: 0 auto; }
  h2 { font-size: 0.95rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; margin: 18px 0 8px; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 10px; }
  .mesa { border: 0; border-radius: 14px; padding: 14px 8px; min-height: 92px; font: inherit; text-align: center; display: flex; flex-direction: column; justify-content: center; gap: 4px; }
  .mesa b { font-size: 1.05rem; }
  .mesa small { font-size: 0.8rem; }
  .libre { background: var(--libre); color: var(--libre-text); }
  .ocupada { background: var(--ocupada); color: var(--ocupada-text); }
  .encocina { background: var(--cocina); color: var(--cocina-text); }
  .precuenta { background: var(--precuenta); color: var(--precuenta-text); }
  .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 14px; margin-bottom: 12px; }
  .linea { display: flex; justify-content: space-between; gap: 10px; padding: 7px 0; border-bottom: 1px solid var(--border); }
  .linea:last-child { border-bottom: 0; }
  .muted { color: var(--muted); font-size: 0.85rem; }
  .chips { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; }
  .chip { flex: 0 0 auto; border: 1px solid var(--border); background: var(--card); color: var(--text); border-radius: 999px; padding: 8px 14px; font: inherit; font-size: 0.9rem; }
  .chip.on { background: var(--primary); color: var(--primary-text); border-color: var(--primary); }
  input[type=search], input[type=text] { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); font: inherit; }
  .productos { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-top: 10px; }
  .prod { border: 1px solid var(--border); background: var(--card); color: var(--text); border-radius: 12px; padding: 12px; text-align: left; font: inherit; min-height: 64px; }
  .prod span { display: block; font-size: 0.85rem; color: var(--muted); margin-top: 4px; }
  .nuevo { display: grid; grid-template-columns: 1fr auto; gap: 6px 10px; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border); }
  .qty { display: flex; align-items: center; gap: 6px; }
  .qty button { width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); font-size: 1.2rem; }
  .qty b { min-width: 24px; text-align: center; }
  .nota { grid-column: 1 / -1; }
  .nota input { padding: 8px 10px; font-size: 0.9rem; }
  .barra { position: fixed; left: 0; right: 0; bottom: 0; background: var(--card); border-top: 1px solid var(--border); padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); display: flex; gap: 10px; z-index: 6; }
  .barra button { flex: 1; padding: 15px; border-radius: 12px; border: 0; font: inherit; font-weight: 700; font-size: 1rem; }
  .enviar { background: var(--primary); color: var(--primary-text); }
  .enviar:disabled { opacity: 0.45; }
  .volver { background: var(--bg); color: var(--text); flex: 0 0 auto !important; padding: 15px 18px !important; }
  .pin { max-width: 320px; margin: 40px auto; text-align: center; }
  .pin .puntos { font-size: 2rem; letter-spacing: 0.4em; min-height: 3rem; margin: 10px 0 18px; }
  .teclado { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
  .teclado button { padding: 18px 0; font-size: 1.4rem; border-radius: 14px; border: 1px solid var(--border); background: var(--card); color: var(--text); }
  .error { color: #d23b2f; min-height: 1.4em; margin-top: 12px; }
  .toast { position: fixed; left: 50%; bottom: 96px; transform: translateX(-50%); background: #1f2633; color: #fff; padding: 12px 18px; border-radius: 10px; z-index: 9; font-size: 0.95rem; max-width: 90vw; text-align: center; }
  .oculto { display: none !important; }
</style>
</head>
<body>
<?php app_instalable_aviso('garzon'); ?>
<?php if (!$garzon): ?>
  <div class="pin">
    <h1 style="font-size: 1.3rem; margin-bottom: 0;"><?= htmlspecialchars($negocio ?: 'Minimarket POS') ?></h1>
    <p class="muted">Ingresa tu PIN de garzón</p>
    <div class="puntos" id="puntos"></div>
    <div class="teclado">
      <?php foreach ([1,2,3,4,5,6,7,8,9] as $n): ?><button type="button" onclick="tecla('<?= $n ?>')"><?= $n ?></button><?php endforeach; ?>
      <button type="button" onclick="tecla('borrar')">⌫</button>
      <button type="button" onclick="tecla('0')">0</button>
      <button type="button" onclick="tecla('ok')" style="background: var(--primary); color: var(--primary-text); border-color: var(--primary);">OK</button>
    </div>
    <div class="error" id="errorPin"></div>
  </div>
  <script>
    let pin = '';
    function tecla(t) {
      if (t === 'borrar') pin = pin.slice(0, -1);
      else if (t === 'ok') return entrar();
      else if (pin.length < 8) pin += t;
      document.getElementById('puntos').textContent = '•'.repeat(pin.length);
    }
    async function entrar() {
      const res = await fetch('api/mesas.php?action=login_pin', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ pin }) });
      const d = await res.json();
      if (d.success) location.reload();
      else { document.getElementById('errorPin').textContent = d.error || 'PIN incorrecto'; pin = ''; tecla(''); }
    }
  </script>
<?php else: ?>
  <header>
    <h1 id="titulo">Mesas</h1>
    <span style="font-size: 0.85rem; opacity: 0.85;"><?= htmlspecialchars($garzon['nombre']) ?></span>
    <button type="button" onclick="salir()">Salir</button>
  </header>

  <main>
    <section id="vistaMesas"><div id="zonas" class="muted">Cargando mesas…</div></section>

    <section id="vistaMesa" class="oculto">
      <div class="card" id="cuentaActual"></div>
      <div class="card" id="cardNuevo">
        <div style="display: flex; justify-content: space-between; align-items: baseline;">
          <b>Pedido nuevo</b><span class="muted" id="totalNuevo"></span>
        </div>
        <div id="listaNuevo"><p class="muted">Toca los productos para agregarlos.</p></div>
      </div>
      <input type="search" id="buscar" placeholder="Buscar producto…" oninput="pintarProductos()">
      <div class="chips" id="categorias" style="margin-top: 10px;"></div>
      <div class="productos" id="productos"></div>
    </section>
  </main>

  <div class="barra oculto" id="barra">
    <button type="button" class="volver" onclick="verMesas()">← Mesas</button>
    <button type="button" class="enviar" id="btnEnviar" onclick="enviar()" disabled>Enviar a cocina</button>
  </div>

  <script>
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    let productos = [], categoria = '', mesaActual = null, nuevo = [], refresco = null;
    const $ = id => document.getElementById(id);
    const fmt = n => '$' + Math.round(n).toLocaleString('es-CL');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function aviso(msg) {
      const t = document.createElement('div'); t.className = 'toast'; t.textContent = msg;
      document.body.appendChild(t); setTimeout(() => t.remove(), 2600);
    }
    async function api(action, opts = {}) {
      const res = await fetch('api/mesas.php?action=' + action, opts);
      const d = await res.json();
      if (d.sin_sesion) { location.reload(); throw new Error(d.error); }
      if (!d.success) throw new Error(d.error || 'Error');
      return d;
    }
    async function salir() { await api('logout', { method: 'POST' }); location.reload(); }

    // ---------- Mesas
    async function cargarMesas() {
      try {
        const d = await api('mesas');
        if (!d.mesas.length) { $('zonas').innerHTML = '<p class="muted">No hay mesas creadas. Pídele al administrador que las configure.</p>'; return; }
        const zonas = {};
        d.mesas.forEach(m => (zonas[m.Zona] = zonas[m.Zona] || []).push(m));
        $('zonas').innerHTML = Object.entries(zonas).map(([z, ms]) => `
          <h2>${esc(z)}</h2>
          <div class="grid">${ms.map(m => {
            const esLibre = !m.PedidoID;
            const esPrecuenta = +m.PrecuentaImpresa > 0;
            const esCocina = +m.EnCocina > 0;
            let clase = 'libre';
            let info = 'Libre';
            if (!esLibre) {
              if (esPrecuenta) {
                clase = 'precuenta';
                info = '🧾 ' + fmt(m.Total) + (m.MinutosOcupada ? ` · ${m.MinutosOcupada}m` : '');
              } else if (esCocina) {
                clase = 'encocina';
                info = '🔥 ' + fmt(m.Total) + (m.MinutosOcupada ? ` · ${m.MinutosOcupada}m` : '');
              } else {
                clase = 'ocupada';
                info = fmt(m.Total) + (m.MinutosOcupada ? ` · ${m.MinutosOcupada}m` : '');
              }
            }
            return `<button class="mesa ${clase}" onclick="abrirMesa(${m.MesaID})"><b>${esc(m.Nombre)}</b><small>${info}</small></button>`;
          }).join('')}</div>`).join('');
      } catch (e) { $('zonas').innerHTML = `<p class="error">${esc(e.message)}</p>`; }
    }
    function verMesas() {
      if (nuevo.length && !confirm('Hay productos sin enviar. ¿Volver igual y descartarlos?')) return;
      nuevo = []; mesaActual = null;
      $('vistaMesa').classList.add('oculto'); $('barra').classList.add('oculto'); $('vistaMesas').classList.remove('oculto');
      $('titulo').textContent = 'Mesas';
      cargarMesas();
    }

    // ---------- Una mesa
    async function abrirMesa(id) {
      if (!productos.length) {
        const d = await api('productos'); productos = d.productos;
        const cats = [...new Set(productos.map(p => p.Categoria))];
        $('categorias').innerHTML = ['Todas', ...cats].map(c => `<button class="chip ${c === 'Todas' ? 'on' : ''}" data-cat="${esc(c === 'Todas' ? '' : c)}" onclick="filtrar(this)">${esc(c)}</button>`).join('');
      }
      mesaActual = id; nuevo = [];
      $('vistaMesas').classList.add('oculto'); $('vistaMesa').classList.remove('oculto'); $('barra').classList.remove('oculto');
      await cargarCuenta();
      pintarNuevo(); pintarProductos();
      window.scrollTo(0, 0);
    }
    async function cargarCuenta() {
      const d = await api('pedido&mesa_id=' + mesaActual);
      $('titulo').textContent = d.mesa.Nombre;
      const esPrecuenta = +d.mesa.PrecuentaImpresa > 0;
      const propinaSugerida = Math.round(d.total * 0.1);
      const totalConPropina = d.total + propinaSugerida;

      $('cuentaActual').innerHTML = d.lineas.length
        ? `<div style="display:flex; justify-content:space-between; align-items:baseline;">
             <div>
               <b>Cuenta de la mesa</b>
               <div class="muted" style="font-size:0.8rem; margin-top:2px;">
                 ${d.mesa.Garzon ? 'Atiende: ' + esc(d.mesa.Garzon) : ''}
                 ${d.mesa.MinutosOcupada ? ' · 🕒 ' + d.mesa.MinutosOcupada + ' min' : ''}
                 ${esPrecuenta ? ' · <span style="color:#d97706; font-weight:bold;">🧾 Pre-cuenta emitida</span>' : ''}
               </div>
             </div>
             <div style="text-align:right;">
               <b style="font-size:1.15rem;">${fmt(d.total)}</b>
               <div class="muted" style="font-size:0.75rem;">+10% prop: ${fmt(totalConPropina)}</div>
             </div>
           </div>
           <div style="display:flex; gap:8px; margin:10px 0 6px;">
             <button type="button" class="chip" onclick="garzonPedirPrecuenta(${mesaActual})" style="font-size:0.82rem; padding:6px 12px; background:var(--precuenta); color:var(--precuenta-text); border-color:#f59e0b;">
               🧾 Pre-cuenta
             </button>
             <button type="button" class="chip" onclick="garzonCambiarMesa(${mesaActual})" style="font-size:0.82rem; padding:6px 12px;">
               ⇄ Cambiar mesa
             </button>
           </div>` +
          d.lineas.map(l => `<div class="linea"><div>${+l.Cantidad} × ${esc(l.Nombre)}${l.Nota ? `<div class="muted">${esc(l.Nota)}</div>` : ''}</div>
            <div class="muted" style="text-align:right;">${l.EstadoCocina === 'Lista' ? '✓ lista' : 'en cocina'}<br>${l.FechaComanda.slice(11, 16)}</div></div>`).join('')
        : '<b>Mesa libre</b><p class="muted" style="margin: 4px 0 0;">Lo que envíes abrirá la cuenta.</p>';
    }

    async function garzonPedirPrecuenta(mesaID) {
      try {
        const d = await api('precuenta&mesa_id=' + mesaID);
        aviso(`🧾 Pre-cuenta emitida. Consumo: ${fmt(d.total_consumo)} (Total c/propina 10%: ${fmt(d.total_con_propina)})`);
        await cargarCuenta();
      } catch (e) {
        aviso('Error: ' + e.message);
      }
    }

    async function garzonCambiarMesa(mesaID) {
      try {
        const d = await api('mesas');
        const libres = d.mesas.filter(m => !m.PedidoID && m.MesaID != mesaID);
        if (!libres.length) {
          aviso('No hay mesas libres disponibles para trasladar la cuenta.');
          return;
        }
        const opciones = libres.map((m, i) => `${i + 1}. ${m.Nombre} (${m.Zona})`).join('\n');
        const seleccion = prompt(`Elige la nueva mesa destino ingresando el número:\n\n${opciones}`);
        if (!seleccion) return;
        const idx = parseInt(seleccion) - 1;
        if (isNaN(idx) || idx < 0 || idx >= libres.length) {
          aviso('Selección no válida.');
          return;
        }
        const destino = libres[idx];
        const res = await api('cambiar_mesa', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
          body: JSON.stringify({ origen_mesa_id: mesaID, destino_mesa_id: destino.MesaID })
        });
        aviso(res.mensaje || 'Mesa cambiada ✓');
        mesaActual = destino.MesaID;
        await cargarCuenta();
      } catch (e) {
        aviso('Error: ' + e.message);
      }
    }
    function filtrar(btn) {
      document.querySelectorAll('.chip').forEach(c => c.classList.remove('on')); btn.classList.add('on');
      categoria = btn.dataset.cat; pintarProductos();
    }
    function pintarProductos() {
      const q = $('buscar').value.trim().toLowerCase();
      const lista = productos.filter(p => (!categoria || p.Categoria === categoria) && (!q || p.Nombre.toLowerCase().includes(q))).slice(0, 80);
      $('productos').innerHTML = lista.map(p => `<button class="prod" onclick="agregar(${p.ProductoID})">${esc(p.Nombre)}<span>${fmt(p.PrecioVenta)}</span></button>`).join('')
        || '<p class="muted">Sin resultados.</p>';
    }
    function agregar(id) {
      const p = productos.find(x => x.ProductoID == id);
      const linea = nuevo.find(n => n.producto_id == id && !n.nota);
      if (linea) linea.cantidad++; else nuevo.push({ producto_id: id, nombre: p.Nombre, precio: +p.PrecioVenta, cantidad: 1, nota: '' });
      pintarNuevo();
      if (navigator.vibrate) navigator.vibrate(15);
    }
    function cambiar(i, delta) {
      nuevo[i].cantidad += delta;
      if (nuevo[i].cantidad <= 0) nuevo.splice(i, 1);
      pintarNuevo();
    }
    function pintarNuevo() {
      const total = nuevo.reduce((s, n) => s + n.precio * n.cantidad, 0);
      $('totalNuevo').textContent = nuevo.length ? fmt(total) : '';
      $('btnEnviar').disabled = !nuevo.length;
      $('btnEnviar').textContent = nuevo.length ? `Enviar a cocina (${nuevo.reduce((s, n) => s + n.cantidad, 0)})` : 'Enviar a cocina';
      $('listaNuevo').innerHTML = nuevo.length ? nuevo.map((n, i) => `
        <div class="nuevo">
          <div>${esc(n.nombre)}<div class="muted">${fmt(n.precio * n.cantidad)}</div></div>
          <div class="qty"><button onclick="cambiar(${i}, -1)">−</button><b>${n.cantidad}</b><button onclick="cambiar(${i}, 1)">+</button></div>
          <div class="nota"><input type="text" maxlength="150" placeholder="Nota (ej: sin cebolla)" value="${esc(n.nota)}" oninput="nuevo[${i}].nota = this.value"></div>
        </div>`).join('') : '<p class="muted">Toca los productos para agregarlos.</p>';
    }
    async function enviar() {
      const btn = $('btnEnviar'); btn.disabled = true; btn.textContent = 'Enviando…';
      try {
        await api('enviar', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
          body: JSON.stringify({ mesa_id: mesaActual, items: nuevo.map(n => ({ producto_id: n.producto_id, cantidad: n.cantidad, nota: n.nota })) }) });
        aviso('Pedido enviado a cocina ✓');
        nuevo = [];
        await cargarCuenta(); pintarNuevo();
      } catch (e) {
        aviso('No se envió: ' + e.message); pintarNuevo();
      }
    }

    cargarMesas();
    refresco = setInterval(() => { if (!mesaActual) cargarMesas(); }, 10000);
  </script>
<?php endif; ?>
</body>
</html>
