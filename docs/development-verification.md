# Verificación del entorno de desarrollo

## Estado inicial — 2026-10-28

El usuario reporta Docker 29.7.2, Compose v5.4.0 y daemon Linux aarch64 (ARM64). Datos facilitados por el usuario, no comprobados desde este contenedor.

Este contenedor de edición no dispone de Docker/PHP/Composer. La consulta directa a Docker Hub falla por DNS restringido y, con escalación, por proxy HTTP 403. Esto no demuestra inexistencia de imágenes. La resolución de tags/digests y compatibilidad de manifiestos se ejecutará en el host.

## Cambios preparados, pendientes de ejecución

- Registro de AppServiceProvider en bootstrap/providers.php, conservando providers por defecto del framework.
- Retirada de configuración y middleware Sanctum accidental, sin instalar Sanctum.
- Prefijo API explícito vacío para mantener /v1/notifications/dispatch.
- Pruebas de regresión de servicios base, registro de canales, salud y ruta.

Ninguna tarea con comprobaciones de ejecución se ha marcado completa. No se ha cambiado composer.json ni manipulado manualmente composer.lock. La depuración de paquetes se hará con Composer en el contenedor preparado.

## Primer paso en el host

Desde la raíz del repositorio:

```bash
sh scripts/dev/inspect-images.sh
```

El script solo consulta manifiestos públicos con Buildx; no descarga ni ejecuta imágenes. Se necesita la salida con digest y plataformas para fijar la base PHP. Si la etiqueta no se resuelve, conservar el error y revisar etiquetas oficiales; no sustituirla por una rama sin parche acreditado.

Antes de los siguientes pasos, comprobar que project_back/composer.json, docker/php/Dockerfile y openspec/changes/setup-development-environment/tasks.md existen en la misma copia del host.

## Evidencia recibida — imagen PHP

Salida de Buildx facilitada por el usuario para `docker.io/library/php:8.4.27-fpm-alpine`: índice OCI `sha256:93aa3f61216b73cc6f878235c9772849449f4efc18dfe3d6678eed645076c00d`. Se registran los manifiestos ARM64 y AMD64 en `docker/images.lock.json`. La imagen ARM64 declara Alpine 3.24 y creación 2026-10-22.

Esto acredita publicación de manifiestos para ambas plataformas; no acredita construcción de nuestra imagen, ejecución nativa, análisis de vulnerabilidades ni verificación criptográfica de attestations. Las entradas unknown/unknown se identifican como attestations; su presencia no significa que se hayan verificado. La tarea 1.1 sigue pendiente de los demás componentes.

## Evidencia recibida — Composer e imagen preparada

El usuario facilitó el índice Composer `sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc`, versión declarada 2.10.3, con manifiestos amd64 y arm64/v8. Registrado en docker/images.lock.json.

Dockerfile preparado con etapas separadas y ambos índices fijados; pendiente de construcción y ejecución por el usuario. La tarea 1.1 sigue pendiente de Nginx, Redis y Mailpit. Las tareas 1.2 y de seguridad siguen pendientes de los resultados de ejecución y auditoría; no se consideran completas por contener código.

## Primer intento de construcción

El host reportó exit code 1 en la instrucción combinada de paquetes y creación de usuario/grupo. La salida recibida no incluye el mensaje causal anterior. Se separaron las etapas para mejorar diagnóstico y se corrigió la creación de grupo para reutilizar un GID existente; no se atribuye todavía el error a esa causa. Construcción pendiente de repetición.

## Construcción y ejecución ARM64 confirmadas por el usuario

El usuario reportó construcción correcta tras reutilizar el GID 20 existente en Alpine. La ejecución de `docker run --rm --network none --entrypoint sh notification-hub-php:dev -c 'id; php -v; composer --version; php -m'` devolvió UID 501 (app), GID 20 (dialout), PHP CLI 8.4.27 y Composer 2.10.3. Se observaron bcmath, curl, dom, intl, mbstring, pcntl, pdo_sqlite, redis y zip, entre otras extensiones. La duplicación visual del grupo primario no implica privilegios root.

Las aserciones del Dockerfile comprobaron en construcción la versión mínima, las extensiones y ausencia de gcc/make; el build terminó según el usuario. Tarea 1.2 completada con evidencia de ejecución nativa reportada, sin acreditar aún la seguridad integral, FPM o la plataforma AMD64. Auditoría de dependencias y prueba de confianza TLS pendientes.

## Auditoría inicial de Composer — salida facilitada por el usuario

`validate_exit=0`, `audit_exit=1`. Sin paquetes abandonados. La salida reporta 12 advisories en cinco paquetes: Laravel (3, uno crítico), CommonMark (6), Flysystem (1), HttpFoundation (1) y YAML (1). Versiones del lockfile antes de remediar: Laravel 12.64.0, CommonMark 2.9.0, Flysystem 3.35.2, HttpFoundation/YAML 7.4.15.

Se preparó resolución selectiva sin instalación ni ejecución de scripts/plugins y depuración de Tinker, Sail, Ignition y Faker. Ningún hallazgo se considera remediado hasta recibir el nuevo lockfile/audit y pruebas. La actualización Laravel conserva la rama 12.x y exige como mínimo 12.69. No se utilizan exclusiones de advisories.

## Primer intento de remediación Composer

Composer rechazó el valor `{}` al configurar allow-plugins desde CLI. El trap restauró manifiesto y lockfile, según salida del usuario. Se corrigió el comando a `composer --no-plugins --no-scripts config allow-plugins false`; no se considera realizada la actualización o auditoría posterior.

## Remediación selectiva completada — evidencia del usuario

