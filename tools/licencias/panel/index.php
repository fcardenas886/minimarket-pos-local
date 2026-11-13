<?php
/**
 * Panel personal de licencias — HERRAMIENTA DEL PROVEEDOR. No se instala en clientes.
 * Se abre con tools/licencias/abrir_panel.bat (servidor PHP solo en 127.0.0.1).
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo desde este equipo.');
}
session_name('mml_panel');
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function sumarMeses(string $desde, int $meses): string { return (new DateTime($desde))->modify("+$meses months")->format('Y-m-d'); }

$error = '';
$aviso = $_SESSION['aviso'] ?? '';
unset($_SESSION['aviso']);
$verificacion = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) throw new Exception('El formulario expiró. Recarga la página.');
        $accion = $_POST['accion'] ?? '';
        $clientes = mml_clientes();

        if ($accion === 'generar') {
            $hasta = ($_POST['modo_fecha'] ?? 'meses') === 'fecha'
                ? (string)($_POST['hasta'] ?? '')
                : sumarMeses(date('Y-m-d'), max(0, (int)($_POST['meses'] ?? 12)));
            $lic = mml_generar((string)$_POST['cliente'], (string)$_POST['rut'], (string)$_POST['plan'], $hasta, (array)($_POST['extras'] ?? []));
            mml_registro_agregar($lic, trim((string)($_POST['nota'] ?? '')) ?: 'Licencia nueva');
            $_SESSION['ultima'] = $lic['texto'];
            $_SESSION['aviso'] = "Licencia {$lic['plan']} creada para {$lic['cliente']}. Cópiala y entrégasela al cliente.";
        } elseif (in_array($accion, ['renovar', 'subir_plan', 'activar_extra'], true)) {
            $rut = mml_rut_normalizar((string)($_POST['rut'] ?? ''));
            if (!isset($clientes[$rut])) throw new Exception('Cliente no encontrado en el registro.');
            $act = $clientes[$rut]['actual'];
            $extrasAct = $act['extras'] ?? [];
            if ($accion === 'renovar') {
                $meses = max(1, (int)($_POST['meses'] ?? 12));
                // Se extiende desde la fecha vigente si aún no vence; si ya venció, desde hoy.
                $base = max($act['actualizaciones_hasta'], date('Y-m-d'));
                $lic = mml_generar($act['cliente'], $rut, $act['plan'], sumarMeses($base, $meses), $extrasAct);
                $nota = "Renovación de soporte +$meses meses";
            } elseif ($accion === 'subir_plan') {
                if ($act['plan'] === 'comercio') throw new Exception('Este cliente ya tiene el plan Comercio.');
                $lic = mml_generar($act['cliente'], $rut, 'comercio', $act['actualizaciones_hasta'], $extrasAct);
                $nota = 'Cambio de plan Básico → Comercio';
            } else {
                $extra = (string)($_POST['extra'] ?? '');
                if (!isset(LICENCIA_EXTRAS[$extra])) throw new Exception('Módulo extra desconocido.');
                if (in_array($extra, $extrasAct, true)) throw new Exception('Ese módulo ya está activado.');
                $lic = mml_generar($act['cliente'], $rut, $act['plan'], $act['actualizaciones_hasta'], array_merge($extrasAct, [$extra]));
                $nota = 'Módulo extra activado: ' . LICENCIA_EXTRAS[$extra];
            }
            mml_registro_agregar($lic, $nota);
            $_SESSION['ultima'] = $lic['texto'];
            $_SESSION['aviso'] = "$nota para {$act['cliente']}. Entrégale la licencia nueva (reemplaza a la anterior).";
        } elseif ($accion === 'verificar') {
            $verificacion = licencia_verificar_texto((string)($_POST['texto'] ?? ''));
        }
        if ($accion !== 'verificar') {
            header('Location: index.php');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$ultima = $_SESSION['ultima'] ?? null;
unset($_SESSION['ultima']);
$clientes = mml_clientes();
$hayPrivada = is_file(mml_ruta_privada());
$hoy = date('Y-m-d');
$f = $_POST;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de licencias</title>
<style>
  :root {
    --bg: #f5f6f8; --card: #ffffff; --text: #1d2330; --muted: #677084; --border: #dfe3ea;
    --primary: #4f46e5; --primary-text: #ffffff; --ok: #0f7b4b; --ok-bg: #e6f6ee; --warn: #9a5b00; --warn-bg: #fdf3e1;
    --bad: #b42318; --bad-bg: #fdecea; --code: #f0f2f6;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #12151c; --card: #1a1f29; --text: #e6e9ef; --muted: #98a1b3; --border: #2c3341;
      --primary: #818cf8; --primary-text: #0f1220; --ok: #5fd39a; --ok-bg: #12301f; --warn: #f2b45c; --warn-bg: #33270f;
      --bad: #f28b82; --bad-bg: #3a1714; --code: #232a37;
    }
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
  h1 { font-size: 1.5rem; margin: 0 0 4px; }
  h2 { font-size: 1.05rem; margin: 0 0 12px; }
  .sub { color: var(--muted); margin: 0 0 20px; }
  .grid { display: grid; grid-template-columns: 380px 1fr; gap: 16px; align-items: start; }
  @media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
  .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 18px; margin-bottom: 16px; }
  label { display: block; font-size: 0.82rem; font-weight: 600; margin: 10px 0 4px; }
  input, select, textarea { width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--card); color: var(--text); font: inherit; }
  textarea { font-family: ui-monospace, Consolas, monospace; font-size: 0.78rem; }
  .row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .row > * { flex: 1; }
  button, .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--card); color: var(--text); font: inherit; font-weight: 600; cursor: pointer; }
  button.primary { background: var(--primary); color: var(--primary-text); border-color: var(--primary); }
  button.small { padding: 4px 9px; font-size: 0.8rem; font-weight: 600; }
  .msg { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
  .msg.ok { background: var(--ok-bg); color: var(--ok); }
  .msg.bad { background: var(--bad-bg); color: var(--bad); }
  .msg.warn { background: var(--warn-bg); color: var(--warn); }
  .tabla-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
  th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--border); vertical-align: top; }
  th { color: var(--muted); font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; }
  .pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 0.76rem; font-weight: 700; }
  .pill.ok { background: var(--ok-bg); color: var(--ok); }
  .pill.warn { background: var(--warn-bg); color: var(--warn); }
  .pill.bad { background: var(--bad-bg); color: var(--bad); }
  .pill.plan { background: var(--code); color: var(--text); }
  .muted { color: var(--muted); font-size: 0.82rem; }
  code { background: var(--code); padding: 1px 5px; border-radius: 4px; font-size: 0.82rem; }
  details summary { cursor: pointer; color: var(--muted); font-size: 0.82rem; }
  .acciones { display: flex; gap: 6px; flex-wrap: wrap; }
  .acciones form { display: inline-flex; gap: 4px; }
  .acciones select { width: auto; padding: 3px 6px; font-size: 0.8rem; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Panel de licencias</h1>
  <p class="sub">Minimarket POS Local · uso personal del proveedor · solo funciona en este equipo</p>

  <?php if (!$hayPrivada): ?>
    <div class="msg bad">No se encontró la clave privada en <code><?= h(mml_ruta_privada()) ?></code>. Sin ella no se pueden emitir licencias.</div>
  <?php endif; ?>
  <?php if ($error): ?><div class="msg bad"><?= h($error) ?></div><?php endif; ?>
  <?php if ($aviso): ?><div class="msg ok"><?= h($aviso) ?></div><?php endif; ?>

  <?php if ($ultima): ?>
  <div class="card">
    <h2>Licencia para entregar</h2>
    <textarea id="ultima" rows="4" readonly><?= h($ultima) ?></textarea>
    <div class="row" style="margin-top: 8px;">
      <button class="primary" type="button" onclick="copiar('ultima', this)" style="flex: 0;">Copiar licencia</button>
      <span class="muted">El cliente la pega en Admin → Licencia → Instalar licencia.</span>
    </div>
  </div>
  <?php endif; ?>

  <div class="grid">
    <div>
      <div class="card">
        <h2>Nueva licencia</h2>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="accion" value="generar">
          <label>Cliente (nombre del negocio)</label>
          <input name="cliente" required value="<?= h(($f['accion'] ?? '') === 'generar' ? ($f['cliente'] ?? '') : '') ?>">
          <label>RUT de la empresa</label>
          <input name="rut" required placeholder="76.123.456-7" value="<?= h(($f['accion'] ?? '') === 'generar' ? ($f['rut'] ?? '') : '') ?>">
          <label>Plan</label>
          <select name="plan">
            <?php foreach (LICENCIA_PLANES as $k => $p): ?>
              <option value="<?= h($k) ?>" <?= ($f['plan'] ?? '') === $k ? 'selected' : '' ?>><?= h($p['nombre']) ?> (<?= (int)$p['max_usuarios'] ?> usuarios)</option>
            <?php endforeach; ?>
          </select>
          <label>Actualizaciones incluidas</label>
          <div class="row">
            <select name="modo_fecha" id="modoFecha" onchange="document.getElementById('boxMeses').style.display = this.value === 'meses' ? '' : 'none'; document.getElementById('boxFecha').style.display = this.value === 'fecha' ? '' : 'none';">
              <option value="meses">Por meses</option>
              <option value="fecha">Hasta una fecha</option>
            </select>
            <div id="boxMeses"><input type="number" name="meses" min="0" value="12" title="Meses desde hoy"></div>
            <div id="boxFecha" style="display: none;"><input type="date" name="hasta" value="<?= h(sumarMeses($hoy, 12)) ?>"></div>
          </div>
          <label>Módulos extra</label>
          <?php foreach (LICENCIA_EXTRAS as $k => $nom): ?>
            <label style="display: flex; gap: 8px; align-items: center; font-weight: 400; margin: 4px 0;">
              <input type="checkbox" name="extras[]" value="<?= h($k) ?>" style="width: auto;"> <?= h($nom) ?>
            </label>
          <?php endforeach; ?>
          <label>Nota (opcional)</label>
          <input name="nota" placeholder="Ej: pagó por transferencia el 10/11">
          <button class="primary" type="submit" style="margin-top: 14px; width: 100%;" <?= $hayPrivada ? '' : 'disabled' ?>>Crear licencia</button>
        </form>
      </div>

      <div class="card">
        <h2>Revisar una licencia</h2>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="accion" value="verificar">
          <textarea name="texto" rows="3" placeholder="Pega aquí un texto MML1.…" required><?= h(($f['accion'] ?? '') === 'verificar' ? ($f['texto'] ?? '') : '') ?></textarea>
          <button type="submit" style="margin-top: 8px;">Revisar</button>
        </form>
        <?php if ($verificacion): ?>
          <?php if ($verificacion['ok']): $d = $verificacion['datos']; ?>
            <div class="msg ok" style="margin: 12px 0 0;">
              Firma válida.<br>
              <strong><?= h($d['cliente'] ?? '') ?></strong> · <?= h(mml_rut_formatear($d['rut'])) ?><br>
              Plan <?= h(LICENCIA_PLANES[$d['plan']]['nombre']) ?><?= $d['extras'] ? ' + ' . h(implode(', ', array_map(fn($x) => explode(' (', LICENCIA_EXTRAS[$x])[0], $d['extras']))) : '' ?> · emitida <?= h($d['emitida'] ?? '-') ?><br>
              Actualizaciones hasta <?= h($d['actualizaciones_hasta'] ?? '-') ?>
              <?= ($d['actualizaciones_hasta'] ?? '') < $hoy ? '(vencidas)' : '(vigentes)' ?>
            </div>
          <?php else: ?>
            <div class="msg bad" style="margin: 12px 0 0;"><?= h($verificacion['error']) ?></div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <h2>Clientes (<?= count($clientes) ?>)</h2>
      <?php if (!$clientes): ?>
        <p class="muted">Aún no hay licencias en el registro. Las que crees aquí o por terminal aparecerán en esta lista.</p>
      <?php else: ?>
      <div class="tabla-wrap">
      <table>
        <thead><tr><th>Cliente</th><th>Plan</th><th>Actualizaciones</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($clientes as $rut => $c): $a = $c['actual']; $vence = $a['actualizaciones_hasta'];
              $dias = (int)floor((strtotime($vence) - strtotime($hoy)) / 86400);
              $estado = $dias < 0 ? ['bad', 'Vencidas'] : ($dias <= 30 ? ['warn', "Vencen en $dias días"] : ['ok', 'Vigentes']); ?>
          <tr>
            <td>
              <strong><?= h($a['cliente']) ?></strong><br>
              <span class="muted"><?= h(mml_rut_formatear($rut)) ?></span>
              <details>
                <summary>Historial (<?= count($c['historial']) ?>)</summary>
                <?php foreach (array_reverse($c['historial']) as $hst): ?>
                  <div class="muted" style="margin-top: 4px;"><?= h($hst['creada_en']) ?> · <?= h(LICENCIA_PLANES[$hst['plan']]['nombre'] ?? $hst['plan']) ?> · hasta <?= h($hst['actualizaciones_hasta']) ?><?= $hst['nota'] ? ' · ' . h($hst['nota']) : '' ?></div>
                <?php endforeach; ?>
              </details>
            </td>
            <td>
              <span class="pill plan"><?= h(LICENCIA_PLANES[$a['plan']]['nombre'] ?? $a['plan']) ?></span>
              <?php foreach (($a['extras'] ?? []) as $x): ?>
                <br><span class="pill ok" style="margin-top: 4px;">+ <?= h(explode(' (', LICENCIA_EXTRAS[$x] ?? $x)[0]) ?></span>
              <?php endforeach; ?>
            </td>
            <td><?= h($vence) ?><br><span class="pill <?= $estado[0] ?>"><?= h($estado[1]) ?></span></td>
            <td>
              <div class="acciones">
                <textarea id="lic_<?= h($a['id']) ?>" style="position: absolute; left: -9999px;" readonly><?= h($a['texto']) ?></textarea>
                <button class="small" type="button" onclick="copiar('lic_<?= h($a['id']) ?>', this)">Copiar</button>
                <form method="POST">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="accion" value="renovar">
                  <input type="hidden" name="rut" value="<?= h($rut) ?>">
                  <select name="meses"><option value="12">+12 meses</option><option value="6">+6 meses</option><option value="24">+24 meses</option></select>
                  <button class="small" type="submit">Renovar</button>
                </form>
                <?php foreach (LICENCIA_EXTRAS as $k => $nom): if (in_array($k, $a['extras'] ?? [], true)) continue; ?>
                <form method="POST" onsubmit="return confirm('¿Activar <?= h(addslashes(explode(' (', $nom)[0])) ?> para <?= h(addslashes($a['cliente'])) ?>?')">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="accion" value="activar_extra">
                  <input type="hidden" name="rut" value="<?= h($rut) ?>">
                  <input type="hidden" name="extra" value="<?= h($k) ?>">
                  <button class="small" type="submit">Activar <?= h(explode(' (', $nom)[0]) ?></button>
                </form>
                <?php endforeach; ?>
                <?php if ($a['plan'] !== 'comercio'): ?>
                <form method="POST" onsubmit="return confirm('¿Crear licencia Comercio para <?= h(addslashes($a['cliente'])) ?>?')">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="accion" value="subir_plan">
                  <input type="hidden" name="rut" value="<?= h($rut) ?>">
                  <button class="small" type="submit">Pasar a Comercio</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
      <p class="muted" style="margin-top: 14px;">
        Registro: <code><?= h(mml_ruta_registro()) ?></code><br>
        Clave privada: <code><?= h(mml_ruta_privada()) ?></code> — respáldala junto al registro (pendrive o nube).
        Si la pierdes no podrás emitir licencias para tus clientes actuales.
      </p>
    </div>
  </div>
</div>
<script>
async function copiar(id, btn) {
  const el = document.getElementById(id);
  try { await navigator.clipboard.writeText(el.value); } catch (e) { el.select(); document.execCommand('copy'); }
  const t = btn.textContent; btn.textContent = 'Copiada ✓'; setTimeout(() => btn.textContent = t, 1500);
}
</script>
</body>
</html>
