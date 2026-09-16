# AGENTS.md

## Proyecto

Aplicación web de turnos desarrollada en PHP 8.3 + MariaDB.

La aplicación funciona online en:

https://hernysgodoy.com/app/

La base de datos de producción existe en Hostinger y no debe modificarse destructivamente sin autorización explícita.

## Reglas de trabajo

- Leer el proyecto antes de modificar código.
- No cambiar la estructura de base de datos sin autorización.
- No ejecutar scripts de instalación o seed automáticamente.
- No modificar `config/database.php`.
- No versionar credenciales.
- No modificar producción directamente.
- Trabajar primero en local.
- Mantener compatibilidad con Linux/Hostinger.
- Respetar mayúsculas y minúsculas en nombres de archivos.
- Antes de cambios estructurales, explicar el plan.
- Después de cada tarea, informar archivos modificados y pruebas realizadas.
- No hacer commit ni push salvo solicitud explícita.