# Seguridad del entorno — verificación en curso

Fecha de corte del inventario aportado por el usuario: 2026-10-28. Plataforma de ejecución: Linux ARM64/aarch64. No constituye una declaración de entorno libre de vulnerabilidades.

## Inventario efectivo

| Componente | Versión/evidencia | Estado |
| --- | --- | --- |
| PHP CLI app/worker y FPM | 8.4.27 | Versión observada; parche mínimo PHP2Shell cubierto según aviso PHP |
| session.upload_progress.enabled | 0 en CLI; Off en FPM | Mitigación residual observada, no sustituye parche |
| Composer | 2.10.3; secure-http=true, disable-tls=false, allow-plugins=false | Configuración reportada; overrides/env y pruebas TLS pendientes |
| OpenSSL | 3.5.9, libssl3/libcrypto3 3.5.9-r0 | Consultar advisories y escaneo; no inferir seguridad de la fecha |
| cURL / libcurl | 8.22.0-r0 | Brotli activo según curl_version; revisar decodificación efectiva |
| Brotli | app/worker: brotli/1.2.1; Nginx: brotli-libs-1.2.1-r0 | Parche observado y regresiones completas/integración aprobadas |
| Extensión PHP Redis | 6.2.0 | Observada en CLI; procedencia/auditoría pendientes |
| CA app/worker | ca-certificates y bundle 20261020-r0, 118 certificados | Revisar huellas exactas y rechazo de cadena afectada |
| Alpine app/Nginx/Mailpit | 3.24.3-r0 | Paquetes reportados; escaneo pendiente |
| Alpine Redis | 3.23.7-r0 | Paquetes reportados; escaneo pendiente |
| Imágenes de servicios | Referencias fijadas en docker/images.lock.json; ARM64 ejecutado | Firmas/provenance y escaneo pendientes |
| Imagen PHP local | sha256:05e36f8cfccf296fda75895dacdebd7998674fc027674b15ee12125fa74ec032 | RepoDigests vacío es esperable en build local; asociar SBOM a ID y reconstrucciones |
| Node/Python | No requeridos por este backend; no aparecen entre paquetes reportados | Revisar también herramientas de escaneo y artefactos estáticos al generar SBOM |

## Matriz de avisos y aplicabilidad

| Aviso | Evidencia y decisión | Estado |
| --- | --- | --- |
| CVE-2026-109618 / 109619 | PHP fija mínimo 8.4.27 y el runtime observado coincide | Parche runtime observado; prueba negativa de política pendiente |
| CVE-2026-115560 | session.upload_progress.enabled desactivado antes de petición, confirmado en FPM | Mantener mientras se revisa el siguiente release; no habilitar sin nueva evaluación |
| CVE-2026-111497 | Alpine v3.24 main.json reporta corrección en 1.2.1-r0, posterior a 1.2.0-r1 instalado | App/worker reportan 1.2.1; Nginx 1.2.1-r0; suite e integración aprobadas |
| Avisos Composer iniciales | Laravel 12.70.2, CommonMark 2.10.4, Flysystem 3.36.0, HttpFoundation 7.4.21; YAML retirado | Última auditoría reportada limpia |
| CVE-2026-45070 | Mime actualizado a 7.4.22; auditoría y suite posteriores correctas | Parche lockfile instalado según usuario; repetir audit al cierre |
| Incidente eMudhra/emSign | Bundle observado no lista ECC G3 por nombre, sí G1 | No concluir mitigación por nombres; validar huellas/cadenas y rutas alternativas |

## Almacenes CA observados

App/worker y FPM usan openssl.cafile y curl.cainfo en `/etc/ssl/certs/ca-certificates.crt`. El archivo predeterminado `/etc/ssl/cert.pem` tiene la misma huella de contenido: `5961c0a3c65459931c023e0075c09733f09aea4ca439cb85bc4a08bb4f3f568b`. openssl.capath no tiene override explícito; falta evaluar directorios CA por defecto y variables de entorno sin revelar secretos.

