# Design

## Context

Ver `proposal.md` para motivación y alcance. La única aplicación está en `project_back/`; Dockerfile en `docker/php/` y Compose/Make en la raíz. La reorganización está archivada en `../archive/2026-10-28-project-roadmap-development-environment/`.

Hallazgos estáticos que condicionan este cambio:

- Laravel está fijado en el lockfile a 12.64.0; Composer declara PHP `^8.2`. El lockfile debe validarse contra el manifiesto antes de instalar; `vendor/` está vacío.
- `bootstrap/app.php` usa el builder de Laravel 12, pero no existe `bootstrap/providers.php`. `config/app.php` declara solo AppServiceProvider como lista de providers, por lo que debe reconciliarse con los defaults del framework.
- El bootstrap añade middleware de Sanctum y `statefulApi()` sin dependencia Sanctum declarada. Se retirará esa configuración accidental; autenticación pertenece a otra fase.
- Las pruebas y README esperan `/v1/notifications/dispatch`; el montaje de rutas API no fija explícitamente el prefijo vacío. Se verificará y fijará el contrato esperado, sin cambiar las rutas versionadas del archivo API.
- SQLite es el almacenamiento local seleccionado; la imagen solo declara PDO MySQL. Hay que verificar PDO SQLite efectivo, no inferir su ausencia únicamente del Dockerfile.
- Compose carece de worker, healthchecks y volumen Redis. MailHog fuerza amd64; `make install` depende de un app ya iniciado. Redis y SMTP se publican al host.
- `retry_after` Redis vale 90 segundos y el job configura tres intentos con backoff. Las pruebas usan SQLite en memoria, caché array y cola sync/fake.

No se ha ejecutado Docker ni PHP durante la planificación. Las versiones de imágenes y sus digests deben resolverse contra los registros al implementar y guardarse antes de dar por terminada la construcción.

## Goals / Non-Goals

**Goals:** una preparación repetible desde una copia limpia con Docker Compose v2; procesos separados para FPM y worker; dependencias y permisos verificables; criterios de salida reproducibles y documentados.

**Non-Goals:** cambio de versión mayor Laravel, reestructuración del dominio, autenticación de API, nueva semántica de idempotencia, canales adicionales, frontend, CI o imagen productiva definitiva. No se cambian las respuestas de negocio esperadas por las pruebas existentes.

## Decisions

### 1. Imagen PHP de desarrollo compartida

Usar PHP 8.4 FPM con parche mínimo 8.4.27 y revisión de avisos posteriores antes de fijar el digest, sobre una base oficial, Composer 2 y extensión Redis con versiones explícitas. Registrar versión de parche y digest de manifiesto de PHP, Composer, Nginx, Redis y Mailpit en `docs/development.md`; no usar `latest`. Verificar manifiestos de amd64/arm64 y probar nativamente al menos la arquitectura del equipo; la otra solo puede declararse ejecutada con evidencia.

Separar etapa de compilación de extensiones de la etapa de desarrollo; conservar únicamente bibliotecas de ejecución y Composer en esta última. Verificar extensiones desde `composer check-platform-reqs` y `php -m`, con PDO SQLite, Redis y pcntl explícitos. Instalar desde lockfile y evitar actualizaciones masivas. Si el hash del lockfile no coincide, reconciliarlo con el menor cambio justificado y registrar el diff y `composer validate --strict`.

Alternativa: conservar PHP 8.2 sin revisar el runtime. Se elige 8.4 para establecer una base nueva sin cambiar Laravel; una incompatibilidad real debe resolverse y documentarse antes de alterar esta decisión.

### 2. Arranque Laravel 12 y contrato existente

Crear `bootstrap/providers.php` con AppServiceProvider y alinear la configuración heredada de providers con los defaults Laravel 12, sin perder registro ni descubrimiento de paquetes. Retirar referencias Sanctum/stateful no utilizadas. Hacer explícito el prefijo API vacío para conservar `/v1/...`; verificar mediante `route:list` y las pruebas existentes. La salud seguirá en `/up`.

Las correcciones de esta fase se limitan a dependencias, arranque, configuración y contrato de rutas existente. Un fallo de lógica de negocio debe registrarse y resolverse como cambio adicional; no eliminar ni debilitar pruebas para simular un cierre.

### 3. Compose con responsabilidades separadas

Servicios: `app`, `nginx`, `worker`, `redis`, `mailpit`. App y worker comparten imagen, configuración Laravel y montaje del backend en `/var/www`. Nginx monta el backend y su configuración en solo lectura; sirve exclusivamente `/var/www/public` y envía FastCGI a app:9000.

