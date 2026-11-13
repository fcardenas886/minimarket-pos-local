-- Datos base mínimos para una instalación nueva (sin productos, ventas ni usuarios).
-- El usuario administrador y los datos del negocio los crea instalar.php.

SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `roles` (`RolID`, `Nombre`, `Descripcion`, `Activo`) VALUES
  ('1', 'Administrador', 'Acceso total al sistema, configuraciones, usuarios y reportes', '1'),
  ('2', 'Supervisor', 'Gestión de inventarios, anulación de ventas, compras y cambios de precios', '1'),
  ('3', 'Cajero', 'Operación del punto de venta, apertura y cierre de caja asignada', '1');

INSERT INTO `permisos` (`PermisoID`, `Nombre`, `Descripcion`) VALUES
  ('1', 'VENTAS_CREAR', 'Permite realizar ventas y emitir boletas/facturas'),
  ('2', 'VENTAS_ANULAR', 'Permite anular ventas completadas (Supervisor/Administrador)'),
  ('3', 'DEVOLUCIONES_CREAR', 'Permite procesar devoluciones parciales o totales de productos'),
  ('4', 'INVENTARIO_AJUSTAR', 'Permite registrar mermas, pérdidas o corregir stock manualmente'),
  ('5', 'COMPRAS_REGISTRAR', 'Permite registrar facturas de compras de proveedores'),
  ('6', 'PRECIOS_MODIFICAR', 'Permite modificar precios de venta y costos de los productos'),
  ('7', 'USUARIOS_GESTIONAR', 'Permite crear, modificar usuarios, asignar roles y permisos'),
  ('8', 'REPORTES_VER', 'Permite visualizar reportes de ventas, utilidades e inventarios');

INSERT INTO `rolespermisos` (`RolID`, `PermisoID`) VALUES
  ('1', '1'),
  ('2', '1'),
  ('3', '1'),
  ('1', '2'),
  ('2', '2'),
  ('1', '3'),
  ('2', '3'),
  ('3', '3'),
  ('1', '4'),
  ('2', '4'),
  ('1', '5'),
  ('2', '5'),
  ('1', '6'),
  ('2', '6'),
  ('1', '7'),
  ('1', '8'),
  ('2', '8');

INSERT INTO `categorias` (`CategoriaID`, `Nombre`, `Descripcion`) VALUES
  ('1', 'Abarrotes', 'Arroz, aceites, pastas, salsas y enlatados en general'),
  ('2', 'Lácteos y Fiambrería', 'Leches, quesos, yogures, cecinas, jamones'),
  ('3', 'Bebidas y Alcoholes', 'Bebidas gaseosas, jugos, aguas minerales, cervezas, licores'),
  ('4', 'Panadería y Pastelería', 'Pan fresco del día, pasteles, masas dulces y empanadas'),
  ('5', 'Frutas y Verduras', 'Frutas, verduras frescas, hortalizas y legumbres a granel'),
  ('6', 'Perfumeria', 'Aseo Personal, Cosmeticos'),
  ('7', 'Carniceria', 'Vacuno - Cerdo - Pollo');

INSERT INTO `cajas` (`CajaID`, `Nombre`, `Activa`) VALUES (1, 'Caja Principal', 1);

INSERT INTO `configuraciones` (`Clave`, `Valor`, `Descripcion`) VALUES
  ('ARQUEO_CIEGO', 'true', NULL),
  ('ARQUEO_CIEGO_ACTIVO', 'true', 'Habilita el conteo manual obligatorio al cerrar caja'),
  ('BALANZA_INGRESO_MANUAL', 'precio', NULL),
  ('BALANZA_MODO', 'manual', NULL),
  ('BALANZA_PREFIJO_CONSOLIDADO', '28', 'Prefijo de codigo de barra de vale consolidado (normalmente 28)'),
  ('BALANZA_PREFIJO_INDIVIDUAL', '20', 'Prefijo de codigo de barra de etiqueta individual (normalmente 20)'),
  ('BALANZA_TIPO_EAN', 'plu_peso', 'Formato del codigo de barra individual (plu_peso o plu_precio)'),
  ('CREDITO_INTERNO_ACTIVO', 'true', 'Permite cobro con crédito interno/fiado'),
  ('DTE_PROVEEDOR', 'ninguno', 'Proveedor de emision DTE: haulmer o local'),
  ('IVA_PORCENTAJE', '19', 'Porcentaje de IVA por defecto'),
  ('LOYALTY_ACTIVO', 'false', 'Activa el Club de Puntos y Fidelización'),
  ('LOYALTY_PORCENTAJE', '1.0', 'Porcentaje de acumulación de puntos sobre el total'),
  ('MINIMARKET_ACTECO', '', 'Codigo Acteco SII'),
  ('MINIMARKET_COMUNA', '', 'Comuna de origen SII'),
  ('MINIMARKET_DIRECCION', '', 'Dirección de la sucursal'),
  ('MINIMARKET_GIRO', '', 'Giro comercial'),
  ('MINIMARKET_NOMBRE', '', 'Nombre fantasía del negocio'),
  ('MINIMARKET_RUT', '', 'RUT del emisor comercial'),
  ('MINIMARKET_TELEFONO', '', NULL),
  ('PERMITIR_STOCK_NEGATIVO', 'true', NULL),
  ('POS_DESCUENTO_MAX_PORC', '5', 'Porcentaje (%) maximo de descuento permitido en caja sin clave de supervisor'),
  ('POS_DISENO_GRID', 'estandar', NULL),
  ('POS_LAYOUT_MODO', 'supermercado', NULL),
  ('POS_REQ_SUPERVISOR_CANCELAR', 'SI', 'Requerir clave de supervisor para cancelar o vaciar venta en proceso en POS'),
  ('POS_REQ_SUPERVISOR_ELIMINAR_ITEM', 'SI', 'Requerir clave de supervisor para eliminar un producto del carrito en POS'),
  ('SONIDO_LECTOR_RAPIDO', 'NO', 'Activa o desactiva el sonido beep al escanear productos en el lector r??pido'),
  ('TEMA_COLOR_ACENTO', 'indigo', NULL),
  ('TEMA_MODO', 'light', NULL),
  ('TICKET_PIE_PAGINA', '¡Gracias por su preferencia!', NULL);

INSERT INTO `configuraciones` (`Clave`, `Valor`, `Descripcion`) VALUES
  ('EBOLETA_RUT_USUARIO', '', 'RUT de la persona que ingresa a eboleta.sii.cl'),
  ('EBOLETA_CLAVE_CIFRADA', '', 'Clave Tributaria cifrada (AES-256-GCM)'),
  ('EBOLETA_SIMULAR_FALLO', 'NO', 'Solo simulador: NO, conexion, rechazo o incierto');

-- Módulo Mesas y garzones
INSERT INTO `roles` (`RolID`, `Nombre`, `Descripcion`, `Activo`) VALUES (4, 'Garzón', 'Toma pedidos de mesas desde tablet o celular con PIN', 1);
INSERT INTO `configuraciones` (`Clave`, `Valor`, `Descripcion`) VALUES ('MESAS_IMPRIMIR_COMANDA', 'SI', 'Imprimir automáticamente en caja cada comanda nueva de los garzones');

SET FOREIGN_KEY_CHECKS = 1;
