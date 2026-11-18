<?php if (!$turnoActivo): ?>
<div style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); z-index: 2000; display: flex; align-items: center; justify-content: center; flex-direction: column; text-align: center; padding: 2rem;">
  <div style="background: var(--card-bg); border: 1px solid var(--border-dark); padding: 3.5rem 3rem; border-radius: 16px; box-shadow: var(--shadow-lg); max-width: 500px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
    <i class="fa-solid fa-cash-register" style="font-size: 4rem; color: var(--warning); margin-bottom: 1.5rem;"></i>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 1rem;">Caja Registradora Cerrada</h2>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 2rem; line-height: 1.5;">
      Debes iniciar o abrir tu turno de caja registradora antes de poder procesar ventas en el sistema.
    </p>
    <a href="caja.php" class="btn btn-success" style="padding: 0.85rem 2rem; font-size: 1rem; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: bold; border-radius: 8px; text-decoration: none;">
      <i class="fa-solid fa-key"></i> Ir a Abrir Turno / Caja
    </a>
  </div>
</div>
<?php endif; ?>

<div class="pos-container" id="posContainer">
  
  <!-- Columna Izquierda: Catálogo y Búsqueda (Se oculta automáticamente en Modo Supermercado) -->
  <div class="pos-catalog">
    
    <div class="pos-search-bar" style="display: flex; gap: 0.75rem; align-items: center;">
      <div style="position: relative; flex: 1;">
        <i class="fa-solid fa-barcode" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.2rem;"></i>
        <input type="text" id="posSearch" class="form-control" placeholder="Escanear código de barras o buscar por nombre..." style="padding-left: 2.8rem; font-size: 1.1rem;" autofocus autocomplete="off">
      </div>
      <?php if (modulo_activo('mesas')): ?>
      <button type="button" id="btnMesas" onclick="abrirModalMesas()" class="btn btn-secondary" style="padding: 0.75rem 1rem; font-size: 0.9rem; color: #f59e0b;" title="Centro de mesas y pedidos (F8)">
        <i class="fa-solid fa-utensils"></i> Mesas <span id="badgeMesasAbiertas" style="display:none; background:#ef4444; color:#fff; border-radius:999px; padding:0.15rem 0.45rem; font-size:0.75rem; margin-left:0.3rem; font-weight:bold;">0</span>
      </button>
      <?php endif; ?>
      <button type="button" id="btnCotizaciones" onclick="abrirModalCotizaciones()" class="btn btn-secondary" style="padding: 0.75rem 1rem; font-size: 0.9rem;" title="Ventas Pausadas / Cotizaciones">
        <i class="fa-solid fa-clock-rotate-left"></i> Pendientes
      </button>
      <button type="button" id="btnMovimientoCaja" onclick="abrirModalMovimiento()" class="btn btn-secondary" style="padding: 0.75rem 1rem; font-size: 0.9rem;" title="Registrar Ingreso o Retiro de Caja">
        <i class="fa-solid fa-cash-register"></i> Retiro / Ingreso
      </button>
    </div>

    <!-- Píldoras de Categorías para Modo Táctil -->
    <div id="posTactilCategories" class="pos-tactil-categories">
      <button type="button" class="tactil-cat-pill active" onclick="setCategoriaTactil(0, this)">
        <i class="fa-solid fa-border-all"></i> Todas
      </button>
      <?php foreach ($categorias as $cat): ?>
        <button type="button" class="tactil-cat-pill" onclick="setCategoriaTactil(<?= (int)$cat['CategoriaID'] ?>, this)">
          <?= htmlspecialchars($cat['Nombre']) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Filtros de Catálogo Personalizable para Modo Clásico -->
    <div id="posClasicoFilters" class="pos-clasico-filters">
      <div class="pos-filter-group">
        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-right: 0.25rem;">
          <i class="fa-solid fa-sliders"></i> Mostrar:
        </span>
        <button type="button" id="btnFiltroMasVendidos" onclick="setFiltroCatalogo('mas_vendidos', this)" class="pos-filter-chip active" title="Ordenar por los productos con mayor volumen histórico de venta">
          <i class="fa-solid fa-fire" style="color: #f59e0b;"></i> Más Vendidos
        </button>
        <button type="button" id="btnFiltroOfertas" onclick="setFiltroCatalogo('ofertas', this)" class="pos-filter-chip" title="Mostrar sólo productos con ofertas y promociones vigentes"<?= modulo_activo('promociones') ? '' : ' style="display: none;"' ?>>
          <i class="fa-solid fa-tag" style="color: #10b981;"></i> En Oferta
        </button>
        <button type="button" id="btnFiltroTodos" onclick="setFiltroCatalogo('todos', this)" class="pos-filter-chip" title="Ver todo el catálogo alfabéticamente">
          <i class="fa-solid fa-boxes-stacked"></i> Todos (A-Z)
        </button>
        <select id="selectFiltroCategoria" class="form-control" onchange="setFiltroCatalogoCategoria(this.value)" style="width: auto; padding: 0.25rem 0.6rem; font-size: 0.8rem; border-radius: 8px;">
          <option value="0">📂 Por Categoría...</option>
          <?php foreach ($categorias as $cat): ?>
            <option value="<?= (int)$cat['CategoriaID'] ?>"><?= htmlspecialchars($cat['Nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display: flex; gap: 0.3rem; background: rgba(0,0,0,0.2); padding: 0.15rem; border-radius: 8px; border: 1px solid var(--border-dark);">
        <button type="button" id="btnGridEstandar" onclick="cambiarDisenoGrid('estandar')" class="btn btn-secondary" style="padding: 0.2rem 0.5rem; font-size: 0.72rem;" title="Diseño Estándar">
          <i class="fa-solid fa-table-cells-large"></i> Estándar
        </button>
        <button type="button" id="btnGridCompacto" onclick="cambiarDisenoGrid('compacto')" class="btn btn-secondary" style="padding: 0.2rem 0.5rem; font-size: 0.72rem;" title="Lista Compacta">
          <i class="fa-solid fa-list-ul"></i> Compacta
        </button>
      </div>
    </div>

    <!-- Grilla de Productos -->
    <div id="productGrid" class="product-grid" style="flex: 1;">
      <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 3rem;">
        <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
        <p>Cargando productos...</p>
      </div>
    </div>

    <!-- Panel de Opciones Rápidas en POS -->
    <div style="background: var(--card-bg); border: 1px solid var(--border-dark); padding: 0.75rem 1rem; border-radius: 12px; display: flex; gap: 0.75rem; align-items: center; justify-content: space-between; margin-top: 0.5rem; flex-shrink: 0;">
      <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem;">
        <i class="fa-solid fa-gears" style="color: var(--primary);"></i> CAJA / OPERACIONES:
      </div>
      <div style="display: flex; gap: 0.5rem;">
        <button type="button" onclick="bloquearCaja()" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.45rem 0.85rem; display: flex; align-items: center; gap: 0.4rem; border-radius: 6px; border: 1px solid var(--border-dark); color: #a78bfa;" title="Bloquear caja temporalmente (Alt+L o F9)">
          <i class="fa-solid fa-lock"></i> Bloquear
        </button>
        <button type="button" onclick="abrirModalConsultaPrecios()" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.45rem 0.85rem; display: flex; align-items: center; gap: 0.4rem; border-radius: 6px; border: 1px solid var(--border-dark);">
          <i class="fa-solid fa-magnifying-glass-dollar" style="color: #fbbf24;"></i> Consultar Precio
        </button>
        <a href="caja.php" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.45rem 0.85rem; display: flex; align-items: center; gap: 0.4rem; border-radius: 6px; border: 1px solid var(--border-dark); text-decoration: none; color: #fff;">
          <i class="fa-solid fa-cash-register" style="color: #34d399;"></i> Cuadratura / Cierre Caja
        </a>
      </div>
    </div>

  </div>

  <!-- Columna Derecha / Centro: Carrito y Cobro -->
  <div class="pos-cart">

    <!-- VISTA A: MODO SUPERMERCADO (Se activa sólo en Modo Supermercado) -->
    <div class="pos-cart-super-view">
      <!-- Barra Superior de Escaneo Supermercado -->
      <div class="super-scanner-bar">
        <div class="super-scanner-input-box">
          <i class="fa-solid fa-barcode"></i>
          <input type="text" id="posSearchSuper" class="form-control super-scanner-input" placeholder="Escanear código de barras o escribir PLU / Nombre..." autocomplete="off">
        </div>
        <div style="display: flex; gap: 0.4rem;">
          <button type="button" onclick="bloquearCaja()" class="btn btn-secondary" style="padding: 0.6rem 0.9rem; font-size: 0.85rem; color: #a78bfa;" title="Bloquear caja temporalmente (Alt+L o F9)">
            <i class="fa-solid fa-lock"></i> Bloquear
          </button>
          <button type="button" onclick="abrirModalConsultaPrecios()" class="btn btn-secondary" style="padding: 0.6rem 0.9rem; font-size: 0.85rem; color: #fbbf24;" title="Consultar precio de un producto">
            <i class="fa-solid fa-magnifying-glass-dollar"></i> Consultar
          </button>
          <button type="button" onclick="guardarCotizacion()" class="btn btn-secondary" style="padding: 0.6rem 0.9rem; font-size: 0.85rem; color: #fbbf24;" title="Pausar venta actual">
            <i class="fa-solid fa-pause"></i> Pausar
          </button>
          <button type="button" onclick="vaciarCarritoPos()" class="btn btn-secondary" style="padding: 0.6rem 0.9rem; font-size: 0.85rem; color: var(--danger);" title="Vaciar carrito">
            <i class="fa-solid fa-trash-can"></i> Vaciar
          </button>
          <?php if (modulo_activo('mesas')): ?>
          <button type="button" onclick="abrirModalMesas()" class="btn btn-secondary" style="padding: 0.6rem 0.9rem; font-size: 0.85rem; color: #f59e0b;" title="Centro de mesas y pedidos (F8)">
            <i class="fa-solid fa-utensils"></i> Mesas <span id="badgeMesasAbiertasSuper" style="display:none; background:#ef4444; color:#fff; border-radius:999px; padding:0.1rem 0.4rem; font-size:0.72rem; margin-left:0.25rem; font-weight:bold;">0</span>
          </button>
          <?php endif; ?>
          <button type="button" onclick="abrirModalCotizaciones()" class="btn btn-secondary" style="padding: 0.6rem 0.9rem; font-size: 0.85rem;" title="Ventas pausadas pendientes">
            <i class="fa-solid fa-clock-rotate-left"></i> Pendientes
          </button>
          <button type="button" onclick="abrirModalMovimiento()" class="btn btn-secondary" style="padding: 0.6rem 0.9rem; font-size: 0.85rem;" title="Retiro o ingreso de caja">
            <i class="fa-solid fa-cash-register"></i> Retiro / Ingreso
          </button>
        </div>
      </div>

      <!-- Grilla / Tabla Central de Productos en Modo Supermercado -->
      <div class="cart-table-wrapper" id="cartTableWrapper">
        <div id="cartTableEmpty" class="cart-empty" style="padding: 4rem 2rem;">
          <i class="fa-solid fa-barcode" style="font-size: 4rem; color: var(--primary); opacity: 0.5; margin-bottom: 1rem;"></i>
          <h3 style="font-size: 1.3rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem;">Caja Lista para Escanear</h3>
          <p style="color: var(--text-muted); font-size: 0.95rem; max-width: 440px; margin: 0 auto;">
            Usa el lector de código de barras o escribe el código / PLU en la barra superior para registrar artículos.
          </p>
        </div>

        <table id="cartTable" class="pos-sale-table" style="display: none;">
          <thead>
            <tr>
              <th style="width: 45px; text-align: center;">#</th>
              <th style="width: 150px;">CÓDIGO / PLU</th>
              <th>DESCRIPCIÓN DEL PRODUCTO</th>
              <th style="width: 120px;">PRECIO UNIT.</th>
              <th style="width: 130px; text-align: center;">CANTIDAD</th>
              <th style="width: 140px;">DCTO / PROMO</th>
              <th style="width: 130px; text-align: right;">SUBTOTAL</th>
              <th style="width: 70px; text-align: center;">QUITAR</th>
            </tr>
          </thead>
          <tbody id="cartTableBody"></tbody>
        </table>
      </div>

      <!-- Barra de Cobro Inferior de Supermercado -->
      <div class="super-checkout-bar">
        <div style="display: flex; align-items: center; gap: 2rem;">
          <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">
            <i class="fa-solid fa-boxes-stacked" style="color: var(--primary);"></i>
            <span id="superItemCount" style="color: #fff; font-weight: 700;">0 productos</span> en esta venta
          </div>
          <div class="super-totals-display">
            <span class="super-total-label">TOTAL A COBRAR:</span>
            <span id="superCartTotal" class="super-total-amount">$0</span>
          </div>
        </div>
        <div>
          <button type="button" onclick="abrirModalPago()" class="btn btn-success btn-super-cobrar">
            <i class="fa-solid fa-credit-card"></i> COBRAR VENTA (F12)
          </button>
        </div>
      </div>
    </div>

    <!-- VISTA B: MODO TÁCTIL Y CLÁSICO (Lista vertical estándar en la derecha) -->
    <div class="pos-cart-standard-view" style="display: flex; flex-direction: column; height: 100%;">
      <div class="cart-header">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <i class="fa-solid fa-cart-shopping" style="color: var(--primary);"></i>
          <h2 style="font-size: 1.1rem; font-weight: 600;">Carrito de Compra</h2>
        </div>
        <div style="display: flex; gap: 0.4rem;">
          <button type="button" onclick="bloquearCaja()" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; color: #a78bfa;" title="Bloquear caja temporalmente (Alt+L o F9)">
            <i class="fa-solid fa-lock"></i>
          </button>
          <button type="button" onclick="guardarCotizacion()" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; color: #fbbf24;">
            <i class="fa-solid fa-pause"></i> Pausar
          </button>
          <button type="button" id="btnVaciar" onclick="vaciarCarritoPos()" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; color: var(--danger);">
            <i class="fa-solid fa-trash-can"></i> Vaciar
          </button>
        </div>
      </div>

      <!-- Lista de Items en Carrito -->
      <div id="cartItems" class="cart-items">
        <div class="cart-empty">
          <i class="fa-solid fa-basket-shopping"></i>
          <p>El carrito está vacío</p>
          <span>Escanea o haz clic en un producto</span>
        </div>
      </div>

      <!-- Panel de Resumen y Cobro -->
      <div class="cart-footer">
        <div class="summary-row total">
          <span>TOTAL A PAGAR:</span>
          <span id="cartTotal" style="color: var(--success);">$0</span>
        </div>

        <button type="button" onclick="abrirModalPago()" class="btn btn-success btn-block" style="padding: 0.9rem; font-size: 1.1rem;">
          <i class="fa-solid fa-credit-card"></i> COBRAR VENTA (F12)
        </button>
      </div>
    </div>

  </div>

</div>

<!-- Modal Interactivo de Pago Avanzado (FormPagoPOS) -->
<div id="pagoModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 1000; align-items: center; justify-content: center;">
  <div style="background: var(--card-bg); border: 1px solid var(--border-dark); border-radius: 20px; width: 920px; max-width: 95vw; padding: 2rem; box-shadow: 0 25px 50px rgba(0,0,0,0.6);">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h2 style="font-size: 1.35rem; font-weight: 700;">Finalizar y Procesar Pago</h2>
      <button onclick="cerrarModalPago()" class="btn btn-secondary" style="padding: 0.3rem 0.6rem;">&times;</button>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.75rem; align-items: start;">

      <!-- Columna Izquierda: Cliente, Documento, Descuento y Vale -->
      <div style="display: flex; flex-direction: column; gap: 0.85rem;">
        <div<?= modulo_activo('clientes') ? '' : ' style="display: none;"' ?>>
          <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">CLIENTE ASIGNADO</label>
          <select id="clienteSelect" class="form-control" style="padding: 0.4rem 0.6rem; font-size: 0.85rem;">
            <option value="" data-puntos="0">Cliente Genérico (Público General)</option>
            <?php foreach ($clientes as $cl): ?>
              <option value="<?= $cl['ClienteID'] ?>" data-puntos="<?= $cl['PuntosAcumulados'] ?>">
                <?= htmlspecialchars($cl['Nombre']) ?> (<?= number_format($cl['PuntosAcumulados'], 0, ',', '.') ?> pts)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
          <div>
            <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">DOCUMENTO</label>
            <select id="tipoDocumento" class="form-control" style="padding: 0.4rem 0.6rem; font-size: 0.85rem;">
              <option value="Boleta">Boleta</option>
              <?php if (modulo_activo('factura')): ?><option value="Factura">Factura</option><?php endif; ?>
              <option value="Sin Documento">Sin Documento</option>
            </select>
          </div>
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
              <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin: 0;">DESCUENTO</label>
              <div style="display: flex; gap: 0.2rem;">
                <button type="button" id="btnDescModoMonto" onclick="setModoDescuento('monto')" class="btn btn-secondary active" style="padding: 0.1rem 0.45rem; font-size: 0.7rem; font-weight: bold;" title="Descuento en pesos ($)">$</button>
                <button type="button" id="btnDescModoPorc" onclick="setModoDescuento('porc')" class="btn btn-secondary" style="padding: 0.1rem 0.45rem; font-size: 0.7rem; font-weight: bold;" title="Descuento en porcentaje (%)">%</button>
              </div>
            </div>
            <div style="position: relative;">
              <input type="number" id="descuentoInput" class="form-control" placeholder="0" min="0" style="padding: 0.4rem 1.6rem 0.4rem 0.6rem; font-size: 0.85rem;" oninput="onDescuentoInputChange()" onkeydown="if(event.key==='Enter'){const el=document.getElementById('inputPassSupervisorInline');if(el&&el.offsetParent!==null){event.preventDefault();el.focus();}}">
              <input type="hidden" id="descuentoGlobal" value="0">
              <span id="descInputSuffix" style="position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-muted); font-weight: bold; pointer-events: none;">$</span>
            </div>
          </div>
        </div>

        <div id="descuentoAvisoSupervisor" style="display: none; flex-direction: column; gap: 0.4rem; font-size: 0.78rem; padding: 0.5rem 0.75rem; border-radius: 8px; background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; color: #fbbf24;">
          <div style="display: flex; align-items: center; gap: 0.4rem;">
            <i class="fa-solid fa-shield-halved"></i> <span>Descuento supera el límite libre (<strong id="lblDescMaxPorc">5%</strong>). <?= modulo_activo('supervisor') ? 'Requiere clave de supervisor:' : 'Solo un administrador puede aplicarlo.' ?></span>
          </div>
          <div id="boxSupervisorAuthInline" style="display: <?= modulo_activo('supervisor') ? 'flex' : 'none' ?>; gap: 0.35rem; align-items: center;">
            <input type="password" id="inputPassSupervisorInline" class="form-control" placeholder="Clave de Supervisor (o admin)..." style="padding: 0.3rem 0.6rem; font-size: 0.82rem; flex: 1;" onkeydown="if(event.key==='Enter'){event.preventDefault();validarSupervisorDescuentoInline();}">
            <button type="button" id="btnAuthSupervisorInline" onclick="validarSupervisorDescuentoInline()" class="btn btn-warning" style="padding: 0.3rem 0.75rem; font-size: 0.78rem; font-weight: bold; white-space: nowrap;">
              <i class="fa-solid fa-key"></i> Autorizar
            </button>
          </div>
          <div id="boxSupervisorAuthOk" style="display: none; align-items: center; gap: 0.4rem; color: #34d399; font-weight: 700;">
            <i class="fa-solid fa-circle-check"></i> <span id="txtSupervisorAuthOk">Autorizado por Supervisor</span>
          </div>
        </div>

        <div<?= modulo_activo('devoluciones') ? '' : ' style="display: none;"' ?>>
          <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">CÓDIGO VALE / N° BOLETA ORIGINAL</label>
          <div style="display: flex; gap: 0.4rem;">
            <input type="text" id="valeCodigoInput" class="form-control" placeholder="Ej: NC-XXXXXXXX o Boleta 18" style="padding: 0.4rem 0.6rem; font-size: 0.85rem; text-transform: uppercase;">
            <button type="button" onclick="aplicarValeCarrito()" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;">Aplicar</button>
          </div>
          <div id="valeAplicadoInfo" style="font-size: 0.78rem; margin-top: 0.3rem; display: none;"></div>
        </div>

        <?php if ($propinaActiva): ?>
        <!-- Propina voluntaria (no forma parte de la venta ni de la boleta) -->
        <div id="bloquePropina">
          <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">PROPINA (VOLUNTARIA)</label>
          <div style="display: flex; gap: 0.35rem; margin-top: 0.2rem;">
            <button type="button" class="btn btn-secondary btn-propina" data-modo="0" onclick="setPropinaModo('0')" style="flex: 1; padding: 0.4rem; font-size: 0.8rem;">Sin propina</button>
            <button type="button" class="btn btn-secondary btn-propina" data-modo="sugerida" onclick="setPropinaModo('sugerida')" style="flex: 1.3; padding: 0.4rem; font-size: 0.8rem;" id="btnPropinaSugerida"><?= (int)$propinaPorcentaje ?>% sugerida</button>
            <div style="position: relative; flex: 1.2;">
              <span style="position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-muted);">$</span>
              <input type="number" id="propinaOtroInput" min="0" step="100" placeholder="Otro" class="form-control" oninput="setPropinaModo('otro')" style="padding: 0.4rem 0.4rem 0.4rem 1.2rem; font-size: 0.85rem;">
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Muestra Total a Pagar Grande -->
        <div style="background: rgba(16,185,129,0.1); border: 1px solid var(--success); padding: 1rem; border-radius: 12px; text-align: center;">
          <span style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">TOTAL A COBRAR</span>
          <div id="modalMontoTotal" style="font-size: 2.5rem; font-weight: 800; color: var(--success); font-family: monospace;">$0</div>
          <div id="modalDesglosePropina" style="display: none; font-size: 0.82rem; color: var(--text-muted);"></div>
          <div id="modalDesgloseRedondeo" style="display: none; font-size: 0.82rem; color: var(--success); font-weight: 600; margin-top: 0.25rem;"></div>
        </div>
      </div>

      <!-- Columna Derecha: Forma de Pago -->
      <div style="display: flex; flex-direction: column; gap: 0.85rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 0.5rem;">SELECCIONA FORMA DE PAGO</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem;">
            <button type="button" class="btn btn-secondary btn-metodo active" data-metodo="Efectivo" onclick="setFormaPago('Efectivo', this)">
              <i class="fa-solid fa-money-bill-wave"></i> Efectivo
            </button>
            <button type="button" class="btn btn-secondary btn-metodo" data-metodo="Tarjeta Debito" onclick="setFormaPago('Tarjeta Debito', this)">
              <i class="fa-solid fa-credit-card"></i> Tarjeta
            </button>
            <button type="button" class="btn btn-secondary btn-metodo" data-metodo="Transferencia" onclick="setFormaPago('Transferencia', this)">
              <i class="fa-solid fa-building-columns"></i> Transferencia
            </button>
            <?php if (modulo_activo('clientes')): ?>
            <button type="button" class="btn btn-secondary btn-metodo" data-metodo="Credito" onclick="setFormaPago('Credito', this)">
              <i class="fa-solid fa-handshake"></i> Fiado / Crédito
            </button>
            <button type="button" class="btn btn-secondary btn-metodo" data-metodo="Puntos" onclick="setFormaPago('Puntos', this)">
              <i class="fa-solid fa-star"></i> Puntos
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-metodo" data-metodo="Mixto" onclick="setFormaPago('Mixto', this)">
              <i class="fa-solid fa-layer-group"></i> Pago Mixto
            </button>
          </div>
        </div>

        <!-- Panel Dinámico Efectivo / Botones Rápido -->
        <div id="panelEfectivoModal">
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 0.4rem;">BOTONES DE EFECTIVO RÁPIDO</label>
          <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.75rem;">
            <button type="button" onclick="setMontoQuick('exacto')" class="btn btn-secondary" style="flex:1; padding:0.5rem;">$ Exacto</button>
            <button type="button" onclick="setMontoQuick(2000)" class="btn btn-secondary" style="flex:1; padding:0.5rem;">$2.000</button>
            <button type="button" onclick="setMontoQuick(5000)" class="btn btn-secondary" style="flex:1; padding:0.5rem;">$5.000</button>
            <button type="button" onclick="setMontoQuick(10000)" class="btn btn-secondary" style="flex:1; padding:0.5rem;">$10.000</button>
            <button type="button" onclick="setMontoQuick(20000)" class="btn btn-secondary" style="flex:1; padding:0.5rem;">$20.000</button>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <div>
              <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">MONTO RECIBIDO ($)</label>
              <input type="number" id="montoRecibidoModal" class="form-control" placeholder="0" style="font-size: 1.2rem; font-weight: bold;" oninput="calcularVueltoModal()">
            </div>
            <div>
              <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">VUELTO A ENTREGAR ($)</label>
              <input type="text" id="vueltoModal" class="form-control" value="$0" readonly style="font-size: 1.2rem; font-weight: bold; color: var(--success); background: rgba(0,0,0,0.3);">
            </div>
          </div>
        </div>

        <!-- Panel Dinámico Pago Mixto -->
        <div id="panelMixtoModal" style="display: none; background: rgba(15,23,42,0.6); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-dark);">
          <label style="font-size: 0.8rem; color: #818cf8; font-weight: 700; display: block; margin-bottom: 0.5rem;">DIVIDIR PAGO EN Varios MÉTODOS</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem;">
            <div>
              <label style="font-size: 0.75rem; color: var(--text-muted);">EFECTIVO ($)</label>
              <input type="number" id="mixtoEfectivo" class="form-control" placeholder="0">
            </div>
            <div>
              <label style="font-size: 0.75rem; color: var(--text-muted);">TARJETA ($)</label>
              <input type="number" id="mixtoTarjeta" class="form-control" placeholder="0">
            </div>
            <div>
              <label style="font-size: 0.75rem; color: var(--text-muted);">TRANSFERENCIA ($)</label>
              <input type="number" id="mixtoTransf" class="form-control" placeholder="0">
            </div>
          </div>
        </div>

        <button type="button" id="btnConfirmarPagoModal" onclick="confirmarPagoModal()" class="btn btn-success btn-block" style="padding: 1rem; font-size: 1.2rem;">
          <i class="fa-solid fa-check-double"></i> CONFIRMAR E IMPRIMIR VENTA
        </button>
      </div>

    </div>
  </div>
</div>

<?php if (modulo_activo('mesas')): ?>
<!-- ===================================================================== -->
<!-- MODAL CENTRO DE CONTROL Y VISOR DE MESAS (Módulo Mesas y Garzones)    -->
<!-- ===================================================================== -->
<style>
  .mesas-modal-container {
    background: var(--card-bg, #1e293b);
    border: 1px solid var(--border-dark, #334155);
    border-radius: 16px;
    width: 1100px;
    max-width: 96vw;
    height: 86vh;
    max-height: 820px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0,0,0,0.55);
  }
  .mesas-header {
    padding: 0.9rem 1.4rem;
    border-bottom: 1px solid var(--border-dark, #334155);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(15, 23, 42, 0.45);
  }
  .mesas-semaforo-bar {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
  }
  .semaforo-pill {
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.35rem;
  }
  .sem-libre { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
  .sem-ocupada { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
  .sem-cocina { background: rgba(249, 115, 22, 0.15); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.3); }
  .sem-precuenta { background: rgba(234, 179, 8, 0.18); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.4); }

  .mesas-toolbar {
    padding: 0.6rem 1.25rem;
    border-bottom: 1px solid var(--border-dark, #334155);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    background: rgba(15, 23, 42, 0.25);
  }
  .mesas-zones-tabs {
    display: flex;
    gap: 0.4rem;
    overflow-x: auto;
  }
  .zone-tab-btn {
    background: transparent;
    border: 1px solid var(--border-dark, #334155);
    color: var(--text-muted, #94a3b8);
    padding: 0.25rem 0.65rem;
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
  }
  .zone-tab-btn.active, .zone-tab-btn:hover {
    background: var(--primary, #4f46e5);
    color: #fff;
    border-color: var(--primary, #4f46e5);
  }

  .mesas-split-body {
    display: flex;
    flex: 1;
    overflow: hidden;
  }
  .mesas-grid-pane {
    flex: 1.15;
    padding: 1rem;
    overflow-y: auto;
    border-right: 1px solid var(--border-dark, #334155);
  }
  .mesas-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.75rem;
  }
  .mesa-card {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid var(--border-dark, #334155);
    border-radius: 12px;
    padding: 0.75rem 0.65rem;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 105px;
    transition: transform 0.15s, border-color 0.15s, box-shadow 0.15s;
    position: relative;
    text-align: left;
    user-select: none;
  }
  .mesa-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.3);
  }
  .mesa-card.selected {
    outline: 2px solid #6366f1;
    box-shadow: 0 0 16px rgba(99, 102, 241, 0.4);
  }
  .mesa-card-libre {
    border-color: rgba(16, 185, 129, 0.35);
  }
  .mesa-card-libre:hover {
    border-color: #10b981;
  }
  .mesa-card-ocupada {
    border-color: rgba(59, 130, 246, 0.45);
    background: rgba(30, 58, 138, 0.15);
  }
  .mesa-card-cocina {
    border-color: rgba(249, 115, 22, 0.55);
    background: rgba(154, 52, 18, 0.18);
  }
  .mesa-card-precuenta {
    border-color: rgba(234, 179, 8, 0.6);
    background: rgba(161, 98, 7, 0.2);
  }

  .mesas-detail-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(15, 23, 42, 0.3);
    overflow: hidden;
  }
  .detail-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: var(--text-muted, #94a3b8);
    text-align: center;
  }
  .detail-content {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
  }
  .detail-header {
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--border-dark, #334155);
    background: rgba(15, 23, 42, 0.4);
  }
  .detail-items-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem 1.25rem;
  }
  .detail-item-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.55rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    gap: 0.5rem;
  }
  .detail-totals-box {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid var(--border-dark, #334155);
    background: rgba(15, 23, 42, 0.55);
  }
  .detail-actions-bar {
    padding: 0.65rem 1.25rem;
    border-top: 1px solid var(--border-dark, #334155);
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    background: rgba(15, 23, 42, 0.75);
  }
</style>

<div id="mesasModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
  <div class="mesas-modal-container">
    <!-- Encabezado del Centro de Mesas -->
    <div class="mesas-header">
      <div style="display: flex; align-items: center; gap: 0.8rem;">
        <i class="fa-solid fa-utensils" style="color: #f59e0b; font-size: 1.4rem;"></i>
        <div>
          <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: #fff;">Centro de Mesas y Pedidos</h2>
          <span style="font-size: 0.8rem; color: var(--text-muted);">Visor de comandas, pre-cuenta, división y cobro en caja</span>
        </div>
      </div>
      <div style="display: flex; align-items: center; gap: 1rem;">
        <!-- Semáforo resumen -->
        <div class="mesas-semaforo-bar">
          <div class="semaforo-pill sem-libre" title="Mesas disponibles">🟢 Libres: <span id="cntMesasLibres">0</span></div>
          <div class="semaforo-pill sem-ocupada" title="Mesas consumiendo">🔵 Ocupadas: <span id="cntMesasOcupadas">0</span></div>
          <div class="semaforo-pill sem-cocina" title="Mesas con comandas en preparación">🟠 En Cocina: <span id="cntMesasCocina">0</span></div>
          <div class="semaforo-pill sem-precuenta" title="Mesas con pre-cuenta impresa">🟡 Pre-cuenta: <span id="cntMesasPrecuenta">0</span></div>
        </div>
        <button onclick="cerrarModalMesas()" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 1.1rem; border-radius: 8px;">&times;</button>
      </div>
    </div>

    <!-- Barra de Zonas y Búsqueda -->
    <div class="mesas-toolbar">
      <div id="mesasZonaFiltros" class="mesas-zones-tabs"></div>
      <div style="display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="filtroMesasInput" placeholder="🔍 Filtrar mesa..." class="form-control" style="width: 170px; padding: 0.35rem 0.65rem; font-size: 0.85rem; border-radius: 8px;" oninput="filtrarMesasCaja()">
        <button type="button" class="btn btn-secondary" onclick="abrirModalMesas()" title="Actualizar mesas" style="padding: 0.35rem 0.65rem; font-size: 0.85rem; border-radius: 8px;">
          <i class="fa-solid fa-rotate"></i>
        </button>
      </div>
    </div>

    <!-- Cuerpo Dividido: Grilla de Mesas (Izq) y Visor de Pedido (Der) -->
    <div class="mesas-split-body">
      <!-- Columna Izquierda: Grilla de Tarjetas -->
      <div class="mesas-grid-pane" id="mesasGridContainer">
        <div id="mesasCardsGrid" class="mesas-cards-grid">
          <p style="text-align:center; color: var(--text-muted); grid-column: 1/-1;">Cargando mesas…</p>
        </div>
      </div>

      <!-- Columna Derecha: Visor de Pedido / Acciones -->
      <div class="mesas-detail-pane" id="mesasDetailPane">
        <!-- Estado Vacío -->
        <div id="mesaDetailEmpty" class="detail-empty-state">
          <i class="fa-solid fa-chair" style="font-size: 3.5rem; opacity: 0.25; margin-bottom: 0.75rem;"></i>
          <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.35rem; color: #fff;">Visor de Pedidos de Mesa</h3>
          <p style="font-size: 0.85rem; max-width: 280px; margin: 0;">Selecciona una mesa ocupada para consultar qué pidieron, imprimir pre-cuenta, dividir cuenta o cobrar en caja.</p>
        </div>

        <!-- Estado Seleccionado -->
        <div id="mesaDetailContent" class="detail-content" style="display: none;">
          <div class="detail-header" id="mesaDetailHeader"></div>
          <div class="detail-items-scroll" id="mesaDetailItems"></div>
          <div class="detail-totals-box" id="mesaDetailTotals"></div>
          <div class="detail-actions-bar" id="mesaDetailActions"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Submodal: Dividir Cuenta -->
<div id="modalDividirCuenta" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 1100; align-items: center; justify-content: center;">
  <div style="background: var(--card-bg, #1e293b); border: 1px solid var(--border-dark, #334155); border-radius: 16px; width: 480px; max-width: 95vw; padding: 1.4rem; box-shadow: var(--shadow-lg);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;"><i class="fa-solid fa-divide" style="color: #60a5fa;"></i> Dividir Cuenta · <span id="dividirMesaNombre" style="color:#f59e0b;"></span></h3>
      <button onclick="cerrarModalDividirCuenta()" class="btn btn-secondary" style="padding: 0.25rem 0.55rem;">&times;</button>
    </div>
    
    <div style="text-align: center; margin-bottom: 1.25rem;">
      <span style="font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 0.4rem;">Número de Personas:</span>
      <div style="display: inline-flex; align-items: center; gap: 0.75rem; background: rgba(15,23,42,0.5); padding: 0.35rem 0.75rem; border-radius: 12px; border: 1px solid var(--border-dark);">
        <button type="button" class="btn btn-secondary" onclick="ajustarPartesDivision(-1)" style="width: 38px; height: 38px; font-size: 1.2rem; font-weight: bold; border-radius: 8px;">−</button>
        <span id="dividirNumPartes" style="font-size: 1.5rem; font-weight: 800; min-width: 40px; color: #fff;">2</span>
        <button type="button" class="btn btn-secondary" onclick="ajustarPartesDivision(1)" style="width: 38px; height: 38px; font-size: 1.2rem; font-weight: bold; border-radius: 8px;">+</button>
      </div>
      <div style="display: flex; justify-content: center; gap: 0.35rem; margin-top: 0.6rem;">
        <?php foreach ([2, 3, 4, 5, 6, 8] as $p): ?>
          <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.6rem; font-size: 0.8rem;" onclick="setPartesDivision(<?= $p ?>)"><?= $p ?> pers.</button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Desglose calculado -->
    <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border-dark); border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
      <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.3rem;">
        <span style="color: var(--text-muted);">Consumo total mesa:</span>
        <strong id="divTotalConsumoMesa">$0</strong>
      </div>
      <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.3rem;">
        <span style="color: var(--text-muted);">Consumo por persona:</span>
        <strong id="divConsumoPorPersona">$0</strong>
      </div>
      <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.5rem; color: #f59e0b;">
        <span>Propina sugerida c/u (10%):</span>
        <strong id="divPropinaPorPersona">+$0</strong>
      </div>
      <div style="border-top: 1px dashed var(--border-dark); padding-top: 0.6rem; display: flex; justify-content: space-between; align-items: baseline;">
        <span style="font-weight: 700; font-size: 0.95rem;">TOTAL POR PERSONA:</span>
        <span id="divTotalPorPersona" style="font-size: 1.35rem; font-weight: 800; color: #10b981;">$0</span>
      </div>
      <div style="font-size: 0.75rem; color: var(--text-muted); text-align: center; margin-top: 0.4rem;">
        (Propina sugerida Ley 20.918 voluntaria)
      </div>
    </div>

    <div id="divListaPersonas" style="max-height: 140px; overflow-y: auto; margin-bottom: 1rem; font-size: 0.85rem;"></div>

    <div style="display: flex; gap: 0.5rem;">
      <button type="button" class="btn btn-secondary" onclick="imprimirTicketDivision()" style="flex: 1; padding: 0.6rem; font-size: 0.85rem;">
        <i class="fa-solid fa-print"></i> Imprimir comprobante
      </button>
      <button type="button" class="btn btn-primary" onclick="cerrarModalDividirCuenta()" style="padding: 0.6rem 1.2rem; font-size: 0.85rem;">
        Listo
      </button>
    </div>
  </div>
</div>

<!-- Submodal: Cambiar Mesa -->
<div id="modalCambiarMesa" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 1100; align-items: center; justify-content: center;">
  <div style="background: var(--card-bg, #1e293b); border: 1px solid var(--border-dark, #334155); border-radius: 16px; width: 440px; max-width: 95vw; padding: 1.4rem; box-shadow: var(--shadow-lg);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;"><i class="fa-solid fa-arrows-split-up-and-left" style="color: #6366f1;"></i> Cambiar de Mesa</h3>
      <button onclick="cerrarModalCambiarMesa()" class="btn btn-secondary" style="padding: 0.25rem 0.55rem;">&times;</button>
    </div>
    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem;">
      Trasladar la cuenta activa de <strong id="cambiarOrigenNombre" style="color: #fff;"></strong> a una mesa vacía:
    </p>
    <div style="margin-bottom: 1.25rem;">
      <label style="font-size: 0.82rem; font-weight: 600; display: block; margin-bottom: 0.35rem; color: var(--text-muted);">Mesa Destino (Disponibles):</label>
      <select id="selectMesaDestino" class="form-control" style="width: 100%; padding: 0.65rem; font-size: 0.95rem; border-radius: 8px;"></select>
    </div>
    <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
      <button type="button" class="btn btn-secondary" onclick="cerrarModalCambiarMesa()" style="padding: 0.6rem 1rem;">Cancelar</button>
      <button type="button" id="btnConfirmarCambioMesa" class="btn btn-primary" onclick="confirmarCambiarMesa()" style="padding: 0.6rem 1.2rem;">
        <i class="fa-solid fa-check"></i> Trasladar Mesa
      </button>
    </div>
  </div>
</div>

<!-- Submodal: Agregar Producto a Mesa desde Caja -->
<div id="modalAgregarProductoMesa" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 1100; align-items: center; justify-content: center;">
  <div style="background: var(--card-bg, #1e293b); border: 1px solid var(--border-dark, #334155); border-radius: 16px; width: 480px; max-width: 95vw; padding: 1.4rem; box-shadow: var(--shadow-lg);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;"><i class="fa-solid fa-plus-circle" style="color: #10b981;"></i> Agregar a <span id="agregarProdMesaNombre" style="color: #f59e0b;"></span></h3>
      <button onclick="cerrarModalAgregarProductoMesa()" class="btn btn-secondary" style="padding: 0.25rem 0.55rem;">&times;</button>
    </div>
    <div style="margin-bottom: 0.75rem;">
      <label style="font-size: 0.82rem; font-weight: 600; display: block; margin-bottom: 0.35rem; color: var(--text-muted);">Buscar Producto:</label>
      <input type="text" id="inputBuscarProdMesa" class="form-control" placeholder="Escribe el nombre o código..." style="padding: 0.6rem; font-size: 0.95rem;" oninput="filtrarProductosParaMesa(this.value)">
    </div>
    <div id="listaResultadosProdMesa" style="max-height: 180px; overflow-y: auto; margin-bottom: 0.75rem; border: 1px solid var(--border-dark); border-radius: 8px; background: rgba(15,23,42,0.4);">
      <p style="text-align: center; color: var(--text-muted); padding: 1rem; font-size: 0.85rem;">Escribe para buscar productos...</p>
    </div>
    <div id="previewProdMesaElegido" style="display: none; background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.3); border-radius: 10px; padding: 0.75rem; margin-bottom: 0.75rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
        <strong id="previewNombreProdMesa" style="color: #fff; font-size: 0.95rem;"></strong>
        <span id="previewPrecioProdMesa" style="color: #38bdf8; font-weight: 700;"></span>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <div style="display: flex; align-items: center; gap: 0.35rem;">
          <span style="font-size: 0.8rem; color: var(--text-muted);">Cant:</span>
          <button type="button" class="btn btn-secondary" onclick="ajustarCantProdMesa(-1)" style="padding: 0.2rem 0.55rem;">−</button>
          <input type="number" id="cantProdMesa" value="1" min="1" max="99" style="width: 48px; text-align: center; padding: 0.25rem; font-weight: bold; border-radius: 6px; border: 1px solid var(--border-dark); background: var(--card-bg); color: #fff;">
          <button type="button" class="btn btn-secondary" onclick="ajustarCantProdMesa(1)" style="padding: 0.2rem 0.55rem;">+</button>
        </div>
        <div style="flex: 1;">
          <input type="text" id="notaProdMesa" class="form-control" placeholder="Nota (ej: sin hielo)..." style="padding: 0.35rem 0.6rem; font-size: 0.85rem;">
        </div>
      </div>
    </div>
    <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
      <button type="button" class="btn btn-secondary" onclick="cerrarModalAgregarProductoMesa()" style="padding: 0.6rem 1rem;">Cancelar</button>
      <button type="button" id="btnEnviarProdMesa" class="btn btn-primary" onclick="confirmarAgregarProdMesa()" style="padding: 0.6rem 1.2rem;" disabled>
        <i class="fa-solid fa-paper-plane"></i> Enviar a Mesa / Cocina
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal Cotizaciones / Pendientes -->
<div id="cotizacionesModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
  <div style="background: var(--card-bg); border: 1px solid var(--border-dark); border-radius: 16px; width: 500px; padding: 1.75rem; box-shadow: var(--shadow-lg);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h2 style="font-size: 1.2rem; font-weight: 700;">Ventas Pausadas / Cotizaciones</h2>
      <button onclick="cerrarModalCotizaciones()" class="btn btn-secondary" style="padding: 0.3rem 0.6rem;">&times;</button>
    </div>
    <div id="cotizacionesLista" style="max-height: 350px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem;">
      <p style="text-align: center; color: var(--text-muted);">Cargando pendientes...</p>
    </div>
  </div>
</div>

<!-- Modal Movimiento de Caja (Ingreso / Retiro) -->
<div id="movimientoModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
  <div style="background: var(--card-bg); border: 1px solid var(--border-dark); border-radius: 16px; width: 400px; padding: 1.75rem; box-shadow: var(--shadow-lg);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h2 style="font-size: 1.2rem; font-weight: 700;">Ingreso / Retiro de Caja</h2>
      <button onclick="cerrarModalMovimiento()" class="btn btn-secondary" style="padding: 0.3rem 0.6rem;">&times;</button>
    </div>
    <div style="display: flex; flex-direction: column; gap: 1rem;">
      <div>
        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">TIPO</label>
        <select id="posMovTipo" class="form-control">
          <option value="RETIRO">Retiro (-)</option>
          <option value="INGRESO">Ingreso (+)</option>
        </select>
      </div>
      <div>
        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">MONTO ($)</label>
        <input type="number" id="posMovMonto" class="form-control" placeholder="10000" style="font-size: 1.1rem; font-weight: bold;">
      </div>
      <div>
        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">CONCEPTO / MOTIVO</label>
        <input type="text" id="posMovConcepto" class="form-control" placeholder="Ej: Pago de panadería / Retiro parcial">
      </div>
      <button type="button" id="btnRegistrarMovimientoPos" onclick="registrarMovimientoPos()" class="btn btn-primary btn-block" style="padding: 0.75rem;">
        <i class="fa-solid fa-floppy-disk"></i> Registrar
      </button>
    </div>
  </div>
</div>

<!-- Modal de Ticket / Comprobante -->
<div id="ticketModal" class="ticket-overlay" style="display: none;">
  <div class="ticket-modal__card">
    <button type="button" class="ticket-cerrar-x no-print" onclick="cerrarTicket()" title="Cerrar (Esc)" aria-label="Cerrar">✕</button>
    <div class="ticket-paper" id="ticketPaper">
      <div class="tk-center">
        <div class="tk-strong tk-lg"><?= htmlspecialchars($cfgLocal['MINIMARKET_NOMBRE'] ?? 'MINIMARKET') ?></div>
        <?php if (!empty($cfgLocal['MINIMARKET_GIRO'])): ?><div><?= htmlspecialchars($cfgLocal['MINIMARKET_GIRO']) ?></div><?php endif; ?>
        <?php if (!empty($cfgLocal['MINIMARKET_RUT'])): ?><div>RUT: <?= htmlspecialchars($cfgLocal['MINIMARKET_RUT']) ?></div><?php endif; ?>
        <?php if (!empty($cfgLocal['MINIMARKET_DIRECCION'])): ?><div><?= htmlspecialchars($cfgLocal['MINIMARKET_DIRECCION']) ?></div><?php endif; ?>
        <?php if (!empty($cfgLocal['MINIMARKET_TELEFONO'])): ?><div>Fono: <?= htmlspecialchars($cfgLocal['MINIMARKET_TELEFONO']) ?></div><?php endif; ?>
      </div>

      <div class="tk-sep"></div>
      <div class="tk-row"><span>Comprobante interno</span><span id="ticketVentaNum"></span></div>
      <div class="tk-row"><span id="ticketFecha"></span><span>Caja: <?= htmlspecialchars($user['nombre']) ?></span></div>
      <div class="tk-sep"></div>

      <div id="ticketDetalle" class="tk-items"></div>

      <div class="tk-sep"></div>
      <div class="tk-row" id="ticketSubtotalRow" style="display: none;"><span>Subtotal</span><span id="ticketSubtotal"></span></div>
      <div class="tk-row" id="ticketDescuentoRow" style="display: none;"><span>Descuento</span><span id="ticketDescuento"></span></div>
      <div class="tk-row tk-strong tk-lg"><span>TOTAL</span><span id="ticketTotal"></span></div>
      <div id="ticketPropinaBox" style="display: none;">
        <div class="tk-row"><span>Propina (voluntaria)</span><span id="ticketPropina"></span></div>
        <div class="tk-row tk-strong"><span>Total pagado</span><span id="ticketTotalPagado"></span></div>
      </div>
      <div id="ticketRedondeoBox" style="display: none;">
        <div class="tk-row"><span>Redondeo Ley 20.956</span><span id="ticketRedondeo"></span></div>
        <div class="tk-row tk-strong"><span>Total en efectivo</span><span id="ticketTotalEfectivo"></span></div>
      </div>

      <div class="tk-sep"></div>
      <div id="ticketPagos" class="tk-pagos"></div>
      <div class="tk-row" id="ticketVueltoRow"><span>Vuelto</span><span id="ticketVuelto"></span></div>

      <!-- Comprobante de crédito interno / fiado -->
      <div id="ticketCreditoBox" class="tk-credito" style="display: none;">
        <div class="tk-sep--strong"></div>
        <div class="tk-center tk-strong">COMPROBANTE DE CRÉDITO INTERNO</div>
        <div class="tk-row"><span>Cliente</span><span id="tkCredCliente"></span></div>
        <div class="tk-row" id="tkCredRutRow"><span>RUT</span><span id="tkCredRut"></span></div>
        <div class="tk-row"><span>Compra a crédito</span><span id="tkCredMonto"></span></div>
        <div class="tk-row tk-strong"><span>Saldo total adeudado</span><span id="tkCredSaldo"></span></div>
        <div class="tk-row"><span>Cupo disponible</span><span id="tkCredCupo"></span></div>
        <div class="tk-firma">
          <div class="tk-firma__line">&nbsp;</div>
          <div class="tk-firma__label">Firma cliente</div>
          <div class="tk-firma__line">&nbsp;</div>
          <div class="tk-firma__label">Nombre y RUT</div>
        </div>
        <div class="tk-center" style="font-size: 0.9em;">Declaro recibir la mercadería y adeudar el monto indicado.</div>
      </div>

      <div class="tk-sep"></div>
      <div class="tk-center tk-pie"><?= htmlspecialchars($cfgLocal['TICKET_PIE_PAGINA'] ?? '¡Gracias por su preferencia!') ?></div>

      <!-- DTE (boleta electrónica) -->
      <div id="ticketDteInfo" class="tk-dte tk-center" style="display: none;">
        <div class="tk-strong">BOLETA ELECTRÓNICA</div>
        <div id="ticketDteFolio">Folio: -</div>
      </div>
    </div>

    <div class="ticket-modal__actions no-print">
      <a id="ticketDtePdfBtn" href="#" target="_blank" class="btn btn-success" style="display: none;">
        <i class="fa-solid fa-file-pdf"></i> Ver PDF SII
      </a>
      <button id="ticketLocalPrintBtn" onclick="window.print()" class="btn btn-primary">
        <i class="fa-solid fa-print"></i> Imprimir comprobante
      </button>
      <button id="ticketDtePrintBtn" onclick="imprimirPdfDirecto(this.dataset.url)" class="btn btn-success" style="display: none;">
        <i class="fa-solid fa-print"></i> Imprimir boleta SII
      </button>
      <button onclick="cerrarTicket()" class="btn btn-primary btn-nueva-venta" id="btnNuevaVenta">
        <i class="fa-solid fa-cart-plus"></i> Nueva venta (Esc)
      </button>
    </div>
  </div>
</div>

<!-- Modal Consulta de Precios POS -->
<div id="consultaPreciosModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(6px); z-index: 2500; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: var(--card-bg); border: 1px solid var(--border-dark); border-radius: 16px; width: 550px; padding: 1.75rem; box-shadow: var(--shadow-lg); position: relative; display: flex; flex-direction: column; gap: 1rem;">
    <button onclick="cerrarModalConsultaPrecios()" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; line-height: 1;"><i class="fa-solid fa-xmark"></i></button>
    
    <h2 style="font-size: 1.2rem; font-weight: 700; color: #818cf8; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
      <i class="fa-solid fa-barcode"></i> Consulta Rápida de Precios
    </h2>
    
    <div>
      <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 0.4rem;">ESCANEA CÓDIGO O BUSCA PRODUCTO</label>
      <input type="text" id="consultaPrecioSearch" class="form-control" placeholder="Escribe el nombre o escanea el código..." oninput="ejecutarConsultaPrecio()" autocomplete="off">
    </div>

    <!-- Resultados -->
    <div id="consultaPrecioResultados" style="max-height: 250px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem; min-height: 100px; padding: 0.25rem;">
      <div style="text-align: center; color: var(--text-muted); padding: 2rem;">Ingresa un término para buscar...</div>
    </div>
  </div>
</div>

<script>
async function abrirModalConsultaPrecios() {
  document.getElementById('consultaPrecioSearch').value = '';
  document.getElementById('consultaPrecioResultados').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 2rem;">Ingresa un término para buscar...</div>';
  document.getElementById('consultaPreciosModal').style.display = 'flex';
  setTimeout(() => document.getElementById('consultaPrecioSearch').focus(), 150);
}

function cerrarModalConsultaPrecios() {
  document.getElementById('consultaPreciosModal').style.display = 'none';
  document.getElementById('posSearch')?.focus();
}

async function ejecutarConsultaPrecio() {
  const q = document.getElementById('consultaPrecioSearch').value.trim();
  const resEl = document.getElementById('consultaPrecioResultados');
  if (q.length < 2) {
    resEl.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 2rem;">Ingresa al menos 2 letras...</div>';
    return;
  }

  try {
    const res = await fetch(`api/buscar_producto.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    if (!data.success) throw new Error(data.error);

    if (data.productos.length === 0) {
      resEl.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 2rem;">No se encontraron productos</div>';
      return;
    }

    resEl.innerHTML = data.productos.map(p => {
      const stockColor = parseFloat(p.Stock) <= parseFloat(p.StockMinimo) ? 'var(--danger)' : 'var(--success)';
      
      let promoHtml = '';
      if (p.PromoTipo) {
        if (p.PromoTipo === 'DESCUENTO_UNIT') {
          promoHtml = `
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 6px; margin-top: 0.35rem; display: inline-flex; align-items: center; gap: 0.3rem; font-weight: bold;">
              <i class="fa-solid fa-tags"></i> Oferta: -${p.PromoDescPorc}% Dcto
            </div>
          `;
        } else if (p.PromoTipo === 'MULTIBUY') {
          const precioPackFmt = '$' + new Intl.NumberFormat('es-CL').format(p.PromoPrecioOf);
          promoHtml = `
            <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; color: #fbbf24; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 6px; margin-top: 0.35rem; display: inline-flex; align-items: center; gap: 0.3rem; font-weight: bold;">
              <i class="fa-solid fa-layer-group"></i> Promo: Lleva ${p.PromoCantMin} por ${precioPackFmt}
            </div>
          `;
        }
      }

      return `
        <div style="background: rgba(15,23,42,0.4); border: 1px solid var(--border-dark); padding: 0.75rem 1rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
          <div>
            <div style="font-weight: 700; color: #fff; font-size: 0.95rem;">${p.Nombre}</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">Código: ${p.CodigoBarras}</div>
            ${promoHtml}
          </div>
          <div style="text-align: right;">
            <div style="font-size: 1.2rem; font-weight: 800; color: var(--success); font-family: monospace;">$${new Intl.NumberFormat('es-CL').format(p.PrecioVenta)}</div>
            <div style="font-size: 0.8rem; font-weight: 600; color: ${stockColor}; margin-top: 0.2rem;">Stock: ${p.Stock} ${p.UnidadMedida}</div>
          </div>
        </div>
      `;
    }).join('');
  } catch (err) {
    resEl.innerHTML = `<div style="text-align: center; color: var(--danger); padding: 2rem;">Error: ${err.message}</div>`;
  }
}
</script>

<!-- Overlay Pantalla de Bloqueo de Caja (Lock Screen) -->
<div id="posLockOverlay" class="pos-lock-overlay" style="display: none;">
  <div class="pos-lock-card">
    <!-- Reloj Digital en Tiempo Real -->
    <div class="pos-lock-clock-box">
      <div id="posLockClockTime" class="pos-lock-clock-time">00:00:00</div>
      <div id="posLockClockDate" class="pos-lock-clock-date">Cargando fecha...</div>
    </div>

    <!-- Icono y Título de Bloqueo -->
    <div class="pos-lock-icon-circle">
      <i class="fa-solid fa-lock"></i>
    </div>
    <h2 class="pos-lock-title">Terminal de Caja Bloqueada</h2>
    <p class="pos-lock-subtitle">Tu venta en curso y turno de caja están protegidos.</p>

    <!-- Usuario / Cajero en Turno -->
    <div class="pos-lock-user-badge">
      <div style="display: flex; align-items: center; gap: 0.6rem;">
        <div class="pos-lock-user-avatar">
          <i class="fa-solid fa-user"></i>
        </div>
        <div style="text-align: left;">
          <div style="font-weight: 700; color: #fff; font-size: 0.95rem;">
            <?= htmlspecialchars($user['nombre'] ?? 'Cajero') ?>
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted);">
            @<?= htmlspecialchars($user['usuario'] ?? '') ?> &bull; <?= htmlspecialchars($user['rol'] ?? 'Cajero') ?>
          </div>
        </div>
      </div>
      <span class="badge badge-success" style="font-size: 0.75rem;">
        <i class="fa-solid fa-circle-check"></i> Turno Activo
      </span>
    </div>

    <!-- Formulario de Desbloqueo -->
    <form id="formPosLockUnlock" onsubmit="desbloquearCaja(event)" style="margin-top: 1.25rem;">
      <div style="margin-bottom: 0.85rem;">
        <input type="password" id="posLockPassword" class="form-control pos-lock-input" placeholder="Ingresa tu contraseña o PIN..." autocomplete="current-password" required>
      </div>

      <div id="posLockError" class="pos-lock-error" style="display: none;"></div>

      <button type="submit" id="btnPosUnlock" class="btn btn-primary btn-block pos-lock-btn">
        <i class="fa-solid fa-lock-open"></i> Desbloquear Terminal
      </button>
    </form>

    <!-- Ayuda / Atajo -->
    <div class="pos-lock-footer-note">
      <i class="fa-solid fa-shield-halved" style="color: var(--primary); font-size: 1.1rem; margin-top: 0.15rem;"></i>
      <div>
        Puede desbloquear el cajero titular o cualquier <strong>supervisor/administrador</strong> con su contraseña.<br>
        Atajo rápido: <kbd>Alt + L</kbd> o <kbd>F9</kbd> para bloquear.
      </div>
    </div>
  </div>
</div>

<script>
  window.BALANZA_PREFIJO_INDIVIDUAL = "<?= htmlspecialchars($configBalanza['BALANZA_PREFIJO_INDIVIDUAL'] ?? '20') ?>";
  window.BALANZA_TIPO_EAN = "<?= htmlspecialchars($configBalanza['BALANZA_TIPO_EAN'] ?? 'plu_peso') ?>";
  window.COTIZACION_PRELOAD = <?= $cotizacionPreload > 0 ? $cotizacionPreload : 'null' ?>;
  window.CONFIG_SUPERVISION = <?= json_encode($configSupervision) ?>;
  window.MODULO_SUPERVISOR = <?= modulo_activo('supervisor') ? 'true' : 'false' ?>;
  window.MESAS_IMPRIMIR_COMANDA = <?= json_encode($mesasImprimirComanda ?? 'SI') ?>;
  window.PROPINA_ACTIVA = <?= !empty($propinaActiva) ? 'true' : 'false' ?>;
  window.PROPINA_PORCENTAJE = <?= (int)($propinaPorcentaje ?? 10) ?>;
  window.PERMITIR_STOCK_NEGATIVO = <?= !empty($permitirStockNegativo) ? 'true' : 'false' ?>;
  window.REDONDEO_LEY_ACTIVO = <?= !empty($redondeoLeyActivo) ? 'true' : 'false' ?>;
  window.CURRENT_USER_ROL = "<?= htmlspecialchars($user['rol'] ?? 'Cajero') ?>";
  window.CURRENT_USER_NAME = "<?= htmlspecialchars($user['nombre'] ?? '') ?>";
  window.POS_DISENO_GRID_DEFAULT = "<?= htmlspecialchars($posDisenoGridDefault ?? 'estandar') ?>";
  window.POS_LAYOUT_MODO = "<?= htmlspecialchars($posLayoutModo ?? 'supermercado') ?>";
  window.CSRF_TOKEN = "<?= htmlspecialchars(csrfToken()) ?>";
</script>

<script src="<?= asset_url('assets/js/pos.js') ?>"></script>
<?php if (modulo_activo('mesas')): ?>
<script src="<?= asset_url('assets/js/mesas_caja.js') ?>"></script>
<?php endif; ?>
