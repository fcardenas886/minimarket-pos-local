# Documentación técnica — Minimarket POS, edición local

**Versión documentada:** v4.1.0 · **Repositorio:** `fcardenas886/minimarket-pos-local` (privado)
**Público:** equipo de desarrollo, soporte e implementación.

> La versión anterior de este documento (sistema v3.3.0 completo) está en el historial de git, commit `97e24cb`.

---

## Índice

1. [Qué es el sistema](#1-qué-es-el-sistema)
2. [Metodología de trabajo](#2-metodología-de-trabajo)
3. [Tecnologías y por qué se eligieron](#3-tecnologías-y-por-qué-se-eligieron)
4. [Arquitectura](#4-arquitectura)
5. [Estructura del proyecto](#5-estructura-del-proyecto)
6. [Componentes principales](#6-componentes-principales)
7. [Comunicación entre componentes](#7-comunicación-entre-componentes)
8. [Base de datos y migraciones](#8-base-de-datos-y-migraciones)
9. [Seguridad](#9-seguridad)
10. [Entorno de desarrollo](#10-entorno-de-desarrollo)
11. [Compilación, versionado y entrega](#11-compilación-versionado-y-entrega)
12. [Operación y soporte](#12-operación-y-soporte)
13. [Riesgos conocidos y deuda técnica](#13-riesgos-conocidos-y-deuda-técnica)
14. [Glosario](#14-glosario)

---

## 1. Qué es el sistema

Punto de venta para comercios chilenos pequeños (almacenes, minimarkets, botillerías y, con un módulo extra, locales de comida). Se **instala en el computador del cliente** como un programa de Windows y funciona sin internet, salvo para emitir boletas electrónicas.

Existe además un **POS online** (otro producto, otro repositorio). Esta edición local está recortada a propósito para no competir con él:

| | **Básico** (local) | **Comercio** (local) | **POS online** |
|---|---|---|---|
| POS, caja, ventas, boleta e-Boleta | ✅ | ✅ | ✅ |
| Productos, stock simple, reportes de ventas | ✅ | ✅ | ✅ |
| Clientes y fiados, devoluciones, compras, kardex, inventario, promociones, actualizador de precios | — | ✅ | ✅ |
| Usuarios (sin contar garzones) | 2 | 3 | sin límite |
| Supervisor, utilidades, varias cajas, varias sucursales, factura | — | — | ✅ |

**Módulo extra** (se vende aparte, se suma a cualquier plan): **Mesas y garzones** — pedidos desde celular/tablet, pantalla de cocina, comandas impresas.

**Modelo comercial:** licencia **de por vida** ligada al RUT del negocio, con **actualizaciones incluidas por un período** (`actualizaciones_hasta`). Los cambios del SII posteriores a ese período se cobran como renovación de soporte.

---

## 2. Metodología de trabajo

El proyecto se desarrolla de forma **iterativa e incremental**: entregas pequeñas, cada una usable, probada y versionada.

### 2.1 Ciclo de cada cambio

1. **Acordar el alcance** con el dueño del producto (qué plan lo incluye, qué queda para el online).
2. **Revisar el código existente** antes de escribir: reutilizar lo que hay (por ejemplo, "Pausar venta" usa la API de cotizaciones; el stock simple usa las tablas de ajustes).
3. **Implementar** con cambios mínimos y comentarios en español que expliquen el *por qué*.
4. **Probar** de verdad, no solo "compila":
   - `php -l` y `node --check` de cada archivo tocado.
   - Scripts de prueba por terminal (por ejemplo, los escenarios de la cola de boletas).
   - Recorrido en el navegador del flujo completo (venta, ticket, cierre de caja).
   - Validaciones del servidor probadas enviando peticiones inválidas a propósito.
   - Verificación externa cuando aplica: vectores oficiales de AWS para la firma SigV4, OpenCV para leer los QR generados.
5. **Revisión de seguridad antes del commit:** que no se suban `.env`, licencias, llaves ni datos de clientes.
6. **Versionar:** subir `APP_VERSION`, escribir las novedades, compilar el instalador, commit y etiqueta.

### 2.2 Ramas y versiones

- Rama principal: **`master`**. Cada versión publicada lleva una **etiqueta** (`v4.0.0`, `v4.1.0`).
- **Versionado semántico:** `MAYOR.MENOR.PARCHE`.
  - MAYOR: cambio de edición o incompatible (v3 → v4: nace la edición local).
  - MENOR: funciones nuevas compatibles (v4.1: propina).
  - PARCHE: correcciones.
- Recomendado al crecer el equipo: una rama por cambio (`feature/...`, `fix/...`) y *pull request* con revisión antes de unir a `master`.

### 2.3 Convenciones

- **Idioma:** código de negocio, comentarios, mensajes y commits **en español** (el dominio es chileno: RUT, boleta, SII, fiado).
- **Mensajes al usuario:** en lenguaje simple, explicando qué pasó y qué hacer.
- **Commits:** título corto con la versión o el área (`v4.1.0: propina voluntaria...`) y cuerpo con viñetas.
- **Base de datos:** todo cambio de tablas va en una migración numerada nueva; nunca se editan las ya publicadas.

---

## 3. Tecnologías y por qué se eligieron

| Capa | Tecnología | Por qué |
|---|---|---|
| Lenguaje servidor | **PHP 8.1**, sin framework | Ver 3.1. |
| Base de datos | **MySQL 8** (InnoDB, utf8mb4) | Transacciones y bloqueo de filas (`FOR UPDATE`) para que dos ventas no descuenten el mismo stock ni dos garzones dupliquen una cuenta; muy conocida; viene con Laragon. |
| Servidor web | **Apache 2.4** con `mod_php` | Estándar en Windows; `.htaccess` permite proteger carpetas sin tocar la configuración central; se instala como servicio de Windows. |
| Interfaz | **HTML + CSS + JavaScript sin framework** | No hay paso de compilación (*build*): lo que está en el repositorio es lo que corre. Carga rápido en PCs modestos y pantallas táctiles. |
| Íconos | Font Awesome (copia local) | Funciona sin internet. |
| Criptografía | **OpenSSL** (incluido en PHP) | Firmas ECDSA de licencias, AES-256-GCM para la Clave Tributaria; no requiere extensiones extra. |
| HTTP saliente | **cURL** (incluido en PHP) | Emisión de boletas contra el SII con control de tiempos de espera y errores. |
| Instalador | **Inno Setup 6** | Gratuito, maduro, genera un `.exe` estándar de Windows, permite lógica (Pascal) para servicios, actualizaciones y desinstalación. |
| Entorno de desarrollo | **Laragon** | Apache + PHP + MySQL listos en Windows; mismo stack que el instalador. |
| Control de versiones | **Git + GitHub** (privado) | Historial, etiquetas de versión, respaldo del código. |

### 3.1 ¿Por qué PHP y sin framework?

- **El destino es el PC del cliente**, no un servidor administrado: hace falta un stack que se empaquete en un instalador de ~25 MB y arranque solo como servicio de Windows. PHP + Apache + MySQL lo permiten con binarios portables.
- **Sin dependencias externas** (no hay Composer en producción): nada que descargar al instalar, nada que se rompa por una librería desactualizada.
- **Reutilización:** el sistema nace del POS existente, escrito en PHP. Reescribirlo no aportaba valor al cliente.
- **Curva de aprendizaje baja:** cualquier desarrollador web puede mantenerlo; cada pantalla es un archivo PHP con su vista.
- **Costo:** sin licencias de software ni servidores.

El costo de no usar framework es tener que aplicar a mano lo que un framework daría (CSRF, protección de rutas, migraciones). Eso está resuelto en `includes/auth.php`, `includes/licencia.php` e `instalador/configurar.php`, y se describe más abajo.

### 3.2 ¿Por qué JavaScript sin framework?

Las pantallas son formularios y listas con interacción moderada. Un framework (React, Vue) obligaría a compilar y a mantener otro ecosistema. Con JavaScript del navegador se consigue lo necesario y el código se lee directo.

---

## 4. Arquitectura

### 4.1 Vista general (instalación en el cliente)

```
  PC de caja (Windows)                                        Internet
 ┌───────────────────────────────────────────────┐
 │  Navegador Edge (modo app)  ──HTTP──┐          │
 │                                     ▼          │
 │  Servicio MinimarketPOS-Web: Apache + PHP 8.1 ─┼──HTTPS──▶ SII e-Boleta (AWS)
 │        │  127.0.0.1:8090 (o red local)         │           (emisión de boletas)
 │        ▼                                       │
 │  Servicio MinimarketPOS-DB: MySQL 8            │
 │        127.0.0.1:3307 (nunca expuesto a la red)│
 │                                                │
 │  Tareas programadas (SYSTEM):                  │
 │   · emitir_pendientes.php  cada 2 minutos      │
 │   · respaldo.php           diario 23:30        │
 └───────────────────────────────────────────────┘
          ▲  WiFi del local (solo si se activó "acceso de red")
          │  solo garzon.php, cocina.php y api/mesas.php
   Celulares y tablets (garzones, cocina)
```

### 4.2 Patrón de cada pantalla

Arquitectura en **capas simples**, sin framework:

```
pantalla.php            → controlador: valida, consulta la base, prepara variables
  └─ includes/header.php → sesión, licencia, menú (incluye auth.php)
  └─ views/pantalla.view.php → solo presentación (HTML + PHP de salida)
  └─ includes/footer.php
api/accion.php          → endpoint JSON para JavaScript (fetch), valida CSRF
assets/js/*.js          → interacción del navegador
```

### 4.3 Decisiones de diseño clave

| Decisión | Motivo |
|---|---|
| Protección por licencia en **un solo punto** (`auth.php` → `licencia_proteger_script_actual()`) | Todas las pantallas y APIs cargan `auth.php`; un mapa `LICENCIA_SCRIPTS` decide qué script pertenece a qué módulo. Un script nuevo de un módulo solo se agrega al mapa. |
| Boleta en **cola** (`dte_emitidos`) en vez de emisión directa | La venta nunca se pierde por falta de internet; la boleta se emite después. |
| Clasificar errores del SII (`conexion`, `incierto`, `rechazo`, `credenciales`) | Reintentar solo cuando es seguro; nunca duplicar una boleta. |
| Propina **fuera** de la venta | No es parte de la venta (no lleva IVA, no va en la boleta); se guarda aparte para el cierre de caja. |
| Migraciones **numeradas** + tabla `migraciones` | Actualizar instalaciones existentes sin perder datos. |
| Versión de CSS/JS por fecha de archivo (`asset_url()`) | El navegador del cliente nunca usa una copia vieja tras actualizar. |

---

## 5. Estructura del proyecto

```
/                         Pantallas (controladores): pos.php, caja.php, ventas.php, productos.php...
├── garzon.php            Pantalla del garzón (celular/tablet) — módulo Mesas
├── cocina.php            Pantalla de cocina — módulo Mesas
├── instalar.php          Asistente de primera instalación
├── manifest.php, sw.js   App instalable (PWA) para garzón y cocina
├── ver_boleta.php        Entrega el PDF de una boleta (requiere sesión)
├── api/                  Endpoints JSON (registrar_venta, mesas, movimiento_stock...)
├── views/                Vistas HTML de cada pantalla
├── assets/               css/, js/ (pos.js, mesas_caja.js, ui.js), img/, vendor/
├── config/database.php   Conexión, APP_VERSION, novedades, asset_url(), zona horaria
├── includes/
│   ├── auth.php          Sesión, CSRF, contraseñas, protección de red y licencia
│   ├── licencia.php      Licencias, planes, módulos, IP del equipo
│   ├── qr.php            Generador de códigos QR (sin dependencias)
│   ├── app_instalable.php Etiquetas y aviso para instalar como app
│   └── sii/              EBoletaCliente.php, EBoletaCola.php, Cifrado.php (+ drivers antiguos)
├── db/
│   ├── migrations/       0001…0013, cambios de esquema en orden
│   └── instalacion/      01_esquema.sql y 02_datos_base.sql para equipos nuevos
├── scripts/              Tareas de línea de comandos: emitir_pendientes, respaldo, restablecer_clave
├── storage/boletas/      PDF de boletas emitidas (privado, no se versiona)
├── instalador/           preparar.php, configurar.php, compilar.php, minimarket.iss, abrir.vbs, icono
└── tools/licencias/      HERRAMIENTA DEL PROVEEDOR: panel y generador de licencias (no se instala)
```

Archivos **que nunca se versionan** (`.gitignore`): `.env`, `config/licencia.lic`, `config/clave_cifrado.key`, `config/eboleta_sesion.json`, `config/instalado.lock`, `storage/boletas/`, `uploads/`, `instalador/paquete/`, `instalador/salida/`.

---

## 6. Componentes principales

### 6.1 Autenticación y sesión — `includes/auth.php`

- Sesión PHP; `requireLogin()` y `checkRole()` por rol (**Administrador**, **Supervisor**, **Cajero**, **Garzón**).
- **Contraseñas** con `password_hash`. `verificarPasswordUsuario()` acepta por única vez claves antiguas en texto plano y las convierte a hash al primer uso.
- **CSRF:** formularios con `csrfField()` / `verifyCsrf()`; APIs con cabecera `X-CSRF-Token` y `verifyCsrfApi()`.
- **Bloqueo por intentos fallidos** de login por IP.
- **Garzones** no entran al sistema normal: usan PIN en `garzon.php` / `cocina.php` (sesión `$_SESSION['garzon']`, separada de `$_SESSION['usuario']`).

### 6.2 Licencias — `includes/licencia.php` y `tools/licencias/`

**Formato:** `MML1.<datos en base64url>.<firma>`. Los datos son JSON:

```json
{ "v":1, "cliente":"...", "rut":"76123456-7", "plan":"comercio",
  "extras":["mesas"], "actualizaciones_hasta":"2027-11-13", "emitida":"2026-11-13" }
```

- **Firma ECDSA P-256 (SHA-256).** El sistema trae solo la **clave pública**; la privada está en el PC del proveedor (`%USERPROFILE%\.minimarket-licencias\privada.pem`), **fuera del repositorio**.
- **Ligada al RUT:** la licencia solo es válida si coincide con `MINIMARKET_RUT` de la configuración.
- **Sin licencia válida:** funciona como Básico y no emite boletas.
- `modulo_activo($m)` decide: módulos base (siempre), de plan (`LICENCIA_PLANES`), extras (`LICENCIA_EXTRAS`) o solo online (`LICENCIA_SOLO_ONLINE`, nunca).
- **Herramientas del proveedor:** `tools/licencias/abrir_panel.bat` (panel web en `127.0.0.1:8099`) y `generar_licencia.php` (terminal). El registro de clientes queda en `registro.json` junto a la clave privada.

### 6.3 Venta — `api/registrar_venta.php`

Todo ocurre en **una transacción**:

1. Bloquea cada producto (`FOR UPDATE`), recalcula precios en el servidor (no confía en el navegador), aplica promociones (si el plan las incluye).
2. Valida descuento, vale, propina y que los pagos cubran **venta + propina**.
3. Inserta venta, detalle, pagos; descuenta stock y registra kardex (salvo productos "sin control de stock").
4. Cierra la cuenta de mesa (`mesa_pedido_id`) si corresponde — si ya estaba cerrada, revierte todo (evita doble cobro).
5. `COMMIT`. Recién después encola y emite la boleta (12 s de límite).

### 6.4 Boleta electrónica — `includes/sii/`

| Archivo | Función |
|---|---|
| `EBoletaCliente.php` | Habla con e-Boleta del SII: login (RUT y Clave Tributaria de la persona), credenciales temporales de AWS (Cognito), firma **SigV4** propia, datos del emisor y emisión. |
| `EBoletaCola.php` | Cola sobre `dte_emitidos`: arma el detalle (reparte descuento global, ajusta precios de granel/packs), emite, reintenta y guarda el PDF en `storage/boletas/`. Incluye **simulador**. |
| `Cifrado.php` | AES-256-GCM con llave propia del equipo (`config/clave_cifrado.key`) para la Clave Tributaria y la sesión. |

**Estados de una boleta:** `Pendiente` → `Enviando` → `Emitida`; o `Revisar` (se envió sin respuesta clara: no se reintenta sola para no duplicar), `Error` (rechazo del SII), `Anulada` (venta anulada antes de emitir).

**Errores:** `conexion` (no llegó al SII → reintento seguro), `incierto` (pudo emitirse → revisión manual), `rechazo` (datos inválidos), `credenciales` (clave o autorización → la cola se detiene).

> ⚠️ e-Boleta es la integración **no oficial** con el sistema gratuito del SII (mismo backend que eboleta.sii.cl). Empresas con software propio certificado no pueden usarlo. Ver riesgos en la sección 13.

### 6.5 Mesas y garzones (módulo extra)

- **`api/mesas.php`**: única API del módulo, con permisos separados (garzón con PIN / caja con sesión y solo desde el PC).
- **Concurrencia:** cada envío bloquea la fila de la mesa (`FOR UPDATE`); dos garzones enviando a la vez suman a la misma cuenta.
- **Comandas:** cada envío del garzón es una comanda. La caja toma las nuevas e imprime **en una operación atómica** (`tomar_para_imprimir`), así cada comanda se imprime una sola vez.
- **Tablas:** `mesas`, `mesapedidos` (cuenta), `comandas`, `mesapedidosdetalle`.

### 6.6 Stock

- Venta descuenta, anulación devuelve (en unidades físicas: cantidad × factor del pack), kardex registra cada movimiento.
- **Stock simple** (todos los planes): `api/movimiento_stock.php` — ingreso, merma, ajuste a conteo — usando las mismas tablas de ajustes del kardex.
- Productos con `ControlaStock = 0` (platos, servicios) no descuentan ni aparecen en alertas.

### 6.7 Propina voluntaria

Configurable (`PROPINA_ACTIVA`, `PROPINA_PORCENTAJE`). Se guarda en `ventas.Propina`; los pagos registran el dinero real recibido (venta + propina), así el **arqueo cuadra**; la boleta usa solo `MontoTotal`. No se permite a crédito/puntos ni mayor que el consumo.

### 6.8 Instalador — `instalador/`

| Archivo | Función |
|---|---|
| `compilar.php` | Un solo comando: lee `APP_VERSION`, arma el paquete y compila con Inno Setup. |
| `preparar.php` | Copia lo justo de PHP, Apache y MySQL desde Laragon + el sistema (sin herramientas, git ni datos). |
| `configurar.php` | Corre al instalar: escribe `php.ini`, `httpd.conf`, `my.ini`; inicializa MySQL; registra servicios; crea base, usuario y `.env` con contraseñas aleatorias; aplica migraciones (con respaldo previo); tareas programadas; firewall. Idempotente. Modo `--probar` para probar sin administrador. |
| `minimarket.iss` | Script de Inno Setup: servicios, accesos directos, actualización (detiene servicios antes de copiar) y desinstalación (pregunta si borrar datos). |
| `abrir.vbs` | Abre el sistema en Edge modo app con impresión directa (`--kiosk-printing`). |

---

## 7. Comunicación entre componentes

### 7.1 Navegador ↔ servidor

| Canal | Formato | Uso |
|---|---|---|
| Formularios HTML (POST) | `application/x-www-form-urlencoded` + `csrf_token` | Configuración, productos, usuarios, caja. Patrón *Post/Redirect/Get* donde corresponde. |
| `fetch()` a `api/*.php` | JSON en ambos sentidos + cabecera `X-CSRF-Token` | Venta, búsqueda de productos, mesas, stock, boletas. Respuesta estándar `{ "success": bool, "error": "...", ... }`. |
| *Polling* (consulta periódica) | JSON | Garzón: mesas cada **10 s**. Cocina: comandas cada **5 s**. Caja: comandas nuevas cada **8 s**. Se eligió *polling* en vez de WebSockets: más simple, sin servicios extra y suficiente para un local. |

### 7.2 Red del local

- Por defecto Apache escucha solo en `127.0.0.1`. Con la opción del instalador ("tablets y celulares") escucha en la red y se crea una regla de firewall **solo para redes privadas**.
- Desde otros equipos, `proteger_acceso_red()` permite **únicamente** `garzon.php`, `cocina.php` y `api/mesas.php` (lista `ACCESO_RED_PERMITIDO`); todo lo demás responde 403.
- MySQL **siempre** escucha solo en `127.0.0.1:3307`.
- La dirección para las tablets la detecta `ips_del_equipo()` (tarjeta de red que sale al router) y se muestra con **código QR** en Configuración → Mesas y garzones.

### 7.3 Sistema ↔ SII (e-Boleta)

```
1. POST /sign-in (RUT persona + Clave Tributaria)   → token Cognito + IdentityId
2. Cognito GetCredentialsForIdentity                → credenciales AWS temporales
3. GET  info-emisor-usuario (firmado SigV4)         → datos del emisor y sucursal
4. POST /api/dte/documentos/generar (firmado SigV4) → folio + PDF (base64)
```

La sesión se guarda cifrada (`config/eboleta_sesion.json`) y se reutiliza hasta que vence. Tiempos de espera: 12 s al vender, 30 s en la tarea de fondo.

### 7.4 Procesos de fondo

| Tarea | Frecuencia | Qué hace |
|---|---|---|
| `scripts/emitir_pendientes.php` | cada 2 min | Emite boletas pendientes en orden; se detiene ante un problema general. Bloqueo de archivo: nunca dos a la vez. |
| `scripts/respaldo.php` | diario 23:30 | `mysqldump` comprimido, guarda 30 días. La contraseña va por variable de entorno, no en la línea de comandos. |

---

## 8. Base de datos y migraciones

- **MySQL 8**, motor InnoDB, `utf8mb4` (`utf8mb4_unicode_ci`). ~40 tablas.
- **Tablas principales:** `productos`, `productoscodigos` (códigos alternativos y packs), `ventas`, `detalleventas`, `pagosventa`, `turnos`, `movimientoscaja`, `kardex`, `ajustesstock`, `dte_emitidos` (cola de boletas), `configuraciones` (clave/valor), `usuarios`, `roles`, `mesas`, `mesapedidos`, `comandas`, `mesapedidosdetalle`.
- **Configuración** del negocio en `configuraciones` (clave/valor): nombre, RUT, proveedor de boletas, propina, etc.

### 8.1 Migraciones

- Archivos `db/migrations/NNNN_descripcion.sql`, en orden. **Nunca se modifica una ya publicada**: se agrega otra.
- **Instalación nueva:** se cargan `db/instalacion/01_esquema.sql` (estructura completa) y `02_datos_base.sql` (roles, permisos, categorías, configuración por defecto). Todas las migraciones quedan marcadas como aplicadas.
- **Actualización:** `configurar.php` respalda la base y aplica en orden las migraciones que falten, registrándolas en la tabla `migraciones`.
- **Al crear una migración nueva**, regenerar `01_esquema.sql` desde la base de desarrollo y agregar a `02_datos_base.sql` las configuraciones nuevas (ver 11.2).
- **Importante:** aplicar SQL con el cliente `mysql` siempre con `--default-character-set=utf8mb4`; sin eso los acentos se guardan mal (pasó con "Garzón").

---

## 9. Seguridad

| Medida | Dónde |
|---|---|
| Contraseñas con `password_hash`; sin claves universales | `auth.php`, `usuarios.php`, `instalar.php` |
| CSRF en formularios y APIs | `auth.php` |
| Bloqueo por intentos fallidos de login y de PIN | `auth.php`, `api/mesas.php` |
| `.htaccess`: deniega `.env`, `.sql`, `.md`, `.lic`, `.json`, etc., y las carpetas `config/`, `db/`, `includes/`, `scripts/`, `tools/`, `storage/` | raíz y cada carpeta |
| Acceso de red limitado a garzón/cocina; MySQL solo local | `licencia.php`, `configurar.php` |
| Clave Tributaria cifrada (AES-256-GCM, llave por equipo) | `includes/sii/Cifrado.php` |
| Contraseñas de MySQL aleatorias por instalación | `configurar.php` (`datos/instalacion.json`) |
| Licencias firmadas, clave privada fuera del repositorio | `licencia.php`, `tools/licencias/` |
| Precios y totales recalculados en el servidor | `api/registrar_venta.php` |
| Asistente de instalación solo desde el propio equipo y bloqueado al terminar | `instalar.php` |

---

## 10. Entorno de desarrollo

1. **Laragon** con Apache, PHP 8.1 y MySQL 8 en `C:\laragon`.
2. Clonar el repositorio en `C:\laragon\www\minimarket-minimal`.
3. Copiar `.env.example` a `.env` y completar `DB_USER`, `DB_PASS`, `DB_NAME=minimarket_local`.
4. Crear la base `minimarket_local` cargando `db/instalacion/01_esquema.sql` y `02_datos_base.sql`, o abrir `http://localhost/minimarket-minimal/` y usar el asistente.
5. Para probar licencias: generar una con el panel (`tools\licencias\abrir_panel.bat`) para el RUT configurado.
6. Para probar boletas **sin emitir boletas reales**: Configuración → Boleta Electrónica → **Simulador** (permite simular sin internet, rechazo o respuesta incierta).
7. Recuperar acceso de un usuario: `php scripts/restablecer_clave.php <usuario>`.

---

## 11. Compilación, versionado y entrega

### 11.1 Generar el instalador

```bash
php instalador/compilar.php
```

Resultado: `instalador/salida/MinimarketPOS-Setup-<versión>.exe` (~25 MB). Requiere Inno Setup 6.

### 11.2 Lista de verificación de una versión nueva

1. Si hay cambios de tablas: migración nueva en `db/migrations/`, aplicada en desarrollo, y regenerar `db/instalacion/01_esquema.sql` / `02_datos_base.sql`.
2. Subir `APP_VERSION` y agregar sus novedades en `config/database.php` (es la **única fuente** de la versión).
3. Revisar sintaxis (`php -l`, `node --check`) y probar los flujos tocados.
4. Comprobar que el esquema nuevo carga en una base vacía.
5. `php instalador/compilar.php`.
6. Revisar que el commit no incluya archivos sensibles; commit, etiqueta `vX.Y.Z` y `git push` con la etiqueta.

### 11.3 Actualizar a un cliente

Entregar el `.exe` nuevo; el cliente lo ejecuta **encima** de lo instalado. Se conservan base de datos, respaldos, `.env`, licencia y Clave Tributaria; se aplican las migraciones con respaldo previo.

*Pendiente:* que el instalador verifique `actualizaciones_hasta` antes de actualizar (ver sección 13).

---

## 12. Operación y soporte

| Necesidad | Cómo |
|---|---|
| Registro de instalación | `C:\MinimarketPOS\logs\instalacion.log` |
| Errores web / PHP / MySQL | `logs\apache_error.log`, `logs\php_error.log`, `logs\mysql_error.log` |
| Servicios | `services.msc` → `MinimarketPOS-Web`, `MinimarketPOS-DB` |
| Tareas | Programador de tareas → carpeta `MinimarketPOS` |
| Respaldos | `C:\MinimarketPOS\respaldos\` (30 días); menú Inicio → "Respaldar ahora" |
| Cliente olvidó su clave | `C:\MinimarketPOS\php\php.exe C:\MinimarketPOS\app\scripts\restablecer_clave.php <usuario>` |
| Boletas pendientes o con error | Ventas → filtro "boletas con problemas"; Configuración → Boleta Electrónica |
| Activar/renovar/cambiar plan | Panel de licencias del proveedor → el cliente pega la licencia en Admin → Licencia |
| Tablets no conectan | Revisar que la red de Windows sea **privada**, que se instaló con "acceso de red" y usar la dirección/QR de Configuración → Mesas y garzones |

---

## 13. Riesgos conocidos y deuda técnica

| Tema | Riesgo / estado | Mitigación o siguiente paso |
|---|---|---|
| e-Boleta no oficial | El SII puede cambiar su sistema sin aviso. | Las ventas siguen y las boletas quedan pendientes; actualizar `EBoletaCliente.php`. Evaluar proveedor oficial o LibreDTE (licencia AGPL a revisar). |
| Empresas con software propio certificado | No pueden usar e-Boleta (el SII deshabilita el sistema gratuito). | Segundo método de emisión con certificado y CAF, si aparece la demanda. |
| Código PHP legible en el cliente | Alguien con conocimientos podría modificarlo. | La licencia va ligada al RUT; evaluar ofuscación (ionCube) si hiciera falta. |
| Sin pruebas automatizadas | Las regresiones dependen de pruebas manuales. | Agregar PHPUnit para cola de boletas, licencias, cálculo de totales y propina. |
| Instalador sin firma digital | Windows muestra el aviso de SmartScreen. | Certificado de firma de código (~100–400 USD/año). |
| MySQL redistribuido | Más pesado y con licenciamiento a revisar para venta. | Cambiar a MariaDB. |
| Control de soporte al actualizar | El instalador aún no exige `actualizaciones_hasta` vigente. | Verificación en `PrepareToInstall`. |
| App en Android por `http` | Se instala con barra del navegador. | Ajuste de Chrome por equipo (documentado en Configuración) o HTTPS local. |
| Pagos mixtos y propina | La propina no se separa por medio de pago en reportes. | Si se necesita, guardar el medio de la propina. |
| Datos de desarrollo | La base `minimarket_local` de desarrollo tiene datos de prueba. | No usarla como base de un cliente; el instalador crea una limpia. |

---

## 14. Glosario

| Término | Significado |
|---|---|
| **SII** | Servicio de Impuestos Internos de Chile. |
| **e-Boleta** | Sistema gratuito del SII para emitir boletas electrónicas (eboleta.sii.cl). |
| **DTE** | Documento Tributario Electrónico (boleta 39, boleta exenta 41, factura 33). |
| **Folio** | Número correlativo que el SII asigna a cada boleta. |
| **CAF** | Código de Autorización de Folios (solo para software propio, no aplica a e-Boleta). |
| **RUT** | Rol Único Tributario (identificador de personas y empresas). |
| **Fiado** | Venta a crédito interno a un cliente de confianza. |
| **Arqueo** | Conteo del dinero de la caja al cerrar el turno. |
| **Comanda** | Pedido que el garzón envía a la cocina. |
| **Kardex** | Registro de cada entrada y salida de stock. |
| **SigV4** | Método de firma de peticiones de AWS (usado por e-Boleta). |
| **PWA** | Aplicación web que se puede instalar en el celular con su ícono. |
| **Migración** | Archivo SQL que cambia la estructura de la base de forma ordenada y versionada. |
