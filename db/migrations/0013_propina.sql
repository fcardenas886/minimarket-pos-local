-- Propina voluntaria (Chile: se sugiere un 10%, el cliente puede dar menos o nada).
-- No forma parte de la venta: no lleva IVA ni va en la boleta. Se guarda aparte para
-- mostrarla en el cierre de caja; el dinero queda en pagosventa tal como se recibió.
-- Requerida por: api/registrar_venta.php, pos.php, caja.php

ALTER TABLE ventas ADD COLUMN Propina INT NOT NULL DEFAULT 0 AFTER MontoTotal;

INSERT IGNORE INTO configuraciones (Clave, Valor, Descripcion) VALUES
  ('PROPINA_ACTIVA', 'NO', 'Ofrecer propina voluntaria en la pantalla de pago'),
  ('PROPINA_PORCENTAJE', '10', 'Porcentaje de propina sugerida');
