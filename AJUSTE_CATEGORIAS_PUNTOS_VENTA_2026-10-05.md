# MeliGil CRM - Categorías / zonas de puntos de venta
Fecha: 05/10/2026

## Web
- Se agregan categorías / zonas dentro del módulo **Puntos de venta**.
- Las categorías se pueden crear, editar, activar/desactivar y ordenar.
- Solo el Administrador puede eliminar una categoría, y únicamente cuando no tenga puntos asignados.
- Cada punto de venta debe seleccionar una categoría / zona.
- Los puntos existentes se asignan automáticamente por ciudad al ejecutar la migración `database/14_meligil_categorias_puntos_venta.sql`.

## Bot
Nuevo flujo de Puntos de venta:
1. El bot muestra las categorías / zonas activas.
2. El usuario selecciona una categoría.
3. El bot muestra solo los puntos asociados a esa categoría.
4. El usuario selecciona un punto y recibe dirección, horario, teléfono y mapa.
5. Hay paginación para categorías y puntos, evitando el límite de 10 opciones de las listas de WhatsApp.

## Instalación
1. Respaldar la BD.
2. Ejecutar una sola vez `database/14_meligil_categorias_puntos_venta.sql`.
3. Subir el `index.php` actualizado a la web.
4. Reemplazar `functions/index.js` del bot.
5. Desplegar `whatsappWebhookMeligilBot`.

No es necesario cambiar secrets ni `config.php`.