Composer informó 0 instalaciones, 4 actualizaciones y 10 eliminaciones. Los archivos del host ya se reflejan en este workspace: Laravel 12.70.2, CommonMark 2.10.4, Flysystem 3.36.0 y HttpFoundation 7.4.21. Se eliminaron Tinker, Sail, Faker, Ignition y sus transitivas innecesarias, incluido YAML. allow-plugins=false y config/ignition.php ausente. Validate válido y auditoría con advisories/abandoned vacíos.

Esto acredita remediación del lockfile frente a la consulta realizada, no instalación, compatibilidad de ejecución ni seguridad integral. Las tareas 2.1, 2.5 y 2.6 todavía requieren plataforma, composer why, descubrimiento y/o pruebas. Se preparó composer-install-check.sh para esas comprobaciones con una APP_KEY efímera y configuración de pruebas aislada de Redis/SMTP; no modifica .env.

## Primera ejecución de pruebas Laravel

El usuario reporta BootstrapTest y SmokeTest correctos; 10 pruebas de dispatch/extensibilidad/validación fallan con HTTP 500. El log sincronizado confirma `Validation rule required_if requires at least 2 parameters` en DispatchNotificationRequest. Se retira la regla mal formada; la obligatoriedad de email ya se valida en el callback existente. Se evita que tipos no array provoquen TypeError en ese callback y se usa el mensaje estándar de canal inválido esperado por la prueba existente.

La inspección detecta también doble asignación de eventId promovido readonly en NotificationDTO. Se declara esa propiedad fuera de la promoción y se asigna una sola vez, conservando UUID automático o ID proporcionado. Se añaden dos regresiones del DTO; la suite original no se debilita. Son defectos previos descubiertos al ejercitar el contrato existente; no se considera pasada la suite hasta nueva ejecución del host.

## Segunda ejecución de pruebas — payload extensible

El usuario reporta un único fallo restante en NotificationExtensibilityTest. El log sincronizado confirma `Undefined array key payload` en NotificationDTO::fromArray. Validator::validated excluye el array padre cuando existen reglas anidadas y no hay claves anidadas validadas. La regla payload.email producía ese efecto en el canal dinámico cuyo payload solo tiene message.

Se configura excludeUnvalidatedArrayKeys=false únicamente en DispatchNotificationRequest: mantiene las claves del payload, ya validado como array y sujeto a los callbacks por canal. No se desactiva validación global ni se admiten campos de primer nivel ajenos a las reglas. Se fortalece la prueba existente para verificar que el job conserva exactamente el payload del canal dinámico. Pendiente de repetir la suite en el host. La validación detallada por canal permanece en la fase correspondiente del roadmap.

## Suite completa correcta — confirmación del usuario

El usuario confirma que todas las pruebas de composer-install-check.sh pasaron tras corregir el payload extensible. El script ejecuta previamente validate, audit, install, check-platform-reqs, package:discover, about, route:list y composer why para Guzzle/Mockery/Collision con set -eu. Se acredita la secuencia completada según confirmación del usuario; no se inventan duración, número de assertions o cobertura.

Completadas tareas 2.1–2.6 junto con la imagen 1.2: 7/33. La tarea 2.7 permanece pendiente porque faltan la instalación separada sin require-dev, integración real SMTP/Redis y actualización SBOM. La suite usa mail array, cache array, cola sync/fake y SQLite en memoria: no acredita todavía worker, persistencia, confianza TLS ni auditoría de imágenes.

## Manifiestos Nginx recibidos

El usuario facilitó el índice OCI de nginx:1.30.6-alpine y manifiestos linux/amd64 y linux/arm64/v8, registrados en docker/images.lock.json. Consulta de metadatos acreditada; ejecución, configuración y auditoría de imagen aún pendientes. La tarea 1.1 sigue pendiente de Redis y Mailpit.

## Manifiestos Redis recibidos

El usuario facilitó el índice OCI de redis:8.10.3-alpine y manifiestos linux/amd64 y linux/arm64/v8, registrados en docker/images.lock.json. La imagen declara base Alpine 3.23. La evidencia acredita metadatos de publicación; ejecución, persistencia, integración y auditoría siguen pendientes. La tarea 1.1 permanece pendiente de Mailpit.

## Manifiestos Mailpit y Compose preparado

Recibido índice Mailpit v1.32.0 con manifiestos amd64 y arm64; registrado en docker/images.lock.json. Compose preparado con todos los índices externos fijados y healthchecks, worker y volumen Redis. Añadidos setup y helper de preparación; configuración existente no se sobrescribe. Pendientes validación Compose, ejecución y pruebas de idempotencia/persistencia en host. No se marcan tareas como completas solo por estos archivos.

## Arranque Compose en host — puerto 8082

El usuario confirma setup correcto hasta la publicación Nginx: 8080 estaba ocupado. Tras cambiar HTTP_PORT a 8082, reporta arranque correcto. Se acredita validación/construcción Compose e inicio de servicios según esa confirmación. Tarea 3.1 completada. No se ha probado todavía parada ordenada, pérdida de dependencia, segundo proyecto, persistencia tras reinicio ni seguridad integral. Usar HTTP_PORT=8082 en comandos posteriores y APP_URL coherente en configuración local.

Se prepara smoke-mail.php para comprobar entrega real con asunto, destinatario y cuerpo únicos, espera limitada y sin vaciar correo, caché o colas. Usa endpoints internos fijos nginx/mailpit y verifica Redis/SMTP local antes del envío. Es una prueba incremental sobre desarrollo; no completa las tareas de verificación aislada 5.1–5.3.

## Corrección del estado de arranque reportado

El usuario aclara que Nginx reinicia. Su log confirma `host not found in upstream app`; el aviso del entrypoint sobre default.conf de solo lectura es informativo y no se elimina el montaje ro para ocultarlo. El arranque integral NO está verificado y la prueba de correo queda pendiente. Compose actual coloca todos los servicios en la red default del proyecto; faltan los estados y redes efectivos del host para distinguir app detenido, contenedores previos o red inconsistente. Se prepara diagnose-network.sh, de solo lectura y sin inspección de variables de entorno.

