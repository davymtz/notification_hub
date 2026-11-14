# Roadmap de Notification Hub

Estado: estructura consolidada en `project_back/`. La estabilización del entorno y las fases posteriores siguen pendientes.
Fecha: 2026-10-28.

Cambio activo para completar la fase 1: [setup-development-environment](../openspec/changes/setup-development-environment/proposal.md), con [diseño](../openspec/changes/setup-development-environment/design.md) y [tareas verificables](../openspec/changes/setup-development-environment/tasks.md). La reorganización quedó cerrada en [el archivo de cambios](../openspec/changes/archive/2026-10-28-project-roadmap-development-environment/proposal.md).

## Objetivo

Establecer un entorno reproducible para desarrollar la API de notificaciones y evolucionarla hacia un servicio fiable, seguro y operable. La primera prioridad es poder construir, iniciar y probar el proyecto desde una copia limpia. La reorganización de carpetas fue autorizada y aplicada. Este documento no acredita que el entorno actual funcione; las restantes fases requieren implementación y verificación.

## Diagnóstico del repositorio

Observaciones de archivos, sin ejecutar Docker ni la aplicación:

- `project_back/` contiene la API multicanal, pruebas y Composer trasladados desde la raíz. Dockerfile vive en `docker/php/`; Compose monta únicamente el backend en `/var/www` y Nginx sirve `/var/www/public`.
- Se eliminó el esqueleto anterior de `project_back/` por solicitud del usuario. La API raíz es la fuente conservada; se verificaron 59 archivos trasladados mediante SHA-256, incluido su `.env`, sin publicar credenciales. Después se corrigió el atributo XML inválido `testdox` de PHPUnit y se ubicó `.editorconfig` en la raíz del backend.
- Composer declara Laravel 12 y PHP `^8.2`. La compatibilidad efectiva del lockfile, extensiones y arranque queda por comprobar.
- Docker usa PHP 8.2 FPM; Composer usa `latest` y Nginx/Redis etiquetas variables. El contexto se limita a `project_back/` y su `.dockerignore` excluye secretos, dependencias del host y artefactos locales.
- `.env.example` selecciona SQLite y Redis. Dockerfile declara PDO MySQL, pero no declara PDO SQLite: hay que comprobar las extensiones efectivas y ajustar su instalación.
- Compose incluye app, Nginx, Redis y MailHog; no define healthchecks, almacenamiento persistente de Redis ni worker dedicado. Publica Redis y SMTP al host. MailHog fuerza `linux/amd64`.
- `make queue` ejecuta el worker manualmente. README ya incluye preparar `.env`; faltan una preparación idempotente completa y la creación explícita del archivo SQLite.
- Existen pruebas de dispatch, validación y extensibilidad. Su ejecución y resultado quedan pendientes. La deduplicación actual consulta y escribe caché en pasos separados; la concurrencia necesita revisión.

## Secuencia

| Fase | Resultado | Dependencia | Estado |
| --- | --- | --- | --- |
| 1 | Entorno local reproducible | Ninguna | Pendiente |
| 2 | Calidad y CI | 1 | Pendiente |
| 3 | Fiabilidad de notificaciones | 2 | Pendiente |
| 4 | Seguridad de acceso y datos | 2; integrar con 3 | Pendiente |
| 5 | Canales y contrato público | 3 y 4 | Pendiente |
| 6 | Operación y despliegue | 3, 4 y alcance de 5 aprobado | Pendiente |

No exponer el servicio fuera del entorno de desarrollo antes de cerrar la fase 4. Las fechas y responsables se asignarán al abrir cada cambio.

La estructura y convenciones se describen en [structure.md](structure.md). Compose y Make se ejecutan desde la raíz; Artisan y Composer en el host desde `project_back/`.

## Fase 1 — Crear un entorno adecuado para el proyecto

### Objetivo

Desarrollar y probar sin depender de PHP, Composer, Redis o servidores de correo instalados en el host. Requisitos del host: Docker con Compose v2 y Git; Make será opcional.

### Trabajo propuesto

