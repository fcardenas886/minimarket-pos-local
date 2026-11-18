<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$esSupervisorNav = in_array($user['rol'], ['Administrador', 'Supervisor'], true);

// Cargar personalización visual
$pdoHeader = getDB();
$stmtAppCfg = $pdoHeader->query("SELECT Clave, Valor FROM configuraciones WHERE Clave IN ('TEMA_MODO', 'TEMA_COLOR_ACENTO', 'MINIMARKET_LOGO_URL', 'MINIMARKET_NOMBRE')");
$appCfg = [];
while ($r = $stmtAppCfg->fetch(PDO::FETCH_ASSOC)) {
    $appCfg[$r['Clave']] = $r['Valor'];
}
$temaModo = $appCfg['TEMA_MODO'] ?? 'dark';
$temaAcento = $appCfg['TEMA_COLOR_ACENTO'] ?? 'indigo';
$logoUrl = $appCfg['MINIMARKET_LOGO_URL'] ?? '';
$nombreEmpresa = $appCfg['MINIMARKET_NOMBRE'] ?? 'Minimarket POS';
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= htmlspecialchars($temaModo) ?>" data-accent="<?= htmlspecialchars($temaAcento) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($nombreEmpresa) ?> - Sistema Web</title>
  <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
  <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
  <script>
    (function() {
      try {
        const localTheme = localStorage.getItem('theme_mode');
        if (localTheme) document.documentElement.setAttribute('data-theme', localTheme);
      } catch (e) {}
    })();
  </script>
</head>
<body>

<?php
$changelogActual = APP_CHANGELOG[APP_VERSION] ?? [];
?>
<!-- Pantalla de bienvenida tras actualizar: barra de progreso + novedades reales -->
<div id="updateOverlay" class="update-overlay" style="display: none;">
  <div class="update-card">

    <div class="update-loader">
      <div class="update-loader__ring"></div>
      <i class="fa-solid fa-cloud-arrow-down update-loader__icon"></i>
    </div>

    <div>
      <h2 class="update-title">Actualizando Sistema</h2>
      <p class="update-subtitle">Aplicando la versión <strong><?= htmlspecialchars(APP_VERSION) ?></strong>. Esto tomará solo unos segundos&hellip;</p>
    </div>

    <div class="update-progress">
      <div id="updateProgressBar" class="update-progress__bar"></div>
    </div>
    <span id="updateProgressText" class="update-progress__text">0%</span>

    <?php if ($changelogActual): ?>
    <div class="update-changelog">
      <div class="update-changelog__label">Novedades de esta versión</div>
      <ul>
        <?php foreach ($changelogActual as $item): ?>
          <li><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($item) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
  (function() {
    const CURRENT_VERSION = '<?= APP_VERSION ?>';
    let lastSeen = null;
    try { lastSeen = localStorage.getItem('last_seen_version'); } catch (e) {}
    if (lastSeen === CURRENT_VERSION) return;

    const overlay = document.getElementById('updateOverlay');
    const bar = document.getElementById('updateProgressBar');
    const txt = document.getElementById('updateProgressText');
    const bullets = Array.from(document.querySelectorAll('.update-changelog li'));
    overlay.style.display = 'flex';

    let progress = 0;
    const interval = setInterval(() => {
      progress = Math.min(100, progress + Math.floor(Math.random() * 8) + 4);
      bar.style.width = progress + '%';
      txt.textContent = progress + '%';

      // Revelar las novedades a medida que avanza la barra
      const revelar = Math.floor((progress / 100) * bullets.length);
      bullets.forEach((li, i) => { if (i < revelar) li.classList.add('is-visible'); });

      if (progress === 100) {
        clearInterval(interval);
        bullets.forEach(li => li.classList.add('is-visible'));
        setTimeout(() => {
          overlay.classList.add('is-hiding');
          setTimeout(() => {
            overlay.style.display = 'none';
            try { localStorage.setItem('last_seen_version', CURRENT_VERSION); } catch (e) {}
          }, 400);
        }, 900);
      }
    }, 120);
  })();
</script>

<script>window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;</script>
<script src="<?= asset_url('assets/js/ui.js') ?>"></script>
<script src="<?= asset_url('assets/js/actualizador.js') ?>"></script>

