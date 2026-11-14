# Entorno de desarrollo — implementación en curso

## Construcción inicial PHP

Desde la raíz del repositorio, en el host con Docker:

```bash
APP_UID="$(id -u)" APP_GID="$(id -g)" docker compose --progress=plain build app nginx
docker run --rm --network none --entrypoint sh notification-hub-php:dev -c 'id; php -v; composer --version; php -m'
```

Usar UID/GID del usuario de desarrollo sin privilegios. Un GID existente se reutiliza. UID/GID cero se rechazan; si el UID ya pertenece a otra cuenta de la imagen, la construcción falla con un diagnóstico explícito y requiere elegir un UID no privilegiado libre. El contenedor ejecuta PHP como usuario app; no se monta Docker socket ni configuración del host en esta comprobación.

PHP 8.4.27 y Composer (versión declarada 2.10.3) están fijados a los índices facilitados por el usuario en docker/images.lock.json. Esos índices contienen ARM64 y AMD64. Redis PHP se propone en 6.2.0, cuya ficha oficial incluye pruebas PHP 8.4: https://pecl.php.net/package/redis/6.2.0. Su procedencia/integridad y advisories siguen pendientes de las comprobaciones de cadena de suministro.

La etapa extensions contiene compiladores; development solo recibe extensiones y bibliotecas de ejecución. El binario de Composer se copia desde el índice fijado y se ejecuta sobre nuestro PHP. PDO SQLite y mbstring se comprueban como parte de la base oficial, sin reinstalarlas innecesariamente. No se instala vendor durante la construcción inicial ni se incluyen credenciales locales. La instalación/auditoría de Composer vendrá después de validar la imagen.

El Dockerfile comprueba versión mínima, extensiones y ausencia de gcc/make. Se configura session.upload_progress.enabled=0 desde php.ini; esto complementa el parche y no sustituye el análisis de seguridad del resto del entorno.

## Pendientes

La construcción y las pruebas se ejecutan en el host. Compose, worker, preparación idempotente, auditoría de paquetes, SBOM y pruebas de confianza TLS aún están en implementación. README/Make utilizan ahora setup; la aceptación de seguridad sigue pendiente. Actualizar un índice implica revisar avisos, reconstruir, recrear procesos y repetir pruebas; el pin por digest no demuestra ausencia de vulnerabilidades.

## Laravel verificado con dependencias de desarrollo

La ejecución en el host de composer-install-check.sh completó instalación desde lockfile, requisitos de plataforma, descubrimiento de paquetes, arranque, rutas y toda la suite después de corregir validación y DTO previos. BootstrapTest cubre servicios base, canales y salud. NotificationDTOTest cubre ID generado y proporcionado; extensibilidad comprueba el payload del canal dinámico. Estos resultados no sustituyen la integración real con Redis, worker y SMTP ni la instalación sin dependencias de desarrollo.

## Compose y preparación inicial

Las cinco imágenes publican ARM64 y AMD64 según evidencia del host registrada en docker/images.lock.json. Todos los servicios externos se fijan por índice; app/worker comparten la imagen PHP local. Antes de arrancar una configuración existente, ajustar explícitamente en project_back/.env: MAIL_HOST=mailpit y DB_DATABASE=/var/www/database/database.sqlite. Se exige además DB_CONNECTION=sqlite, REDIS_HOST=redis, QUEUE_CONNECTION=redis y CACHE_STORE=redis. No se sobrescribe la configuración existente.

Ejecutar `sh scripts/dev/setup.sh` desde el host. Prepara .env solo si falta, construye con UID/GID del usuario, audita/instala y prueba Composer, genera APP_KEY solo si está vacía, crea SQLite solo si falta, inicia dependencias, migra explícitamente e inicia la aplicación. El helper de preparación falla con una instrucción si la configuración anterior es incompatible. No utilizar este setup sobre bases productivas. La verificación completa de seguridad de fase sigue pendiente, aunque se repita audit Composer antes de instalar.

