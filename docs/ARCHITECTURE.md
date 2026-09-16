# Arquitectura de la aplicación

## Tipo de proyecto

Aplicación web de turnos desarrollada en:

- PHP 8.3
- MariaDB
- HTML
- CSS
- JavaScript vanilla

## URL de producción

https://hernysgodoy.com/app/

## Estructura principal

- `api/`
  Endpoints públicos y administrativos.

- `assets/`
  Recursos visuales y estilos.

- `config/`
  Configuración de la aplicación.
  `database.php` contiene credenciales y no debe versionarse.

- `controllers/`
  Controladores de la aplicación.

- `core/`
  Clases y lógica central compartida.

- `models/`
  Acceso y representación de datos.

- `setup/`
  Scripts de instalación o inicialización.
  No deben ejecutarse automáticamente.
  Los archivos de `setup/` pueden existir localmente sin estar versionados.
  En particular, `setup/create_users.php` está excluido mediante `.gitignore`
  y no debe considerarse parte del repositorio ni desplegarse automáticamente.

- `views/admin/`
  Vistas del backoffice.

- `views/public/`
  Interfaz pública de reserva.

- `index.php`
  Punto de entrada principal.

## Flujo general

### Área pública

Usuario
→ interfaz de reserva
→ APIs públicas
→ lógica de disponibilidad
→ base de datos

### Área administrativa

Login
→ panel administrativo
→ controladores
→ modelos
→ base de datos

## Base de datos

La base de datos de producción está alojada en Hostinger.

No asumir que puede recrearse desde cero.

Antes de cualquier migración o cambio estructural:

1. realizar backup;
2. revisar impacto;
3. solicitar autorización.

## Entorno

Desarrollo:
Windows local.

Producción:
Linux / Hostinger.

Por este motivo, los nombres de archivos deben respetar exactamente mayúsculas y minúsculas.