## Diagnóstico efectivo de red — evidencia del host

App está running/healthy, responde 200 en FastCGI y tiene alias app en notificacionhub_default (172.24.0.4). Nginx está restarting y la inspección no muestra ninguna red adjunta; no publica 8082 en ps. Esto explica la falta de resolución de app. Se infiere estado incompleto de Nginx tras el conflicto previo de puerto, sin atribuirlo a un fallo del código PHP. Se configura HTTP_PORT=8082 en .env local de Compose y se indica recrear exclusivamente Nginx; no borrar volúmenes ni recrear Redis. La recuperación queda pendiente de confirmación.

## Nginx recuperado e integración SMTP real correcta

Tras recrear exclusivamente Nginx, el usuario obtuvo en navegador la respuesta JSON de servicio running en 8082. Posteriormente smoke-mail.php devolvió HTTP 202 y PASS HTTP -> Redis -> worker -> Mailpit con destinatario y cuerpo verificados. Esto acredita entrega real incremental sobre desarrollo, sin borrar datos. No equivale aún a verificación desde copia limpia ni pruebas de pérdida de dependencia/persistencia.

## Verificación aislada detenida por nuevo advisory

verify.sh validó Composer y se detuvo antes de instalar o enviar correo: audit detectó CVE-2026-45070 / GHSA-vqc8-7275-q272, severidad media, para symfony/mime 7.4.15. La consulta aportada por el usuario reporta publicación 2026-10-28T18:08:54Z y corrección en rama 7.4 desde 7.4.22. La consulta directa al enlace de Symfony no fue accesible desde la herramienta; el rango registrado proviene de la salida real de Composer, no de verificación independiente del aviso.

La limpieza eliminó correctamente el volumen y la red temporales nhverify, sin tocar el proyecto de desarrollo. No se considera completada la integración aislada. Se prepara composer-security-update.sh para resolver únicamente symfony/mime en ^7.4.22 y dependencias estrictamente necesarias, con plugins/scripts desactivados y sin instalación. Deben repetirse instalación/pruebas e integración tras la auditoría limpia; no se excluye el advisory.

## Actualización Mime e instalación posterior

El usuario reporta actualización única de symfony/mime 7.4.15 a 7.4.22, validate correcto y audit sin advisories ni paquetes abandonados. Confirma también que composer-install-check.sh terminó satisfactoriamente: instalación, plataforma, arranque y suite sin errores. El lockfile sincronizado contiene v7.4.22. El resultado de verify.sh todavía está pendiente; no se marca la verificación aislada como completada.

## Verificación aislada completada — evidencia del usuario

Proyecto temporal nhverify-1793215210-16111. La salida final contiene PASS de entrega con destinatario/cuerpo verificados y PASS global de entrega aislada, repetición de preparación, reinicio persistente y prueba negativa con worker detenido. La ejecución llegó a ese punto con set -eu y el control negativo solo admite código 2 (timeout de entrega). Se acredita preservación de .env/APP_KEY y marcadores SQLite/Redis.

La limpieza eliminó los cinco servicios, la red y el volumen de ese proyecto. El mensaje genérico de Docker sobre Gordon no invalida estos PASS; puede acompañar la salida no cero esperada del control negativo. No se infiere salud adicional ni ausencia de avisos por ese texto. Completadas tareas 3.3 y 5.1–5.3; restantes controles de seguridad, instalación sin require-dev y aceptación global siguen pendientes.

## Comprobación sin require-dev — webhook Slack ausente

La instalación temporal sin .env real llegó a resolución de canales y falló porque SlackNotifier asignaba config nula a una propiedad string. La configuración permite webhook ausente y send ya contempla explícitamente un modo mock; el tipo impedía alcanzarlo. Se hace nullable únicamente la propiedad, conservando la lógica existente y sin añadir credenciales ni peticiones externas. Se añaden regresiones para null y cadena vacía que verifican Http::assertNothingSent. Pendiente de repetir instalación sin require-dev y suite con desarrollo.

## Instalación sin require-dev y suite posterior correctas

El usuario facilita PASS de no-dev-check.sh: arranque, ruta de dispatch y resolución de todos los canales sin PHPUnit instalado. Confirma además composer-install-check.sh sin errores tras las regresiones de Slack con webhook null/vacío. La instalación de desarrollo queda preservada porque no-dev usa una copia temporal.

La tarea 2.7 conserva pendiente la actualización de SBOM/auditorías de imagen; sus comprobaciones de instalación y ejecución con/sin require-dev ya tienen evidencia. No se cierra el bloque de seguridad ni la fase 1 por estos resultados.

## Inventario de seguridad recibido

App y worker: PHP 8.4.27, OpenSSL 3.5.9, cURL 8.22.0 con Brotli 1.2.0, redis PHP 6.2.0. FPM confirma upload progress Off y rutas CA compartidas. Todos los servicios ejecutan ARM64. Composer reporta secure-http=true, disable-tls=false y allow-plugins=false. Se crea matriz en docs/development-security.md; la tarea 6.1 sigue pendiente de completar aplicabilidad/backports y escaneo. Brotli 1.2.0-r1 requiere contrastar el parche Alpine frente a CVE-2026-111497 antes de concluir afectación o corrección. No se elimina emSign G1 por nombre ni se da por resuelto eMudhra sin comprobar huellas/cadenas.

## Brotli: parche Alpine acreditado y remediación preparada

