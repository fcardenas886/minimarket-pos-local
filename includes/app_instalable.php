<?php
/**
 * Hace instalables las pantallas de garzón y cocina en celulares y tablets
 * ("Agregar a pantalla de inicio"): manifiesto, íconos, modo pantalla completa
 * y un aviso con las instrucciones según el equipo.
 */

/** Etiquetas para el <head>. $app: 'garzon' o 'cocina'. */
function app_instalable_head(string $app, string $tituloCorto, string $color): void {
    $app = $app === 'cocina' ? 'cocina' : 'garzon';
    ?>
<link rel="manifest" href="manifest.php?app=<?= $app ?>">
<link rel="icon" type="image/png" sizes="192x192" href="assets/img/app-192.png">
<link rel="apple-touch-icon" href="assets/img/app-180.png">
<meta name="theme-color" content="<?= htmlspecialchars($color) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($tituloCorto) ?>">
<?php
}

/** Aviso "Instala esta pantalla" (se oculta solo cuando ya está instalada o si se descarta). */
function app_instalable_aviso(string $app): void {
    $clave = 'aviso_instalar_' . ($app === 'cocina' ? 'cocina' : 'garzon');
    ?>
<div id="avisoInstalar" style="display:none; margin: 12px 16px 0; padding: 12px 14px; border-radius: 12px; background: #eef2ff; color: #1e1b4b; border: 1px solid #c7d2fe; font-size: 0.92rem; line-height: 1.4;">
  <div style="display:flex; gap:10px; align-items:flex-start;">
    <div style="font-size:1.4rem;">📲</div>
    <div style="flex:1;">
      <b>Instala esta pantalla como app</b>
      <div id="avisoInstalarTexto" style="margin-top:4px;"></div>
      <button type="button" id="avisoInstalarBtn" style="display:none; margin-top:8px; padding:9px 16px; border:0; border-radius:8px; background:#4f46e5; color:#fff; font-weight:700; font-size:0.95rem;">Instalar app</button>
    </div>
    <button type="button" onclick="cerrarAvisoInstalar()" aria-label="Cerrar" style="background:none; border:0; font-size:1.2rem; color:#4338ca; cursor:pointer;">✕</button>
  </div>
</div>
<script>
(function () {
  const CLAVE = <?= json_encode($clave) ?>;
  const instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  let descartado = false;
  try { descartado = localStorage.getItem(CLAVE) === '1'; } catch (e) {}
  const aviso = document.getElementById('avisoInstalar');
  const texto = document.getElementById('avisoInstalarTexto');
  const boton = document.getElementById('avisoInstalarBtn');
  window.cerrarAvisoInstalar = () => { aviso.style.display = 'none'; try { localStorage.setItem(CLAVE, '1'); } catch (e) {} };

  // La app funciona con o sin esto; con conexión segura (o el ajuste de Chrome) permite instalación completa.
  if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  }
  if (instalada || descartado) return;

  const ua = navigator.userAgent;
  const esIOS = /iPhone|iPad|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const esAndroid = /Android/i.test(ua);
  if (esIOS) {
    texto.innerHTML = 'En <b>Safari</b> toca <b>Compartir</b> (el cuadrado con la flecha ↑) y luego <b>“Agregar a inicio”</b>.';
  } else if (esAndroid) {
    texto.innerHTML = 'En <b>Chrome</b> toca el menú <b>⋮</b> (arriba a la derecha) y luego <b>“Agregar a la pantalla principal”</b> o <b>“Instalar app”</b>.';
  } else {
    texto.innerHTML = 'En <b>Chrome</b> o <b>Edge</b>: menú <b>⋮</b> → <b>“Guardar y compartir” / “Aplicaciones”</b> → <b>“Instalar”</b> o <b>“Crear acceso directo”</b> (marca “Abrir como ventana”).';
  }
  aviso.style.display = 'block';

  // Si el navegador ofrece instalación directa, mostrar el botón.
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    boton.style.display = 'inline-block';
    boton.onclick = async () => { e.prompt(); const r = await e.userChoice; if (r.outcome === 'accepted') cerrarAvisoInstalar(); };
  });
  window.addEventListener('appinstalled', () => cerrarAvisoInstalar());
})();
</script>
<?php
}
