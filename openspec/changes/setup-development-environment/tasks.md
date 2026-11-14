# Tasks

## 1. Imagen y dependencias de ejecución

- [ ] 1.1 Fijar PHP 8.4 FPM >= 8.4.27 tras revisar avisos posteriores, Composer 2 y versiones compatibles de Nginx, Redis, Mailpit y extensión Redis; registrar versiones y digests en docs/development.md y verificar su resolución y manifiestos amd64/arm64 sin usar latest.
- [x] 1.2 Separar compilación de extensiones y etapa de desarrollo en docker/php/Dockerfile; incluir bibliotecas de ejecución, PDO SQLite, Redis y pcntl, usuario sin privilegios y UID/GID configurables; verificar construcción, php -m, id no root y ausencia de compiladores en la etapa final.
- [ ] 1.3 Verificar que el contexto project_back y su .dockerignore excluyen configuración local, claves, bases y artefactos generados; inspeccionar la imagen construida sin mostrar secretos y registrar la política de actualización de imágenes en docs/development.md.

## 2. Composer, bootstrap y rutas Laravel

- [x] 2.1 Validar composer.json y composer.lock con el runtime nuevo y reconciliar únicamente los desajustes necesarios; verificar composer validate --strict, descarga/auditoría inicial con --no-plugins --no-scripts y composer install desde lockfile tras revisión de plugins y scripts y composer check-platform-reqs en un contenedor puntual con el backend montado.
- [x] 2.2 Crear bootstrap/providers.php, reconciliar providers heredados con Laravel 12 y retirar middleware Sanctum/stateful sin dependencia; verificar package:discover, php artisan about y resolución del NotificationRegistry con email, slack y telegram registrados.
- [x] 2.3 Fijar el prefijo de montaje API para conservar POST /v1/notifications/dispatch; verificar route:list, salud /up y las pruebas existentes de dispatch, validación, extensibilidad y duplicados, sin cambiar respuestas esperadas ni silenciar fallos.
- [x] 2.4 Añadir una regresión de arranque o registro de proveedores si las pruebas existentes no la cubren y documentar las correcciones en docs/development.md; verificar php artisan test con SQLite en memoria, mail/cache array y cola sync/fake sin servicios externos.

- [x] 2.5 Inventariar todos los paquetes directos require/require-dev en docs/development-dependencies.md y decidir conservar, retirar o mover a desarrollo con referencias de código/configuración/providers/scripts/pruebas y composer why; verificar cobertura de cada dependencia y justificar Sail, Tinker, Ignition, Faker, Mockery, Collision, Guzzle y herramientas de calidad.
- [x] 2.6 Retirar con Composer los paquetes acreditados como innecesarios y limpiar configuración, providers, scripts y allow-plugins huérfanos, incluidas referencias Sanctum accidentales; verificar diff mínimo de manifiesto/lockfile, composer validate --strict y package:discover sin referencias a clases eliminadas.
- [ ] 2.7 Verificar instalaciones limpias desde lockfile con y sin dependencias de desarrollo en entornos separados; comprobar about, route:list y registro de canales en ambas, ejecutar suite completa e integración con dependencias de desarrollo y actualizar auditorías/SBOM y documentación con el resultado de la depuración.

## 3. Servicios, persistencia y disponibilidad

- [x] 3.1 Definir app, nginx, worker, redis y mailpit en Compose con imagen PHP compartida, montajes del backend coherentes y Nginx/configuración en solo lectura; retirar version, container_name y plataforma forzada; verificar docker compose config --quiet y construcción de los servicios.
- [x] 3.2 Publicar solo HTTP y visor Mailpit en 127.0.0.1 con puertos configurables, mantener Redis/FPM/SMTP internos y actualizar .env.example para Mailpit y SQLite; verificar puertos efectivos de Compose y documentar migración de MAIL_HOST y DB_DATABASE existentes sin sobrescribir .env.
- [x] 3.3 Configurar volumen Redis con AOF y SQLite compartido por app/worker, junto con directorios escribibles por PHP; verificar escritura en ambos servicios, migraciones y conservación de marcadores después de reiniciar en un proyecto de prueba separado.
- [x] 3.4 Añadir healthchecks Redis, Mailpit, FPM mediante FastCGI y Nginx mediante HTTP, con dependencias service_healthy sin ciclos; verificar que el arranque espera disponibilidad y detecta una dependencia detenida en un plazo máximo documentado de 180 segundos.
- [x] 3.5 Configurar worker en primer plano con timeout 60, retry_after Redis 90, stop_grace_period de al menos 75 y timeout SMTP inferior a 60; verificar configuración efectiva y parada ordenada, y documentar cola, logs y diagnóstico en docs/development.md.

## 4. Preparación y comandos de desarrollo

- [ ] 4.1 Crear scripts/dev/setup.sh en shell POSIX para comprobar Docker/Compose, preparar carpetas y copiar .env solo si falta; verificar sh -n y ejecuciones aisladas con .env ausente y presente, sin revelar ni cambiar configuración existente.
- [ ] 4.2 Implementar en setup construcción, Composer en contenedor puntual, creación de SQLite solo si falta y APP_KEY solo si está vacía; verificar primera ejecución desde copia limpia y segunda ejecución con iguales hashes de configuración y contenido previo conservado.
- [ ] 4.3 Completar setup con arranque de dependencias, migración explícita, servicios y espera de salud con límite de 180 segundos; detectar MAIL_HOST/DB_DATABASE incompatibles con instrucciones de ajuste; verificar preparación exitosa y fallo diagnosticable sin sobrescritura silenciosa.
- [ ] 4.4 Añadir comandos Make setup, up, down, build, install, migrate, test, verify, logs y shell, mantener queue como acceso documentado al worker y corregir .PHONY; verificar que down conserva volúmenes, up no migra ni regenera claves y cada comando tiene equivalente Compose en README y docs/development.md.

