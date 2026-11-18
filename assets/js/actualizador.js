/**
 * Sistema de Actualizaciones Automáticas Remotas - Minimarket POS
 * Comprueba periódicamente y al abrir el programa si existe una nueva versión,
 * avisa al usuario con el changelog y aplica la actualización silenciosamente.
 */

(function () {
  'use strict';

  let updateDataGlobal = null;
  let pingInterval = null;

  // Renderizar o mostrar el modal de actualización
  function mostrarModalActualizacion(datos) {
    updateDataGlobal = datos;
    const modal = document.getElementById('modalActualizacionPOS');
    if (!modal) return;

    // Actualizar campos
    const txtNueva = document.getElementById('updateModalNuevaVersion');
    const txtActual = document.getElementById('updateModalVersionActual');
    const txtFecha = document.getElementById('updateModalFecha');
    const txtTamano = document.getElementById('updateModalTamano');
    const ulNovedades = document.getElementById('updateModalNovedades');
    const divLicencia = document.getElementById('updateModalLicenciaAviso');
    const btnInstalar = document.getElementById('btnIniciarActualizacion');
    const vistaDetalle = document.getElementById('updateModalVistaDetalle');
    const vistaProgreso = document.getElementById('updateModalVistaProgreso');

    if (txtNueva) txtNueva.textContent = datos.nueva_version || 'Nueva versión';
    if (txtActual) txtActual.textContent = datos.version_actual || '';
    if (txtFecha) txtFecha.textContent = datos.fecha ? 'Lanzada el ' + datos.fecha : '';
    if (txtTamano) txtTamano.textContent = datos.tamano_mb ? '(' + datos.tamano_mb + ' MB)' : '';

    // Novedades (changelog)
    if (ulNovedades) {
      ulNovedades.innerHTML = '';
      const items = Array.isArray(datos.novedades) && datos.novedades.length > 0 
        ? datos.novedades 
        : ['Mejoras de rendimiento, estabilidad y correcciones generales.'];
      items.forEach(function (it) {
        const li = document.createElement('li');
        li.className = 'is-visible';
        li.style.cssText = 'opacity: 1 !important; transform: none !important; color: #f1f5f9; display: flex; gap: 0.6rem; align-items: flex-start;';
        li.innerHTML = '<i class="fa-solid fa-circle-check" style="color: var(--success, #10b981); margin-top: 0.15rem; flex-shrink: 0;"></i><span style="color: #f1f5f9;">' + it + '</span>';
        ulNovedades.appendChild(li);
      });
    }

    // Licencia
    if (divLicencia) {
      if (!datos.permite_instalar && datos.mensaje_licencia) {
        divLicencia.style.display = 'block';
        divLicencia.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i> ' + datos.mensaje_licencia;
        if (btnInstalar) {
          btnInstalar.disabled = true;
          btnInstalar.style.opacity = '0.5';
          btnInstalar.title = 'Requiere renovar la licencia';
        }
      } else {
        divLicencia.style.display = 'none';
        if (btnInstalar) {
          btnInstalar.disabled = false;
          btnInstalar.style.opacity = '1';
        }
      }
    }

    if (vistaDetalle) vistaDetalle.style.display = 'block';
    if (vistaProgreso) vistaProgreso.style.display = 'none';

    modal.style.display = 'flex';
  }

  function cerrarModalActualizacion(posponer = false) {
    const modal = document.getElementById('modalActualizacionPOS');
    if (modal) modal.style.display = 'none';
    if (posponer) {
      // Posponer aviso por 12 horas
      try {
        localStorage.setItem('pos_update_dismissed_until', String(Date.now() + 12 * 3600 * 1000));
      } catch (e) {}
    }
  }

  // Ejecutar proceso de descarga y actualización
  function ejecutarActualizacion() {
    if (!updateDataGlobal) return;

    const vistaDetalle = document.getElementById('updateModalVistaDetalle');
    const vistaProgreso = document.getElementById('updateModalVistaProgreso');
    const lblProgreso = document.getElementById('updateProgresoEstado');
    const lblSubProgreso = document.getElementById('updateProgresoSubtexto');
    const btnCerrar = document.getElementById('updateModalBtnCerrar');

    if (vistaDetalle) vistaDetalle.style.display = 'none';
    if (vistaProgreso) vistaProgreso.style.display = 'flex';
    if (btnCerrar) btnCerrar.style.display = 'none';

    if (lblProgreso) lblProgreso.textContent = 'Descargando paquete de actualización...';
    if (lblSubProgreso) lblSubProgreso.textContent = 'Por favor espere, no cierre la ventana ni apague el equipo.';

    const csrfToken = window.CSRF_TOKEN || (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '');

    const formData = new FormData();
    formData.append('action', 'download_and_install');
    formData.append('url', updateDataGlobal.url || '');
    formData.append('sha256', updateDataGlobal.sha256 || '');

    fetch('api/actualizacion.php', {
      method: 'POST',
      headers: {
        'X-CSRF-Token': csrfToken
      },
      body: formData
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.ok) {
        throw new Error(res.error || 'Error desconocido al descargar la actualización.');
      }

      if (lblProgreso) lblProgreso.textContent = 'Instalando actualización y reiniciando servicios...';
      if (lblSubProgreso) lblSubProgreso.textContent = 'Los servicios se están reiniciando. Reconectando en breve...';

      // Esperar 4 segundos antes de comenzar a chequear si Apache volvió
      setTimeout(function () {
        iniciarSondeoReconexion();
      }, 4000);
    })
    .catch(function (err) {
      if (lblProgreso) lblProgreso.textContent = 'Error al actualizar';
      if (lblSubProgreso) lblSubProgreso.innerHTML = '<span style="color: var(--danger);">' + err.message + '</span>';
      if (btnCerrar) {
        btnCerrar.style.display = 'inline-block';
        btnCerrar.textContent = 'Cerrar';
      }
    });
  }

  // Sondeo cada 2 segundos a api/ping.php hasta que el servidor web responda
  function iniciarSondeoReconexion() {
    if (pingInterval) clearInterval(pingInterval);

    let intentos = 0;
    const maxIntentos = 45; // ~90 segundos máximo de espera
    const lblProgreso = document.getElementById('updateProgresoEstado');
    const lblSubProgreso = document.getElementById('updateProgresoSubtexto');

    pingInterval = setInterval(function () {
      intentos++;
      fetch('api/ping.php?t=' + Date.now(), { cache: 'no-store' })
        .then(function (r) {
          if (!r.ok) throw new Error('Servidor no listo');
          return r.json();
        })
        .then(function (pingRes) {
          if (pingRes && pingRes.ok) {
            clearInterval(pingInterval);
            if (lblProgreso) lblProgreso.textContent = '¡Actualización completada!';
            if (lblSubProgreso) lblSubProgreso.textContent = 'Reiniciando el sistema a la versión ' + (pingRes.version || '') + '...';

            try {
              sessionStorage.removeItem('pos_update_checked_session');
              // Al borrar last_seen_version, el overlay de bienvenida se mostrará celebrando la nueva versión
              localStorage.removeItem('last_seen_version');
            } catch (e) {}

            setTimeout(function () {
              window.location.reload();
            }, 1200);
          }
        })
        .catch(function () {
          if (intentos >= maxIntentos) {
            clearInterval(pingInterval);
            if (lblProgreso) lblProgreso.textContent = 'Tiempo de espera superado';
            if (lblSubProgreso) {
              lblSubProgreso.innerHTML = 'El instalador puede estar tardando más de lo habitual. Haz <a href="javascript:location.reload()" style="color:var(--primary); font-weight:bold;">clic aquí para recargar</a>.';
            }
            const btnCerrar = document.getElementById('updateModalBtnCerrar');
            if (btnCerrar) btnCerrar.style.display = 'inline-block';
          }
        });
    }, 2000);
  }

  // Función pública para comprobar actualizaciones (manual o automática)
  window.comprobarActualizaciones = function (esManual = false) {
    if (!esManual) {
      // Si no es manual, verificar si se pospuso recientemente
      try {
        const pospuesto = localStorage.getItem('pos_update_dismissed_until');
        if (pospuesto && Number(pospuesto) > Date.now()) {
          return;
        }
      } catch (e) {}
    }

    const btnManual = document.getElementById('btnBuscarActualizacionesManual');
    let textoOriginal = '';
    if (btnManual) {
      textoOriginal = btnManual.innerHTML;
      btnManual.disabled = true;
      btnManual.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Comprobando...';
    }

    fetch('api/actualizacion.php?action=check', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (btnManual) {
          btnManual.disabled = false;
          btnManual.innerHTML = textoOriginal;
        }

        if (!res.ok) {
          if (esManual) {
            if (window.toast) {
              window.toast(res.error || 'No se pudo consultar el servidor de actualizaciones.', 'warning');
            } else {
              alert(res.error || 'No se pudo consultar el servidor de actualizaciones.');
            }
          }
          return;
        }

        if (res.hay_actualizacion) {
          if (!esManual && res.modo === 'automatica' && res.permite_instalar) {
            // Modo automático silencioso: iniciar de inmediato mostrando el progreso
            mostrarModalActualizacion(res);
            ejecutarActualizacion();
          } else {
            mostrarModalActualizacion(res);
          }
        } else {
          if (esManual) {
            if (window.toast) {
              window.toast('El sistema ya está al día (Versión ' + res.version_actual + ').', 'success');
            } else {
              alert('El sistema ya está al día (Versión ' + res.version_actual + ').');
            }
          }
        }
      })
      .catch(function (err) {
        if (btnManual) {
          btnManual.disabled = false;
          btnManual.innerHTML = textoOriginal;
        }
        if (esManual) {
          if (window.toast) {
            window.toast('Error de red al comprobar actualizaciones.', 'danger');
          } else {
            alert('Error de red al comprobar actualizaciones.');
          }
        }
      });
  };

  // Exponer funciones auxiliares para botones onclick
  window.cerrarModalActualizacion = cerrarModalActualizacion;
  window.ejecutarActualizacion = ejecutarActualizacion;

  // Verificación automática al abrir el programa:
  // Se ejecuta 2.5 segundos después de cargar la página para no ralentizar el inicio de la caja.
  document.addEventListener('DOMContentLoaded', function () {
    try {
      const yaRevisado = sessionStorage.getItem('pos_update_checked_session');
      if (!yaRevisado) {
        sessionStorage.setItem('pos_update_checked_session', '1');
        setTimeout(function () {
          window.comprobarActualizaciones(false);
        }, 2500);
      }
    } catch (e) {}
  });

})();
