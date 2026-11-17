-- Migración 0014: Regla de redondeo de la Ley 20.956 para pagos en efectivo en Chile
-- (1 a 5 pesos hacia abajo a 0; 6 a 9 pesos hacia arriba a 10).
-- Aplica exclusivamente a pagos en efectivo. La boleta electrónica se mantiene por el total comercial.

ALTER TABLE ventas ADD COLUMN Redondeo INT NOT NULL DEFAULT 0 AFTER Propina;

INSERT IGNORE INTO configuraciones (Clave, Valor, Descripcion) VALUES
  ('REDONDEO_LEY_ACTIVO', 'true', 'Aplicar regla de redondeo (Ley 20.956) en pagos en efectivo');