## 5. Verificación automatizada del recorrido local

- [x] 5.1 Crear scripts/dev/verify.sh con proyecto Compose y directorio de backend temporales, puertos alternativos y SQLite/Redis separados; no copiar .env real ni datos locales; verificar aislamiento y limpieza limitada a recursos de prueba, incluso ante error o interrupción.
- [x] 5.2 Añadir a verify un envío email con idempotency_key y destinatario únicos; comprobar HTTP 202, recepción del mensaje exacto en Mailpit y consumo por el worker en un máximo de 120 segundos, con salida no cero ante fallo; verificar también que worker detenido hace fallar la comprobación de entrega.
- [x] 5.3 Añadir comprobaciones de repetición de setup, preservación de APP_KEY/SQLite/Redis y reinicio, sin volcar secretos; verificar que fallan si hay pérdida de datos o regeneración de clave y documentar la ejecución y limitaciones en docs/development.md.

## 6. Seguridad del entorno y cadena de suministro

- [ ] 6.1 Crear docs/development-security.md con inventario y matriz de advisories al 2026-10-28 y actualizarla al implementar; verificar versiones efectivas de PHP CLI/FPM, extensiones, Composer, OpenSSL/cURL/Brotli y servicios, fuentes oficiales, backports y aplicabilidad de cada hallazgo; documentar runtimes ausentes sin instalarlos.
- [x] 6.2 Fijar parche PHP que cubra CVE-2026-109618/109619 y revisar avisos posteriores, aplicar session.upload_progress.enabled=0 antes de las peticiones y evaluar CVE-2026-115560 y CVE-2026-111497 según componentes presentes; reconstruir y recrear servicios y verificar versiones/configuración efectiva CLI/FPM con prueba negativa de la política para un runtime vulnerable.
- [x] 6.3 Identificar y actualizar los almacenes CA efectivos de app/worker, PHP streams, cURL y Composer según el incidente eMudhra/emSign; registrar huellas oficiales, paquete CA, rutas y overrides y verificar que no quedan rutas de confianza afectadas, sin modificar automáticamente el host o daemon Docker.
- [x] 6.4 Añadir pruebas TLS con cadena afectada de eMudhra y control válido, sin claves comprometidas ni destinos atacantes; verificar rechazo por desconfianza y aceptación válida en cada cliente relevante, mantener verificación de hostname y prohibir bypass TLS; registrar fixtures y limitaciones en docs/development-security.md y dejar pendiente si la evidencia no es concluyente.
- [ ] 6.5 Revisar lockfile, orígenes, repositorios, plugins y scripts Composer y aplicar descarga/auditoría previa a ejecución de terceros dentro de contenedor sin credenciales/socket Docker; verificar auditoría del lockfile, ausencia de allow-plugins=true y rechazo de plugin no autorizado con fixture inocuo; documentar la secuencia en docs/development.md.
- [ ] 6.6 Seleccionar y verificar origen, versión e integridad de la herramienta de escaneo/SBOM antes de ejecutarla; generar CycloneDX/SPDX y reportes de Composer e imágenes asociados a digest/arquitectura y base de advisories; verificar checksums y firmas/provenance disponibles con identidad y origen esperados, documentando evidencia y límites en docs/development-security.md.
- [x] 6.7 Implementar scripts/dev/security-check.sh y make security e integrarlos como condición de aceptación local; verificar sh -n, ejecución real y fixtures para hallazgo crítico/alto aplicable, fallo de consulta, runtime vulnerable y error TLS, con salida no cero y sin secretos; no admitir exclusiones generales ni interpretar falta de conectividad como resultado limpio.
- [ ] 6.8 Documentar actualización semanal y respuesta a avisos críticos con triage en un día hábil, reconstrucción, pruebas y rotación si hubo exposición; verificar que docs/development-verification.md registra fecha/fuentes/reportes y ninguna vulnerabilidad alta/crítica aplicable o exposición eMudhra queda sin remediar antes de cerrar fase 1.

## 7. Aceptación integral y cierre de fase

- [ ] 7.1 Ejecutar desde una copia limpia la secuencia documentada usando solo Docker/Compose y shell en host; registrar en docs/development-verification.md comandos, runtime, arquitectura, resultados de pruebas y salud, incluidas la verificación HTTP → Redis → worker → Mailpit y make security con evidencia de parches, CA y cadena de suministro.
- [x] 7.2 Levantar una segunda instancia aislada con nombre de proyecto y puertos alternativos y verificar ausencia de colisiones; registrar arquitectura nativa probada y dejar la otra como no ejecutada si solo se verificó su manifiesto.
- [ ] 7.3 Revisar los criterios funcionales y de seguridad de salida de la fase 1 en docs/roadmap.md y enlazar evidencias; marcarla completa solo si todas las pruebas exigidas pasaron, conservar pendientes de otras fases y verificar openspec validate setup-development-environment --strict.