El usuario obtuvo v3.24/main.json por TLS: SHA-256 de respuesta 30fe355d68d0fe1555df2487eaac58500d65c7aeefcf4d1805910dd99b180849, secfixes brotli 1.2.1-r0 para CVE-2026-111497. La revisión instalada 1.2.0-r1 es anterior y no cumple ese mínimo. last_update ausente no se interpreta como fecha de actualización acreditada.

Se añade restricción brotli-libs>=1.2.1-r0 al runtime PHP y a una imagen local de Nginx derivada del índice original fijado. Compose construye esa imagen y setup construye ambas. No se altera un contenedor manualmente ni se elimina Brotli sin atender dependencias cURL. Pendientes construcción, recreación de app/worker/nginx, versiones efectivas y regresiones. El resto de imágenes/paquetes sigue sujeto al escaneo completo.

## Brotli actualizado en procesos PHP

Tras reconstrucción/recreación solicitadas, el usuario reporta curl_version()[brotli_version] = brotli/1.2.1 tanto en app como en worker. Se acredita la biblioteca efectiva en ambos procesos CLI del servicio. La consulta apk info -v brotli-libs en Nginx devolvió descripción, no revisión exacta; falta extraerla del listado apk info -v completo. También falta repetir suite/integración sobre las nuevas imágenes y completar los demás controles de seguridad.

## Remediación Brotli verificada y regresiones correctas

El usuario reporta brotli-libs-1.2.1-r0 en Nginx. Junto a brotli/1.2.1 observado en app/worker, cumple el mínimo Alpine acreditado para CVE-2026-111497 en estos tres servicios. Confirma composer-install-check.sh sin errores y verify.sh con PASS de entrega exacta y PASS global de preparación repetida, reinicio persistente y control negativo con worker detenido después de reconstruir/recrear.

No se repiten pruebas adicionales de esta corrección sin nuevo riesgo. El bloque 6.2 conserva pendiente su prueba negativa automatizada de política; no se cierra seguridad integral sin CA, escaneo y SBOM.

## Confianza TLS revisada por SPKI

El usuario reporta 119 archivos y 118 certificados únicos inspeccionados por tls-trust-review.php en app, sin claves SPKI de las tres jerarquías del aviso AWS. Composer config cafile devolvió salida vacía: no hay cafile explícito en esa consulta. Esto no prueba todavía el almacén autodetectado de Composer, ausencia de todos los overrides, ejecución del worker ni rechazo de cadenas reales. No se retiran certificados por coincidencias de nombre. Tareas 6.3/6.4 siguen pendientes de cerrar esas comprobaciones.

## Corrección del diagnóstico de CA Composer

El helper que asumía un vendor/autoload.php dentro del PHAR falló antes de revisar confianza. No es evidencia de un problema TLS. Se sustituye por ejecución del binario instalado con diagnose -vvv, capturando salida en archivo privado temporal y mostrando únicamente el CA file validado, su hash y comparación con PHP/cURL. No se publica el diagnóstico completo ni se interpreta el exit code global como fallo exclusivo de CA. Pendiente de repetir en el host.

## Worker TLS y herramienta de escaneo disponibles

El usuario reporta worker con 119 archivos/118 certificados únicos, sin claves SPKI afectadas según tls-trust-review.php. Reporta Docker Scout v1.24.0 instalado en host. No se ha instalado una herramienta descargada automáticamente. Identidad/procedencia de esa distribución y bases de advisories requieren evidencia adicional; la versión sola no certifica integridad. Se prepara scan-images.sh para generar SPDX y SARIF de las imágenes realmente ejecutadas, usando IDs locales y reportes privados excluidos de Git. Composer CA continúa pendiente de repetir tras corregir el helper.

## Composer CA confirmada; primer escaneo Scout incompleto

El helper corregido reporta bundle Composer /etc/ssl/certs/ca-certificates.crt, hash 5961c0a3c65459931c023e0075c09733f09aea4ca439cb85bc4a08bb4f3f568b y coincidencia con PHP/cURL. diagnose_exit=2 corresponde al resultado global, no identifica una incidencia CA.

Scout v1.24.0 (go1.26.3 darwin/arm64, commit b1c9331b2166aef7ec690aa16fd655b8798ea4c6) generó app.spdx.json e indexó 75 paquetes de la imagen ejecutada sha256:08e3c43bb60f5a068a0c03a6a390a3b37147f3574ade4583c5cf71cb63f9cf18. El log sincronizado indica que cves requiere autenticación Docker; no produjo SARIF. Esto no es un escaneo limpio ni prueba de vulnerabilidades.

Se corrige scan-images.sh para calcular huellas solo de archivos generados y tratar SARIF ausente como control inconcluso, continuando hasta el resumen de todos los servicios. La autenticación debe hacerla el usuario en su host; no se solicitan ni almacenan sus tokens en el repositorio.

## Escaneo completo de imágenes recibido

Scout v1.24.0 generó SBOM SPDX y SARIF para app, nginx, redis y mailpit en artifacts-security.jkfwM6yN; código global 2 por advisories, no fallo de autenticación. Se leyó el contenido sincronizado, no solo el resumen de consola. Resumen por paquete/CVE en docs/development-scan-review.json. No se crean exclusiones ni se marca el escaneo limpio.

App/Nginx reportan cURL 8.22.0-r0 (corrección 8.23.0-r0), musl 1.2.6-r3 (corrección 1.2.6-r6) y libxml2 2.13.9-r2 con varios avisos, incluido CVE-2026-86140 alto sin corrección indicada por Scout. App además reporta unzip/tar; debe contrastarse CVE-2008-0888 con rango upstream y revisión Alpine, no asumir aplicabilidad por nombre. Redis reporta un aviso medio BusyBox sin corrección indicada. Mailpit reporta dependencias Go; la pseudoversión gomarkdown de 2026 requiere contrastar el advisory de 2023 antes de concluir afectación. La corrección upstream cURL 8.23.0 se verificó en https://curl.se/docs/CVE-2026-92392.html; demás rangos permanecen como datos del escáner hasta evaluación de proveedor.