HTTP en `127.0.0.1:8080` y visor Mailpit en `127.0.0.1:8025`, configurables mediante variables de Compose documentadas. No publicar FPM, Redis ni SMTP. Quitar `container_name`, `version` y plataforma forzada. Usar el nombre de proyecto Compose para aislar instancias; probar una segunda instancia con puertos alternativos sin colisiones.

Mailpit recibirá SMTP en `mailpit:1025`; sin relays externos, credenciales reales ni envíos Slack/Telegram en la verificación local. `.env.example` reflejará los defaults. Para `.env` existente con `MAIL_HOST=mailhog`, fallar con instrucción de migración o instruir actualización explícita; no reescribir configuración existente silenciosamente.

### 4. Persistencia y permisos

Redis tendrá volumen nombrado y AOF activado. SQLite usará la ruta absoluta interna `/var/www/database/database.sqlite`, conservada en el backend montado y compartida por app/worker. Crear el archivo solo cuando falte; si DB_DATABASE existente es relativo, conservarlo y explicar el ajuste necesario sin cambiar otros valores. El archivo y su directorio deben ser escribibles por el usuario PHP. No añadir MySQL.

Parametrizar UID/GID de desarrollo cuando sea necesario para Linux; no ejecutar Artisan/Composer como root ni usar permisos globales 777. Crear únicamente las carpetas necesarias de `storage/`, `bootstrap/cache/` y SQLite con permisos acotados. Git y contexto Docker seguirán excluyendo secretos, cachés y base local. Los bind mounts pueden ocultar dependencias de la imagen: instalar Composer en el backend montado con un contenedor puntual, para que app y worker vean el mismo vendor.

Alternativa de SQLite en volumen independiente: añade complejidad de montaje y no es necesaria para este entorno local. La base de producción queda fuera de alcance.

### 5. Preparación idempotente antes de servicios dependientes

Añadir `scripts/dev/setup.sh` y `make setup`, con operaciones Docker/Compose y shell POSIX, sin PHP/Composer en host. Orden:

1. Verificar Docker/Compose y preparar carpetas y `.env` solo si faltan; no imprimir secretos.
2. Validar configuración Compose y construir la imagen de desarrollo.
3. Ejecutar `composer install` con contenedor puntual, sin depender de FPM o Redis ya iniciados. Evitar que scripts de Composer consulten servicios todavía no disponibles.
4. Preparar SQLite y generar APP_KEY solo si el valor está vacío; nunca regenerarla al reiniciar.
5. Iniciar Redis/Mailpit, esperar disponibilidad, ejecutar migraciones pendientes mediante comando explícito de preparación y levantar app, worker y Nginx.
6. Esperar salud y mostrar URLs locales y comandos de diagnóstico.

`make up` y el arranque normal no migran ni regeneran datos o claves. `make down` preserva volúmenes. El borrado de datos exige un comando manual documentado separado; no se invoca en setup ni pruebas habituales. Documentar los comandos Compose equivalentes y límites de overrides existentes.

### 6. Disponibilidad y ciclo de vida

Redis: PING. Mailpit: comprobación HTTP de salud que soporte la versión fijada. App: prueba FastCGI real de `/up` con herramientas presentes en la imagen, no curl a un puerto FPM. Nginx: HTTP `/up` comprobando el recorrido hasta PHP. `depends_on` con `service_healthy` donde corresponda, sin ciclos.

Worker: proceso de cola en primer plano con manejo de señales. Fijar timeout en 60 segundos, Redis `retry_after` en 90 y `stop_grace_period` en al menos 75; las entregas SMTP de prueba deben tener timeout inferior al del worker. Una marca de proceso vivo no demuestra consumo: la prueba integral demuestra procesamiento real. La preparación debe fallar con un diagnóstico acotado si una dependencia no está lista en el plazo definido; no esperar indefinidamente.

### 7. Verificación y documentación por entrega

Mantener `php artisan test` aislado con SQLite en memoria, caché array, mail array y queue sync/fake; conservar pruebas de validación, extensibilidad y duplicados. Añadir regresiones de bootstrap/rutas donde las existentes no cubran esos fallos.

Añadir `scripts/dev/verify.sh` para un entorno Compose de verificación independiente: HTTP y SMTP solo locales, SQLite y Redis separados del desarrollo, clave de idempotencia y destinatario únicos. No vaciar cachés, colas o correo del equipo. Comprobar 202, recepción en Mailpit y cola procesada dentro de un plazo; terminar con código no cero y diagnóstico si falla. No limitarse a buscar mensajes antiguos ni a observar que el worker está iniciado.

