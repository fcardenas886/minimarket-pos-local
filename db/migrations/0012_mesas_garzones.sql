-- Módulo "Mesas y garzones" (extra por licencia) + productos sin control de stock.
-- Requerida por: garzon.php, cocina.php, api/mesas.php, pos.php, api/registrar_venta.php

-- Productos que no descuentan inventario (platos preparados, servicios, recargas).
ALTER TABLE productos ADD COLUMN ControlaStock TINYINT(1) NOT NULL DEFAULT 1 AFTER EsPrecioVariable;

-- PIN de acceso rápido (garzones). Se guarda con password_hash.
ALTER TABLE usuarios ADD COLUMN PinHash VARCHAR(255) NULL AFTER PasswordHash;

-- Rol Garzón: solo entra a la pantalla de garzón/cocina con su PIN.
INSERT INTO roles (Nombre, Descripcion, Activo)
SELECT 'Garzón', 'Toma pedidos de mesas desde tablet o celular con PIN', TRUE
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE Nombre = 'Garzón');

CREATE TABLE IF NOT EXISTS mesas (
  MesaID INT NOT NULL AUTO_INCREMENT,
  Nombre VARCHAR(40) NOT NULL,
  Zona VARCHAR(40) NOT NULL DEFAULT 'Salón',
  Orden INT NOT NULL DEFAULT 0,
  Activa TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (MesaID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuenta de una mesa: se abre con el primer pedido y se cierra al cobrar en caja.
CREATE TABLE IF NOT EXISTS mesapedidos (
  PedidoID INT NOT NULL AUTO_INCREMENT,
  MesaID INT NOT NULL,
  Estado VARCHAR(15) NOT NULL DEFAULT 'Abierta',   -- Abierta, Cobrada, Anulada
  FechaApertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FechaCierre DATETIME NULL,
  UsuarioApertura INT NULL,
  VentaID INT NULL,
  PRIMARY KEY (PedidoID),
  KEY idx_mesa_estado (MesaID, Estado),
  CONSTRAINT fk_mesapedidos_mesa FOREIGN KEY (MesaID) REFERENCES mesas (MesaID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada envío del garzón es una comanda (lo que ve e imprime la cocina).
CREATE TABLE IF NOT EXISTS comandas (
  ComandaID INT NOT NULL AUTO_INCREMENT,
  PedidoID INT NOT NULL,
  UsuarioID INT NULL,
  Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  EstadoCocina VARCHAR(15) NOT NULL DEFAULT 'Pendiente',  -- Pendiente, Lista
  FechaLista DATETIME NULL,
  Impresa TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (ComandaID),
  KEY idx_comandas_estado (EstadoCocina),
  KEY idx_comandas_impresa (Impresa),
  CONSTRAINT fk_comandas_pedido FOREIGN KEY (PedidoID) REFERENCES mesapedidos (PedidoID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mesapedidosdetalle (
  DetalleID INT NOT NULL AUTO_INCREMENT,
  PedidoID INT NOT NULL,
  ComandaID INT NOT NULL,
  ProductoID INT NOT NULL,
  Cantidad DECIMAL(10,3) NOT NULL,
  PrecioUnitario INT NOT NULL,
  Nota VARCHAR(150) NULL,
  Anulado TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (DetalleID),
  KEY idx_detalle_pedido (PedidoID),
  CONSTRAINT fk_mpd_pedido FOREIGN KEY (PedidoID) REFERENCES mesapedidos (PedidoID),
  CONSTRAINT fk_mpd_comanda FOREIGN KEY (ComandaID) REFERENCES comandas (ComandaID),
  CONSTRAINT fk_mpd_producto FOREIGN KEY (ProductoID) REFERENCES productos (ProductoID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuraciones (Clave, Valor, Descripcion) VALUES
  ('MESAS_IMPRIMIR_COMANDA', 'SI', 'Imprimir automáticamente en caja cada comanda nueva de los garzones');