Se prepara alpine-findings-check.php para consultar secfixes relevantes de la rama real del contenedor. No se cambian versiones ABI de libxml2 ni se mezclan repositorios de otra distribución sin evidencia.

## Secfixes Alpine contrastados con Scout

La consulta aportada por el usuario a Alpine v3.24 main.json confirma cURL 8.23.0-r0 corregido para CVE-2026-92392 y Brotli 1.2.1-r0 para CVE-2026-111497. Se exigen curl/libcurl >=8.23.0-r0 en PHP/Nginx. La misma fuente declara musl 1.2.6-r3 corregido para CVE-2026-107891, que coincide con la revisión instalada: discrepancia con Scout (<r6). Se registra como discrepancia de metadatos, sin actualizar innecesariamente musl ni suprimir el advisory.

Libxml2, unzip, tar y BusyBox no incluyen secfixes para los avisos consultados; no se concluye ausencia de vulnerabilidad. Libxml2 sigue siendo bloqueante hasta acreditar parche/backport o aplicar corrección compatible. Pendientes reconstrucción/recreación y versión efectiva cURL antes de repetir escaneo.

## cURL actualizado — confirmación parcial

El usuario reporta curl_version()[version] = 8.23.0 en app después de reconstrucción/recreación. El filtro del listado apk de Nginx no mostró líneas, sin detalle de error. No se acredita todavía la versión efectiva del servicio Nginx ni worker. Se solicita identidad de imagen y versión cURL de Nginx antes de concluir.

## cURL Nginx confirmado

El usuario corrige la ejecución del filtro y reporta curl-8.23.0-r0 y libcurl-8.23.0-r0 en Nginx. App ya reportó cURL 8.23.0. Se acredita la corrección observada de cURL en ambos servicios; worker comparte imagen PHP, aunque su versión efectiva debe quedar en el próximo inventario. La investigación continúa en libxml2 y demás hallazgos sin corrección acreditada.

## Backport libxml2 autorizado — pendiente de ejecución

Se prepara `docker/libxml2/` con APKBUILD, parche CVE-2026-86140 basado en commit oficial d1686f91dbda141a752200419d35639fd6b38340 y prueba de límites con control negativo del código original. Conserva tarball 2.13.9 y ambos parches Alpine para CVE-2026-6732 con SHA-512 verificados. APK local 2.13.9-r2000 firmado con clave efímera del builder; no se exporta clave privada ni se usa allow-untrusted. PHP y Nginx incorporan el mismo paquete manteniendo libxml2.so.2. Contexto adicional limitado a docker/libxml2; el backend mantiene su contexto y exclusiones.

La firma local y el checksum no acreditan identidad upstream: las fuentes y huellas se revisan por separado. La revisión local requiere mantenimiento y retirada explícita cuando exista paquete oficial; no se presupone actualización automática por apk upgrade. Ver instrucciones, límites y retirada en docker/libxml2/README.md. Construcción, pruebas upstream/Alpine, regresión negativa/positiva, carga efectiva en PHP/Nginx, nuevos IDs y escaneo todavía pendientes. No se marca CVE ni tarea de seguridad resuelta con archivos preparados.

## Backport: permisos de entradas del builder

La primera construcción ejecutó las pruebas upstream, pero falló al leer /backport/bounds-check.py como builder: los archivos y carpeta del contexto conservaban modos privados 0600/0700 del host. Se normalizan explícitamente el directorio a 0755 y los tres archivos públicos necesarios a 0644 antes de cambiar a USER builder, en PHP, Nginx y Dockerfile independiente. La compilación y las pruebas siguen sin privilegios. Pendiente reconstrucción en host; no se acredita todavía el parche.

## Backport: firma durante creación del índice

El segundo build ARM64 aportado verificó SHA-512 de fuentes/parches, compiló y generó los APK. Pasó la regresión negativa/positiva de límites y las suites disponibles (3806 casos runtest, 49 recursión, 324154 API y 2158 entradas fuzz). XML Conformance externo no estuvo disponible; no se contabiliza como ejecutado. Falló la creación del índice por UNTRUSTED signature: faltaba registrar en el builder la clave pública recién generada. Se separa generación de clave como builder, instalación de su parte pública como root y abuild como builder en los tres Dockerfiles. No se desactiva validación de firmas. Pendientes construcción completa, instalación y pruebas de los consumidores.

## Backport: nombres de clave iniciados con guion

