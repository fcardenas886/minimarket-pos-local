// Módulo "Mesas y garzones" en la caja (POS): Centro de mesas, visor de pedidos,
// pre-cuenta (Ley 20.918), división de cuentas, cambio de mesa y cobro rápido.
// Se carga solo si la licencia tiene el módulo. Usa cart, renderCart, toast, formatNumber,
// escapeHtml y mesaPedidoActivo definidos en pos.js.
(function () {
  let mesasCache = [];
  let zonaActual = 'Todas';
  let mesaSeleccionadaID = null;
  let pedidoDetalleActual = null;
  let divisionActual = { pedido_id: 0, partes: 2, nombre: '', data: null };
  let cambioMesaOrigenID = null;
  let productosParaMesaCache = null;
  let prodMesaElegido = null;

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

  // ---------------------------------------------------------------- Actualizar badges de mesas en el POS
  function actualizarBadgesMesasEnCaja(totalAbiertas) {
    const b1 = document.getElementById('badgeMesasAbiertas');
    const b2 = document.getElementById('badgeMesasAbiertasSuper');
    [b1, b2].forEach(b => {
      if (b) {
        if (totalAbiertas > 0) {
          b.textContent = totalAbiertas;
          b.style.display = 'inline-block';
        } else {
          b.style.display = 'none';
        }
      }
    });
  }

  // ---------------------------------------------------------------- Modal Centro de Mesas
  window.abrirModalMesas = async function () {
    const modal = document.getElementById('mesasModal');
    if (!modal) return;
    modal.style.display = 'flex';

    const grid = document.getElementById('mesasCardsGrid');
    if (grid) grid.innerHTML = '<p style="text-align:center; color: var(--text-muted); grid-column: 1/-1;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando mesas…</p>';

    try {
      const d = await api('mesas');
      mesasCache = d.mesas || [];

      // Conteo de semáforo
      const libres = mesasCache.filter(m => !m.PedidoID).length;
      const cocina = mesasCache.filter(m => m.PedidoID && +m.EnCocina > 0).length;
      const precuenta = mesasCache.filter(m => m.PedidoID && +m.PrecuentaImpresa > 0).length;
      const ocupadas = mesasCache.filter(m => m.PedidoID && !+m.EnCocina && !+m.PrecuentaImpresa).length;
      const totalAbiertas = mesasCache.filter(m => m.PedidoID).length;

      // Actualizar contadores del encabezado
      const elLibres = document.getElementById('cntMesasLibres');
      const elOcup = document.getElementById('cntMesasOcupadas');
      const elCoc = document.getElementById('cntMesasCocina');
      const elPre = document.getElementById('cntMesasPrecuenta');
      if (elLibres) elLibres.textContent = libres;
      if (elOcup) elOcup.textContent = ocupadas;
      if (elCoc) elCoc.textContent = cocina;
      if (elPre) elPre.textContent = precuenta;

      // Actualizar badge en la barra de caja
      actualizarBadgesMesasEnCaja(totalAbiertas);

      // Renderizar pestañas de zonas
      renderZonasTabs();

      // Renderizar grilla de tarjetas
      renderMesasCardsGrid();

      // Si había una mesa seleccionada abierta, refrescar su visor
      if (mesaSeleccionadaID) {
        const mesaExiste = mesasCache.find(m => m.MesaID == mesaSeleccionadaID && m.PedidoID);
        if (mesaExiste) {
          seleccionarMesa(mesaSeleccionadaID, false);
        } else {
          deseleccionarMesa();
        }
      } else {
        deseleccionarMesa();
      }

    } catch (e) {
      if (grid) grid.innerHTML = `<p style="color: var(--danger); grid-column: 1/-1;">Error al cargar mesas: ${escapeHtml(e.message)}</p>`;
    }
  };

  window.cerrarModalMesas = () => {
    const modal = document.getElementById('mesasModal');
    if (modal) modal.style.display = 'none';
  };

  function renderZonasTabs() {
    const tabsContainer = document.getElementById('mesasZonaFiltros');
    if (!tabsContainer) return;

    const zonas = ['Todas', ...new Set(mesasCache.map(m => m.Zona).filter(Boolean))];
    tabsContainer.innerHTML = zonas.map(z => `
      <button type="button" class="zone-tab-btn ${zonaActual === z ? 'active' : ''}" onclick="cambiarZonaFiltro('${escapeHtml(z)}')">
        ${escapeHtml(z)}
      </button>
    `).join('');
  }

  window.cambiarZonaFiltro = function (zona) {
    zonaActual = zona;
    renderZonasTabs();
    renderMesasCardsGrid();
  };

  window.filtrarMesasCaja = function () {
    renderMesasCardsGrid();
  };

  function renderMesasCardsGrid() {
    const grid = document.getElementById('mesasCardsGrid');
    if (!grid) return;

    const filtroTexto = (document.getElementById('filtroMesasInput')?.value || '').trim().toLowerCase();

    const mesasFiltradas = mesasCache.filter(m => {
      const matchZona = (zonaActual === 'Todas' || m.Zona === zonaActual);
      const matchTexto = !filtroTexto || 
        m.Nombre.toLowerCase().includes(filtroTexto) || 
        (m.Zona && m.Zona.toLowerCase().includes(filtroTexto)) ||
        (m.Garzon && m.Garzon.toLowerCase().includes(filtroTexto));
      return matchZona && matchTexto;
    });

    if (!mesasFiltradas.length) {
      grid.innerHTML = '<p style="text-align:center; color: var(--text-muted); grid-column: 1/-1; padding: 2rem;">No hay mesas que coincidan con el filtro.</p>';
      return;
    }

    grid.innerHTML = mesasFiltradas.map(m => {
      const esLibre = !m.PedidoID;
      const esPrecuenta = +m.PrecuentaImpresa > 0;
      const esCocina = +m.EnCocina > 0;
      const isSelected = mesaSeleccionadaID == m.MesaID;

      let cardClass = 'mesa-card ';
      let badgeHtml = '';
      let tiempoHtml = '';
      let totalHtml = '';

      if (esLibre) {
        cardClass += 'mesa-card-libre';
        badgeHtml = '<span style="color:#34d399; font-size:0.75rem; font-weight:700;"><i class="fa-solid fa-circle-check"></i> Libre</span>';
        totalHtml = '<span style="font-size:0.75rem; color:var(--text-muted);">Disponible</span>';
      } else if (esPrecuenta) {
        cardClass += 'mesa-card-precuenta';
        badgeHtml = '<span style="color:#facc15; font-size:0.75rem; font-weight:700;"><i class="fa-solid fa-receipt"></i> Pre-cuenta</span>';
        tiempoHtml = `<span style="font-size:0.75rem; color:#facc15;"><i class="fa-solid fa-clock"></i> ${m.MinutosOcupada || 0}m</span>`;
        totalHtml = `<span style="color:#facc15; font-weight:800; font-size:0.95rem;">$${formatNumber(m.Total)}</span>`;
      } else if (esCocina) {
        cardClass += 'mesa-card-cocina';
        badgeHtml = `<span style="color:#fb923c; font-size:0.75rem; font-weight:700;"><i class="fa-solid fa-fire"></i> Cocina (${m.EnCocina})</span>`;
        tiempoHtml = `<span style="font-size:0.75rem; color:#fb923c;"><i class="fa-solid fa-clock"></i> ${m.MinutosOcupada || 0}m</span>`;
        totalHtml = `<span style="color:#fb923c; font-weight:800; font-size:0.95rem;">$${formatNumber(m.Total)}</span>`;
      } else {
        cardClass += 'mesa-card-ocupada';
        badgeHtml = '<span style="color:#60a5fa; font-size:0.75rem; font-weight:700;"><i class="fa-solid fa-utensils"></i> Ocupada</span>';
        tiempoHtml = `<span style="font-size:0.75rem; color:#60a5fa;"><i class="fa-solid fa-clock"></i> ${m.MinutosOcupada || 0}m</span>`;
        totalHtml = `<span style="color:#60a5fa; font-weight:800; font-size:0.95rem;">$${formatNumber(m.Total)}</span>`;
      }

      if (isSelected) cardClass += ' selected';

      return `
        <div class="${cardClass}" onclick="clickMesaCard(${m.MesaID})">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.25rem;">
            <span style="font-size:0.72rem; color:var(--text-muted);">${escapeHtml(m.Zona)}</span>
            ${badgeHtml}
          </div>
          <div>
            <div style="font-size:1.05rem; font-weight:800; color:#fff;">${escapeHtml(m.Nombre)}</div>
            ${m.Garzon ? `<div style="font-size:0.75rem; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">👤 ${escapeHtml(m.Garzon)}</div>` : ''}
          </div>
          <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:0.4rem; border-top:1px solid rgba(255,255,255,0.06); padding-top:0.35rem;">
            ${tiempoHtml}
            ${totalHtml}
          </div>
        </div>
      `;
    }).join('');
  }

  window.clickMesaCard = function (mesaID) {
    const mesa = mesasCache.find(m => m.MesaID == mesaID);
    if (!mesa) return;

    if (mesa.PedidoID) {
      seleccionarMesa(mesaID, true);
    } else {
      seleccionarMesaLibre(mesaID);
    }
  };

  function deseleccionarMesa() {
    mesaSeleccionadaID = null;
    pedidoDetalleActual = null;
    const empty = document.getElementById('mesaDetailEmpty');
    const content = document.getElementById('mesaDetailContent');
    if (empty) empty.style.display = 'flex';
    if (content) content.style.display = 'none';
    renderMesasCardsGrid();
  }

  function seleccionarMesaLibre(mesaID) {
    mesaSeleccionadaID = mesaID;
    pedidoDetalleActual = null;
    renderMesasCardsGrid();

    const empty = document.getElementById('mesaDetailEmpty');
    const content = document.getElementById('mesaDetailContent');
    if (empty) empty.style.display = 'none';
    if (content) content.style.display = 'flex';

    const mesa = mesasCache.find(m => m.MesaID == mesaID);
    const header = document.getElementById('mesaDetailHeader');
    const items = document.getElementById('mesaDetailItems');
    const totals = document.getElementById('mesaDetailTotals');
    const actions = document.getElementById('mesaDetailActions');

    header.innerHTML = `
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:#fff;">${escapeHtml(mesa.Nombre)} <span style="font-size:0.85rem; font-weight:normal; color:var(--text-muted);">(${escapeHtml(mesa.Zona)})</span></h3>
          <span style="display:inline-block; margin-top:4px; font-size:0.8rem; background:rgba(16,185,129,0.15); color:#34d399; padding:2px 8px; border-radius:999px; font-weight:600;">🟢 Mesa Libre</span>
        </div>
      </div>
    `;

    items.innerHTML = `
      <div style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
        <i class="fa-solid fa-circle-check" style="font-size:3rem; color:#10b981; opacity:0.4; margin-bottom:0.75rem;"></i>
        <h4 style="color:#fff; margin-bottom:0.35rem;">Esta mesa está disponible</h4>
        <p style="font-size:0.85rem; max-width:300px; margin:0 auto 1.5rem;">Puedes abrir un pedido nuevo agregándole productos directamente desde la caja.</p>
        <button type="button" class="btn btn-primary" onclick="abrirModalAgregarProductoMesa(${mesa.MesaID}, '${escapeHtml(mesa.Nombre).replace(/'/g, '')}')" style="padding:0.6rem 1.2rem; font-size:0.9rem;">
          <i class="fa-solid fa-plus-circle"></i> Abrir Comanda para esta Mesa
        </button>
      </div>
    `;

    totals.innerHTML = '';
    actions.innerHTML = '';
  }

  // ---------------------------------------------------------------- Visor de Pedido de Mesa
  window.seleccionarMesa = async function (mesaID, doScroll = true) {
    mesaSeleccionadaID = mesaID;
    renderMesasCardsGrid();

    const empty = document.getElementById('mesaDetailEmpty');
    const content = document.getElementById('mesaDetailContent');
    if (empty) empty.style.display = 'none';
    if (content) content.style.display = 'flex';

    const header = document.getElementById('mesaDetailHeader');
    const items = document.getElementById('mesaDetailItems');
    const totals = document.getElementById('mesaDetailTotals');
    const actions = document.getElementById('mesaDetailActions');

    items.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Cargando comanda…</div>';

    try {
      const d = await api('pedido&mesa_id=' + mesaID);
      pedidoDetalleActual = d;

      // Encabezado
      const mesa = d.mesa;
      const esPrecuenta = +mesa.PrecuentaImpresa > 0;
      const enCocinaCant = d.lineas.filter(l => l.EstadoCocina === 'Pendiente').length;

      header.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
          <div>
            <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:#fff;">
              ${escapeHtml(mesa.Nombre)} 
              <span style="font-size:0.85rem; font-weight:normal; color:var(--text-muted);">(${escapeHtml(mesa.Zona)})</span>
            </h3>
            <div style="font-size:0.82rem; color:var(--text-muted); margin-top:3px; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
              <span>👤 Garzón: <b style="color:#fff;">${escapeHtml(mesa.Garzon)}</b></span>
              <span>•</span>
              <span>🕒 Abierta hace <b style="color:#fff;">${mesa.MinutosOcupada} min</b> (${String(mesa.FechaApertura).slice(11, 16)})</span>
            </div>
          </div>
          <div style="display:flex; gap:0.35rem; align-items:center;">
            ${esPrecuenta ? '<span class="semaforo-pill sem-precuenta" title="Pre-cuenta ya emitida"><i class="fa-solid fa-receipt"></i> Pre-cuenta emitida</span>' : ''}
            ${enCocinaCant > 0 ? `<span class="semaforo-pill sem-cocina" title="${enCocinaCant} producto(s) preparándose"><i class="fa-solid fa-fire"></i> ${enCocinaCant} en cocina</span>` : '<span class="semaforo-pill sem-libre" title="Todo servido">✓ Todo servido</span>'}
          </div>
        </div>
      `;

      // Lista de ítems pedidos
      if (!d.lineas.length) {
        items.innerHTML = '<p style="text-align:center; color:var(--text-muted); padding:2rem;">La cuenta está abierta pero aún no tiene productos registrados.</p>';
      } else {
        items.innerHTML = `
          <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-muted); font-weight:700; margin-bottom:0.5rem;">
            Productos en comanda (${d.lineas.length} ítems):
          </div>
          ${d.lineas.map(l => {
            const enPrep = l.EstadoCocina === 'Pendiente';
            const badgeCocina = enPrep
              ? `<span style="background:rgba(249,115,22,0.18); color:#fb923c; border:1px solid rgba(249,115,22,0.3); border-radius:6px; padding:2px 6px; font-size:0.72rem; font-weight:600;"><i class="fa-solid fa-clock"></i> En cocina (${String(l.FechaComanda).slice(11, 16)})</span>`
              : `<span style="background:rgba(16,185,129,0.18); color:#34d399; border:1px solid rgba(16,185,129,0.3); border-radius:6px; padding:2px 6px; font-size:0.72rem; font-weight:600;"><i class="fa-solid fa-check"></i> Listo (${String(l.FechaComanda).slice(11, 16)})</span>`;

            return `
              <div class="detail-item-row">
                <div style="flex:1;">
                  <div style="display:flex; align-items:baseline; gap:0.4rem;">
                    <strong style="color:#60a5fa; font-size:0.95rem;">${+l.Cantidad}×</strong>
                    <span style="font-weight:600; color:#fff;">${escapeHtml(l.Nombre)}</span>
                  </div>
                  ${l.Nota ? `<div style="font-size:0.78rem; color:#f59e0b; font-style:italic; margin-top:2px; margin-left:1.4rem;"><i class="fa-solid fa-comment-dots"></i> ${escapeHtml(l.Nota)}</div>` : ''}
                  <div style="margin-top:4px; margin-left:1.4rem;">
                    ${badgeCocina}
                  </div>
                </div>
                <div style="text-align:right;">
                  <div style="font-weight:700; font-size:0.95rem; color:#fff;">$${formatNumber(l.Subtotal)}</div>
                  <div style="font-size:0.75rem; color:var(--text-muted);">$${formatNumber(l.PrecioUnitario)} c/u</div>
                </div>
              </div>
            `;
          }).join('')}
        `;
      }

      // Totales
      const totalConsumo = d.total;
      const propinaSugerida = Math.round(totalConsumo * 0.1);
      const totalConPropina = totalConsumo + propinaSugerida;

      totals.innerHTML = `
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:0.25rem;">
          <span style="color:var(--text-muted);">Subtotal Consumo:</span>
          <strong style="color:#fff;">$${formatNumber(totalConsumo)}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:0.35rem; color:#f59e0b;">
          <span>Propina Sugerida (10% Ley 20.918):</span>
          <strong>+$${formatNumber(propinaSugerida)}</strong>
        </div>
        <div style="border-top:1px dashed var(--border-dark); padding-top:0.4rem; display:flex; justify-content:space-between; align-items:baseline;">
          <span style="font-weight:700; font-size:0.95rem; color:#fff;">TOTAL CON PROPINA:</span>
          <span style="font-size:1.35rem; font-weight:800; color:#10b981;">$${formatNumber(totalConPropina)}</span>
        </div>
      `;

      // Barra de Acciones
      const mesaEsc = escapeHtml(mesa.Nombre).replace(/'/g, '');
      const esAdmin = ['Administrador', 'Supervisor'].includes(window.CURRENT_USER_ROL);

      actions.innerHTML = `
        <button type="button" class="btn btn-secondary" onclick="imprimirPrecuenta(${mesa.PedidoID})" title="Emitir comprobante no fiscal con propina del 10%" style="padding:0.45rem 0.75rem; font-size:0.82rem; color:#f59e0b;">
          <i class="fa-solid fa-receipt"></i> Pre-cuenta
        </button>
        <button type="button" class="btn btn-secondary" onclick="abrirModalDividirCuenta(${mesa.PedidoID}, '${mesaEsc}')" title="Dividir cuenta por N personas" style="padding:0.45rem 0.75rem; font-size:0.82rem; color:#60a5fa;">
          <i class="fa-solid fa-divide"></i> Dividir
        </button>
        <button type="button" class="btn btn-secondary" onclick="abrirModalCambiarMesa(${mesa.MesaID}, '${mesaEsc}')" title="Trasladar cuenta a otra mesa desocupada" style="padding:0.45rem 0.75rem; font-size:0.82rem;">
          <i class="fa-solid fa-arrows-split-up-and-left"></i> Cambiar
        </button>
        <button type="button" class="btn btn-secondary" onclick="abrirModalAgregarProductoMesa(${mesa.MesaID}, '${mesaEsc}')" title="Agregar producto a la comanda sin tocar el carrito de caja" style="padding:0.45rem 0.75rem; font-size:0.82rem; color:#34d399;">
          <i class="fa-solid fa-plus-circle"></i> Agregar
        </button>
        <button type="button" class="btn btn-secondary" onclick="reimprimirComandasMesa(${mesa.PedidoID})" title="Reimprimir comandas de cocina" style="padding:0.45rem 0.65rem; font-size:0.82rem;">
          <i class="fa-solid fa-print"></i>
        </button>
        ${esAdmin ? `
          <button type="button" class="btn btn-secondary" onclick="anularCuentaMesa(${mesa.PedidoID}, '${mesaEsc}')" title="Anular cuenta de la mesa" style="padding:0.45rem 0.65rem; font-size:0.82rem; color:var(--danger);">
            <i class="fa-solid fa-ban"></i>
          </button>
        ` : ''}
        <button type="button" class="btn btn-primary" onclick="cobrarMesa(${mesa.PedidoID})" style="padding:0.45rem 1rem; font-size:0.85rem; font-weight:700; margin-left:auto;">
          <i class="fa-solid fa-cash-register"></i> Cargar y Cobrar
        </button>
      `;

    } catch (e) {
      items.innerHTML = `<p style="color:var(--danger); padding:1.5rem;">Error al cargar detalle: ${escapeHtml(e.message)}</p>`;
    }
  };

  // ---------------------------------------------------------------- 1.B Pre-Cuenta Térmica (Ley 20.918)
  function htmlTicketPrecuenta(d) {
    const local = d.local || {};
    const ped = d.pedido || {};
    const lineas = d.lineas || [];

    const itemsHtml = lineas.map(l => `
      <tr>
        <td style="text-align: left; vertical-align: top; padding: 2px 0;">
          <b>${+l.Cantidad}x</b> ${escapeHtml(l.Nombre)}
          ${l.Nota ? `<div style="font-size: 8pt; font-style: italic; margin-left: 8px;">&gt; ${escapeHtml(l.Nota)}</div>` : ''}
        </td>
        <td style="text-align: right; vertical-align: top; padding: 2px 0; white-space: nowrap;">
          $${formatNumber(l.Subtotal)}
        </td>
      </tr>
    `).join('');

    return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Pre-cuenta</title><style>
      @page { size: 80mm auto; margin: 3mm; }
      body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; width: 72mm; margin: 0 auto; color: #000; font-size: 10pt; line-height: 1.25; }
      .center { text-align: center; }
      .right { text-align: right; }
      .bold { font-weight: bold; }
      .titulo { font-size: 14pt; font-weight: 800; margin-bottom: 2mm; }
      .subtitulo { font-size: 9pt; border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 2mm 0; margin: 2mm 0; }
      .tabla-items { width: 100%; border-collapse: collapse; margin: 3mm 0; font-size: 9.5pt; }
      .totales { border-top: 1px dashed #000; padding-top: 2mm; margin-top: 2mm; }
      .totales-row { display: flex; justify-content: space-between; margin-bottom: 1.5mm; font-size: 10pt; }
      .total-destacado { font-size: 13pt; font-weight: 800; border-top: 1px solid #000; border-bottom: 2px solid #000; padding: 2mm 0; margin: 2mm 0; }
      .pie { font-size: 8pt; text-align: center; margin-top: 4mm; color: #333; line-height: 1.3; }
    </style></head><body>
      <div class="center titulo">${escapeHtml(local.nombre || 'PRE-CUENTA')}</div>
      ${local.rut ? `<div class="center" style="font-size: 8.5pt;">RUT: ${escapeHtml(local.rut)}</div>` : ''}
      ${local.direccion ? `<div class="center" style="font-size: 8.5pt;">${escapeHtml(local.direccion)}</div>` : ''}
      ${local.telefono ? `<div class="center" style="font-size: 8.5pt;">Tel: ${escapeHtml(local.telefono)}</div>` : ''}

      <div class="center subtitulo bold">
        *** PRE-CUENTA ***<br>
        <span style="font-size: 8pt; font-weight: normal;">DOCUMENTO NO VÁLIDO COMO BOLETA</span>
      </div>

      <div style="font-size: 9pt; margin-bottom: 2mm;">
        <div><b>Mesa:</b> ${escapeHtml(ped.Mesa || '')} (${escapeHtml(ped.Zona || '')})</div>
        <div><b>Atiende:</b> ${escapeHtml(ped.Garzon || 'Caja')}</div>
        <div><b>Fecha:</b> ${String(d.fecha_impresion || '').slice(0, 16)} · Pedido #${ped.PedidoID}</div>
      </div>

      <table class="tabla-items">
        <thead>
          <tr style="border-bottom: 1px dotted #999;">
            <th style="text-align: left; padding-bottom: 1.5mm;">DETALLE</th>
            <th style="text-align: right; padding-bottom: 1.5mm;">TOTAL</th>
          </tr>
        </thead>
        <tbody>${itemsHtml}</tbody>
      </table>

      <div class="totales">
        <div class="totales-row">
          <span>Subtotal Consumo:</span>
          <b>$${formatNumber(d.total_consumo)}</b>
        </div>
        <div class="totales-row">
          <span>Propina Sugerida (${d.porcentaje_propina}% Ley 20.918):</span>
          <b>+$${formatNumber(d.propina_sugerida)}</b>
        </div>
        <div class="totales-row total-destacado">
          <span>TOTAL C/PROPINA:</span>
          <span>$${formatNumber(d.total_con_propina)}</span>
        </div>
        <div class="center" style="font-size: 8pt; font-style: italic; margin-top: 1mm;">
          La propina del 10% es voluntaria y sugerida.
        </div>
      </div>

      <div class="pie">
        ¡Muchas gracias por su visita!<br>
        Por favor solicite su Boleta Electrónica al pagar en caja.
      </div>
    </body></html>`;
  }

  function imprimirTicketIframe(htmlContent) {
    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'position: fixed; width: 0; height: 0; border: 0; right: 0; bottom: 0;';
    document.body.appendChild(iframe);
    iframe.onload = () => {
      try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) {}
      setTimeout(() => iframe.remove(), 60000);
    };
    iframe.srcdoc = htmlContent;
  }

  window.imprimirPrecuenta = async function (pedidoID) {
    try {
      toast('Generando pre-cuenta térmica…', 'info');
      const d = await api('precuenta&pedido_id=' + pedidoID);
      imprimirTicketIframe(htmlTicketPrecuenta(d));
      toast('Pre-cuenta impresa con éxito ✓', 'success');

      // Actualizar tarjeta en grilla y visor
      await abrirModalMesas();
      if (mesaSeleccionadaID) seleccionarMesa(mesaSeleccionadaID, false);
    } catch (e) {
      toast('Error al emitir pre-cuenta: ' + e.message, 'error');
    }
  };

  // ---------------------------------------------------------------- 1.D División de Cuentas
  window.abrirModalDividirCuenta = async function (pedidoID, nombreMesa) {
    divisionActual = { pedido_id: pedidoID, partes: 2, nombre: nombreMesa, data: null };
    const modal = document.getElementById('modalDividirCuenta');
    const nom = document.getElementById('dividirMesaNombre');
    if (nom) nom.textContent = nombreMesa;
    if (modal) modal.style.display = 'flex';
    await cargarCalculoDivision();
  };

  window.cerrarModalDividirCuenta = function () {
    const modal = document.getElementById('modalDividirCuenta');
    if (modal) modal.style.display = 'none';
  };

  window.ajustarPartesDivision = async function (delta) {
    divisionActual.partes = Math.max(2, Math.min(20, divisionActual.partes + delta));
    await cargarCalculoDivision();
  };

  window.setPartesDivision = async function (partes) {
    divisionActual.partes = partes;
    await cargarCalculoDivision();
  };

  async function cargarCalculoDivision() {
    try {
      const d = await api(`dividir_cuenta&pedido_id=${divisionActual.pedido_id}&partes=${divisionActual.partes}`);
      divisionActual.data = d;

      const numEl = document.getElementById('dividirNumPartes');
      const totMesa = document.getElementById('divTotalConsumoMesa');
      const conPer = document.getElementById('divConsumoPorPersona');
      const propPer = document.getElementById('divPropinaPorPersona');
      const totPer = document.getElementById('divTotalPorPersona');
      const lista = document.getElementById('divListaPersonas');

      if (numEl) numEl.textContent = d.partes;
      if (totMesa) totMesa.textContent = '$' + formatNumber(d.total_consumo);
      if (conPer) conPer.textContent = '$' + formatNumber(d.distribucion[0]?.consumo || 0);
      if (propPer) propPer.textContent = '+$' + formatNumber(d.distribucion[0]?.propina_sugerida || 0);
      if (totPer) totPer.textContent = '$' + formatNumber(d.distribucion[0]?.total_con_propina || 0);

      if (lista) {
        lista.innerHTML = d.distribucion.map(p => `
          <div style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid rgba(255,255,255,0.06);">
            <span>Persona ${p.persona}:</span>
            <span>$${formatNumber(p.consumo)} + prop. $${formatNumber(p.propina_sugerida)} = <b style="color:#10b981;">$${formatNumber(p.total_con_propina)}</b></span>
          </div>
        `).join('');
      }
    } catch (e) {
      toast('Error al dividir cuenta: ' + e.message, 'error');
    }
  }

  window.imprimirTicketDivision = function () {
    if (!divisionActual.data) return;
    const d = divisionActual.data;
    const filas = d.distribucion.map(p => `
      <tr>
        <td style="padding: 2px 0;">Persona #${p.persona}:</td>
        <td style="text-align: right; padding: 2px 0;">$${formatNumber(p.consumo)} (prop: $${formatNumber(p.propina_sugerida)}) = <b>$${formatNumber(p.total_con_propina)}</b></td>
      </tr>
    `).join('');

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>División de Cuenta</title><style>
      @page { size: 80mm auto; margin: 3mm; }
      body { font-family: Arial, sans-serif; width: 72mm; margin: 0 auto; color: #000; font-size: 10pt; line-height: 1.3; }
      .center { text-align: center; }
      .bold { font-weight: bold; }
    </style></head><body>
      <div class="center bold" style="font-size: 13pt; margin-bottom: 2mm;">DIVISIÓN DE CUENTA</div>
      <div class="center" style="font-size: 10pt; margin-bottom: 2mm;">${escapeHtml(divisionActual.nombre)}</div>
      <div style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 2mm 0; margin-bottom: 2mm;">
        <div>Total Mesa: <b>$${formatNumber(d.total_consumo)}</b></div>
        <div>Propina sugerida (${d.porcentaje_propina}%): <b>+$${formatNumber(d.propina_total)}</b></div>
        <div>Dividido en: <b>${d.partes} personas</b></div>
      </div>
      <div class="center bold" style="font-size: 12pt; background: #eee; padding: 2mm; margin-bottom: 3mm;">
        CADA PERSONA PAGA:<br>
        $${formatNumber(d.distribucion[0]?.total_con_propina || 0)}
      </div>
      <table style="width: 100%; border-collapse: collapse; font-size: 9pt;">
        ${filas}
      </table>
      <div class="center" style="font-size: 8pt; margin-top: 4mm; color: #444;">
        Documento informativo de división.<br>Solicite su boleta electrónica al momento del pago.
      </div>
    </body></html>`;

    imprimirTicketIframe(html);
    toast('Comprobante de división enviado a impresión ✓', 'success');
  };

  // ---------------------------------------------------------------- 1.A Cambio de Mesa
  window.abrirModalCambiarMesa = function (origenMesaID, origenNombre) {
    cambioMesaOrigenID = origenMesaID;
    const nom = document.getElementById('cambiarOrigenNombre');
    const sel = document.getElementById('selectMesaDestino');
    if (nom) nom.textContent = origenNombre;

    // Llenar select con mesas libres
    const libres = mesasCache.filter(m => !m.PedidoID && m.MesaID != origenMesaID);
    if (!libres.length) {
      toast('No hay mesas libres disponibles para trasladar la comanda.', 'warning');
      return;
    }

    if (sel) {
      sel.innerHTML = libres.map(m => `
        <option value="${m.MesaID}">${escapeHtml(m.Nombre)} (${escapeHtml(m.Zona)})</option>
      `).join('');
    }

    const modal = document.getElementById('modalCambiarMesa');
    if (modal) modal.style.display = 'flex';
  };

  window.cerrarModalCambiarMesa = function () {
    const modal = document.getElementById('modalCambiarMesa');
    if (modal) modal.style.display = 'none';
  };

  window.confirmarCambiarMesa = async function () {
    const sel = document.getElementById('selectMesaDestino');
    const destID = parseInt(sel?.value || 0);
    if (!destID || !cambioMesaOrigenID) return;

    const btn = document.getElementById('btnConfirmarCambioMesa');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Trasladando…'; }

    try {
      const res = await post('cambiar_mesa', {
        origen_mesa_id: cambioMesaOrigenID,
        destino_mesa_id: destID
      });
      toast(res.mensaje || 'Mesa cambiada con éxito ✓', 'success');
      cerrarModalCambiarMesa();
      mesaSeleccionadaID = destID;
      await abrirModalMesas();
      seleccionarMesa(destID, false);
    } catch (e) {
      toast('Error al cambiar mesa: ' + e.message, 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check"></i> Trasladar Mesa'; }
    }
  };

  // ---------------------------------------------------------------- Agregar Producto a Mesa desde Caja
  window.abrirModalAgregarProductoMesa = async function (mesaID, nombreMesa) {
    mesaSeleccionadaID = mesaID;
    prodMesaElegido = null;

    const nom = document.getElementById('agregarProdMesaNombre');
    if (nom) nom.textContent = nombreMesa;

    const input = document.getElementById('inputBuscarProdMesa');
    if (input) input.value = '';

    const cant = document.getElementById('cantProdMesa');
    if (cant) cant.value = 1;

    const nota = document.getElementById('notaProdMesa');
    if (nota) nota.value = '';

    const preview = document.getElementById('previewProdMesaElegido');
    if (preview) preview.style.display = 'none';

    const btn = document.getElementById('btnEnviarProdMesa');
    if (btn) btn.disabled = true;

    const modal = document.getElementById('modalAgregarProductoMesa');
    if (modal) modal.style.display = 'flex';

    if (!productosParaMesaCache) {
      try {
        const d = await api('productos');
        productosParaMesaCache = d.productos || [];
      } catch (e) {
        toast('Error al cargar catálogo de productos: ' + e.message, 'error');
      }
    }

    filtrarProductosParaMesa('');
    setTimeout(() => input?.focus(), 100);
  };

  window.cerrarModalAgregarProductoMesa = function () {
    const modal = document.getElementById('modalAgregarProductoMesa');
    if (modal) modal.style.display = 'none';
  };

  window.filtrarProductosParaMesa = function (q) {
    const listEl = document.getElementById('listaResultadosProdMesa');
    if (!listEl || !productosParaMesaCache) return;

    q = (q || '').trim().toLowerCase();
    const filtrados = productosParaMesaCache.filter(p => !q || p.Nombre.toLowerCase().includes(q) || (p.Categoria && p.Categoria.toLowerCase().includes(q))).slice(0, 30);

    if (!filtrados.length) {
      listEl.innerHTML = '<p style="text-align: center; color: var(--text-muted); padding: 1rem; font-size: 0.85rem;">No se encontraron productos.</p>';
      return;
    }

    listEl.innerHTML = filtrados.map(p => `
      <div onclick="elegirProdParaMesa(${p.ProductoID})" style="padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--border-dark); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.background='transparent'">
        <div>
          <strong style="color: #fff; font-size: 0.9rem;">${escapeHtml(p.Nombre)}</strong>
          <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: 0.4rem;">${escapeHtml(p.Categoria)}</span>
        </div>
        <span style="font-weight: 700; color: #10b981; font-size: 0.9rem;">$${formatNumber(p.PrecioVenta)}</span>
      </div>
    `).join('');
  };

  window.elegirProdParaMesa = function (productoID) {
    const p = productosParaMesaCache?.find(x => x.ProductoID == productoID);
    if (!p) return;
    prodMesaElegido = p;

    const preview = document.getElementById('previewProdMesaElegido');
    const nom = document.getElementById('previewNombreProdMesa');
    const prec = document.getElementById('previewPrecioProdMesa');
    const btn = document.getElementById('btnEnviarProdMesa');

    if (nom) nom.textContent = p.Nombre;
    if (prec) prec.textContent = '$' + formatNumber(p.PrecioVenta);
    if (preview) preview.style.display = 'block';
    if (btn) btn.disabled = false;
  };

  window.ajustarCantProdMesa = function (delta) {
    const cantInput = document.getElementById('cantProdMesa');
    if (!cantInput) return;
    let v = parseInt(cantInput.value) || 1;
    v = Math.max(1, Math.min(99, v + delta));
    cantInput.value = v;
  };

  window.confirmarAgregarProdMesa = async function () {
    if (!prodMesaElegido || !mesaSeleccionadaID) return;

    const cant = parseInt(document.getElementById('cantProdMesa')?.value || 1);
    const nota = (document.getElementById('notaProdMesa')?.value || '').trim();
    const btn = document.getElementById('btnEnviarProdMesa');

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando…'; }

    try {
      await post('enviar', {
        mesa_id: mesaSeleccionadaID,
        items: [{
          producto_id: prodMesaElegido.ProductoID,
          cantidad: cant,
          nota: nota
        }]
      });

      toast(`Agregado a la mesa: ${cant}× ${prodMesaElegido.Nombre} ✓`, 'success');
      cerrarModalAgregarProductoMesa();
      await abrirModalMesas();
      seleccionarMesa(mesaSeleccionadaID, false);
    } catch (e) {
      toast('Error al agregar producto: ' + e.message, 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar a Mesa / Cocina'; }
    }
  };

  // ---------------------------------------------------------------- Cobrar Mesa en Caja
  window.cobrarMesa = async function (pedidoID) {
    if (cart.length && !(await confirmDialog({
      title: 'Cobrar mesa',
      message: 'El carrito actual de la caja tiene productos. ¿Deseas reemplazarlos por la cuenta de la mesa?',
      confirmText: 'Reemplazar y Cobrar'
    }))) return;

    try {
      const d = await api('para_cobrar&pedido_id=' + pedidoID);
      cart = d.items.map(i => ({
        ProductoID: i.ProductoID,
        Nombre: i.Nombre,
        CodigoBarras: i.CodigoBarras,
        PrecioVenta: parseInt(i.PrecioVenta),
        Stock: parseFloat(i.Stock),
        ControlaStock: (i.ControlaStock !== undefined && i.ControlaStock !== null) ? (parseInt(i.ControlaStock) === 1) : true,
        EsPesable: i.EsPesable,
        CategoriaID: i.CategoriaID,
        cantidad: parseFloat(i.Cantidad),
      }));
      cotizacionActiva = null;
      mesaPedidoActivo = { id: d.pedido.PedidoID, mesa: d.pedido.Mesa };
      actualizarBannerMesa();
      renderCart();
      cerrarModalMesas();
      toast(`Cuenta de ${d.pedido.Mesa} cargada en caja. Cobra normalmente (F12).`, 'success');
    } catch (e) {
      toast(e.message, 'error');
    }
  };

  window.anularCuentaMesa = async function (pedidoID, nombre) {
    const ok = await confirmDialog({
      title: 'Anular cuenta de mesa',
      message: `¿Estás seguro de anular la cuenta de ${nombre} sin cobrarla? La mesa quedará inmediatamente libre.`,
      confirmText: 'Anular Cuenta',
      danger: true
    });
    if (!ok) return;

    try {
      await post('anular_cuenta', { pedido_id: pedidoID });
      toast(`Cuenta de ${nombre} anulada.`, 'info');
      deseleccionarMesa();
      abrirModalMesas();
    } catch (e) {
      toast(e.message, 'error');
    }
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
    toast('La mesa sigue abierta en el sistema; puedes cobrarla después.', 'info');
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
    imprimirTicketIframe(htmlComandas(comandas));
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
      toast('Comandas enviadas a reimpresión ✓', 'info');
    } catch (e) {
      toast(e.message, 'error');
    }
  };

  async function revisarComandasNuevas() {
    try {
      const d = await post('tomar_para_imprimir');
      if (d.comandas && d.comandas.length) {
        pitido();
        toast('Nueva comanda: ' + [...new Set(d.comandas.map(c => c.Mesa))].join(', '), 'info');
        if (window.MESAS_IMPRIMIR_COMANDA === 'SI') imprimirComandas(d.comandas);
      }
    } catch (e) { /* sin conexión momentánea */ }

    // Silenciosamente refrescar conteo de mesas abiertas para los badges
    try {
      const mData = await api('mesas');
      if (mData.mesas) {
        const totalAbiertas = mData.mesas.filter(m => m.PedidoID).length;
        actualizarBadgesMesasEnCaja(totalAbiertas);
      }
    } catch (e) {}
  }

  // Atajo de teclado global F8 para abrir el Centro de Mesas
  document.addEventListener('keydown', (e) => {
    if (e.key === 'F8') {
      e.preventDefault();
      abrirModalMesas();
    }
  });

  setInterval(revisarComandasNuevas, 8000);
  setTimeout(revisarComandasNuevas, 1500);
})();
