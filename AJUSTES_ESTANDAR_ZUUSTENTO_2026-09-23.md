# MeliGil CRM - Ajustes finales

Cambios vigentes sobre el sistema original:

- El historial completo de Conversaciones permanece fuera de la interfaz web.
- Mensajes permite envío individual, por Customer Journey y a Todos.
- Customer Journey usa únicamente cuatro etapas: Prospecto, Prospecto recurrente, Cliente y Cliente recurrente.
- Prospecto corresponde al 1.er y 2.º contacto.
- Prospecto recurrente comienza en el 3.er contacto y permanece hasta la conversión manual.
- La conversión a Cliente se realiza manualmente desde la ficha del contacto por un Colaborador con permiso Editar en Contactos.
- Cliente recurrente se obtiene automáticamente cuando un Cliente alcanza su 3.ª compra confirmada o cerrada.
- Para contabilizar contactos comerciales sin confundir mensajes individuales con contactos nuevos, se considera nueva interacción cuando han pasado al menos 24 horas desde el último mensaje entrante.
- Usuarios se presenta ahora como Colaboradores. Internamente se conservan las claves técnicas existentes para no romper sesiones ni permisos.
- El sistema mantiene únicamente Administrador y Colaborador a nivel de interfaz; el valor técnico del rol no administrador sigue siendo `user`.
- El Administrador tiene acceso completo.
- Al Colaborador se le asignan permisos Ver / Editar por cada módulo existente.
- Los permisos se validan también en backend.
- Mi perfil permite actualizar foto, nombre, apellidos, correo y contraseña.
- No se agregaron módulos comerciales nuevos.

## Instalación

1. Respaldar archivos y base de datos.
2. Confirmar que ya se ejecutó `database/12_meligil_usuarios_permisos_perfil.sql`.
3. Ejecutar `database/13_meligil_colaboradores_customer_journey.sql`.
4. Sustituir los archivos de la web por esta versión.
5. Cerrar sesión e iniciar de nuevo.
6. Probar con una cuenta Administrador y una cuenta Colaborador con permisos limitados.
