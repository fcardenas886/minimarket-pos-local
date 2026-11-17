-- Migración 0015: Configuración para el sistema de actualizaciones automáticas remotas
-- Permite recibir actualizaciones sin visitar al cliente físicamente.

INSERT IGNORE INTO configuraciones (Clave, Valor, Descripcion) VALUES
  ('UPDATE_CHECK_URL', '', 'URL remota del archivo version.json para comprobar actualizaciones'),
  ('UPDATE_MODO', 'preguntar', 'Modo de actualización: preguntar, automatica o desactivada'),
  ('UPDATE_ULTIMA_REVISION', '', 'Fecha y hora de la última comprobación de actualizaciones');
