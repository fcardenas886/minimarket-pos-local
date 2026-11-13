-- Cola de boletas electrónicas (e-Boleta) y credenciales del SII.
-- Requerida por: includes/sii/EBoletaCola.php, api/registrar_venta.php, scripts/emitir_pendientes.php
--
-- dte_emitidos pasa a funcionar como cola: una boleta puede existir sin folio
-- (Pendiente) hasta que el SII la acepta.

ALTER TABLE dte_emitidos
  MODIFY Folio INT NULL,
  MODIFY PdfUrl VARCHAR(500) NULL,
  ADD COLUMN Intentos INT NOT NULL DEFAULT 0 AFTER EstadoSii,
  ADD COLUMN UltimoIntento DATETIME NULL AFTER Intentos,
  ADD COLUMN Payload MEDIUMTEXT NULL AFTER Mensaje,
  ADD INDEX idx_dte_estado (TipoDocumento, EstadoSii);

-- RUT de la PERSONA que entra al SII y su Clave Tributaria (cifrada con la llave del equipo).
INSERT IGNORE INTO configuraciones (Clave, Valor, Descripcion) VALUES
  ('EBOLETA_RUT_USUARIO', '', 'RUT de la persona que ingresa a eboleta.sii.cl'),
  ('EBOLETA_CLAVE_CIFRADA', '', 'Clave Tributaria cifrada (AES-256-GCM)'),
  ('EBOLETA_SIMULAR_FALLO', 'NO', 'Solo simulador: NO, conexion, rechazo o incierto');
