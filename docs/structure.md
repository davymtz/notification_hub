# Estructura del repositorio

## Distribución

```text
NotificacionHub/
├── project_back/             # Única aplicación Laravel
│   ├── app/                  # Código PHP con namespace App
│   │   ├── Actions/          # Casos de uso
│   │   ├── Contracts/        # Interfaces
│   │   ├── DTO/              # Datos tipados entre capas
│   │   ├── Http/             # Controladores, validación y middleware
│   │   ├── Jobs/             # Trabajo asíncrono
│   │   ├── Providers/        # Registro y enlaces del contenedor
│   │   └── Services/         # Registro, enrutamiento y notifiers
│   ├── bootstrap/            # Arranque y cachés del framework
│   ├── config/               # Configuración Laravel
│   ├── database/             # Migraciones y datos locales
│   ├── public/               # Única raíz pública HTTP
│   ├── resources/            # Vistas y recursos fuente
│   ├── routes/               # Rutas
│   ├── storage/              # Archivos generados y logs
│   ├── tests/                # Pruebas Feature y Unit
│   ├── vendor/               # Dependencias generadas, fuera de Git
│   ├── .dockerignore         # Exclusiones del contexto de construcción
│   ├── .env.example          # Variables de ejemplo sin secretos reales
│   ├── artisan
│   ├── composer.json
│   ├── composer.lock
│   └── phpunit.xml
├── docker/
│   ├── php/Dockerfile
│   └── nginx/default.conf
├── docs/                     # Documentación mantenida
├── openspec/                 # Especificaciones y cambios
├── docker-compose.yml
├── Makefile
└── README.md
```

## Convenciones

Conservar los directorios estándar de Laravel dentro de `project_back/`. Composer resuelve `App\` desde `app/`; las clases de Actions, Contracts y DTO pertenecen allí. Evitar copias de la aplicación y carpetas paralelas con los mismos namespaces. Crear nuevos módulos solo cuando el dominio y sus dependencias lo justifiquen.

Mantener las pruebas junto a la aplicación y las migraciones en `database/migrations/`. Versionar `composer.lock` para instalar las mismas dependencias. `vendor/`, cachés, logs, bases locales y credenciales son artefactos de ejecución y no deben versionarse. Las carpetas vacías requeridas por Laravel deben conservar un `.gitignore` propio.

Infraestructura compartida vive en `docker/`; Compose y Make permanecen en la raíz. No crear carpetas vacías para un frontend u otros servicios hasta que existan requisitos. El directorio `public/` del backend es la única raíz que debe servir Nginx.

## Rutas y comandos

Ejecutar desde la raíz:

```bash
docker compose build
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan test
```

Para herramientas instaladas en el host:

```bash
cd project_back
composer install
php artisan test
```

Compose construye con contexto `./project_back` y receta `../docker/php/Dockerfile` relativa a ese contexto. Las instrucciones COPY del Dockerfile ven únicamente los archivos del backend permitidos por `project_back/.dockerignore`. App y Nginx montan `./project_back` en `/var/www`; la ruta pública interna sigue siendo `/var/www/public`.

La configuración Laravel está en `project_back/.env`. Un `.env` en la raíz de Compose tiene otra función (interpolación de Compose); no duplicar credenciales allí. La preparación debe copiar el ejemplo solo si no existe configuración local y no debe regenerar APP_KEY de forma automática.

## Estado de validación

Se verificaron integridad del traslado y rutas de forma estática. Este entorno de edición no dispone de PHP, Composer ni Docker. Construcción, pruebas Laravel y arranque quedan pendientes dentro de la fase 1 del [roadmap](roadmap.md). El `vendor/` trasladado estaba vacío: ejecutar Composer es obligatorio antes de usar Artisan.

## Referencias

- [Estructura oficial Laravel 12](https://laravel.com/docs/12.x/structure).
- [Contexto de construcción Docker y exclusiones](https://docs.docker.com/build/concepts/context/).
