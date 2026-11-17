<div style="margin-bottom: 1.5rem;">
  <h1 style="font-size: 1.5rem; font-weight: 700;">Parametrización y Configuración del Sistema</h1>
  <p style="color: var(--text-muted); font-size: 0.9rem;">Parámetros del negocio, terminales de caja y boleta electrónica SII</p>
</div>

<?php if (!empty($message)): ?>
  <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: #34d399; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem;">
    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?>
  </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #f87171; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem;">
    <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<!-- Tabs de Navegación de Configuración -->
<div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-dark); padding-bottom: 0.5rem;">
  <button onclick="switchTab('tabGenerales')" class="btn btn-secondary tab-btn active" id="btn-tabGenerales">
    ⚙️ Parámetros Generales
  </button>
  <button onclick="switchTab('tabTerminales')" class="btn btn-secondary tab-btn" id="btn-tabTerminales">
    💻 Terminales de Caja
  </button>
  <button onclick="switchTab('tabHaulmer')" class="btn btn-secondary tab-btn" id="btn-tabHaulmer">
    🧾 Boleta Electrónica (SII)
  </button>
  <button onclick="switchTab('tabBalanza')" class="btn btn-secondary tab-btn" id="btn-tabBalanza">
    ⚖️ Balanza de Pesaje
  </button>
  <?php if (modulo_activo('supervisor')): ?>
  <button onclick="switchTab('tabSupervision')" class="btn btn-secondary tab-btn" id="btn-tabSupervision">
    🛡️ Supervisión en Caja (POS)
  </button>
  <?php endif; ?>
  <button onclick="switchTab('tabApariencia')" class="btn btn-secondary tab-btn" id="btn-tabApariencia">
    🎨 Apariencia y Marca
  </button>
  <?php if (modulo_activo('mesas')): ?>
  <button onclick="switchTab('tabMesas')" class="btn btn-secondary tab-btn" id="btn-tabMesas">
    🍽️ Mesas y garzones
  </button>
  <?php endif; ?>
  <button onclick="switchTab('tabLicencia')" class="btn btn-secondary tab-btn" id="btn-tabLicencia">
    🔑 Licencia
  </button>
  <button onclick="switchTab('tabActualizaciones')" class="btn btn-secondary tab-btn" id="btn-tabActualizaciones">
    <i class="fa-solid fa-cloud-arrow-down"></i> Actualizaciones
  </button>
</div>

<!-- PESTAÑA: LICENCIA -->
<div id="tabLicencia" class="tab-pane" style="display: none;">
  <div class="table-card" style="padding: 1.75rem; max-width: 800px;">
    <h3 style="font-size: 1.1rem; margin-bottom: 1rem;"><i class="fa-solid fa-key"></i> Licencia del sistema</h3>

    <?php if ($licencia['valida']): ?>
      <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid var(--success); border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem;">
        <div style="font-weight: 700; font-size: 1.05rem; margin-bottom: 0.4rem;">
          <i class="fa-solid fa-circle-check" style="color: var(--success);"></i> Plan <?= htmlspecialchars($licencia['plan_nombre']) ?> — licencia de por vida
        </div>
        <div style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.7;">
          Cliente: <strong><?= htmlspecialchars((string)$licencia['cliente']) ?></strong><br>
          RUT: <strong><?= htmlspecialchars((string)$licencia['rut']) ?></strong><br>
          Actualizaciones incluidas hasta: <strong><?= htmlspecialchars((string)$licencia['actualizaciones_hasta']) ?></strong>
          <?php if ($licencia['actualizaciones_hasta'] && $licencia['actualizaciones_hasta'] < date('Y-m-d')): ?>
            <span style="color: #f59e0b;">(vencidas: el sistema sigue funcionando, pero las actualizaciones nuevas requieren renovar el soporte)</span>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid #f59e0b; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem; font-size: 0.9rem;">
        <strong><i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i> Sin licencia válida.</strong>
        <?= htmlspecialchars((string)$licencia['error']) ?><br>
        El sistema funciona como plan Básico y no podrá emitir boletas electrónicas hasta instalar una licencia.
      </div>
    <?php endif; ?>

    <table class="table" style="margin-bottom: 1.25rem;">
      <thead><tr><th>Incluido en tu plan</th><th style="text-align:center;">Estado</th></tr></thead>
      <tbody>
        <tr><td>Caja POS, turnos, ventas, productos, stock simple, reportes de ventas</td><td style="text-align:center;">✅</td></tr>
        <?php foreach (LICENCIA_PLANES['comercio']['modulos'] as $mod): ?>
          <tr>
            <td><?= htmlspecialchars(LICENCIA_NOMBRES_MODULO[$mod]) ?></td>
            <td style="text-align:center;"><?= modulo_activo($mod) ? '✅' : '<span style="color: var(--text-muted);">Plan Comercio</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach (LICENCIA_EXTRAS as $mod => $nomExtra): ?>
          <tr>
            <td><?= htmlspecialchars($nomExtra) ?></td>
            <td style="text-align:center;"><?= modulo_activo($mod) ? '✅' : '<span style="color: var(--text-muted);">Módulo adicional</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td>Usuarios activos (sin contar garzones)</td>
          <td style="text-align:center;"><?= $licUsuarios['activos'] ?> de <?= $licUsuarios['max'] ?></td>
        </tr>
      </tbody>
    </table>

    <?php if ($user['rol'] === 'Administrador'): ?>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="instalar_licencia">
      <label style="display:block; font-weight: 600; margin-bottom: 0.4rem;">Instalar o cambiar licencia</label>
      <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
        Pega el texto de licencia que te entregó tu proveedor (empieza con <code>MML1.</code>).
        Debe corresponder al RUT del negocio configurado en Parámetros Generales.
      </p>
      <textarea name="licencia_texto" rows="4" class="form-control" style="width: 100%; font-family: monospace; font-size: 0.8rem;" required></textarea>
      <button type="submit" class="btn btn-primary" style="margin-top: 0.75rem;"><i class="fa-solid fa-key"></i> Instalar licencia</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- PESTAÑA: ACTUALIZACIONES REMOTAS -->