<!-- Modal de Notificación de Actualización Remota -->
<div id="modalActualizacionPOS" class="update-overlay" style="display: none; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(6px); z-index: 10000;">
  <div class="update-card" style="background: var(--card-bg); border: 1px solid var(--border-dark); border-radius: 20px; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); max-width: 520px;">
    
    <!-- Vista 1: Detalle y novedades de la actualización -->
    <div id="updateModalVistaDetalle" style="width: 100%;">
      <div style="display: flex; justify-content: center; margin-bottom: 1rem;">
        <div style="width: 64px; height: 64px; border-radius: 18px; background: rgba(99, 102, 241, 0.15); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.8rem;">
          <i class="fa-solid fa-cloud-arrow-down"></i>
        </div>
      </div>

      <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
        ¡Nueva Versión Disponible!
      </h2>
      <p style="color: var(--text-muted); font-size: 0.92rem; margin-bottom: 1.25rem;">
        Hay una actualización lista: <strong id="updateModalNuevaVersion" style="color: var(--primary); font-size: 1.05rem;"></strong> 
        <span id="updateModalTamano" style="font-size: 0.8rem; color: var(--text-muted);"></span><br>
        <span style="font-size: 0.82rem;">Tu versión actual es <strong id="updateModalVersionActual"></strong> &bull; <span id="updateModalFecha"></span></span>
      </p>

      <div id="updateModalLicenciaAviso" style="display: none; background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; border-radius: 8px; padding: 0.75rem; font-size: 0.85rem; color: var(--text-main); margin-bottom: 1rem; text-align: left;"></div>

      <div class="update-changelog" style="max-height: 220px; overflow-y: auto; margin-bottom: 1.5rem; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-dark); border-radius: 12px; padding: 0.9rem 1.1rem; text-align: left;">
        <div class="update-changelog__label" style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.6rem;">Novedades y mejoras</div>
        <ul id="updateModalNovedades" style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem;"></ul>
      </div>

      <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
        <button type="button" id="btnIniciarActualizacion" onclick="ejecutarActualizacion()" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-size: 0.95rem; font-weight: 700; border-radius: 10px;">
          <i class="fa-solid fa-cloud-arrow-down"></i> Actualizar Ahora
        </button>
        <button type="button" onclick="cerrarModalActualizacion(true)" class="btn btn-secondary" style="padding: 0.75rem 1.25rem; font-size: 0.95rem; border-radius: 10px;">
          Más Tarde
        </button>
      </div>
    </div>

    <!-- Vista 2: Descargando e instalando en segundo plano -->
    <div id="updateModalVistaProgreso" style="display: none; width: 100%; flex-direction: column; align-items: center; gap: 1.25rem;">
      <div class="update-loader">
        <div class="update-loader__ring"></div>
        <i class="fa-solid fa-arrows-rotate fa-spin update-loader__icon"></i>
      </div>

      <div>
        <h3 id="updateProgresoEstado" style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">
          Descargando actualización...
        </h3>
        <p id="updateProgresoSubtexto" style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.5;">
          Por favor espere, no cierre la ventana ni apague el equipo.
        </p>
      </div>

      <button type="button" id="updateModalBtnCerrar" onclick="cerrarModalActualizacion(false)" class="btn btn-secondary" style="display: none; margin-top: 0.5rem;">
        Cerrar
      </button>
    </div>

  </div>
</div>