Redis persiste mediante volumen nombrado con AOF everysec: puede perder hasta aproximadamente un segundo de escrituras ante caída abrupta. SQLite se conserva en el backend del host. `docker compose down` conserva datos; no añadir -v. Los nombres de proyecto Compose aíslan redes y volúmenes. BACKEND_PATH permite un backend temporal separado en futuras pruebas; el comando setup actual prepara exclusivamente project_back y no debe usarse con ese override.

HTTP y visor Mailpit solo se publican en 127.0.0.1 (8080 y 8025). HTTP_PORT y MAIL_UI_PORT permiten puertos alternativos. SMTP, Redis y FPM no se publican al host. Nginx monta backend/configuración en solo lectura. La disponibilidad usa PING Redis, /mailpit readyz, HTTP /up y una petición FastCGI real a /up; no se usa HTTP contra el puerto FPM. Worker no tiene un falso indicador de consumo: su procesamiento se comprobará con un envío real. Timeout worker=60s, retry_after=90s, parada=75s y timeout SMTP predeterminado=30s.

Reinicio normal: `docker compose up -d --wait --wait-timeout 180`. No ejecuta migraciones ni genera claves. Diagnóstico: `docker compose ps` y `docker compose logs --tail=80 app worker nginx`. No compartir logs con secretos. La configuración y el arranque Compose todavía necesitan evidencia del host.

## Puerto usado por el equipo

El host utiliza HTTP_PORT=8082 porque 8080 estaba ocupado. Guardar HTTP_PORT=8082 en el .env de la raíz para que Compose lo lea automáticamente, o exportarlo en el shell. Este archivo local está excluido de Git. Ajustar APP_URL=http://localhost:8082 en project_back/.env; esta variable Laravel no controla el puerto publicado por Compose.

Prueba de entrega local (crea un único correo de prueba, sin borrar mensajes existentes): `HTTP_PORT=8082 docker compose exec -T app php < scripts/dev/smoke-mail.php`. Verifica Redis como cola, destino Mailpit, 202 y mensaje con asunto/destinatario/cuerpo únicos. Pendiente de ejecución.

## Verificación aislada con persistencia

Ejecutar `sh scripts/dev/verify.sh` en el host con la imagen PHP ya construida. Crea un backend temporal sin .env real, vendor, logs o bases existentes, proyecto Compose único, puertos libres y volumen Redis propio. Instala desde el lockfile auditado y verifica entrega, repetición de preparación/migraciones, preservación de configuración/clave/SQLite/Redis tras recrear contenedores y fallo de entrega con worker detenido. El FAIL de entrega durante los cinco segundos con worker detenido es esperado; el script debe terminar con el PASS global tras volver a iniciarlo.

La repetición verifica los pasos idempotentes de preparación y migraciones; no repite el script setup del directorio de desarrollo. La limpieza elimina solo el proyecto, volumen y directorio temporal creados por verify.sh; no elimina datos del equipo. Requiere poder montar el directorio temporal desde Docker Desktop y descargar dependencias. Si no puede limpiar el proyecto, conserva el directorio temporal y termina con error. Los controles de vulnerabilidades/TLS no se consideran verificados por esta integración.

## Instalación sin dependencias de desarrollo

`sh scripts/dev/no-dev-check.sh` crea una copia temporal sin configuración ni datos locales y sin vendor, audita el lockfile e instala con --no-dev. Comprueba requisitos de plataforma, descubrimiento, about, route:list y resolución de canales, además de ausencia de PHPUnit. Limpia únicamente esa copia temporal y no cambia el vendor del desarrollo. Esta comprobación no define una imagen de producción ni sustituye el escaneo/SBOM.

## Inventario de seguridad inicial