Verificar reinicio con un marcador de prueba y preservación de SQLite/Redis, repetir setup comparando configuración/APP_KEY sin mostrar su valor, probar puertos alternativos y comprobar ausencia de secretos en imagen/contexto. El entorno aislado es efímero y su limpieza no debe tocar recursos de desarrollo.

Actualizar README y `docs/development.md` junto a cada grupo; usar `docs/development-verification.md` para registrar comandos, versiones, plataforma y resultados realmente obtenidos. No incluir `.env`, tokens ni payloads sensibles. La fase 1 del roadmap solo se cierra cuando todos sus criterios tienen evidencia.

### 8. Parches de octubre de 2026 y mantenimiento continuo

Fecha de corte inicial: 2026-10-28 UTC; repetir consultas al implementar. PHP publicó el 21 de octubre correcciones para CVE-2026-109618 y CVE-2026-109619; el mínimo en la rama elegida es 8.4.27. El aviso del 23 recomienda `session.upload_progress.enabled=0` para el caso residual CVE-2026-115560: aplicarlo antes del procesamiento de peticiones, verificar CLI/FPM y reiniciar procesos. No usar mitigaciones parciales como sustituto del parche. Revisar también Brotli CVE-2026-111497 en bibliotecas efectivas; una reconstrucción Windows no prueba el parche del contenedor Linux.

Crear `docs/development-security.md` con una matriz por componente: versión instalada, CVE/advisory, fecha, alcance, versión corregida o backport acreditado, aplicabilidad y evidencia. Incluir PHP CLI/FPM y extensiones, Composer, OpenSSL, cURL, Brotli si está presente, paquetes de distribución y todos los servicios Compose. Node/Python no son requisitos actuales; si aparecen como herramientas transitivas, inventariarlos y revisar sus avisos oficiales, o documentar ausencia sin instalarlos. Revisar fuentes del proveedor y de la distribución; el comparador de versiones upstream no basta para paquetes con backports.

Repetir consultas en cada actualización de dependencias/imágenes y antes de cerrar la fase; revisión rutinaria al menos semanal y revisión inmediata tras aviso crítico aplicable. En un día hábil registrar triage y contención; un parche crítico aplicable bloquea la aceptación hasta reconstruir, recrear servicios y verificar el binario efectivo. Para otros hallazgos altos/críticos, remediar o demostrar no aplicabilidad con evidencia individual; no permitir supresiones globales ni aceptar un resultado sin acceso a advisories. Esta política es local; su automatización CI se entrega en fase 2.

### 9. eMudhra/emSign y confianza TLS efectiva

Mozilla y Google comunicaron el compromiso de ocho intermedias eMudhra y medidas de desconfianza de emSign ECC Root CA - G3. Registrar en la matriz el alcance por huella DER/SHA-256 y SPKI a partir de fuentes oficiales y del proveedor de ca-certificates; no bloquear todas las CA de eMudhra por coincidencia de nombre. Revisar también certificados cruzados y rutas alternativas según las actualizaciones oficiales.

Identificar el almacén realmente usado por OpenSSL, cURL, PHP streams y Composer, incluidos `SSL_CERT_FILE`, `SSL_CERT_DIR`, `CURL_CA_BUNDLE`, `openssl.cafile`, `openssl.capath` y cafile de Composer. Las protecciones OneCRL/CRLSet de navegadores no se heredan automáticamente en esos clientes. Actualizar el paquete CA y reconstruir; si el proveedor no incluye la desconfianza requerida, aplicar una política reproducible con huellas oficiales exactas y revisión, sin volver a confiar en las claves comprometidas. No asumir que CRL/OCSP se comprueba por defecto.

La aceptación exige comprobar el bundle efectivo de app/worker y herramientas de descarga: una cadena afectada, vigente y cuya única causa de rechazo sea la política de confianza debe fallar, y una cadena válida de control debe pasar con hostname correcto. Usar certificados públicos y fixtures documentados, nunca claves privadas comprometidas ni infraestructura atacante. Acreditar el resultado con OpenSSL y los clientes PHP/cURL/Composer que utilicen ese almacén; la prueba genérica de certificado autofirmado solo cubre validación básica y no sustituye la del incidente. Si no hay fixture concluyente o hay rutas alternativas no resueltas, dejar el control pendiente.

Mantener validación de cadena y hostname; prohibir `verify=false`, `curl -k`, `disable-tls` y `secure-http=false`. Documentar migración de destinos legítimos con cadenas afectadas: reemitir bajo jerarquía confiable, sin excepciones TLS. Inventariar el host/Docker engine como prerrequisito externo para pull de imágenes: no afirmar que actualizar el bundle de una imagen actualiza al daemon o al host. No modificar sus almacenes automáticamente.

