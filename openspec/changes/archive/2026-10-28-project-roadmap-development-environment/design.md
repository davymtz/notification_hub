# Design

## Context

Antes de la reorganización, la raíz contiene la API de notificaciones y `project_back/` otro esqueleto Laravel. El usuario autoriza reemplazar ese esqueleto por la API raíz. La raíz Laravel actual es `project_back/`; dentro del contenedor permanecerá `/var/www`. `.env.example` selecciona SQLite y Redis; el Dockerfile instala PDO MySQL pero no declara PDO SQLite. Existe `project_back/.dockerignore`; healthchecks y worker en Compose siguen pendientes. Estas observaciones son estáticas: no se ha ejecutado la aplicación.

## Goals / Non-Goals

**Goals:** consolidar la API en `project_back/`, separar infraestructura y documentación y entregar un roadmap con criterios de cierre verificables.

**Non-Goals:** ejecutar las restantes tareas del entorno, corregir lógica de negocio, seleccionar infraestructura de producción o prometer fechas.

## Decisions

1. Ubicar el documento en `docs/roadmap.md`, separado de los artefactos temporales de OpenSpec. Frente a dejarlo solo en el cambio, esta ubicación conserva su utilidad tras archivar.
2. Reemplazar el contenido anterior de `project_back/` por los archivos Laravel de la raíz, preservando sus bytes y permisos. La raíz del repositorio conserva Make, Compose, documentación y OpenSpec. Mantener las carpetas estándar Laravel y las clases bajo `app/` según PSR-4, evitando carpetas duplicadas como `Actions/` fuera de `app/`.
3. Proponer SQLite local y Redis, coherentes con la configuración actual. Añadir MySQL ahora crearía un servicio sin una necesidad demostrada.
4. Usar `project_back/` como contexto de construcción con `.dockerignore` propio y `docker/php/Dockerfile` como receta externa al contexto. Montar solo el backend en app y Nginx; conservar `/var/www/public` como document root. Mantener `skip_specs: true`: la reorganización no cambia comportamiento público. Las fases funcionales tendrán propuestas propias.
5. Ordenar fases por dependencias: entorno, calidad, fiabilidad, seguridad, canales y operación. Seguridad debe estar cerrada antes de exponer el servicio fuera del equipo.
6. Cada fase describe objetivo, entregables y evidencia de salida. No asignar fechas ni responsables ficticios; la reorganización está aplicada y las restantes tareas técnicas siguen pendientes.

## Risks / Trade-offs

- Diagnóstico estático → etiquetar verificaciones de ejecución como pendientes.
- Eliminación del esqueleto anterior → ejecutar solo sobre `project_back/` y verificar por hashes que el backend trasladado coincide con la API original.
- Entorno sin PHP, Composer ni Docker → realizar comprobaciones estáticas y dejar explícitamente pendientes las de ejecución.
- Roadmap confundido con ejecución autorizada → indicar estado y solicitar un cambio posterior por fase.
- Versiones e imágenes evolucionan → resolver versiones compatibles y mantenidas al implementar; registrar versiones/digests y política de actualización.
- SQLite simplifica desarrollo pero no define producción → documentar límites y decidir almacenamiento productivo en la fase operativa.
