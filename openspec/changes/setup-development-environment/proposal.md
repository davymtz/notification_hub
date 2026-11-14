# Proposal

## Why

La reorganización ya está cerrada, pero la API aún no tiene un arranque local reproducible ni una ejecución de pruebas acreditada. Necesitamos completar la fase 1 del roadmap para desarrollar desde una copia limpia usando Docker y sin instalar PHP o Composer en el host.

## What Changes

- Estabilizar Composer y el arranque de Laravel 12: lockfile válido, proveedores registrados y eliminación de referencias accidentales a Sanctum en una API que aún no define autenticación.
- Depurar dependencias Laravel de ejecución y desarrollo: justificar cada paquete, retirar los innecesarios y sus configuraciones/plugins huérfanos, actualizar el lockfile con cambios mínimos y comprobar instalación limpia y pruebas.
- Alinear el montaje de rutas con el endpoint ya documentado y probado, `POST /v1/notifications/dispatch`, y mantener `/up` y `/`.
- Preparar una imagen PHP con extensiones verificadas, etapas separadas, herramientas de compilación excluidas de ejecución, usuario sin privilegios y versiones/digests registrados.
- Exigir versiones parcheadas de runtimes, bibliotecas nativas y herramientas frente a avisos vigentes, incluidos los de octubre de 2026; registrar aplicabilidad, versiones instaladas, fuentes y evidencia.
- Revisar el incidente eMudhra/emSign en los almacenes TLS reales de contenedores, PHP/cURL y Composer, con pruebas de rechazo y sin desactivar validación TLS.
- Incorporar inventario/SBOM, auditoría de dependencias e imágenes y controles de procedencia e instalación para reducir riesgos de cadena de suministro, incluidas herramientas de seguridad.
- Completar Compose con app, Nginx, worker, Redis persistente y Mailpit como capturador SMTP local; limitar HTTP y visor de correo a localhost.
- Añadir preparación idempotente que preserve configuración, APP_KEY y datos; compartir SQLite entre app y worker y aislar datos de pruebas.
- Entregar comandos Make y equivalentes Compose, comprobaciones de disponibilidad y una verificación integral HTTP → Redis → worker → SMTP local.
- Actualizar README, documentación de entorno y roadmap con comandos ejecutados y evidencia de cierre.

## Capabilities

### New Capabilities

Ninguna capacidad funcional nueva. Es un cambio de herramientas y entorno que restablece el contrato HTTP ya descrito en README y las pruebas existentes. Se declara `skip_specs: true`; los criterios operativos se concretan en diseño y tareas.

### Modified Capabilities

Ninguna. No se implementan autenticación, idempotencia nueva, nuevos canales ni cambios de respuestas o validaciones de negocio. La estabilización de bootstrap y rutas debe conservar las expectativas de las pruebas existentes.

## Impact

Archivos afectados al aplicar: `docker/php/Dockerfile`, `docker/nginx/default.conf`, `docker-compose.yml`, `Makefile`, scripts de preparación/verificación en `scripts/dev/`, configuración y bootstrap de `project_back/`, Composer y lockfile si necesitan reconciliación, pruebas de arranque existentes, `.env.example` y documentación. Se conserva Laravel 12 y la estructura actual. Mailpit sustituye a MailHog; cambia el hostname SMTP local, con instrucciones para configuración existente. Puertos predeterminados: HTTP 8080 y visor SMTP 8025, ambos configurables y ligados a 127.0.0.1. Redis y SMTP no se publican al host. No hay despliegue productivo ni CI en este cambio. Se añaden scripts de comprobación local de seguridad y reportes versionados sin secretos; las evidencias extensas y SBOM se generan como artefactos identificados por hash. No se instalarán Node.js, Python ni otros runtimes sin necesidad del proyecto.

El cambio se considerará terminado solo con construcción y ejecución verificadas. La ausencia de Docker permite avanzar en archivos, pero no dar por cumplidos los criterios de ejecución.

## Security acceptance

La fase no se cierra con hallazgos críticos o altos aplicables sin remediar, ni con exposición al incidente eMudhra pendiente de resolver. Fallos de consulta o bases de advisories obsoletas se registran como verificación inconclusa, nunca como ausencia de vulnerabilidades. Un cambio de digest o un escaneo sin hallazgos no demuestra por sí solo procedencia confiable ni corrección completa. Toda excepción de no aplicabilidad necesita evidencia; no se crearán exclusiones generales.

Se incorpora el backport temporal autorizado de libxml2 para CVE-2026-86140 en PHP/Nginx, conservando correcciones Alpine previas, con fuentes verificadas, APK local firmado, pruebas y política de retirada. Su mantenimiento forma parte del entorno hasta disponer de revisión oficial compatible. La aceptación sigue pendiente de ejecución y evaluación de otros avisos.

La corrección de dependencias Go de Mailpit se prepara como reconstrucción temporal de su versión existente, con Go fijado por digest y herramientas de frontend solo en builder. Activación condicionada a auditoría, pruebas, preservación de interfaz y entrega local. No añade runtimes al backend Laravel ni al host.