### 10. Cadena de suministro y controles locales

El incidente LiteLLM de octubre de 2026 se usa como caso de amenaza de artefactos publicados desde un origen suplantado; no hay evidencia de que LiteLLM sea dependencia de este backend. La revisión incluirá la composición real, sin añadir ese paquete. Un digest fija contenido, pero puede fijar contenido malicioso; una firma requiere comprobar identidad del publicador y origen autorizado.

Inventariar dependencias Composer de ejecución y desarrollo, paquetes del sistema, imágenes de servicios y herramientas de construcción/escaneo. Generar SBOM en formato CycloneDX o SPDX, asociada a imagen/digest/arquitectura y fecha, y reportes de `composer audit --locked --format=json` y escaneo de imágenes/paquetes con herramienta cuya versión, origen e integridad se verifiquen primero. Elegir y documentar el escáner antes de integrarlo; no instalarlo con curl-pipe-shell, etiquetas móviles o un binario de procedencia no comprobada. Guardar versión de base de vulnerabilidades y fecha; el escaneo no acredita ausencia de malware o vulnerabilidades desconocidas.

Instalar dependencias desde lockfile revisado en contenedor sin credenciales del host, SSH agent ni socket Docker; revisar cambios de repositorios, nombres/typosquatting, fuentes y dist references. Usar `--no-plugins --no-scripts` para descarga/auditoría inicial y permitir después únicamente plugins concretos y scripts Laravel revisados; auditar antes de ejecutar código de terceros. Esto debe integrarse en setup antes del paso Composer con scripts y complementa la tarea 2.1. Prohibir `allow-plugins=true` y revisar los plugins permitidos actualmente aunque no estén instalados.

Para descargas externas comprobar checksum desde canal autenticado independiente del artefacto y firmas/provenance cuando el publicador las ofrezca; verificar identidad, repositorio y digest esperados, no solo presencia de firma. Si no hay attestation publicada, registrar la limitación y verificar al menos origen oficial y digest/checksum; no afirmar cumplimiento SLSA por generar una SBOM. BuildKit secrets cuando sea imprescindible autenticarse; no ARG/ENV ni copia de tokens a capas. Revisar cachés y logs para evitar persistencia de credenciales.

Añadir `scripts/dev/security-check.sh` y `make security` con verificación de política PHP/CA, auditoría Composer, escaneo y códigos de salida. Debe poder ejecutarse sin secretos reales, fallar ante control inconcluso y probarse con fixtures de reporte deterministas más una ejecución real de advisories; no descargar ni ejecutar exploits ni paquetes comprometidos. Mantener pruebas negativas de versión vulnerable, reporte crítico, fallo de consulta, plugin no autorizado y certificado no confiable. Los reportes describen límites y riesgos residuales; no prometen invulnerabilidad.

### 11. Dependencias Laravel necesarias y limpieza verificable

Revisar todos los paquetes directos en require y require-dev antes de cerrar Composer y volver a generar el inventario/SBOM tras la limpieza. Documentar en docs/development-dependencies.md una decisión por paquete: uso real, dependencia transitiva, entorno necesario, conservar/eliminar/mover a desarrollo y evidencia. La ausencia de imports no basta: revisar descubrimiento de proveedores, comandos Artisan, configuración, scripts, pruebas y dependencias mediante composer why.

Candidatos iniciales: laravel/sail por existir un entorno Compose propio; laravel/tinker si no se usa su consola; spatie/laravel-ignition si no se necesita su diagnóstico; fakerphp/faker si no hay factories, seeders o pruebas que lo requieran. Son candidatos, no eliminaciones acreditadas. Conservar las herramientas de calidad/pruebas necesarias, incluidas Pint y PHPUnit; evaluar Mockery y Collision según la suite y los comandos elegidos. Guzzle requiere revisión especial: SlackNotifier utiliza Http de Laravel, por lo que no se eliminará una dependencia HTTP necesaria por carecer de referencias directas a su namespace.

Eliminar paquetes mediante Composer dentro del contenedor, sin borrar manualmente entradas del lockfile ni forzar la retirada de transitivas necesarias. Retirar referencias residuales en configuración, providers, scripts, permisos allow-plugins y documentación; config/ignition.php contiene clases del paquete Ignition y debe eliminarse o reconciliarse al retirarlo. La limpieza de Sanctum contempla también config/sanctum.php y middleware heredado, sin añadir el paquete para resolver referencias accidentales.