<div id="tabActualizaciones" class="tab-pane" style="display: none;">
  <div class="table-card" style="padding: 1.75rem; max-width: 800px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-dark); padding-bottom: 1rem;">
      <div>
        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.25rem;">
          <i class="fa-solid fa-cloud-arrow-down" style="color: var(--primary);"></i> Actualizaciones del Sistema
        </h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
          Permite recibir nuevas versiones, correcciones y funciones directamente sin visitas presenciales.
        </p>
      </div>

      <button type="button" id="btnBuscarActualizacionesManual" onclick="comprobarActualizaciones(true)" class="btn btn-primary" style="padding: 0.6rem 1.25rem; font-size: 0.9rem;">
        <i class="fa-solid fa-arrows-rotate"></i> Buscar actualizaciones ahora
      </button>
    </div>

    <!-- Ficha de estado actual -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.75rem;">
      <div style="background: var(--surface-dark); border: 1px solid var(--border-dark); border-radius: 12px; padding: 1rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Versión Instalada</div>
        <div style="font-size: 1.35rem; font-weight: 800; color: var(--success); font-family: monospace;">
          <?= htmlspecialchars(APP_VERSION) ?>
        </div>
      </div>

      <div style="background: var(--surface-dark); border: 1px solid var(--border-dark); border-radius: 12px; padding: 1rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Última Comprobación</div>
        <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-top: 0.25rem;">
          <?= !empty($config['UPDATE_ULTIMA_REVISION']) ? htmlspecialchars($config['UPDATE_ULTIMA_REVISION']) : 'Nunca' ?>
        </div>
      </div>

      <div style="background: var(--surface-dark); border: 1px solid var(--border-dark); border-radius: 12px; padding: 1rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Soporte de Licencia</div>
        <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-top: 0.25rem;">
          <?= !empty($licencia['actualizaciones_hasta']) ? 'Hasta ' . htmlspecialchars($licencia['actualizaciones_hasta']) : 'Sin límite' ?>
        </div>
      </div>
    </div>

    <!-- Formulario de Configuración -->
    <form method="POST" action="configuracion.php" style="display: flex; flex-direction: column; gap: 1.25rem;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_config">

      <div>
        <label style="display: block; font-weight: 600; margin-bottom: 0.4rem;">
          URL del Manifiesto de Actualizaciones (version.json)
        </label>
        <input type="url" name="config[UPDATE_CHECK_URL]" value="<?= htmlspecialchars($config['UPDATE_CHECK_URL'] ?? '') ?>" class="form-control" style="width: 100%; font-family: monospace; font-size: 0.85rem;" placeholder="https://raw.githubusercontent.com/fcardenas886/minimarket-pos-local/master/version.json">
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">
          Enlace directo al archivo <code>version.json</code>. Si lo dejas vacío, usa por defecto tu repositorio de GitHub (<code>fcardenas886/minimarket-pos-local</code>).
        </p>
      </div>

      <div>
        <label style="display: block; font-weight: 600; margin-bottom: 0.4rem;">
          Modo de Comprobación y Actualización
        </label>
        <?php $modoAct = $config['UPDATE_MODO'] ?? 'preguntar'; ?>
        <select name="config[UPDATE_MODO]" class="form-control" style="max-width: 450px;">
          <option value="preguntar" <?= $modoAct === 'preguntar' ? 'selected' : '' ?>>
            🔔 Preguntar al usuario antes de actualizar (Recomendado)
          </option>
          <option value="automatica" <?= $modoAct === 'automatica' ? 'selected' : '' ?>>
            ⚡ Actualizar automáticamente al iniciar el programa
          </option>
          <option value="desactivada" <?= $modoAct === 'desactivada' ? 'selected' : '' ?>>
            🚫 Desactivar comprobación automática (solo manual)
          </option>
        </select>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">
          En modo <em>Preguntar</em>, al abrir el POS o iniciar sesión se mostrará una ventana con las novedades para que el usuario confirme cuándo aplicar la actualización.
        </p>
      </div>

      <div style="margin-top: 0.5rem;">
        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-size: 1rem; font-weight: bold;">
          <i class="fa-solid fa-floppy-disk"></i> Guardar Ajustes de Actualización
        </button>
      </div>
    </form>
  </div>
</div>

<!-- PESTAÑA 1: PARÁMETROS GENERALES -->
<div id="tabGenerales" class="tab-pane">
  <div class="table-card" style="padding: 1.75rem; max-width: 800px;">
    <form method="POST" action="configuracion.php" style="display: flex; flex-direction: column; gap: 1.25rem;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_config">

      <h2 style="font-size: 1.1rem; font-weight: 600; color: #818cf8; border-bottom: 1px solid var(--border-dark); padding-bottom: 0.4rem;">Datos Comerciales de la Empresa</h2>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">NOMBRE COMERCIAL / RAZÓN SOCIAL *</label>
          <input type="text" name="config[MINIMARKET_NOMBRE]" value="<?= htmlspecialchars($config['MINIMARKET_NOMBRE'] ?? 'Minimarket & Negocio de Barrio') ?>" class="form-control" required>
        </div>
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">IVA DEFECTO (%)</label>
          <input type="number" name="config[IVA_PORCENTAJE]" value="<?= htmlspecialchars($config['IVA_PORCENTAJE'] ?? '19') ?>" class="form-control" required>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">RUT EMPRESA</label>
          <input type="text" name="config[MINIMARKET_RUT]" value="<?= htmlspecialchars($config['MINIMARKET_RUT'] ?? '76.543.210-K') ?>" class="form-control">
        </div>
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">GIRO COMERCIAL</label>
          <input type="text" name="config[MINIMARKET_GIRO]" value="<?= htmlspecialchars($config['MINIMARKET_GIRO'] ?? 'Minimarket y Venta de Abarrotes') ?>" class="form-control">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">DIRECCIÓN COMERCIAL</label>
          <input type="text" name="config[MINIMARKET_DIRECCION]" value="<?= htmlspecialchars($config['MINIMARKET_DIRECCION'] ?? 'Av. Principal #123, Santiago') ?>" class="form-control">
        </div>
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">TELÉFONO</label>
          <input type="text" name="config[MINIMARKET_TELEFONO]" value="<?= htmlspecialchars($config['MINIMARKET_TELEFONO'] ?? '+56 9 1234 5678') ?>" class="form-control">
        </div>
      </div>

      <div>
        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">MENSAJE PIE DE PÁGINA DEL TICKET</label>
        <input type="text" name="config[TICKET_PIE_PAGINA]" value="<?= htmlspecialchars($config['TICKET_PIE_PAGINA'] ?? '¡Gracias por su preferencia!') ?>" class="form-control">
      </div>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">PROPINA VOLUNTARIA EN CAJA</label>
          <select name="config[PROPINA_ACTIVA]" class="form-control">
            <option value="NO" <?= ($config['PROPINA_ACTIVA'] ?? 'NO') !== 'SI' ? 'selected' : '' ?>>No ofrecer propina</option>
            <option value="SI" <?= ($config['PROPINA_ACTIVA'] ?? '') === 'SI' ? 'selected' : '' ?>>Ofrecer propina al cobrar (restaurant, picada, café)</option>
          </select>
          <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;">
            La propina es voluntaria: se sugiere y el cliente puede dar menos o nada. No va en la boleta ni lleva IVA.
          </p>
        </div>
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">% SUGERIDO</label>
          <input type="number" name="config[PROPINA_PORCENTAJE]" min="1" max="30" step="1" value="<?= htmlspecialchars($config['PROPINA_PORCENTAJE'] ?? '10') ?>" class="form-control">
        </div>
      </div>

      <h2 style="font-size: 1.1rem; font-weight: 600; color: #818cf8; border-bottom: 1px solid var(--border-dark); padding-bottom: 0.4rem; margin-top: 0.5rem;">Módulos y Reglas del Sistema</h2>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">CLUB DE PUNTOS (FIDELIZACIÓN)</label>
          <select name="config[LOYALTY_ACTIVO]" class="form-control">
            <option value="true" <?= ($config['LOYALTY_ACTIVO'] ?? 'true') === 'true' ? 'selected' : '' ?>>Habilitado (Puntos por Compras)</option>
            <option value="false" <?= ($config['LOYALTY_ACTIVO'] ?? '') === 'false' ? 'selected' : '' ?>>Deshabilitado</option>
          </select>
        </div>
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">PAGO A CRÉDITO INTERNO (FIADO)</label>
          <select name="config[CREDITO_INTERNO_ACTIVO]" class="form-control">
            <option value="true" <?= ($config['CREDITO_INTERNO_ACTIVO'] ?? 'true') === 'true' ? 'selected' : '' ?>>Permitir Ventas Fiadas</option>
            <option value="false" <?= ($config['CREDITO_INTERNO_ACTIVO'] ?? '') === 'false' ? 'selected' : '' ?>>Solo Pago Inmediato</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">VENDER CON STOCK NEGATIVO</label>
          <select name="config[PERMITIR_STOCK_NEGATIVO]" class="form-control">
            <option value="false" <?= ($config['PERMITIR_STOCK_NEGATIVO'] ?? 'false') === 'false' ? 'selected' : '' ?>>No permitir (bloquear venta sin stock)</option>
            <option value="true" <?= ($config['PERMITIR_STOCK_NEGATIVO'] ?? '') === 'true' ? 'selected' : '' ?>>Permitir (el stock puede quedar en negativo)</option>
          </select>
          <p style="color: var(--text-muted); font-size: 0.72rem; margin-top: 0.25rem;">Útil si registras ventas antes de actualizar el stock de una recepción. Con "Permitir", el sistema deja vender aunque no alcance el stock registrado.</p>
        </div>
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">LEY DE REDONDEO EN EFECTIVO (LEY 20.956)</label>
          <select name="config[REDONDEO_LEY_ACTIVO]" class="form-control">
            <option value="true" <?= ($config['REDONDEO_LEY_ACTIVO'] ?? 'true') === 'true' ? 'selected' : '' ?>>Activo (1-5 baja a 0, 6-9 sube a 10)</option>
            <option value="false" <?= ($config['REDONDEO_LEY_ACTIVO'] ?? '') === 'false' ? 'selected' : '' ?>>Desactivado (cobro exacto)</option>
          </select>
          <p style="color: var(--text-muted); font-size: 0.72rem; margin-top: 0.25rem;">Regla legal obligatoria en Chile para pagos en efectivo por el retiro de monedas de $1 y $5. No afecta tarjetas ni transferencias.</p>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">SONIDO LECTOR RÁPIDO</label>
          <select name="config[SONIDO_LECTOR_RAPIDO]" class="form-control">
            <option value="SI" <?= ($config['SONIDO_LECTOR_RAPIDO'] ?? 'SI') === 'SI' ? 'selected' : '' ?>>Habilitado (Beep)</option>
            <option value="NO" <?= ($config['SONIDO_LECTOR_RAPIDO'] ?? '') === 'NO' ? 'selected' : '' ?>>Silencioso</option>
          </select>
        </div>
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">ARQUEO DE CAJA CIEGO</label>
          <select name="config[ARQUEO_CIEGO]" class="form-control">
            <option value="true" <?= ($config['ARQUEO_CIEGO'] ?? 'true') === 'true' ? 'selected' : '' ?>>Obligatorio (Oculta saldo en sistema)</option>
            <option value="false" <?= ($config['ARQUEO_CIEGO'] ?? '') === 'false' ? 'selected' : '' ?>>Muestra saldo esperado</option>
          </select>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="padding: 0.85rem; font-weight: bold;">
        <i class="fa-solid fa-floppy-disk"></i> Guardar Parámetros Generales
      </button>
    </form>
  </div>