Se observó emSign Root CA - G1, SHA-256 DER `40f6af0346a99aa1cd1d555a4e9cce62c7f9634603ee406615833dc8c8d00367`. Su presencia no autoriza retirarla ni prueba exposición a las intermedias comprometidas. El incidente publicado incluye medidas sobre ECC Root CA - G3; consultar huellas oficiales y posibles rutas cruzadas. El inventario por subject no sustituye una comprobación criptográfica de confianza ni confirma revocación.

## Cierre y respuesta

Revisar advisories al menos semanalmente y antes de cada actualización/cierre. Un aviso crítico aplicable requiere triage y contención en un día hábil; no cerrar con hallazgos altos/críticos aplicables o controles inconclusos. Reconstruir/recrear tras parches y repetir pruebas. Si se acredita ejecución de artefactos comprometidos, evaluar exposición y rotar los secretos alcanzables. No incorporar bypass TLS ni exclusiones globales. La automatización CI queda en fase 2.

## Fuentes y límites

- [PHP: avisos oficiales de octubre](https://www.php.net/archive/2026.php).
- [Google Brotli: repositorio y releases](https://github.com/google/brotli/releases).
- [Alpine: base de advisories](https://secdb.alpinelinux.org/). La consulta desde este entorno no fue accesible; verificar desde el host.
- [Mozilla: eMudhra](https://blog.mozilla.org/security/2026/10/20/protecting-firefox-users-following-the-compromise-of-emudhra-intermediate-certificates/).

El reporte recibido contiene versiones y paquetes de todos los servicios, pero aún faltan SBOM, escáner verificado, firmas/procedencia, fixtures TLS y pruebas negativas de los controles. No se han ejecutado exploits.

## Rutas cruzadas — revisión adicional

[AWS 2026-150](https://aws.amazon.com/security/security-bulletins/2026-150-aws/) identifica claves SPKI de tres jerarquías: AAA Certificate Services, emSign Root TLS CA - G3 y emSign ECC Root CA - G3. [Apple](https://support.apple.com/en-us/149555) confirma rutas cruzadas para las intermedias TLS. Se prepara tls-trust-review.php para buscarlas en bundles y directorios efectivos por clave, no por nombre. Esto no elimina automáticamente ninguna raíz y no acredita rechazo de cadenas afectadas. La retirada de una raíz amplia puede afectar cadenas legítimas; cualquier política debe quedar documentada y probada.

## Evidencia SPKI recibida y control pendiente

La revisión de app inspeccionó 119 archivos/118 CA únicas y no encontró las tres claves del aviso AWS. Composer cafile explícito vacío según usuario. Se añade composer-ca-review.php para comprobar selección de bundle mediante la librería incluida en el PHAR instalado y comparar su contenido con PHP/cURL. Pendientes worker y fixture de cadena afectada para la aceptación completa; el repositorio público emSign no fue accesible desde esta herramienta en esta consulta. No se sustituirá esa prueba por un certificado autofirmado genérico.

## Escaneo y SBOM locales

`sh scripts/dev/scan-images.sh` utiliza Docker Scout 1.24.0 ya instalado en el host según el usuario; no ejecuta un instalador ni descarga un escáner con etiquetas móviles. Se requieren credenciales Docker propias de Scout si el servicio las solicita; no compartir tokens. Se consultan imágenes del almacén local por ID de los contenedores app, nginx, redis y mailpit (worker comparte imagen PHP). No se hace push. Scout puede consultar servicios Docker para vulnerabilidades; no interpretar local:// como escaneo totalmente sin red.

Se generan SPDX y SARIF completos, metadatos de ID/arquitectura, timestamps y checksums en artifacts-security.* excluido de Git, con permisos privados. Una salida 2 indica advisories para revisar, no que todos sean altos/críticos; una salida 1 indica controles incompletos. No se filtran avisos sin corrección ni se desactiva revisión de base images. Falta acreditar procedencia de la instalación Scout, frescura de su fuente de advisories, resultados y sus límites antes de cerrar tarea 6.6.

## Hallazgos Scout para evaluar

Los cuatro reportes llegaron con estado 2 y los archivos son legibles en la carpeta compartida. docs/development-scan-review.json conserva el detalle compacto de todos los avisos por imagen y paquete, incluidos severity UNSPECIFIED y sin corrección indicada. El estado global no permite dar por saneadas las imágenes. [cURL](https://curl.se/docs/CVE-2026-92392.html) confirma que la rama 8.22.0 está afectada y corrige en 8.23.0. libxml2 exige contrastar backports Alpine y ABI antes de reconstruir. No se retiran parsers necesarios para Laravel para ocultar hallazgos.

## Decisiones tras secdb Alpine

- CVE-2026-92392: parche confirmado curl 8.23.0-r0; app reporta cURL 8.23.0 y Nginx curl/libcurl 8.23.0-r0 tras reconstrucción/recreación.
- CVE-2026-107891: Alpine v3.24 declara secfix en musl 1.2.6-r3, ya instalado. Scout indica r6. Conservar evidencia de la discrepancia y verificar versión/base de datos antes de emitir no aplicabilidad; no añadir ignore global.
- CVE-2026-86140 libxml2: sin secfix de esa rama en la respuesta recibida. Sigue abierto, no apto para cierre de seguridad. Revisar código del paquete/backport o una base compatible corregida.

Fuente consultada por el usuario: https://secdb.alpinelinux.org/v3.24/main.json, SHA-256 30fe355d68d0fe1555df2487eaac58500d65c7aeefcf4d1805910dd99b180849.

## Backport libxml2 autorizado — pendiente de ejecución

Se prepara `docker/libxml2/` con APKBUILD, parche CVE-2026-86140 basado en commit oficial d1686f91dbda141a752200419d35639fd6b38340 y prueba de límites con control negativo del código original. Conserva tarball 2.13.9 y ambos parches Alpine para CVE-2026-6732 con SHA-512 verificados. APK local 2.13.9-r2000 firmado con clave efímera del builder; no se exporta clave privada ni se usa allow-untrusted. PHP y Nginx incorporan el mismo paquete manteniendo libxml2.so.2. Contexto adicional limitado a docker/libxml2; el backend mantiene su contexto y exclusiones.

La firma local y el checksum no acreditan identidad upstream: las fuentes y huellas se revisan por separado. La revisión local requiere mantenimiento y retirada explícita cuando exista paquete oficial; no se presupone actualización automática por apk upgrade. Ver instrucciones, límites y retirada en docker/libxml2/README.md. Construcción, pruebas upstream/Alpine, regresión negativa/positiva, carga efectiva en PHP/Nginx, nuevos IDs y escaneo todavía pendientes. No se marca CVE ni tarea de seguridad resuelta con archivos preparados.

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

## Comprobación local unificada (implementada; ejecución pendiente)

`make security` o `sh scripts/dev/security-check.sh` recopila inventarios de app y
worker, FPM, auditoría Composer, selección CA, política TLS/plugins Composer,
revisión actual de los dos avisos altos y SPDX/SARIF de las imágenes ejecutadas.
Guarda reportes privados en artifacts-security.* y evalúa los controles en un
contenedor sin red, sin .env y con montajes de lectura. Los archivos JSON inválidos,
consultas fallidas, runtime vulnerable o avisos altos/críticos sin evaluación exacta
impiden aceptación. Se conserva el hallazgo original de Scout. Las dos decisiones
conocidas se limitan a advisory + paquete + imagen; un cambio de imagen exige revisión.

`make security-policy-test` ejecuta lint PHP y fixtures deterministas de runtime
vulnerable, ini inseguro, consulta de audit fallida, advisory Composer, inventario
TLS inconcluso/afectado, SARIF ausente, hallazgos alto/crítico y decisión aplicada a
otra imagen. Estos fixtures comprueban la política, no sustituyen una prueba real
TLS ni las consultas oficiales. Aquí no están disponibles PHP/Docker/Make para
ejecutarlos; pendiente host.

La aceptación del comando permanece explícitamente bloqueada por tres requisitos
sin evidencia concluyente: cadena afectada eMudhra/control válido por cliente,
procedencia/integridad de la instalación del escáner y frescura de advisories, y
revisión individual de los avisos Go/sistema/libxml2 restantes. No hay opción para
omitirlos. El código de evaluación debe actualizarse junto a la evidencia real al
completar cada requisito; no basta con cambiar un indicador en configuración.

## Secfixes musl confirmados por el host

Alpine 3.24 main.json SHA-256
30fe355d68d0fe1555df2487eaac58500d65c7aeefcf4d1805910dd99b180849 declara
CVE-2026-107891 corregido en 1.2.6-r3 (app/Nginx/Mailpit). Alpine 3.23 main.json
SHA-256 2bf1824b91d854b273d2ffb3274cb5afa2187294efc31e70fc2ba1c96eba1188
declara la corrección en 1.2.5-r24 (Redis). Coinciden con inventarios recibidos.
Se registra discrepancia con el umbral r6 de Scout, sin exclusión global. No hay
secfixes relevantes para BusyBox/tar/libxml2 en las respuestas recibidas; sus
pendientes no quedan resueltos por ausencia de registros.

## Prueba TLS de cadenas públicas preparada

El sitio del investigador original, enlazado por Kudelski, publica destinos de prueba
para las rutas emSign y Sectigo: dv-g3b-emudhra.we-have-a-problem.com y
dv-g3b-aaa-emudhra.we-have-a-problem.com. Se añade tls-chain-check.php como prueba de
OpenSSL, cURL y PHP streams con control válido repo.packagist.org. No se ejecuta
contenido remoto ni se usan claves privadas o bypass de verificación. Se fijan las
huellas DER públicas de las intermedias del inventario del investigador.

Para contar el rechazo, OpenSSL debe mostrar la intermedia fijada, firma de la hoja,
fechas vigentes y hostname correcto, y fallar por confianza (20/21), no por red o
expiración. cURL exige error 60 y verify 20/21; streams exige fallo de verificación.
Si OpenSSL no expone la cadena después de abortar, se considera inconcluso; no se
relajan controles para extraerla. Composer aún requiere comprobar su transporte.
Solo se cubren dos rutas de las intermedias afectadas; no se acredita toda la matriz
por esta prueba. Pendiente ejecución y revisión del resultado del host.

Fuentes: https://emudhra.we-have-a-problem.com/ ;
https://raw.githubusercontent.com/galadran/we-have-a-problem.com/main/impacted-intermediates.json ;
https://kudelskisecurity.com/research/emudhra-intermediate-ca-private-key-compromise .

En macOS security-check recopila además la ruta del plugin Scout reportada por Docker, SHA-256 del binario, verificación codesign estricta e identidad de firma. La comparación de identidad con el publicador esperado y el origen de instalación sigue requiriendo revisión de esos resultados; no se acepta cualquier firma presente como prueba suficiente. No se instala ni modifica el plugin.

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

## Siguiente corrección real: Mailpit x/net

Se revisó el go.mod oficial de Mailpit v1.32.0: utiliza x/net 0.61.0. El módulo
upstream x/net v0.62.0 exige Go 1.26 y corrige los dos avisos de HTML identificados.
No se consideran falsos positivos esos rangos. El último release Mailpit consultado
sigue siendo v1.32.0; preparar una reconstrucción temporal de ese código con x/net
0.62.0 y herramienta Go 1.26.10 (release oficial de seguridad del 2026-10-19), antes
de cambiar el servicio Compose. Go será herramienta de construcción, no requisito
instalado en el host ni runtime adicional del backend Laravel. La interfaz embebida
debe conservarse y sus entradas y dependencias de construcción deben revisarse.

Primero resolver índice y plataformas de golang:1.26.10-alpine3.24 en el registro del
host y fijar el digest. Después fijar código/lockfiles, ejecutar pruebas upstream,
verificar módulos del binario resultante, repetir entrega y SBOM. No se habilita una
imagen nueva con fuentes o digest pendientes. Registrar retirada cuando Mailpit
publique release corregido. Fuentes:
https://raw.githubusercontent.com/axllent/mailpit/v1.32.0/go.mod ;
https://raw.githubusercontent.com/golang/net/v0.62.0/go.mod ;
https://go.dev/doc/devel/release#go1.26.10 .

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