Ejecutar `sh scripts/dev/security-inventory.sh` con los servicios de desarrollo iniciados. Solo consulta versiones, extensiones, parámetros PHP seleccionados, huellas de bundles/candidatos emSign, imágenes y paquetes del sistema. No carga .env en el inventario PHP ni imprime phpinfo o configuraciones Docker completas. Nombres coincidentes de certificados son candidatos a revisar, no una lista automática de certificados comprometidos. El inventario no sustituye escaneo, SBOM ni pruebas de cadena TLS.

## Parche Brotli de octubre

Alpine v3.24 secdb confirma CVE-2026-111497 corregida en brotli-libs 1.2.1-r0. PHP y Nginx se construyen con ese mínimo. Nginx ahora usa notification-hub-nginx:dev, derivada del índice oficial registrado; el ID resultante debe asociarse al siguiente inventario/SBOM. Reconstruir app/nginx y recrear app/worker/nginx para dejar de usar bibliotecas anteriores. Si el repositorio no ofrece el paquete corregido, la construcción debe fallar; no relajar el mínimo.

## Comandos operativos actuales

| Make | Equivalente shell/Compose |
| --- | --- |
| setup | sh scripts/dev/setup.sh |
| up | docker compose up -d --wait --wait-timeout 180 |
| down | docker compose down |
| build | APP_UID="$(id -u)" APP_GID="$(id -g)" docker compose build app nginx |
| install | sh scripts/dev/composer-install-check.sh |
| key | docker compose run --rm --no-deps -T app php < scripts/dev/prepare-runtime.php |
| migrate | docker compose run --rm --no-deps -T app php artisan migrate --no-interaction |
| test | docker compose exec -T app php artisan test |
| verify | sh scripts/dev/verify.sh |
| no-dev-check | sh scripts/dev/no-dev-check.sh |
| security | sh scripts/dev/security-check.sh |
| security-policy-test | sh scripts/dev/security-policy-test.sh |
| acceptance | sh scripts/dev/acceptance.sh |
| queue | docker compose logs -f worker |
| logs | docker compose logs -f app worker nginx |
| shell | docker compose exec app sh |

`key` conserva una APP_KEY presente; usa el mismo helper que setup. `queue` consulta
el worker existente y no crea un segundo consumidor. `down` no borra volúmenes.
Setup rechaza BACKEND_PATH porque prepara exclusivamente project_back. Los puertos
mostrados se obtienen de Compose y respetan el .env raíz, incluido HTTP_PORT=8082.

## Estado de las comprobaciones

La evidencia histórica detallada está en development-verification.md. Se verificaron
construcción ARM64, 20 pruebas/62 aserciones, nginx -t, instalación sin dev y recorrido
real HTTP/Redis/worker/Mailpit con persistencia y worker detenido. Las menciones
anteriores de ejecución pendiente describen el momento inicial de cada entrega.
AMD64 tiene manifiesto revisado, sin ejecución nativa acreditada.

## Aceptación desde copia limpia

`make acceptance` o `sh scripts/dev/acceptance.sh` copia únicamente tooling público y
backend sin .env, vendor, claves ni datos; utiliza nombre de proyecto, puertos y
volúmenes aislados. Ejecuta setup dos veces, compara persistencia, verifica no-dev,
integración y fixtures de política. Limpia únicamente esos recursos temporales.
Finalmente recopila seguridad de los servicios de desarrollo originales, para
conservar sus reportes en la raíz. Requiere servicios originales iniciados y acceso
al registro/repositorios. Mientras falten los controles de seguridad documentados,
el resultado final será no cero aunque la parte funcional haya pasado.

La integración ampliada comprueba puertos ligados a loopback, ausencia de publicación
FPM/worker/Redis, timeout SMTP efectivo, retry_after, parada de worker con exit 0 y
estado unhealthy de Nginx al detener FPM en el proyecto temporal (presupuesto de
sondeo de 180 segundos), seguido de recuperación y una entrega nueva.
