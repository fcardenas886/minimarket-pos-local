-- Migración 0016: Pre-cuenta y control operativo para el módulo Mesas y garzones
ALTER TABLE mesapedidos ADD COLUMN PrecuentaImpresa TINYINT(1) NOT NULL DEFAULT 0 AFTER VentaID;
ALTER TABLE mesapedidos ADD COLUMN FechaPrecuenta DATETIME NULL AFTER PrecuentaImpresa;
