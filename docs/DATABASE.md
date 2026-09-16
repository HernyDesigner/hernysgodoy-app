# Base de datos

## Motor

MariaDB.

## Producción

La base de datos de producción está alojada en Hostinger y contiene datos reales del sistema.

No debe eliminarse, recrearse ni modificarse estructuralmente sin autorización explícita.

## Configuración

El archivo local:

`config/database.php`

contiene las credenciales de conexión.

Ese archivo:

- está excluido mediante `.gitignore`;
- no debe versionarse;
- no debe compartirse;
- no debe modificarse automáticamente por Codex.

## Reglas

Antes de cualquier cambio de esquema:

1. realizar backup de producción;
2. documentar el cambio;
3. evaluar impacto sobre datos existentes;
4. verificar compatibilidad con el código actual;
5. solicitar autorización explícita;
6. recién después ejecutar la modificación.

## Scripts de setup

Los archivos dentro de:

`setup/`

pueden contener lógica de creación o inicialización de datos.

No deben ejecutarse automáticamente en producción.

## Seguridad

No almacenar en Git:

- usuarios de base de datos;
- contraseñas;
- tokens;
- claves privadas;
- credenciales de servicios externos.

## Entornos

Actualmente:

- desarrollo: Windows local;
- producción: Linux / Hostinger;
- base de producción: Hostinger.

Evitar asumir que producción puede reconstruirse desde archivos locales.