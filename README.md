# MeliGil CRM

Web PHP + MySQL adaptada al flujo de MeliGil.

## Ruta esperada
Sube el contenido de esta carpeta a la carpeta pública `/meligil/` para que el login quede en:

`https://crmcamaradecomercio.com.mx/meligil/login`

La regla `.htaccess` reescribe `/login` a `login.php`.

## Base de datos
Importa `meligil.sql` en `crmcamar_meligil`.

## Usuario inicial del CRM
- Correo: `admin@meligil.com`
- Contraseña: `MeliGilCRM#2026!`

Cambia la contraseña desde **Mi cuenta** después de validar el acceso.

## Configuración incluida
`config.php` ya contiene los datos MySQL, la URL esperada de `crmSendMeligil`, la plantilla `marketing_meligil_texto`, idioma `es_MX` y el admin web token proporcionado.

## Módulos
- Dashboard
- Contactos y notas internas
- Productos (el bot los consulta directamente desde la BD)
- Pedidos
- Cotizaciones
- Puntos de venta
- Consultas de ubicación
- Conversaciones
- Plantillas: envío individual seleccionando usuario y envío a todos
- Configuración
- Mi cuenta

## Plantilla Meta
El CRM envía todo el texto dinámico como una sola variable `{{1}}`, compatible con `marketing_meligil_texto`.

## Ajustes de interfaz 2026-09-15
- El botón para mostrar/ocultar la contraseña quedó contenido dentro del campo de contraseña en el login.
- El dashboard de Inicio fue ampliado con resumen comercial, Customer Journey, nuevos contactos de los últimos seis meses, actividad comercial, conversión y atención por tipo de solicitud.
- Estos indicadores se calculan con las tablas actuales; no requieren cambios de estructura en MySQL.

## Ajuste de estilos del dashboard (2026-09-15)
El CSS ahora se carga con una versión automática basada en `filemtime()` (`app.css?v=...`). Esto evita que el navegador o un proxy conserve la versión anterior de `app.css` cuando se actualiza el dashboard. Si el dashboard llega a verse como texto sin tarjetas, confirma que `assets/css/app.css` se haya sustituido al subir esta versión.