- [x] Consolidar Laravel en `project_back/`, eliminar el esqueleto anterior y documentar la estructura; verificar integridad del traslado y ajustar rutas Docker.
- [ ] Validar Composer y lockfile con la versión PHP elegida; comprobar el arranque Laravel y dependencias referenciadas, incluido Sanctum. Resolver incompatibilidades mínimas sin mezclar nuevas funcionalidades.
- [ ] Depurar paquetes Composer innecesarios de Laravel con justificación por dependencia, limpieza de configuraciones huérfanas y verificación de instalación limpia con/sin dependencias de desarrollo; actualizar lockfile, auditorías y SBOM.
- [ ] Elegir imágenes oficiales o confiables con versiones explícitas compatibles. Registrar digests para construcciones reproducibles y un procedimiento de actualización; evitar `latest`.
- [ ] Separar etapas de construcción y ejecución cuando aporten valor. Aprovechar caché de dependencias a partir de Composer y su lockfile; no dejar compiladores en la imagen de ejecución.
- [x] Añadir `project_back/.dockerignore` para excluir `.env`, credenciales, dependencias del host, logs y datos locales; mantener `.env.example` disponible. Git también excluye la configuración local.
- [ ] Configurar usuario sin privilegios para PHP/Artisan y permisos acotados en `storage/`, `bootstrap/cache/` y SQLite; evitar `chmod 777`. Comprobar permisos de FPM y archivos creados en el host.
- [ ] Mantener SQLite para desarrollo; verificar `pdo_sqlite`, crear la base en una ruta persistente y documentar migraciones. No añadir MySQL sin un requisito concreto.
- [ ] Incorporar un worker como servicio separado que reutilice la imagen/configuración PHP y procese Redis automáticamente. Ajustar timeout, `retry_after` y parada ordenada de acuerdo con la duración de jobs.
- [ ] Añadir healthchecks apropiados y dependencias por disponibilidad. Distinguir proceso iniciado de servicio listo; verificar Redis, HTTP y capacidad de procesamiento de cola.
- [ ] Persistir Redis para el uso local de colas con política documentada y volumen nombrado. Compartir SQLite y los recursos necesarios entre app y worker; separar datos de pruebas.
- [ ] Restringir puertos públicos: HTTP y visor de correo ligados a localhost; Redis y SMTP en red interna salvo necesidad documentada. Montar configuración Nginx en solo lectura.
- [ ] Revisar el capturador SMTP para desarrollo y su compatibilidad amd64/arm64; evitar emulación obligatoria si existe una opción compatible. Mantener bloqueadas las entregas reales por defecto.
- [ ] Sustituir nombres fijos de contenedor por identificación del proyecto Compose, permitir configurar puertos y retirar el campo `version` obsoleto.
- [ ] Definir comandos de preparación, inicio, parada, logs, shell, migraciones y pruebas, con equivalentes Compose sin Make. Preparar `.env`, dependencias y APP_KEY sin sobrescribir secretos ni regenerar claves existentes.
- [ ] Documentar primera instalación, reinicio, recuperación de fallos y borrado explícito de datos. La parada habitual debe conservar volúmenes; no ejecutar migraciones o borrados destructivos de forma implícita.

- [ ] Completar la revisión de vulnerabilidades de octubre de 2026 y avisos vigentes de runtimes/bibliotecas, confianza TLS eMudhra/emSign y cadena de suministro según las tareas de seguridad del cambio activo; entregar matriz de aplicabilidad, SBOM, reportes y make security.

### Criterios de salida

1. Desde una copia limpia, las instrucciones preparan y levantan el entorno sin PHP/Composer instalados en el host y sin credenciales externas.
2. `docker compose config` valida; la construcción termina y `docker compose ps` muestra servicios disponibles, incluido worker.
3. `composer check-platform-reqs`, `php artisan migrate` y `php artisan test`, ejecutados dentro del contenedor, terminan correctamente; se registra la evidencia.
4. La ruta de salud `/up` responde y un envío de email de prueba recorre HTTP → Redis → worker → capturador SMTP. Confirmar la ruta efectiva de dispatch en Laravel, sin inferirla solo del README.
5. Reiniciar conserva SQLite y datos Redis según la política definida; repetir la preparación no cambia APP_KEY ni elimina datos.
6. Verificar plataformas declaradas: al menos la del equipo; documentar las demás como pendientes hasta probarlas. No afirmar soporte arm64/amd64 sin evidencia.

7. `make security` acredita parches aplicables, almacenes CA protegidos frente al incidente eMudhra y auditoría de procedencia/dependencias/imágenes; no quedan hallazgos altos/críticos aplicables sin remediar ni controles inconclusos. Evidencia y límites documentados, sin desactivar TLS.