</div>

<!-- PESTAÑA 2: TERMINALES DE CAJA -->
<div id="tabTerminales" class="tab-pane" style="display: none;">
  <div style="display: grid; grid-template-columns: 340px 1fr; gap: 1.5rem;">
    
    <!-- Formulario Vincular Terminal -->
    <div class="table-card" style="padding: 1.5rem;">
      <h2 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Vincular Nueva Terminal</h2>
      <form method="POST" action="configuracion.php" style="display: flex; flex-direction: column; gap: 1rem;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_terminal">

        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">NOMBRE EQUIPO (PC / HOSTNAME) *</label>
          <input type="text" name="nombre_equipo" class="form-control" required placeholder="Ej: CAJA-LOCAL-01, POS-BARRIO">
        </div>

        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">CAJA ASIGNADA *</label>
          <select name="caja_id" class="form-control" required>
            <?php foreach ($cajas as $cj): ?>
              <option value="<?= $cj['CajaID'] ?>"><?= htmlspecialchars($cj['Nombre']) ?> (#<?= $cj['CajaID'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-link"></i> Vincular equipo a Caja</button>
      </form>
    </div>

    <!-- Lista de Terminales vinculadas -->
    <div class="table-card">
      <div class="table-header">
        <h2 style="font-size: 1.1rem; font-weight: 600;">Terminales Mapeadas</h2>
      </div>
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre Equipo (PC)</th>
            <th>Caja Asignada</th>
            <th>Estado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($terminales)): ?>
            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No hay terminales vinculadas.</td></tr>
          <?php else: ?>
            <?php foreach ($terminales as $t): ?>
              <tr>
                <td>#<?= $t['TerminalID'] ?></td>
                <td style="font-weight: 600; color: #fff;"><code><?= htmlspecialchars($t['NombreEquipo']) ?></code></td>
                <td><?= htmlspecialchars($t['CajaName']) ?></td>
                <td><span class="badge badge-success">Activo</span></td>
                <td>
                  <form method="POST" action="configuracion.php" onsubmit="return confirm('¿Desvincular esta terminal?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_terminal">
                    <input type="hidden" name="terminal_id" value="<?= $t['TerminalID'] ?>">
                    <button type="submit" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;"><i class="fa-solid fa-trash-can"></i> Desvincular</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- PESTAÑA 3: BOLETA ELECTRÓNICA (e-Boleta SII) -->
<div id="tabHaulmer" class="tab-pane" style="display: none;">
  <div class="table-card" style="padding: 1.75rem; max-width: 800px; display: flex; flex-direction: column; gap: 1.25rem;">
    <h2 style="font-size: 1.1rem; font-weight: 600; border-bottom: 1px solid var(--border-dark); padding-bottom: 0.4rem; margin: 0;">
      <i class="fa-solid fa-receipt"></i> Boleta electrónica (e-Boleta del SII)
    </h2>

    <?php $provActual = $config['DTE_PROVEEDOR'] ?? 'ninguno'; ?>
    <?php if (in_array($provActual, ['eboleta', 'eboleta_simulador'], true)): ?>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
      <span class="badge <?= $eboletaResumen['Pendiente'] ? 'badge-warning' : 'badge-success' ?>">Pendientes: <?= $eboletaResumen['Pendiente'] ?></span>
      <span class="badge <?= $eboletaResumen['Revisar'] ? 'badge-danger' : 'badge-secondary' ?>">Por revisar: <?= $eboletaResumen['Revisar'] ?></span>
      <span class="badge <?= $eboletaResumen['Error'] ? 'badge-danger' : 'badge-secondary' ?>">Con error: <?= $eboletaResumen['Error'] ?></span>
      <a href="ventas.php?boleta=problemas" style="font-size: 0.85rem;">Ver en Ventas</a>
      <?php if ($eboletaResumen['Pendiente']): ?>
      <form method="POST" action="configuracion.php?tab=haulmer" style="margin-left: auto;">
        <?= csrfField() ?><input type="hidden" name="action" value="eboleta_enviar">
        <button class="btn btn-secondary" style="padding: 0.35rem 0.8rem; font-size: 0.85rem;"><i class="fa-solid fa-paper-plane"></i> Enviar pendientes ahora</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="configuracion.php?tab=haulmer" style="display: flex; flex-direction: column; gap: 1rem;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="guardar_eboleta">

      <div>
        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">EMISIÓN DE BOLETAS</label>
        <select name="dte_proveedor" id="dteProveedorSelect" class="form-control" onchange="toggleDteFields()">
          <option value="ninguno" <?= $provActual === 'ninguno' ? 'selected' : '' ?>>Desactivada (solo comprobante interno)</option>
          <option value="eboleta" <?= $provActual === 'eboleta' ? 'selected' : '' ?>>e-Boleta SII (boletas reales)</option>
          <option value="eboleta_simulador" <?= $provActual === 'eboleta_simulador' ? 'selected' : '' ?>>Simulador (pruebas, sin validez tributaria)</option>
        </select>
      </div>

      <div id="divConfigEboleta" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">RUT EMISOR (EMPRESA)</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($config['MINIMARKET_RUT'] ?? '') ?>" disabled>
          <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;">Se toma de Parámetros Generales.</p>
        </div>
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">RUT DE LA PERSONA QUE ENTRA AL SII</label>
          <input type="text" name="eboleta_rut_usuario" class="form-control" placeholder="12.345.678-9" value="<?= htmlspecialchars($config['EBOLETA_RUT_USUARIO'] ?? '') ?>" autocomplete="off">
          <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;">Dueño o representante con permiso para emitir boletas de la empresa.</p>
        </div>
        <div style="grid-column: 1 / -1;">
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">CLAVE TRIBUTARIA</label>
          <input type="password" name="eboleta_clave" class="form-control" autocomplete="new-password"
                 placeholder="<?= !empty($config['EBOLETA_CLAVE_CIFRADA']) ? 'Guardada (cifrada). Escribe solo si quieres cambiarla.' : 'Clave del SII de esa persona' ?>">
          <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;">Se guarda cifrada en este equipo y nunca se muestra.</p>
        </div>
      </div>

      <div id="divConfigSimulador" style="display: none;">
        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">SIMULAR</label>
        <select name="eboleta_simular_fallo" class="form-control">
          <?php foreach (['NO' => 'Emisión correcta', 'conexion' => 'Sin internet (queda pendiente)', 'rechazo' => 'Rechazo del SII', 'incierto' => 'SII no responde (queda por revisar)'] as $k => $t): ?>
            <option value="<?= $k ?>" <?= ($config['EBOLETA_SIMULAR_FALLO'] ?? 'NO') === $k ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="txtDteHelp" style="font-size: 0.8rem; color: var(--text-muted); background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.75rem;">
        La emisión usa el mismo sistema que la página <strong>eboleta.sii.cl</strong>. No es una conexión oficial documentada por el SII:
        si el SII cambia su página, la emisión puede dejar de funcionar hasta recibir una actualización. Mientras tanto
        las ventas siguen y las boletas quedan pendientes.
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
      </div>
    </form>

    <?php if (!empty($config['EBOLETA_RUT_USUARIO']) && !empty($config['EBOLETA_CLAVE_CIFRADA'])): ?>
    <form method="POST" action="configuracion.php?tab=haulmer">
      <?= csrfField() ?><input type="hidden" name="action" value="eboleta_probar">
      <button class="btn btn-secondary"><i class="fa-solid fa-plug-circle-check"></i> Probar conexión con el SII (no emite boletas)</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if (modulo_activo('mesas')): ?>