<nav class="navbar">
  <a href="index.php" class="brand" style="display: flex; align-items: center; gap: 0.6rem; text-decoration: none;">
    <?php if (!empty($logoUrl)): ?>
      <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="brand-logo-img">
    <?php else: ?>
      <div class="brand-icon">
        <i class="fa-solid fa-store"></i>
      </div>
    <?php endif; ?>
    <span><?= htmlspecialchars($nombreEmpresa) ?></span>
  </a>

  <ul class="nav-links">
    <!-- Inicio -->
    <li class="nav-item">
      <a href="index.php" class="nav-link <?= $currentPage == 'index.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-pie"></i> Inicio
      </a>
    </li>

    <!-- Caja POS -->
    <li class="nav-item">
      <a href="pos.php" class="nav-link <?= $currentPage == 'pos.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-cart-shopping"></i> Caja POS
      </a>
    </li>

    <!-- Grupo: Caja y Ventas -->
    <li class="nav-item">
      <div class="nav-link <?= in_array($currentPage, ['caja.php', 'ventas.php', 'devoluciones.php', 'cotizaciones.php']) ? 'active' : '' ?>">
        <i class="fa-solid fa-cash-register"></i> Caja y Ventas <i class="fa-solid fa-chevron-down nav-caret"></i>
      </div>
      <ul class="dropdown-menu" style="min-width: 230px;">
        <!-- Sección: Caja -->
        <li class="dropdown-section" style="--section-color: #f59e0b;">
          <i class="fa-solid fa-wallet"></i> Operaciones de Caja
        </li>
        <li><a href="caja.php" class="dropdown-item"><i class="fa-solid fa-wallet"></i> Turnos y Arqueo</a></li>
        
        <li class="dropdown-divider"></li>

        <!-- Sección: Ventas -->
        <li class="dropdown-section" style="--section-color: #3b82f6;">
          <i class="fa-solid fa-receipt"></i> Ventas y Devoluciones
        </li>
        <li><a href="ventas.php" class="dropdown-item"><i class="fa-solid fa-receipt"></i> Ventas y Comprobantes</a></li>
        <?php if (modulo_activo('devoluciones')): ?>
        <li><a href="devoluciones.php" class="dropdown-item"><i class="fa-solid fa-rotate-left"></i> Devoluciones y Vales</a></li>
        <?php endif; ?>
        <?php if (modulo_activo('cotizaciones')): ?>
        <li><a href="cotizaciones.php" class="dropdown-item"><i class="fa-solid fa-file-lines"></i> Cotizaciones</a></li>
        <?php endif; ?>
      </ul>
    </li>

    <!-- Grupo: Mantenedores -->
    <li class="nav-item">
      <div class="nav-link <?= in_array($currentPage, ['productos.php', 'actualizar_precios.php', 'categorias.php', 'clientes.php', 'promociones.php']) ? 'active' : '' ?>">
        <i class="fa-solid fa-folder-open"></i> Mantenedores <i class="fa-solid fa-chevron-down nav-caret"></i>
      </div>
      <ul class="dropdown-menu">
        <?php if ($esSupervisorNav): ?>
        <li><a href="productos.php" class="dropdown-item"><i class="fa-solid fa-box"></i> Productos y Precios</a></li>
        <?php if (modulo_activo('precios_rapidos')): ?>
        <li><a href="actualizar_precios.php" class="dropdown-item"><i class="fa-solid fa-bolt" style="color: #f59e0b;"></i> Actualizador Rápido Precios</a></li>
        <?php endif; ?>
        <li><a href="categorias.php" class="dropdown-item"><i class="fa-solid fa-folder"></i> Categorías</a></li>
        <?php endif; ?>
        <?php if (modulo_activo('clientes')): ?>
        <li><a href="clientes.php" class="dropdown-item"><i class="fa-solid fa-users"></i> Clientes y Fiado</a></li>
        <?php endif; ?>
        <?php if ($esSupervisorNav && modulo_activo('promociones')): ?>
        <li><a href="promociones.php" class="dropdown-item"><i class="fa-solid fa-tags"></i> Ofertas y Promociones</a></li>
        <?php endif; ?>
      </ul>
    </li>

    <!-- Grupo: Stock y Compras -->
    <li class="nav-item">
      <div class="nav-link <?= in_array($currentPage, ['kardex.php', 'ajustes.php', 'alertas_stock.php', 'inventario.php', 'notaspedido.php', 'compras.php', 'proveedores.php']) ? 'active' : '' ?>">
        <i class="fa-solid fa-boxes-stacked"></i> Stock y Compras <i class="fa-solid fa-chevron-down nav-caret"></i>
      </div>
      <ul class="dropdown-menu" style="min-width: 250px;">
        <!-- Sección: Inventario -->
        <li class="dropdown-section" style="--section-color: #818cf8;">
          <i class="fa-solid fa-warehouse"></i> Control de Stock
        </li>
        <?php if ($esSupervisorNav): ?>
        <li><a href="productos.php" class="dropdown-item"><i class="fa-solid fa-plus-minus"></i> Ingresar / Ajustar Stock</a></li>
        <?php endif; ?>
        <?php if (modulo_activo('kardex')): ?>
        <li><a href="kardex.php" class="dropdown-item"><i class="fa-solid fa-arrow-right-arrow-left"></i> Kardex de Movimientos</a></li>
        <?php if ($esSupervisorNav): ?>
        <li><a href="ajustes.php" class="dropdown-item"><i class="fa-solid fa-sliders"></i> Ajustes de Stock</a></li>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($esSupervisorNav && modulo_activo('inventario')): ?>
        <li><a href="inventario.php" class="dropdown-item"><i class="fa-solid fa-clipboard-list"></i> Toma de Inventario</a></li>
        <?php endif; ?>
        <li><a href="alertas_stock.php" class="dropdown-item"><i class="fa-solid fa-triangle-exclamation"></i> Alertas de Stock</a></li>

        <!-- Separador y Compras (solo para Supervisor) -->
        <?php if ($esSupervisorNav && modulo_activo('compras')): ?>
        <li class="dropdown-divider"></li>

        <li class="dropdown-section" style="--section-color: #10b981;">
          <i class="fa-solid fa-truck-ramp-box"></i> Compras y Proveedores
        </li>
        <?php if (modulo_activo('notaspedido')): ?>
        <li><a href="notaspedido.php" class="dropdown-item"><i class="fa-solid fa-file-signature"></i> Notas de Pedido</a></li>
        <?php endif; ?>
        <li><a href="compras.php" class="dropdown-item"><i class="fa-solid fa-truck-arrow-right"></i> Recepción de Compras</a></li>
        <li><a href="proveedores.php" class="dropdown-item"><i class="fa-solid fa-truck"></i> Proveedores</a></li>
        <?php endif; ?>
      </ul>
    </li>

    <!-- Grupo: Reportes -->
    <?php if ($esSupervisorNav): ?>
    <li class="nav-item">
      <div class="nav-link <?= in_array($currentPage, ['reportes.php', 'reporte_utilidades.php', 'reportes_z.php']) ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-line"></i> Reportes <i class="fa-solid fa-chevron-down nav-caret"></i>
      </div>
      <ul class="dropdown-menu" style="min-width: 250px;">
        <li><a href="reportes.php" class="dropdown-item"><i class="fa-solid fa-chart-pie" style="color: var(--primary);"></i> <strong>Centro de Reportes</strong></a></li>
        <li class="dropdown-divider"></li>
        <li><a href="reportes.php?tab=ventas" class="dropdown-item"><i class="fa-solid fa-money-bill-wave"></i> Ventas y Medios de Pago</a></li>
        <li><a href="reportes.php?tab=productos" class="dropdown-item"><i class="fa-solid fa-cubes-stacked"></i> Ranking y Rotación</a></li>
        <li><a href="reportes.php?tab=inventario" class="dropdown-item"><i class="fa-solid fa-warehouse"></i> Valorización Inventario</a></li>
        <?php if (modulo_activo('clientes')): ?>
        <li><a href="reportes.php?tab=creditos" class="dropdown-item"><i class="fa-solid fa-handshake"></i> Cartera y Fiados</a></li>
        <?php endif; ?>
        <?php if (modulo_activo('compras')): ?>
        <li><a href="reportes.php?tab=compras" class="dropdown-item"><i class="fa-solid fa-truck-ramp-box"></i> Compras y Proveedores</a></li>
        <?php endif; ?>
        <?php if (modulo_activo('reportes_cajeros')): ?>
        <li><a href="reportes.php?tab=cajeros" class="dropdown-item"><i class="fa-solid fa-users-gear"></i> Rendimiento de Cajeros</a></li>
        <?php endif; ?>
        <?php if (modulo_activo('utilidades')): ?>
        <li><a href="reportes.php?tab=utilidades" class="dropdown-item"><i class="fa-solid fa-sack-dollar"></i> Utilidades y Márgenes</a></li>
        <?php endif; ?>
        <li class="dropdown-divider"></li>
        <li><a href="reportes_z.php" class="dropdown-item"><i class="fa-solid fa-file-invoice-dollar"></i> Cierre Z Fiscal</a></li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Grupo: Administración -->
    <?php if (in_array($user['rol'], ['Administrador', 'Supervisor'])): ?>
    <li class="nav-item">
      <div class="nav-link <?= in_array($currentPage, ['usuarios.php', 'cajas.php', 'configuracion.php', 'ayuda.php']) ? 'active' : '' ?>">
        <i class="fa-solid fa-shield-halved"></i> Admin <i class="fa-solid fa-chevron-down nav-caret"></i>
      </div>
      <ul class="dropdown-menu">
        <li><a href="usuarios.php" class="dropdown-item"><i class="fa-solid fa-user-gear"></i> Usuarios y Roles</a></li>
        <?php if (modulo_activo('multicaja')): ?>
        <li><a href="cajas.php" class="dropdown-item"><i class="fa-solid fa-cash-register"></i> Cajas Físicas</a></li>
        <?php endif; ?>
        <li><a href="configuracion.php?tab=licencia" class="dropdown-item"><i class="fa-solid fa-key"></i> Licencia</a></li>
        <li><a href="configuracion.php" class="dropdown-item"><i class="fa-solid fa-sliders"></i> Ajustes Generales</a></li>
        <li><a href="ayuda.php" class="dropdown-item"><i class="fa-solid fa-circle-question"></i> Soporte / Ayuda</a></li>
        <li><a href="acerca.php" class="dropdown-item"><i class="fa-solid fa-circle-info"></i> Acerca de y Novedades</a></li>
      </ul>
    </li>
    <?php endif; ?>
  </ul>

  <div style="display: flex; align-items: center; gap: 0.75rem;">
    <!-- Botón Modo Claro / Oscuro -->
    <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleThemeLive()" title="Alternar Modo Claro / Oscuro">
      <i class="fa-solid fa-sun" id="themeToggleIcon"></i>
    </button>

    <div class="user-pill">
      <i class="fa-solid fa-user"></i>
      <span><?= htmlspecialchars($user['nombre']) ?></span>
      <span class="user-badge"><?= htmlspecialchars($user['rol']) ?></span>
      <a href="logout.php" title="Cerrar Sesión" style="color: var(--danger); margin-left: 0.5rem;">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </div>
