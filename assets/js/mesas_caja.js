// Módulo "Mesas y garzones" en la caja (POS): cobrar cuentas de mesas e imprimir comandas.
// Se carga solo si la licencia tiene el módulo. Usa cart, renderCart, toast, formatNumber,
// escapeHtml y mesaPedidoActivo definidos en pos.js.
(function () {
  const api = async (action, opts = {}) => {
    const res = await fetch('api/mesas.php?action=' + action, opts);
    const d = await res.json();
    if (!d.success) throw new Error(d.error || 'Error');
    return d;
  };
  const post = (action, body) => api(action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
    body: JSON.stringify(body || {}),
  });

  // ---------------------------------------------------------------- Modal de mesas
  window.abrirModalMesas = async function () {
    const modal = document.getElementById('mesasModal');
    const lista = document.getElementById('mesasLista');
    modal.style.display = 'flex';
    lista.innerHTML = '<p style="text-align:center; color: var(--text-muted);">Cargando mesas…</p>';
    try {
      const d = await api('mesas');
      const abiertas = d.mesas.filter(m => m.PedidoID);
      const libres = d.mesas.length - abiertas.length;
      if (!d.mesas.length) {
        lista.innerHTML = '<p style="text-align:center; color: var(--text-muted);">No hay mesas creadas. Créalas en Configuración → Mesas y garzones.</p>';
        return;
      }
      lista.innerHTML = (abiertas.length ? abiertas.map(m => `
        <div style="background: rgba(15,23,42,0.35); border: 1px solid var(--border-dark); padding: 0.75rem 1rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
          <div>
            <strong>${escapeHtml(m.Nombre)}</strong> <span style="color: var(--text-muted); font-size: 0.8rem;">${escapeHtml(m.Zona)}</span>
            <div style="font-size: 0.85rem; color: var(--text-muted);">$${formatNumber(m.Total)} · desde ${String(m.FechaApertura).slice(11, 16)}${+m.EnCocina ? ' · ' + m.EnCocina + ' comanda(s) en cocina' : ''}</div>
          </div>
          <div style="display: flex; gap: 0.35rem;">
            <button class="btn btn-secondary" style="padding: 0.4rem 0.6rem; font-size: 0.8rem;" onclick="reimprimirComandasMesa(${m.PedidoID})" title="Reimprimir comandas"><i class="fa-solid fa-print"></i></button>
            ${['Administrador', 'Supervisor'].includes(window.CURRENT_USER_ROL) ? `<button class="btn btn-secondary" style="padding: 0.4rem 0.6rem; font-size: 0.8rem; color: var(--danger);" onclick="anularCuentaMesa(${m.PedidoID}, '${escapeHtml(m.Nombre).replace(/'/g, '')}')" title="Anular cuenta"><i class="fa-solid fa-ban"></i></button>` : ''}
            <button class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;" onclick="cobrarMesa(${m.PedidoID})"><i class="fa-solid fa-cash-register"></i> Cobrar</button>
          </div>
        </div>`).join('') : '<p style="text-align:center; color: var(--text-muted);">No hay mesas con cuenta abierta.</p>')
        + `<p style="text-align:center; color: var(--text-muted); font-size: 0.8rem; margin-top: 0.5rem;">${libres} mesa(s) libre(s)</p>`;
    } catch (e) {
      lista.innerHTML = `<p style="color: var(--danger);">${escapeHtml(e.message)}</p>`;
    }
  };
  window.cerrarModalMesas = () => { document.getElementById('mesasModal').style.display = 'none'; };

  window.cobrarMesa = async function (pedidoID) {
    if (cart.length && !(await confirmDialog({ title: 'Cobrar mesa', message: 'El carrito tiene productos. ¿Reemplazarlos por la cuenta de la mesa?', confirmText: 'Reemplazar' }))) return;
    try {
      const d = await api('para_cobrar&pedido_id=' + pedidoID);
      cart = d.items.map(i => ({
        ProductoID: i.ProductoID,
        Nombre: i.Nombre,
        CodigoBarras: i.CodigoBarras,
        PrecioVenta: parseInt(i.PrecioVenta),
        Stock: parseFloat(i.Stock),
        EsPesable: i.EsPesable,
        CategoriaID: i.CategoriaID,
        cantidad: parseFloat(i.Cantidad),
      }));
      cotizacionActiva = null;
      mesaPedidoActivo = { id: d.pedido.PedidoID, mesa: d.pedido.Mesa };
      actualizarBannerMesa();
      renderCart();
      cerrarModalMesas();
      toast(`Cuenta de ${d.pedido.Mesa} cargada. Cobra como una venta normal.`, 'success');
    } catch (e) {
      toast(e.message, 'error');
    }
  };

  window.anularCuentaMesa = async function (pedidoID, nombre) {
    const ok = await confirmDialog({ title: 'Anular cuenta', message: `¿Anular la cuenta de ${nombre} sin cobrarla? La mesa quedará libre.`, confirmText: 'Anular', danger: true });
    if (!ok) return;
    try {
      await post('anular_cuenta', { pedido_id: pedidoID });
      toast(`Cuenta de ${nombre} anulada.`, 'info');
      abrirModalMesas();
    } catch (e) { toast(e.message, 'error'); }
  };

  window.actualizarBannerMesa = function () {
    let b = document.getElementById('bannerMesa');
    if (!mesaPedidoActivo) { if (b) b.remove(); return; }
    if (!b) {
      b = document.createElement('div');
      b.id = 'bannerMesa';
      b.style.cssText = 'position: fixed; top: 76px; left: 50%; transform: translateX(-50%); z-index: 90; background: #f59e0b; color: #1f1300; padding: 0.45rem 1rem; border-radius: 999px; font-weight: 700; font-size: 0.9rem; box-shadow: 0 6px 18px rgba(0,0,0,0.25); display: flex; gap: 0.6rem; align-items: center;';
      document.body.appendChild(b);
    }
    b.innerHTML = `<i class="fa-solid fa-utensils"></i> Cobrando ${escapeHtml(mesaPedidoActivo.mesa)}
      <button type="button" style="background: rgba(0,0,0,0.15); border: 0; border-radius: 999px; padding: 0.1rem 0.5rem; cursor: pointer; font-weight: 700;" title="Dejar de cobrar esta mesa" onclick="soltarMesa()">✕</button>`;
  };
  window.soltarMesa = function () {
    mesaPedidoActivo = null;
    cart = [];
    renderCart();
    actualizarBannerMesa();
    toast('La mesa sigue abierta; puedes cobrarla después.', 'info');
  };

  // ---------------------------------------------------------------- Comandas impresas en caja
  function htmlComandas(comandas) {
    const filas = comandas.map(c => `
      <div class="c">
        <div class="t">${escapeHtml(c.Mesa)}</div>
        <div class="m">${escapeHtml(c.Zona)} · ${String(c.Fecha).slice(11, 16)} · ${escapeHtml(c.Garzon)} · #${c.ComandaID}</div>
        ${c.items.map(i => `<div class="i"><b>${+i.cantidad} x</b> ${escapeHtml(i.nombre)}${i.nota ? `<div class="n">&gt; ${escapeHtml(i.nota)}</div>` : ''}</div>`).join('')}
      </div>`).join('');
    return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Comanda</title><style>
      @page { size: 80mm auto; margin: 3mm; }
      body { font-family: Arial, sans-serif; width: 72mm; margin: 0; color: #000; }
      .c { page-break-after: always; padding-bottom: 6mm; }
      .t { font-size: 22pt; font-weight: 800; text-align: center; border-bottom: 2px dashed #000; padding-bottom: 2mm; }
      .m { font-size: 9pt; text-align: center; margin: 2mm 0 3mm; }
      .i { font-size: 13pt; padding: 1.5mm 0; border-bottom: 1px dotted #999; }
      .n { font-size: 11pt; font-style: italic; margin-left: 6mm; }
    </style></head><body>${filas}</body></html>`;
  }
  function imprimirComandas(comandas) {
    if (!comandas.length) return;
    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'position: fixed; width: 0; height: 0; border: 0; right: 0; bottom: 0;';
    document.body.appendChild(iframe);
    iframe.onload = () => {
      try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) {}
      setTimeout(() => iframe.remove(), 60000);
    };
    iframe.srcdoc = htmlComandas(comandas);
  }
  function pitido() {
    try {
      const a = new (window.AudioContext || window.webkitAudioContext)();
      const o = a.createOscillator(), g = a.createGain();
      o.frequency.value = 880; o.connect(g); g.connect(a.destination); g.gain.value = 0.2;
      o.start(); o.stop(a.currentTime + 0.2);
    } catch (e) {}
  }

  window.reimprimirComandasMesa = async function (pedidoID) {
    try {
      const d = await api('reimprimir&pedido_id=' + pedidoID);
      imprimirComandas(d.comandas);
    } catch (e) { toast(e.message, 'error'); }
  };

  async function revisarComandasNuevas() {
    try {
      const d = await post('tomar_para_imprimir');
      if (!d.comandas.length) return;
      pitido();
      toast('Nueva comanda: ' + [...new Set(d.comandas.map(c => c.Mesa))].join(', '), 'info');
      if (window.MESAS_IMPRIMIR_COMANDA === 'SI') imprimirComandas(d.comandas);
    } catch (e) { /* sin conexión momentánea: se reintenta */ }
  }

  setInterval(revisarComandasNuevas, 8000);
  setTimeout(revisarComandasNuevas, 1500);
})();