<!-- PESTAÑA: MESAS Y GARZONES -->
<div id="tabMesas" class="tab-pane" style="display: none;">
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.25rem; align-items: start;">

    <div class="table-card" style="padding: 1.5rem;">
      <h3 style="font-size: 1.05rem; margin: 0 0 0.75rem;"><i class="fa-solid fa-chair"></i> Mesas</h3>
      <form method="POST" action="configuracion.php?tab=mesas" style="display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1rem;">
        <?= csrfField() ?><input type="hidden" name="action" value="mesas_agregar">
        <div style="flex: 2; min-width: 140px;">
          <label style="font-size: 0.8rem; font-weight: 600;">Zona</label>
          <input name="zona" class="form-control" list="zonasSugeridas" placeholder="Salón" required>
          <datalist id="zonasSugeridas"><option value="Salón"><option value="Terraza"><option value="Barra"></datalist>
        </div>
        <div style="flex: 1; min-width: 80px;">
          <label style="font-size: 0.8rem; font-weight: 600;">Cantidad</label>
          <input name="cantidad" type="number" min="1" max="50" value="4" class="form-control">
        </div>
        <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Crear</button>
      </form>
      <?php if (!$mesasLista): ?>
        <p style="color: var(--text-muted);">Aún no hay mesas. Crea por ejemplo "Salón" × 8 y "Terraza" × 6.</p>
      <?php else: ?>
        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
          <?php foreach ($mesasLista as $m): ?>
            <form method="POST" action="configuracion.php?tab=mesas" style="display: inline;">
              <?= csrfField() ?><input type="hidden" name="action" value="mesa_toggle"><input type="hidden" name="mesa_id" value="<?= (int)$m['MesaID'] ?>">
              <button class="btn btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; <?= $m['Activa'] ? '' : 'opacity: 0.45; text-decoration: line-through;' ?>"
                      title="<?= $m['Activa'] ? 'Clic para desactivar' : 'Clic para activar' ?>">
                <?= htmlspecialchars($m['Nombre']) ?><?= $m['Abierta'] ? ' •' : '' ?>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
        <p style="color: var(--text-muted); font-size: 0.78rem; margin-top: 0.5rem;">Clic en una mesa para desactivarla o activarla. • = cuenta abierta.</p>
      <?php endif; ?>
    </div>

    <div class="table-card" style="padding: 1.5rem;">
      <h3 style="font-size: 1.05rem; margin: 0 0 0.75rem;"><i class="fa-solid fa-user-tie"></i> Garzones</h3>
      <form method="POST" action="configuracion.php?tab=mesas" style="display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1rem;">
        <?= csrfField() ?><input type="hidden" name="action" value="garzon_agregar">
        <div style="flex: 2; min-width: 140px;">
          <label style="font-size: 0.8rem; font-weight: 600;">Nombre</label>
          <input name="nombre" class="form-control" required>
        </div>
        <div style="flex: 1; min-width: 90px;">
          <label style="font-size: 0.8rem; font-weight: 600;">PIN (4 a 8 números)</label>
          <input name="pin" class="form-control" inputmode="numeric" pattern="\d{4,8}" required autocomplete="off">
        </div>
        <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Agregar</button>
      </form>
      <?php if (!$garzones): ?>
        <p style="color: var(--text-muted);">Aún no hay garzones. Los garzones no cuentan en el límite de usuarios del plan.</p>
      <?php else: ?>
        <table class="table">
          <tbody>
          <?php foreach ($garzones as $g): ?>
            <tr style="<?= $g['Activo'] ? '' : 'opacity: 0.5;' ?>">
              <td><?= htmlspecialchars($g['Nombre']) ?><?= $g['Activo'] ? '' : ' (inactivo)' ?></td>
              <td style="text-align: right; white-space: nowrap;">
                <form method="POST" action="configuracion.php?tab=mesas" style="display: inline-flex; gap: 0.3rem;">
                  <?= csrfField() ?><input type="hidden" name="action" value="garzon_pin"><input type="hidden" name="usuario_id" value="<?= (int)$g['UsuarioID'] ?>">
                  <input name="pin" class="form-control" inputmode="numeric" pattern="\d{4,8}" placeholder="PIN nuevo" required style="width: 100px; padding: 0.3rem 0.5rem;" autocomplete="off">
                  <button class="btn btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Cambiar</button>
                </form>
                <form method="POST" action="configuracion.php?tab=mesas" style="display: inline;">
                  <?= csrfField() ?><input type="hidden" name="action" value="garzon_toggle"><input type="hidden" name="usuario_id" value="<?= (int)$g['UsuarioID'] ?>">
                  <button class="btn btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;"><?= $g['Activo'] ? 'Desactivar' : 'Activar' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="table-card" style="padding: 1.5rem;">
      <h3 style="font-size: 1.05rem; margin: 0 0 0.75rem;"><i class="fa-solid fa-tablet-screen-button"></i> Tablets, celulares y cocina</h3>
      <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 0;">1. Conecta el celular o la tablet a la <strong>misma red WiFi del local</strong> (no la de clientes).<br>2. Abre esta dirección en su navegador:</p>
      <?php $puerto = (int)($_SERVER['SERVER_PORT'] ?? 80); $sufijo = in_array($puerto, [80, 443], true) ? '' : ":$puerto";
            $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'); ?>
      <?php if (!empty($ipsLocales['principal'])): $urlBase = 'http://' . $ipsLocales['principal'] . $sufijo . $base;
            require_once __DIR__ . '/../includes/qr.php'; ?>
        <div style="background: rgba(79, 70, 229, 0.08); border: 1px solid rgba(79, 70, 229, 0.35); border-radius: 10px; padding: 0.85rem 1rem; margin: 0.5rem 0;">
          <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <?php foreach (['garzon' => 'Garzones (celular / tablet)', 'cocina' => 'Cocina (tablet / PC)'] as $pag => $titulo): $url = "$urlBase/$pag.php"; ?>
              <div class="qr-bloque" style="flex: 1; min-width: 180px; text-align: center;">
                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;"><?= $titulo ?></div>
                <div class="qr-svg" style="display: inline-block; margin: 0.5rem 0; border-radius: 8px; overflow: hidden;"><?= QrCodigo::svg($url, 170) ?></div>
                <div class="qr-url" style="font-family: monospace; font-size: 0.9rem; font-weight: 700; word-break: break-all;"><?= htmlspecialchars($url) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0.6rem 0 0; text-align: center;">
            Escanéalo con la <strong>cámara</strong> del celular o tablet (conectado al WiFi del local) y abre el enlace.
          </p>
          <div style="text-align: center; margin-top: 0.6rem;">
            <button type="button" class="btn btn-secondary" onclick="imprimirCartelQr()" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;"><i class="fa-solid fa-print"></i> Imprimir cartel con los QR</button>
          </div>
        </div>
        <script>
        function imprimirCartelQr() {
          const negocio = <?= json_encode($config['MINIMARKET_NOMBRE'] ?? '') ?>;
          const bloques = [...document.querySelectorAll('#tabMesas .qr-bloque')].map(b => `
            <div class="b"><h2>${b.querySelector('div').textContent}</h2>${b.querySelector('.qr-svg').innerHTML.replace(/width="\d+" height="\d+"/, 'width="300" height="300"')}<p>${b.querySelector('.qr-url').textContent}</p></div>`).join('');
          const w = window.open('', '_blank', 'width=800,height=900');
          w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>QR Mesas y cocina</title><style>
            body { font-family: Arial, sans-serif; text-align: center; color: #000; }
            h1 { font-size: 26px; margin: 10px 0 4px; } .sub { margin: 0 0 20px; font-size: 15px; }
            .b { display: inline-block; width: 45%; vertical-align: top; margin: 10px 2%; }
            h2 { font-size: 20px; } p { font-family: monospace; font-size: 14px; word-break: break-all; }
          </style></head><body><h1>${negocio}</h1><p class="sub">Conéctate al WiFi del local y escanea con la cámara</p>${bloques}</body></html>`);
          w.document.close(); w.focus(); setTimeout(() => w.print(), 300);
        }
        </script>
        <?php if ($ipsLocales['otras']): ?>
          <details style="font-size: 0.8rem; color: var(--text-muted);">
            <summary>¿No conecta? Otras direcciones de este equipo</summary>
            <?php foreach ($ipsLocales['otras'] as $ip): ?><div style="font-family: monospace;">http://<?= htmlspecialchars($ip . $sufijo . $base) ?>/garzon.php</div><?php endforeach; ?>
            <div>Suelen ser de programas como VirtualBox o VPN; la correcta es la del WiFi o cable del local.</div>
          </details>
        <?php endif; ?>
      <?php else: ?>
        <p style="color: #f59e0b;">No se detectó la dirección de este equipo en la red. Revisa que esté conectado al WiFi o al router del local.</p>
      <?php endif; ?>

      <p style="font-size: 0.88rem; margin: 0.9rem 0 0.3rem;"><strong>3. Instálala como app</strong> (queda con su ícono en la pantalla de inicio):</p>
      <ul style="font-size: 0.84rem; color: var(--text-muted); margin: 0; padding-left: 1.1rem; line-height: 1.6;">
        <li><strong>Android (Chrome):</strong> menú ⋮ → “Agregar a la pantalla principal”.</li>
        <li><strong>iPhone / iPad (Safari):</strong> botón Compartir ⬆ → “Agregar a inicio”.</li>
        <li><strong>PC de cocina (Chrome / Edge):</strong> menú → Aplicaciones / Guardar y compartir → “Instalar” o “Crear acceso directo”.</li>
      </ul>
      <details style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
        <summary>En Android se abre con la barra del navegador. ¿Cómo dejarla en pantalla completa?</summary>
        <div style="margin-top: 0.4rem; line-height: 1.5;">
          Chrome solo instala apps “completas” en conexiones seguras. En una red de local se puede habilitar una vez por equipo:
          en Chrome abre <code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code>, escribe
          <code><?= htmlspecialchars(!empty($urlBase) ? $urlBase : 'http://IP-DE-LA-CAJA' . $sufijo) ?></code>, elige <em>Enabled</em>, reinicia Chrome
          y vuelve a “Instalar app”. En iPhone no hace falta: ya abre en pantalla completa.
        </div>
      </details>
      <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.8rem;">Conviene fijar la IP de este computador en el router para que la dirección no cambie.
        Desde otros equipos solo se pueden abrir estas dos pantallas; la caja y la configuración siguen solo en este PC.</p>

      <form method="POST" action="configuracion.php?tab=mesas" style="margin-top: 1rem; border-top: 1px solid var(--border-dark); padding-top: 1rem;">
        <?= csrfField() ?><input type="hidden" name="action" value="mesas_config">
        <label style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.9rem;">
          <input type="checkbox" name="imprimir_comanda" value="SI" <?= ($config['MESAS_IMPRIMIR_COMANDA'] ?? 'SI') === 'SI' ? 'checked' : '' ?>>
          Imprimir en la caja cada comanda nueva (con la caja abierta en el POS)
        </label>
        <button class="btn btn-secondary" style="margin-top: 0.6rem;">Guardar</button>
      </form>
    </div>

  </div>
</div>
<?php endif; ?>

<!-- PESTAÑA 4: CONFIGURACIÓN DE BALANZA -->
<div id="tabBalanza" class="tab-pane" style="display: none;">
  <div class="table-card" style="padding: 1.75rem; max-width: 800px;">
    <form method="POST" action="configuracion.php" style="display: flex; flex-direction: column; gap: 1.25rem;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_config">

      <h2 style="font-size: 1.1rem; font-weight: 600; color: #818cf8; border-bottom: 1px solid var(--border-dark); padding-bottom: 0.4rem;">Configuración de Balanzas y Lector de Códigos (EAN-13)</h2>

      <div>
        <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">FORMATO DEL CÓDIGO DE BARRAS DE BALANZA (INDIVIDUAL)</label>
        <select name="config[BALANZA_TIPO_EAN]" class="form-control" required>
          <option value="plu_peso" <?= ($config['BALANZA_TIPO_EAN'] ?? 'plu_peso') === 'plu_peso' ? 'selected' : '' ?>>PLU + Peso (20 PPPP QQQQQ C) - Recomendado</option>
          <option value="plu_precio" <?= ($config['BALANZA_TIPO_EAN'] ?? '') === 'plu_precio' ? 'selected' : '' ?>>PLU + Precio/Valor (20 PPPP $$$$$ C)</option>
        </select>
        <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem;">
          Determina si los 5 dígitos de cantidad en la etiqueta representan gramos de peso (ej. 01250 para 1.250kg) o el valor de venta en pesos (ej. 12500 para $12.500).
        </p>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">PREFIJO EAN-13 ETIQUETA INDIVIDUAL</label>
          <input type="text" name="config[BALANZA_PREFIJO_INDIVIDUAL]" value="<?= htmlspecialchars($config['BALANZA_PREFIJO_INDIVIDUAL'] ?? '20') ?>" class="form-control" placeholder="Ej: 20" maxlength="2" required style="font-family: monospace;">
          <p style="color: var(--text-muted); font-size: 0.72rem; margin-top: 0.25rem;">Prefijo GS1 para identificar que el código contiene peso/precio (normalmente 20).</p>
        </div>
        <div>
          <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">PREFIJO EAN-13 VALE CONSOLIDADO</label>
          <input type="text" name="config[BALANZA_PREFIJO_CONSOLIDADO]" value="<?= htmlspecialchars($config['BALANZA_PREFIJO_CONSOLIDADO'] ?? '28') ?>" class="form-control" placeholder="Ej: 28" maxlength="2" required style="font-family: monospace;">
          <p style="color: var(--text-muted); font-size: 0.72rem; margin-top: 0.25rem;">Prefijo GS1 para identificar vales consolidados con monto total final (normalmente 28).</p>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="padding: 0.85rem; font-weight: bold;">
        <i class="fa-solid fa-floppy-disk"></i> Guardar Configuración de Balanza
      </button>
    </form>
  </div>
</div>

<!-- PESTAÑA 5: SUPERVISIÓN EN CAJA (POS) -->
<div id="tabSupervision" class="tab-pane" style="display: none;">
  <div class="table-card" style="padding: 1.75rem; max-width: 800px;">
    <form method="POST" action="configuracion.php" style="display: flex; flex-direction: column; gap: 1.25rem;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_config">

      <div>
        <h2 style="font-size: 1.15rem; font-weight: 700; color: #818cf8; border-bottom: 1px solid var(--border-dark); padding-bottom: 0.4rem; margin-bottom: 0.4rem;">
          <i class="fa-solid fa-user-shield"></i> Políticas de Supervisión y Control en Caja
        </h2>
        <p style="color: var(--text-muted); font-size: 0.85rem;">
          Personaliza qué acciones del cajero en el punto de venta requieren la contraseña o autorización de un Administrador o Supervisor.
        </p>
      </div>

      <!-- Control 1: Anular/Vaciar Venta en Proceso -->
      <div style="background: rgba(15, 23, 42, 0.4); border: 1px solid var(--border-dark); padding: 1.25rem; border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
          <div style="flex: 1;">
            <label style="font-size: 0.95rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
              <i class="fa-solid fa-ban" style="color: var(--danger);"></i> Cancelar Venta en Curso (Vaciar Carrito)
            </label>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0; line-height: 1.4;">
              Si está activado, cuando un Cajero intente cancelar la venta en proceso o vaciar el carrito, el sistema solicitará la clave de un Supervisor para autorizar la acción.
            </p>
          </div>
          <div style="width: 200px; flex-shrink: 0;">
            <select name="config[POS_REQ_SUPERVISOR_CANCELAR]" class="form-control" style="font-weight: 600;">
              <option value="SI" <?= ($config['POS_REQ_SUPERVISOR_CANCELAR'] ?? 'SI') === 'SI' ? 'selected' : '' ?>>🛡️ Exigir Supervisor</option>
              <option value="NO" <?= ($config['POS_REQ_SUPERVISOR_CANCELAR'] ?? '') === 'NO' ? 'selected' : '' ?>>Permitir Libremente</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Control 2: Eliminar Producto de la Grilla -->
      <div style="background: rgba(15, 23, 42, 0.4); border: 1px solid var(--border-dark); padding: 1.25rem; border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
          <div style="flex: 1;">
            <label style="font-size: 0.95rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
              <i class="fa-solid fa-trash-can" style="color: #f59e0b;"></i> Eliminar Producto del Carrito
            </label>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0; line-height: 1.4;">
              Si está activado, cuando un Cajero intente quitar un producto agregado a la grilla (usando el botón papelera o reduciendo la cantidad a cero), se exigirá la autorización de un Supervisor.
            </p>
          </div>
          <div style="width: 200px; flex-shrink: 0;">
            <select name="config[POS_REQ_SUPERVISOR_ELIMINAR_ITEM]" class="form-control" style="font-weight: 600;">
              <option value="SI" <?= ($config['POS_REQ_SUPERVISOR_ELIMINAR_ITEM'] ?? 'SI') === 'SI' ? 'selected' : '' ?>>🛡️ Exigir Supervisor</option>
              <option value="NO" <?= ($config['POS_REQ_SUPERVISOR_ELIMINAR_ITEM'] ?? '') === 'NO' ? 'selected' : '' ?>>Permitir Libremente</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Control 3: Descuento Máximo de Cajero por Porcentaje -->
      <div style="background: rgba(15, 23, 42, 0.4); border: 1px solid var(--border-dark); padding: 1.25rem; border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
          <div style="flex: 1;">
            <label style="font-size: 0.95rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
              <i class="fa-solid fa-percent" style="color: #34d399;"></i> Descuento Máximo en Caja sin Supervisor (%)
            </label>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0; line-height: 1.4;">
              Porcentaje máximo que un cajero puede otorgar como descuento sin pedir autorización. Si el descuento aplicado supera este porcentaje (o si se establece en <strong>0%</strong>), será obligatorio ingresar la clave de un Supervisor para completar el cobro.
            </p>
          </div>
          <div style="width: 200px; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <input type="number" name="config[POS_DESCUENTO_MAX_PORC]" class="form-control" min="0" max="100" step="1" value="<?= htmlspecialchars($config['POS_DESCUENTO_MAX_PORC'] ?? '5') ?>" style="font-size: 1.1rem; font-weight: 700; text-align: center;" required>
              <span style="font-weight: 700; font-size: 1.1rem; color: #fff;">%</span>
            </div>
            <span style="font-size: 0.72rem; color: var(--text-muted); display: block; margin-top: 0.25rem; text-align: center;">0 = Siempre pide clave</span>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="align-self: flex-start; padding: 0.75rem 2rem; font-size: 1rem; font-weight: bold; margin-top: 0.5rem;">
        <i class="fa-solid fa-floppy-disk"></i> Guardar Políticas de Supervisión
      </button>
    </form>
  </div>
</div>

<!-- PESTAÑA 6: APARIENCIA Y MARCA -->
<div id="tabApariencia" class="tab-pane" style="display: none;">
  <div class="table-card" style="padding: 1.75rem; max-width: 850px;">
    <form method="POST" action="configuracion.php" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.5rem;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_config">

      <div>
        <h2 style="font-size: 1.1rem; font-weight: 600; color: #818cf8; border-bottom: 1px solid var(--border-dark); padding-bottom: 0.4rem;">
          <i class="fa-solid fa-palette"></i> Tema de Color y Apariencia
        </h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.35rem;">Personaliza la apariencia general del sistema para adaptarlo a la iluminación de tu local y a los colores de tu marca.</p>
      </div>

      <!-- Modo de Color (Oscuro vs Claro) -->
      <div>
        <label style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 0.5rem;">MODO DE COLOR PREDETERMINADO</label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <label style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border: 2px solid <?= ($config['TEMA_MODO'] ?? 'dark') === 'dark' ? 'var(--primary)' : 'var(--border-dark)' ?>; border-radius: 12px; cursor: pointer; background: rgba(15,23,42,0.6);" id="lblTemaDark">
            <input type="radio" name="config[TEMA_MODO]" value="dark" <?= ($config['TEMA_MODO'] ?? 'dark') === 'dark' ? 'checked' : '' ?> onchange="actualizarBordeTema('dark')">
            <div>
              <div style="font-weight: bold; color: var(--text-main);"><i class="fa-solid fa-moon"></i> Modo Oscuro (Dark)</div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">Ideal para turnos largos y pantallas nocturnas. Reduce el cansancio visual.</div>
            </div>
          </label>
          <label style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border: 2px solid <?= ($config['TEMA_MODO'] ?? '') === 'light' ? 'var(--primary)' : 'var(--border-dark)' ?>; border-radius: 12px; cursor: pointer; background: rgba(255,255,255,0.06);" id="lblTemaLight">
            <input type="radio" name="config[TEMA_MODO]" value="light" <?= ($config['TEMA_MODO'] ?? '') === 'light' ? 'checked' : '' ?> onchange="actualizarBordeTema('light')">
            <div>
              <div style="font-weight: bold; color: var(--text-main);"><i class="fa-solid fa-sun"></i> Modo Claro (Light)</div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">Fondo blanco y alto contraste. Recomendado para locales con mucha luz natural.</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Color de Acento Corporativo -->
      <div>
        <label style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 0.5rem;">COLOR DE ACENTO CORPORATIVO (MARCA)</label>
        <?php $curAcento = $config['TEMA_COLOR_ACENTO'] ?? 'indigo'; ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem;">
          
          <label class="accent-card" style="display: flex; flex-direction: column; align-items: center; gap: 0.4rem; padding: 0.75rem 0.5rem; border: 2px solid <?= $curAcento === 'indigo' ? '#4f46e5' : 'var(--border-dark)' ?>; border-radius: 10px; cursor: pointer; text-align: center; background: rgba(30,41,59,0.5);">
            <input type="radio" name="config[TEMA_COLOR_ACENTO]" value="indigo" <?= $curAcento === 'indigo' ? 'checked' : '' ?> style="display: none;" onchange="actualizarAcentoPreview(this.value, this)">
            <span style="width: 28px; height: 28px; border-radius: 50%; background: #4f46e5; display: inline-block; box-shadow: 0 0 10px rgba(79,70,229,0.5);"></span>
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-main);">Azul Índigo</span>
            <span style="font-size: 0.7rem; color: var(--text-muted);">Estándar</span>
          </label>

          <label class="accent-card" style="display: flex; flex-direction: column; align-items: center; gap: 0.4rem; padding: 0.75rem 0.5rem; border: 2px solid <?= $curAcento === 'verde' ? '#10b981' : 'var(--border-dark)' ?>; border-radius: 10px; cursor: pointer; text-align: center; background: rgba(30,41,59,0.5);">
            <input type="radio" name="config[TEMA_COLOR_ACENTO]" value="verde" <?= $curAcento === 'verde' ? 'checked' : '' ?> style="display: none;" onchange="actualizarAcentoPreview(this.value, this)">
            <span style="width: 28px; height: 28px; border-radius: 50%; background: #10b981; display: inline-block; box-shadow: 0 0 10px rgba(16,185,129,0.5);"></span>
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-main);">Verde Minimarket</span>
            <span style="font-size: 0.7rem; color: var(--text-muted);">Fresco / Eco</span>
          </label>

          <label class="accent-card" style="display: flex; flex-direction: column; align-items: center; gap: 0.4rem; padding: 0.75rem 0.5rem; border: 2px solid <?= $curAcento === 'naranja' ? '#f59e0b' : 'var(--border-dark)' ?>; border-radius: 10px; cursor: pointer; text-align: center; background: rgba(30,41,59,0.5);">
            <input type="radio" name="config[TEMA_COLOR_ACENTO]" value="naranja" <?= $curAcento === 'naranja' ? 'checked' : '' ?> style="display: none;" onchange="actualizarAcentoPreview(this.value, this)">
            <span style="width: 28px; height: 28px; border-radius: 50%; background: #f59e0b; display: inline-block; box-shadow: 0 0 10px rgba(245,158,11,0.5);"></span>
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-main);">Naranja / Ámbar</span>
            <span style="font-size: 0.7rem; color: var(--text-muted);">Comercial</span>
          </label>

          <label class="accent-card" style="display: flex; flex-direction: column; align-items: center; gap: 0.4rem; padding: 0.75rem 0.5rem; border: 2px solid <?= $curAcento === 'cyan' ? '#06b6d4' : 'var(--border-dark)' ?>; border-radius: 10px; cursor: pointer; text-align: center; background: rgba(30,41,59,0.5);">
            <input type="radio" name="config[TEMA_COLOR_ACENTO]" value="cyan" <?= $curAcento === 'cyan' ? 'checked' : '' ?> style="display: none;" onchange="actualizarAcentoPreview(this.value, this)">
            <span style="width: 28px; height: 28px; border-radius: 50%; background: #06b6d4; display: inline-block; box-shadow: 0 0 10px rgba(6,182,212,0.5);"></span>
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-main);">Cyan / Turquesa</span>
            <span style="font-size: 0.7rem; color: var(--text-muted);">Moderno</span>
          </label>

          <label class="accent-card" style="display: flex; flex-direction: column; align-items: center; gap: 0.4rem; padding: 0.75rem 0.5rem; border: 2px solid <?= $curAcento === 'rojo' ? '#ef4444' : 'var(--border-dark)' ?>; border-radius: 10px; cursor: pointer; text-align: center; background: rgba(30,41,59,0.5);">
            <input type="radio" name="config[TEMA_COLOR_ACENTO]" value="rojo" <?= $curAcento === 'rojo' ? 'checked' : '' ?> style="display: none;" onchange="actualizarAcentoPreview(this.value, this)">
            <span style="width: 28px; height: 28px; border-radius: 50%; background: #ef4444; display: inline-block; box-shadow: 0 0 10px rgba(239,68,68,0.5);"></span>
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-main);">Rojo Dinámico</span>
            <span style="font-size: 0.7rem; color: var(--text-muted);">Enérgico</span>
          </label>

        </div>
      </div>

      <!-- Logo del Negocio -->
      <div>
        <h2 style="font-size: 1.1rem; font-weight: 600; color: #818cf8; border-bottom: 1px solid var(--border-dark); padding-bottom: 0.4rem;">
          <i class="fa-solid fa-image"></i> Logo de la Empresa
        </h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.35rem;">Aparecerá en la barra superior (Navbar), en la pantalla de bienvenida y en el inicio de sesión.</p>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; align-items: center; margin-top: 0.75rem;">
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div>
              <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">SUBIR ARCHIVO DE LOGO (PNG, JPG, SVG, WebP)</label>
              <input type="file" name="logo_file" id="logoFileInput" class="form-control" accept="image/*" onchange="previewLogoFile(this)">
            </div>
            <div>
              <label style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">O RUTA / URL DE IMAGEN EXISTENTE</label>
              <input type="text" name="config[MINIMARKET_LOGO_URL]" id="logoUrlInput" value="<?= htmlspecialchars($config['MINIMARKET_LOGO_URL'] ?? '') ?>" class="form-control" placeholder="uploads/logo/... o https://..." oninput="previewLogoUrl(this.value)">
            </div>
          </div>
          <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(0,0,0,0.25); border: 2px dashed var(--border-dark); border-radius: 12px; padding: 1rem; min-height: 120px;">
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.4rem; font-weight: 600;">VISTA PREVIA</div>
            <?php $logoActual = $config['MINIMARKET_LOGO_URL'] ?? ''; ?>
            <img id="logoPreviewImg" src="<?= htmlspecialchars($logoActual) ?>" alt="Logo Empresa" style="<?= empty($logoActual) ? 'display:none;' : 'display:block;' ?> max-height: 60px; max-width: 100%; object-fit: contain;">
            <div id="logoPreviewPlaceholder" style="<?= !empty($logoActual) ? 'display:none;' : 'display:flex;' ?> flex-direction: column; align-items: center; color: var(--text-muted);">
              <i class="fa-solid fa-store" style="font-size: 2rem; margin-bottom: 0.25rem; opacity: 0.5;"></i>
              <span style="font-size: 0.75rem;">Sin logo personalizado</span>
            </div>
            <?php if (!empty($logoActual)): ?>
              <button type="button" onclick="quitarLogo()" class="btn btn-secondary" style="margin-top: 0.5rem; font-size: 0.7rem; padding: 0.2rem 0.5rem; color: var(--danger);">
                <i class="fa-solid fa-trash-can"></i> Quitar Logo
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Modo de Operación del POS (Caja Registradora) -->
      <div>
        <h2 style="font-size: 1.1rem; font-weight: 600; color: #818cf8; border-bottom: 1px solid var(--border-dark); padding-bottom: 0.4rem;">
          <i class="fa-solid fa-cash-register"></i> Modo de Operación de la Caja (POS)
        </h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.35rem;">Elige el modo de caja que se utilizará por defecto en el punto de venta:</p>
        
        <?php $layoutModo = $config['POS_LAYOUT_MODO'] ?? 'supermercado'; ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
          
          <label id="lblModoSupermercado" class="pos-mode-card-cfg" style="display: flex; flex-direction: column; padding: 1.1rem; border: 2px solid <?= $layoutModo === 'supermercado' ? 'var(--primary)' : 'var(--border-dark)' ?>; border-radius: 12px; cursor: pointer; background: rgba(30,41,59,0.5); transition: all 0.2s ease;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.45rem;">
              <input type="radio" name="config[POS_LAYOUT_MODO]" value="supermercado" <?= $layoutModo === 'supermercado' ? 'checked' : '' ?> onchange="actualizarBordeModoPos('supermercado')">
              <strong style="color: var(--text-main); font-size: 0.95rem;"><i class="fa-solid fa-barcode"></i> Modo Supermercado</strong>
            </div>
            <span style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.45;">Caja Rápida por Escáner. Oculta el catálogo y muestra una tabla central amplia de venta para registrar artículos velozmente con lector de código de barras.</span>
          </label>

          <label id="lblModoTactil" class="pos-mode-card-cfg" style="display: flex; flex-direction: column; padding: 1.1rem; border: 2px solid <?= $layoutModo === 'tactil' ? 'var(--primary)' : 'var(--border-dark)' ?>; border-radius: 12px; cursor: pointer; background: rgba(30,41,59,0.5); transition: all 0.2s ease;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.45rem;">
              <input type="radio" name="config[POS_LAYOUT_MODO]" value="tactil" <?= $layoutModo === 'tactil' ? 'checked' : '' ?> onchange="actualizarBordeModoPos('tactil')">
              <strong style="color: var(--text-main); font-size: 0.95rem;"><i class="fa-solid fa-hand-pointer"></i> Modo Táctil / Kiosco</strong>
            </div>
            <span style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.45;">Optimizado para Touchscreen. Barra horizontal de categorías superiores y tarjetas grandes con alto contraste para presionar cómodamente con el dedo.</span>
          </label>

          <label id="lblModoClasico" class="pos-mode-card-cfg" style="display: flex; flex-direction: column; padding: 1.1rem; border: 2px solid <?= $layoutModo === 'clasico' ? 'var(--primary)' : 'var(--border-dark)' ?>; border-radius: 12px; cursor: pointer; background: rgba(30,41,59,0.5); transition: all 0.2s ease;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.45rem;">
              <input type="radio" name="config[POS_LAYOUT_MODO]" value="clasico" <?= $layoutModo === 'clasico' ? 'checked' : '' ?> onchange="actualizarBordeModoPos('clasico')">
              <strong style="color: var(--text-main); font-size: 0.95rem;"><i class="fa-solid fa-table-columns"></i> Modo Clásico Dividido</strong>
            </div>
            <span style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.45;">Diseño tradicional de 2 columnas (catálogo a la izquierda y carrito a la derecha) con filtros interactivos de Más Vendidos, Ofertas, Todos o por categoría.</span>
          </label>

        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="align-self: flex-start; padding: 0.75rem 2rem; font-size: 1rem; font-weight: bold; margin-top: 0.5rem;">
        <i class="fa-solid fa-floppy-disk"></i> Guardar Apariencia y Marca
      </button>
    </form>
  </div>
</div>

<script>
function actualizarBordeTema(modo) {
  const lblDark = document.getElementById('lblTemaDark');
  const lblLight = document.getElementById('lblTemaLight');
  if (modo === 'dark') {
    lblDark.style.borderColor = 'var(--primary)';
    lblLight.style.borderColor = 'var(--border-dark)';
  } else {
    lblLight.style.borderColor = 'var(--primary)';
    lblDark.style.borderColor = 'var(--border-dark)';
  }
  document.documentElement.setAttribute('data-theme', modo);
}

function actualizarAcentoPreview(color, radioEl) {
  document.documentElement.setAttribute('data-accent', color);
  document.querySelectorAll('.accent-card').forEach(c => c.style.borderColor = 'var(--border-dark)');
  if (radioEl) {
    radioEl.closest('.accent-card').style.borderColor = 'var(--primary)';
  }
}

function actualizarBordeModoPos(modo) {
  const cards = {
    supermercado: document.getElementById('lblModoSupermercado'),
    tactil: document.getElementById('lblModoTactil'),
    clasico: document.getElementById('lblModoClasico')
  };
  Object.keys(cards).forEach(k => {
    if (cards[k]) {
      cards[k].style.borderColor = (k === modo) ? 'var(--primary)' : 'var(--border-dark)';
    }
  });
}

function previewLogoFile(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById('logoPreviewImg');
      const placeholder = document.getElementById('logoPreviewPlaceholder');
      img.src = e.target.result;
      img.style.display = 'block';
      placeholder.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function previewLogoUrl(url) {
  const img = document.getElementById('logoPreviewImg');
  const placeholder = document.getElementById('logoPreviewPlaceholder');
  if (url && url.trim() !== '') {
    img.src = url.trim();
    img.style.display = 'block';
    placeholder.style.display = 'none';
  } else {
    img.style.display = 'none';
    placeholder.style.display = 'flex';
  }
}

function quitarLogo() {
  document.getElementById('logoUrlInput').value = '';
  document.getElementById('logoPreviewImg').style.display = 'none';
  document.getElementById('logoPreviewPlaceholder').style.display = 'flex';
}
function switchTab(tabId) {
  document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active', 'btn-primary'));
  
  const selectedPane = document.getElementById(tabId);
  const selectedBtn = document.getElementById('btn-' + tabId);
  
  if (selectedPane) selectedPane.style.display = 'block';
  if (selectedBtn) selectedBtn.classList.add('active', 'btn-primary');
  try { localStorage.setItem('config_active_tab', tabId); } catch(e) {}
}

function toggleDteFields() {
  const sel = document.getElementById('dteProveedorSelect');
  if (!sel) return;
  const cred = document.getElementById('divConfigEboleta');
  const sim = document.getElementById('divConfigSimulador');
  if (cred) cred.style.display = sel.value === 'eboleta' ? 'grid' : 'none';
  if (sim) sim.style.display = sel.value === 'eboleta_simulador' ? 'block' : 'none';
}

// Ejecutar al cargar la página
document.addEventListener("DOMContentLoaded", () => {
  toggleDteFields();
  try {
    const savedTab = localStorage.getItem('config_active_tab');
    if (savedTab && document.getElementById(savedTab) && document.getElementById('btn-' + savedTab)) {
      switchTab(savedTab);
    }
  } catch(e) {}
  <?php if ($tabInicial !== ''): ?>
  const urlTab = 'tab' + <?= json_encode(ucfirst($tabInicial)) ?>;
  if (document.getElementById(urlTab)) switchTab(urlTab);
  <?php endif; ?>
});
</script>