La construcción avanzó hasta exportar el APK, pero sha256sum interpretó como opción el nombre de clave pública generado por abuild-keygen, iniciado por guion. Se usan patrones ./*.apk y ./*.pub al generar el manifiesto, y ./*.rsa.pub al instalar claves en consumidores. Las entradas del manifiesto quedan relativas al directorio de verificación. Pendiente repetir construcción; no se considera instalación ni ejecución acreditada.

## Backport libxml2 instalado — evidencia del host

El usuario confirmó construcción completa de app/Nginx y `apk list --installed libxml2` con `libxml2-2.13.9-r2000 aarch64 [installed]` en app, worker y nginx, en ese orden. `nginx -t` aprobó. Suite Laravel en PHP 8.4.27: 20 pruebas, 62 aserciones, todas aprobadas. El build previo mostró PASS para el control negativo del original y positivo del parche. Se acredita instalación del backport y regresión funcional; todavía no se acredita cierre global de vulnerabilidades. Pendientes integración aislada tras esta reconstrucción, nuevos IDs/SBOM/SARIF y evaluación individual de hallazgos restantes, además de controles TLS pendientes.

## Escaneo posterior al backport e integración

El usuario reportó verify.sh completo sin errores tras instalar el backport. Se leyeron SPDX/SARIF en artifacts-security.2hxBg1xH, Scout 1.24.0, estado global 2. App: 5 avisos (1 alto unzip, 3 medios tar, 1 sin clasificación musl); Nginx: 1 musl sin clasificación; Redis: 1 medio BusyBox; Mailpit: 5 (1 alto gomarkdown, 4 sin clasificación). IDs y hashes de reportes en docs/development-scan-review.json. SPDX confirma cURL/libcurl 8.23.0-r0, Brotli 1.2.1-r0 y libxml2 2.13.9-r2000 en app/Nginx.

Los rangos Scout anteriores de libxml2 terminaban en <=2.13.9-r2; r2000 queda fuera incluso para avisos que este backport NO corrige. Su desaparición no demuestra remediación de esos avisos. Se conservan individualmente los diez avisos libxml2 restantes de cada imagen en el resumen para evaluación; solo CVE-2026-86140 tiene parche y regresión acreditados. Ninguna exclusión global aplicada. Pendientes evaluación unzip/gomarkdown, demás hallazgos y controles TLS/suministro. El advisory upstream gomarkdown GHSA-m9xq-6h2j-65r2 identifica fix 14b1601; falta acreditar su inclusión en el commit de la pseudoversión instalada, no basta su fecha posterior.

## Evaluación individual de avisos altos: unzip y gomarkdown

CVE-2008-0888: el registro CNA Red Hat del programa CVE declara info-zip/unzip afectado en versiones <6.0 y defaultStatus unaffected. SPDX de app sha256:7cea7546d993f42742334b632e2d349285779918cc3c08923fbb68fa8475e839 contiene 6.0-r16. Decisión: no afectado por el rango oficial de este aviso; discrepancia con Scout <=6.0-r16, sin supresión global ni afirmación sobre otros avisos unzip. Fuente: https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/2008/0xxx/CVE-2008-0888.json (consultada 2026-10-28). Revalidar con cambios y antes del cierre.

CVE-2023-42821: el commit oficial 14b16010c2ee7ff33a940a541d993bd043a88940 añade un límite antes de indexar citation[j]. La consulta del commit instalado 6c848fa1b723 no fue accesible desde esta herramienta; permanece pendiente, sin inferir corrección por la fecha de la pseudoversión. Fuente: https://github.com/gomarkdown/markdown/commit/14b16010c2ee7ff33a940a541d993bd043a88940.

Se añade `sh scripts/dev/review-high-findings.sh`: consulta datos oficiales mediante TLS validado dentro de app, comprueba unzip instalado, vincula la revisión de gomarkdown a la imagen Mailpit del SPDX revisado y comprueba ancestro del fix más presencia del control de límites en el código de ese commit. No ejecuta código descargado ni instala Go. Emite hashes de fuentes y JSON; HTTP fallido, cambio de versión/imagen o estructura inesperada salen con código 1/inconcluso. No modifica reportes ni configura exclusiones. Pendiente ejecución PHP en host; aquí solo se comprobó sintaxis shell y revisión estática.

## Resultado de revisión de los dos avisos altos

Ejecución reportada 2026-10-28T21:44:46+00:00: unzip 6.0-r16 no afectado por rango CNA <6.0; gomarkdown commit 6c848fa1b723738de008ec0360fbc38befbe513d confirma ancestro del arreglo 14b16010c2ee7ff33a940a541d993bd043a88940 y guarda de límites presente. Evidencias, alcance y cuatro huellas SHA-256 recibidas en docs/development-high-findings-review.json. Ambos avisos altos actuales quedan evaluados individualmente: discrepancia de rango en unzip y parche ya incluido en gomarkdown. No se modifica la severidad original ni los SARIF y no se añaden exclusiones globales. No acredita cierre global: otros avisos, TLS y suministro siguen pendientes.

Siguiente revisión: los registros CNA oficiales de CVE-2026-78661/97045 confirman corrección x/net/html en 0.62.0; Mailpit v1.32.0 declara 0.61.0 y sigue siendo el último release consultado, por lo que no se supone que haya actualización oficial disponible. GO-2026-5932 corresponde específicamente a x/crypto/openpgp; la presencia del módulo x/crypto no demuestra uso de ese paquete. Tar conserva tres hallazgos medios; no se declara no aplicable por uso solo local. BusyBox 60876 afecta wget, con rango impreciso en CNA; falta revisar parche de la revisión Alpine instalada. Fuentes: https://raw.githubusercontent.com/axllent/mailpit/v1.32.0/go.mod ; https://vuln.go.dev/ID/GO-2026-5932.json ; registros oficiales en CVEProject/cvelistV5.

## Compleción de tooling y aceptación — pendiente host

Se actualiza Make con setup/up/down/build/install/key/migrate/test/verify/no-dev-check/
security/security-policy-test/acceptance/queue/logs/shell. .PHONY corregido; queue
consulta el consumidor Compose; key conserva la clave existente mediante helper;
install utiliza contenedor puntual y auditoría previa. Setup muestra bindings reales
y rechaza BACKEND_PATH en shell para evitar preparar un backend distinto.

Se añaden security-check.sh, política compartida/fixtures, consulta Composer segura,
aceptación desde copia limpia y controles operativos adicionales a verify. sh -n
aprobó scripts modificados. No se ha ejecutado PHP, Docker ni Make desde esta sesión
porque no están instalados en este entorno; el intento make -n indicó command not
found. No se marcan tareas de ejecución como completas por generación de archivos.
Evidencias ya recibidas permanecen válidas para sus revisiones anteriores; los
controles nuevos requieren ejecución en el host antes de acreditarlos.

## Primera ejecución unificada de seguridad — resultado revisado

Reportes artifacts-security.8oTeUKzH, 2026-10-28: app/worker registran Brotli
`brotli/1.2.1`; el validador original solo admitía el formato numérico y causó dos
rechazos falsos. Se corrige el parser para admitir ambos formatos exactos y rechazar
versiones antiguas o contenido adicional, con regresiones. Pendiente ejecutar las
regresiones PHP corregidas en host.

Se leyeron los reportes TLS completos: OpenSSL/cURL/PHP streams aceptaron el control
repo.packagist.org y rechazaron ambas rutas DV G3B (emSign y AAA) en app y worker,
con huellas fijadas, firma de hoja, hostname, fechas y fallo de confianza comprobados.
Se incorporan explícitamente al evaluador y se añaden fixtures para otro bundle,
reporte antiguo y resultado inconcluso. Límites: dos rutas, no cada certificado
individual del incidente. La tarea pide cadena afectada/control por cliente; resta
acreditar transporte Composer con el mismo almacén efectivo.

Scout pasó codesign --verify --strict: firma observada Docker Inc, TeamIdentifier
9BNSXJN65R; binario SHA-256
11c19e990ecc3ee9a3159208b407e143d6beb601dea42ff5745e9cf7d1a22bfe. La firma e
identidad observadas se registran sin afirmar aún cierre de procedencia/frescura.
Huellas de reportes y resultados en docs/development-security-check-review.json.

Se añade composer-tls-check.php: configura un repositorio temporal con el bundle
revisado e invoca Composer instalado para consultar metadatos (sin instalar paquetes,
plugins ni scripts, sin credenciales). Exige éxito del control válido y curl error 60
por emisor no confiable en ambas rutas afectadas; timeout/DNS/HTTP no cuentan como
rechazo. Pendiente ejecución. security-reevaluate.sh permite reutilizar reportes,
probar política corregida y añadir solo estas pruebas faltantes verificando que la
imagen app/worker coincide con la del reporte. No vuelve a escanear imágenes.

## Composer TLS: primer resultado inconcluso

La reevaluación del host aprobó 21 fixtures de política y todos los controles runtime,
CA, FPM, auditoría y evaluación de imágenes. Composer aceptó el control válido y
salió con código 100 en las dos rutas afectadas, en app y worker. El código de salida
por sí solo no acredita rechazo por confianza. La versión inicial del helper no
conservó diagnóstico suficiente para distinguir la causa.

Se desactiva formato ANSI, normalizan saltos/espacios del diagnóstico y se comprueban
ambos canales. Se conserva la exigencia de curl error 60 y emisor no confiable; no
se acepta cualquier salida 100. Ante resultado inconcluso se emite un extracto
limitado del proceso aislado (sin credenciales ni configuración del proyecto), con
ruta temporal sustituida y dos indicadores específicos de error. Versión de prueba
2; reevaluate vuelve a ejecutar solo este helper si encuentra versión anterior y
conserva los JSON anteriores como *.previous.json. Pendiente nueva ejecución; no
se declara todavía cumplida la prueba Composer.

## Composer TLS: causa del falso inconcluso identificada

Los JSON versión 2 recibidos contienen éxito del control válido y rechazo emSign
confirmado. En la ruta AAA, el diagnóstico exacto contiene curl error 60 y OpenSSL
verify result: unable to get l ocal issuer certificate (20). Symfony Console partió
una palabra por el ancho de salida. El detector anterior normalizaba espacios entre
palabras pero no reconocía este corte interno. Se compara ahora la frase sin
espacios, manteniendo exigencia de curl error 60 y causa de emisor no confiable.
El evaluador reinterpreta el diagnóstico guardado sin modificar los originales.
Regresiones añadidas para el texto real, DNS, hostname incorrecto, timeout y salida
100 aislada. Pendiente ejecución de las regresiones corregidas en el host; las
conexiones ya observadas muestran rechazo de ambas rutas y aceptación del control
en los cuatro clientes, incluyendo Composer, de app y worker. No se amplía la
cobertura declarada más allá de las dos rutas ensayadas.

## Controles runtime/TLS/política acreditados en host

La última reevaluación recibida aprueba los 26 fixtures, app/worker runtime,
inventario de confianza, OpenSSL/cURL/streams en control válido y dos rutas afectadas,
transporte Composer con el mismo bundle, Composer audit/CA/TLS/plugins, FPM y cuatro
evaluaciones de imagen. Se completan tareas 6.2, 6.3, 6.4 y 6.7 de OpenSpec con esa
evidencia; el comando global sigue rechazando aceptación por otros pendientes.
No se afirma cobertura de cada una de las 16 intermedias individualmente.

Se concreta ahora el criterio de procedencia del escáner: Scout 1.24.0/commit fijado,
binario SHA-256 revisado, firma estricta válida de Apple con identidad Docker Inc y
TeamIdentifier 9BNSXJN65R, ubicado en Docker.app. Docker documenta que Scout se
incluye con Desktop: https://docs.docker.com/scout/install/ . Se exige el binario
observado; un cambio requiere nueva revisión, no aceptación automática de cualquier
firma. No se ofrece evidencia SLSA reproducible del binario, y no se presume necesaria
una actualización de CLI solo por disponer de un release posterior.

Frescura: consulta Scout completada en ventana de 24 horas y presencia de los avisos
Go CVE-2026-78661/97045 publicados 2026-10-27. El servicio de Docker agrega fuentes
actualizadas continuamente según https://docs.docker.com/scout/deep-dive/advisory-db-sources/ .
Límite explícito: el SARIF no expone una versión inmutable ni last-updated de la base;
los controles prueban edad mínima observada y fecha de consulta, no exhaustividad de
advisories hasta ese instante. Se conserva contraste con secdb/CNA y rechazo de
consultas fallidas. No se interpreta ausencia de registros como ausencia de riesgo.
Pruebas nuevas de identidad/hash/frescura del evaluador pendientes de ejecución en
host; tarea 6.6 todavía no se marca completa con código sin ejecutar.

## Reconstrucción candidata Mailpit con x/net corregido

Se registró Go 1.26.10-alpine3.24 por índice
sha256:a2012b4a1d1f9f7e9f44e0d39b805a901e856b1dd80b4e6ee4338db547050308,
con manifiestos ARM64/AMD64 recibidos del host. Se prepara docker/mailpit/Dockerfile
y build.sh; etiqueta candidata notification-hub-mailpit:candidate, sin activar aún
la imagen en Compose. Código Mailpit v1.32.0 autenticado mediante proxy/sumdb Go;
actualización directa x/net 0.62.0 y compilación -mod=readonly. La interfaz se
construye desde package-lock con npm ci sin hooks, audit bloqueante alto/crítico y
scripts upstream revisados. Tests Go/frontend, inventario de módulos del binario,
ausencia de paquetes OpenPGP y licencias forman parte del build. No se ejecutan
pruebas de esta imagen antes de construirla; no se dan por pasadas.

Go, Node/npm/git y dependencias de compilación se usan solo en builder, sin
credenciales/socket del host; no se añaden al backend ni al host. Versiones y
lockfiles resultantes se exportan como evidencia de imagen. No se instala vendor
Laravel ni se modifica la configuración existente. Si audit/tests/locks fallan,
revisar el error sin omitir gates. Pendientes build nativo, inspección de la interfaz,
entrega aislada y nuevo SBOM/escaneo antes de migrar Compose. Mantenimiento temporal
hasta release upstream corregido; ver docker/mailpit/README.md.

## Mailpit candidato construido — 2026-10-29

El host construyó notification-hub-mailpit:candidate, ID
sha256:1612ed48a57c21dbc83489911eb22d6c450864a22baf2485036113d2d85f0f52,
linux/arm64. Go 1.26.10, Node 24.22.1, npm 11.12.1. La verificación de módulos
aprobó; npm audit superó el umbral configurado de alto/crítico, frontend 38/38
pruebas y compilación de assets aprobadas; las suites Go aprobaron. El build
comprobó x/net 0.62.0 en los metadatos del binario y ausencia de OpenPGP en el grafo.
No se acredita todavía arranque, interfaz o entrega real de la candidata.

La herramienta de licencias emitió avisos sobre código ensamblador que no puede
inspeccionar para dependencias adicionales. No son errores de las pruebas, pero su
informe no debe presentarse como análisis exhaustivo de todo código nativo. Durante
esa herramienta se descargó x/net 0.43.0 como dependencia de go-licenses v2.0.1;
esas herramientas/cachés no pasan al runtime. La afirmación x/net 0.62.0 se limita
al binario Mailpit verificado, no a todas las dependencias temporales del builder.

Se añade verify-mailpit-candidate.sh, fijado al ID recibido. Primero verifica
huellas de evidencias y del binario, luego ejecuta verify con MAILPIT_IMAGE limitado
al proceso hijo y escanea solo la imagen candidata. Compose conserva por defecto
la imagen upstream anterior. Se añade comprobación de HTML y recursos JS/CSS locales
servidos por Mailpit (sin ejecutar código en navegador); la prueba no equivale a
una sesión visual de navegador. Los proyectos/datos de prueba son separados y el
Mailpit del desarrollo no se sustituye. Los reportes candidatos quedan en
artifacts-security.*; salida 2 significa hallazgos por revisar, no fallo funcional.
Pendiente ejecución del host antes de activar la imagen en Compose.

## Mailpit candidato: verificación funcional e inspección de escaneo

El host verificó hashes de evidencias y /mailpit; interfaz y dos recursos JS/CSS,
entrega HTTP/Redis/worker/Mailpit, persistencia, puertos solo loopback, configuración
de tiempos, parada worker exit 0, prueba negativa sin worker, detección de FPM
perdido y recuperación: PASS. Limpieza de proyecto/volumen temporal aprobada.
Se completan tareas operativas 3.2, 3.4, 3.5 y 7.2 con esta evidencia ARM64; AMD64
permanece sin ejecución nativa, con manifiesto revisado.

Reportes artifacts-security.zL2AQno3: cinco reglas, incluidas las mismas dos CVE de
x/net 0.61.0. SPDX registra simultáneamente x/net 0.61.0 y 0.62.0 como evidentes en
/mailpit, cuya huella observada es
27fbc5717fc3e8cd37e648adf88a1eed62cb5d74224c76f4585907668070b07b. La comprobación de
metadatos Go del build aprobó 0.62.0; la imagen tiene una capa heredada con el binario
original sustituido. La contribución exacta de capas/caché/inventario a esa doble
atribución sigue pendiente; no se declara remediación por ausencia o se suprime CVE.
Resumen y huellas: docs/development-mailpit-candidate-review.json.

Se prepara empaquetado final FROM scratch copiando el filesystem fusionado de la
etapa runtime: conserva bibliotecas, CA, symlinks y archivos Alpine del runtime,
restablece ENTRYPOINT, puertos, salud, usuario y etiquetas explícitos. El binario
antiguo no se distribuye como capa del resultado. No es una migración a runtime
vacío sin bibliotecas. La etapa Go permanece igual y puede reutilizar caché. Nueva
verificación exige la misma huella de binario ya ensayada, resuelve la etiqueta local
una sola vez a ID y usa ese ID; exporta módulos del binario y metadatos de fuente
para diagnóstico posterior. Pendientes nuevo build, verificación y scan. Compose
sigue en upstream salvo override limitado al proceso de prueba.