</nav>

<?php $licNav = licencia_estado(); if (!$licNav['valida'] && $currentPage !== 'pos.php'): ?>
<div style="background: rgba(245, 158, 11, 0.15); border-bottom: 1px solid rgba(245, 158, 11, 0.4); color: var(--text-main, inherit); padding: 0.5rem 1rem; font-size: 0.85rem; text-align: center;">
  <i class="fa-solid fa-key" style="color: #f59e0b;"></i>
  Sistema sin licencia válida: funciona como plan Básico y no podrá emitir boletas electrónicas.
  <?= htmlspecialchars($licNav['error'] ?? '') ?>
  <?php if ($esSupervisorNav): ?><a href="configuracion.php?tab=licencia" style="margin-left: 0.5rem;">Instalar licencia</a><?php endif; ?>
</div>
<?php endif; ?>

<script>
// Menús desplegables: se abren con el mouse (CSS) y también con clic o toque
// (pantallas táctiles de caja). Clic afuera o Escape los cierra.
(function () {
  const items = document.querySelectorAll('.nav-links > .nav-item');
  const cerrarTodos = (excepto) => items.forEach(li => { if (li !== excepto) li.classList.remove('abierto', 'cerrando'); });
  items.forEach(li => {
    const boton = li.querySelector(':scope > div.nav-link');
    if (!boton || !li.querySelector('.dropdown-menu')) return;
    boton.setAttribute('role', 'button');
    boton.tabIndex = 0;
    const alternar = (e) => {
      e.preventDefault();
      e.stopPropagation();
      const abrir = !li.classList.contains('abierto');
      cerrarTodos();
      if (abrir) li.classList.add('abierto');
    };
    boton.addEventListener('click', alternar);
    boton.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') alternar(e); });
    // Al pasar el mouse a otro menú, el abierto con clic se cierra para que no queden dos.
    li.addEventListener('mouseenter', () => { clearTimeout(li._t); cerrarTodos(li); });
    // Al salir, el menú espera un instante antes de cerrarse (un desvío del mouse no lo cierra).
    li.addEventListener('mouseleave', () => {
      li.classList.add('cerrando');
      clearTimeout(li._t);
      li._t = setTimeout(() => li.classList.remove('cerrando'), 300);
    });
  });
  document.addEventListener('click', (e) => { if (!e.target.closest('.nav-links')) cerrarTodos(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarTodos(); });
})();

function toggleThemeLive() {
  const cur = document.documentElement.getAttribute('data-theme') || 'dark';
  const next = (cur === 'dark') ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  try { localStorage.setItem('theme_mode', next); } catch(e) {}
  updateThemeIcon();
}
function updateThemeIcon() {
  const cur = document.documentElement.getAttribute('data-theme') || 'dark';
  const icon = document.getElementById('themeToggleIcon');
  if (icon) {
    icon.className = (cur === 'dark') ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
  }
}
document.addEventListener('DOMContentLoaded', updateThemeIcon);
</script>

<main class="container">