No eliminar paquetes de framework por pertenecer a funciones todavía no usadas: una dependencia transitiva puede ser obligatoria. No usar una lista de exclusión de CVE como sustituto de retirar o actualizar una dependencia. Verificar una instalación limpia desde lockfile con dependencias de desarrollo y otra sin require-dev; ambas deben arrancar y registrar los canales, y las pruebas completas e integración se ejecutan con las herramientas necesarias instaladas. Actualizar SBOM y auditorías después del cambio; documentar paquetes retirados y dependencias que permanecen justificadas.

## Risks / Trade-offs

- Lockfile desalineado → validar antes de instalar y justificar cambios mínimos; conservar Laravel 12.
- Bind mounts y UID/GID → comprobar escritura desde usuario PHP y archivos generados sin root en Linux.
- Imagen fijada puede quedar obsoleta → documentar actualización de parches/digests y volver a construir y probar.
- Colas locales persistentes pueden repetir entregas → usar solo correo capturado y datos separados; no prometer entrega exactamente una vez.
- Puertos y carpetas existentes → no sobrescribir `.env`; documentar variables y fallar de forma accionable.
- Vulnerabilidades nuevas o certificados con rutas alternativas → revalidar por componente y almacén efectivo; controles inconclusos impiden cierre.
- Herramienta de escaneo comprometida → fijar y verificar su origen antes de ejecutarla, con acceso mínimo y sin credenciales.
- Host sin Docker → registrar bloqueo de comprobaciones de ejecución; no marcar integración como completa por validación estática.
- Cambio accidental del contrato de negocio → mantener respuestas, validaciones y tests existentes; tramitar cualquier ampliación por separado.

## Migration Plan

Cambios locales mediante revisión normal, sin despliegue. Guardar de forma privada la configuración y los datos existentes antes de probar migraciones; documentar `MAIL_HOST=mailpit` y SQLite absoluto. Preparar y verificar en un proyecto Compose separado antes de usar datos del equipo. Para rollback, detener los servicios, restaurar archivos/configuración previos y volver a levantar sin borrar volúmenes ni bases; no revertir migraciones de datos automáticamente.

## Referencias

- [Docker: construcción y versiones de imágenes](https://docs.docker.com/build/building/best-practices/).
- [Compose: disponibilidad y orden de inicio](https://docs.docker.com/compose/how-tos/startup-order/).
- [Laravel 12: registro de proveedores](https://laravel.com/docs/12.x/providers).
- [Mailpit: imagen y configuración Docker](https://mailpit.axllent.org/docs/install/docker/).

- [PHP: avisos oficiales de octubre de 2026](https://www.php.net/archive/2026.php).
- [Mozilla: incidente eMudhra](https://blog.mozilla.org/security/2026/10/20/protecting-firefox-users-following-the-compromise-of-emudhra-intermediate-certificates/).
- [Google: alcance eMudhra y desconfianza de raíz](https://blog.google/security/protecting-chrome-and-android-users-from-compromised-emudhra-intermediate-ca-certificates/).
- [Composer: plugins y configuración TLS](https://getcomposer.org/doc/06-config.md).
- [Composer: auditoría de dependencias](https://getcomposer.org/doc/03-cli.md#audit).
- [LiteLLM: informe del incidente de octubre](https://docs.litellm.ai/blog/security-incident-oct-21-2026).
- [SLSA: procedencia y verificación de artefactos](https://slsa.dev/spec/v1.2/).

## Backport libxml2 autorizado — pendiente de ejecución

Se prepara `docker/libxml2/` con APKBUILD, parche CVE-2026-86140 basado en commit oficial d1686f91dbda141a752200419d35639fd6b38340 y prueba de límites con control negativo del código original. Conserva tarball 2.13.9 y ambos parches Alpine para CVE-2026-6732 con SHA-512 verificados. APK local 2.13.9-r2000 firmado con clave efímera del builder; no se exporta clave privada ni se usa allow-untrusted. PHP y Nginx incorporan el mismo paquete manteniendo libxml2.so.2. Contexto adicional limitado a docker/libxml2; el backend mantiene su contexto y exclusiones.

La firma local y el checksum no acreditan identidad upstream: las fuentes y huellas se revisan por separado. La revisión local requiere mantenimiento y retirada explícita cuando exista paquete oficial; no se presupone actualización automática por apk upgrade. Ver instrucciones, límites y retirada en docker/libxml2/README.md. Construcción, pruebas upstream/Alpine, regresión negativa/positiva, carga efectiva en PHP/Nginx, nuevos IDs y escaneo todavía pendientes. No se marca CVE ni tarea de seguridad resuelta con archivos preparados.

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