## Fase 2 — Calidad y automatización

Objetivo: detectar regresiones antes de integrar cambios.

Entregables: CI que instale desde lockfile, compruebe formato con Pint, ejecute pruebas unitarias/feature y valide/construya Docker; revisar coherencia de autoload y bootstrap. Separar pruebas aisladas de integración con Redis/SMTP y documentar comandos locales equivalentes. Incorporar revisión de dependencias e imágenes con una política de tratamiento de hallazgos.

Criterio de salida: una copia limpia ejecuta los mismos controles localmente y en CI; una regresión de dispatch o validación bloquea la integración. Registrar qué controles existen realmente.

## Fase 3 — Fiabilidad del envío

Objetivo: definir y verificar qué ocurre ante duplicados, concurrencia y fallos externos.

Entregables: contrato de idempotencia con alcance, TTL y respuesta estable; deduplicación atómica; semántica de entrega documentada; reintentos con backoff, timeouts y tratamiento de jobs fallidos. Diseñar trazabilidad de evento/intento y recuperación sin registrar payloads sensibles. La idempotencia HTTP no garantiza por sí sola una única entrega externa.

Criterio de salida: pruebas de concurrencia, duplicados, caída de proveedor, reintentos y recuperación demuestran el contrato; el fallo de un canal no bloquea los demás. Documentación operativa y pruebas se entregan con el cambio.

## Fase 4 — Seguridad de acceso y datos

Objetivo: controlar quién puede enviar notificaciones y proteger los datos.

Entregables: definir consumidores y autenticación de API, autorización por canal/destino, límites de tamaño y frecuencia, validación de payload por canal, protección de URLs de destino y gestión/rotación de secretos. Definir retención y redacción de datos en logs; separar configuración local y productiva.

Criterio de salida: solicitudes sin credenciales o permisos y payloads no válidos se rechazan mediante pruebas; los límites funcionan y logs/imagen no contienen secretos. Revisar amenazas antes de cualquier exposición externa.

## Fase 5 — Canales y contrato público

Objetivo: ofrecer integraciones verificables y una API documentada.

Entregables: contrato OpenAPI con ruta efectiva, validaciones, respuestas y duplicados; pruebas de integración por canal. Revisar email y Slack existentes, distinguir mocks de integraciones reales y definir Telegram real solo tras aprobar su alcance. Mantener Strategy + Registry y pruebas de extensibilidad.

Criterio de salida: ejemplos de API coinciden con pruebas de contrato; cada canal declarado disponible acredita un envío controlado y manejo de errores sin usar credenciales productivas.

## Fase 6 — Operación y despliegue

Objetivo: desplegar y recuperar el servicio con evidencia operativa.

Entregables: imagen de ejecución sin código montado desde el host ni herramientas de desarrollo innecesarias; entorno de staging; decisión de almacenamiento productivo y persistencia Redis; HTTPS, secretos, migraciones controladas y rollback. Métricas de profundidad/edad de cola, latencia, errores y fallos definitivos; alertas, backups, restauración y runbooks. Dimensionar recursos a partir de una prueba de carga representativa.

Criterio de salida: despliegue y rollback demostrados en staging, restauración probada y alertas comprobadas mediante un fallo controlado. Acordar objetivos de servicio y capacidad antes de publicar.

## Verificación pendiente del entorno

La reorganización se comprobó estáticamente. PHP, Composer y Docker no están disponibles en este entorno de edición; no se han ejecutado pruebas Laravel ni construcción o arranque de contenedores. `project_back/vendor/` está vacío y necesita instalación desde lockfile.

## Mantenimiento del roadmap

Abrir una propuesta OpenSpec por fase o incremento revisable. Marcar una fase completada solo con evidencias enlazadas, actualizar dependencias cuando cambien decisiones y mantener los comandos ejecutables en la documentación del cambio correspondiente. La consolidación de carpetas y las exclusiones están aplicadas; el resto de las tareas técnicas permanece pendiente.

## Referencias

- [Buenas prácticas de construcción Docker](https://docs.docker.com/build/building/best-practices/): etapas, exclusiones, imágenes y caché.
- [Disponibilidad y orden de inicio en Compose](https://docs.docker.com/compose/how-tos/startup-order/): healthchecks y condiciones de dependencia.
