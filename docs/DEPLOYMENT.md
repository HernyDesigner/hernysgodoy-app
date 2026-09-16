# Deployment

## Producción

La aplicación funciona en:

https://hernysgodoy.com/app/

El servidor de producción está alojado en Hostinger.

## Ubicación

La aplicación se encuentra en:

`public_html/app/`

## Flujo actual de despliegue

Por ahora el despliegue es manual.

Flujo:

1. desarrollar y probar localmente;
2. revisar cambios con Git;
3. hacer commit;
4. hacer push al repositorio privado de GitHub;
5. subir manualmente los archivos modificados a `public_html/app/`;
6. verificar funcionamiento en producción.

## Repositorio

Repositorio privado:

`HernyDesigner/hernysgodoy-app`

Rama estable:

`main`

## Archivos sensibles

No subir desde GitHub ni versionar:

- `config/database.php`
- credenciales
- contraseñas
- tokens
- archivos de configuración sensibles

`config/database.php` debe existir manualmente en producción.

## Base de datos

La base de datos de producción no se reemplaza durante un deploy.

No ejecutar automáticamente:

- migraciones;
- scripts de setup;
- seeds;
- scripts de creación de usuarios.

## Compatibilidad

Producción utiliza Linux.

Respetar exactamente mayúsculas y minúsculas en:

- nombres de archivos;
- rutas;
- imports;
- `require`;
- `include`.

## Validación posterior al deploy

Después de cada publicación comprobar:

- login;
- navegación del backoffice;
- agenda;
- turnos;
- profesionales;
- servicios;
- disponibilidad;
- reserva pública;
- conexión con base de datos;
- errores PHP;
- responsive mobile.

## Restricciones para agentes IA

Codex no debe:

- desplegar automáticamente;
- modificar producción directamente;
- cambiar credenciales;
- ejecutar scripts de setup;
- modificar la base de datos sin autorización explícita.