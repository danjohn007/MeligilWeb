# MeliGil CRM Web

Versión homologada al estándar de Zuustento con Customer Journey comercial actualizado.

## Módulos web

- Dashboard / Customer Journey
- Contactos
- Productos
- Pedidos
- Cotizaciones
- Puntos de venta
- Consultas de ubicación
- Mensajes
- Colaboradores (solo Administrador)
- Configuración
- Mi perfil

El historial completo de Conversaciones no forma parte de la interfaz web.

## Actualización de base de datos

Si vienes de la versión anterior, primero debe estar aplicada `database/12_meligil_usuarios_permisos_perfil.sql` y después ejecutar `database/13_meligil_colaboradores_customer_journey.sql`.

## Customer Journey

- Prospecto: 1.er y 2.º contacto.
- Prospecto recurrente: desde el 3.er contacto, hasta que un Colaborador lo convierta manualmente en Cliente.
- Cliente: conversión manual desde la ficha del contacto.
- Cliente recurrente: Cliente que alcanza su 3.ª compra confirmada o cerrada.

Para evitar contar cada mensaje de WhatsApp como un contacto distinto, un nuevo contacto comercial se contabiliza cuando existe una nueva interacción entrante después de al menos 24 horas sin mensajes entrantes.

## Mensajes

Permite enviar a un contacto específico, a cada una de las cuatro etapas del Customer Journey o a Todos los contactos activos. Los envíos segmentados procesan hasta 200 destinatarios por ejecución.
