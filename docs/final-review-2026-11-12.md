# Revisión final — 2026-11-12

## Resultado

La aplicación y el entorno local pasan la verificación funcional después de las correcciones de esta revisión. La aceptación global **no pasa**: `make acceptance` termina con código 1 en su etapa de seguridad. El cambio OpenSpec `setup-development-environment` sigue abierto. No se creó un commit, un remoto ni se hizo push; el usuario pidió instrucciones para publicarlo con otra cuenta.

La revisión se ejecutó en Docker sobre ARM64, a partir de copias temporales del proyecto de `/Users/davidmartinez/Desktop/proyectos/NotificacionHub`. La ruta anterior en `Documents` ya no existe.

## Correcciones verificadas

1. **Slack con título o mensaje opcional.** La validación permite cualquiera de los dos, pero el transporte real leía ambos índices sin valor predeterminado y lanzaba `Undefined array key`. Dos PoC reprodujeron el error. Se corrigieron los bloques y el texto alternativo; se añadieron pruebas sin llamadas reales al proveedor.
2. **Seis avisos nuevos de Composer.** Se actualizaron únicamente `symfony/http-foundation` de 7.4.21, `symfony/http-kernel` de 7.4.15 y `symfony/routing` de 7.4.15 a **7.4.23**. Se usó Composer con cambios mínimos y sin plugins/scripts al resolver. No cambió `composer.json`. El lockfile resultante tiene cero avisos y cero paquetes abandonados en la consulta del día. [Aviso oficial HTTP Foundation](https://symfony.com/cve-2026-118514), [aviso oficial Routing](https://symfony.com/cve-2026-118516), [aviso oficial HttpKernel](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9).
3. **Directorios de una copia limpia.** La copia de aceptación excluye directorios de ejecución y fallaba al ejecutar `package:discover` antes de crear `bootstrap/cache`. El instalador ahora prepara los directorios de caché y almacenamiento antes del hook de Composer. La aceptación desde copia limpia y su repetición comprobaron la corrección.

## Pruebas ejecutadas

| Comprobación | Resultado |
| --- | --- |
| Suite original antes de las correcciones | 20 pruebas, 62 aserciones; dos nuevas PoC de Slack reprodujeron el error |
| Suite tras los cambios, instalación desde lockfile y copia limpia | **24 pruebas, 72 aserciones, aprobadas** |
| Respuesta 202, jobs por canal, duplicado secuencial 200 y rechazo 422 | Aprobados por la suite |
| Slack: solo mensaje, solo título y error HTTP 503 propagado | Aprobados con HTTP simulado; sin mensajes reales a Slack |
| Payload `channels` mal formado | 422 y ningún job, aprobado |
| Construcción y arranque de app, worker, Nginx, Redis y Mailpit | Aprobados en ARM64 |
| Dos ejecuciones reales de setup en una copia limpia | Aprobadas; configuración, APP_KEY, SQLite y Redis conservados |
| Instalación `--no-dev`, arranque, ruta y registro de canales | Aprobada, sin PHPUnit en esa instalación |
| HTTP → Redis → worker → Mailpit | Aprobado, verificando destinatario, asunto y cuerpo exactos |
| Interfaz y dos recursos JS/CSS de Mailpit | Aprobados por HTTP; sin ejecución de navegador |
| Reinicio y persistencia | Aprobados |
| Worker detenido | Ausencia de entrega detectada como se esperaba; parada con código 0 y recuperación aprobadas |
| FPM detenido | Nginx detecta falta de disponibilidad; recuperación y nueva entrega aprobadas |
| Puertos HTTP/Mailpit en loopback; FPM/Redis internos; tiempos efectivos | Aprobados |
| Fixtures de rechazo de evidencia de seguridad insegura/incompleta | Aprobados |
| Sintaxis POSIX de scripts | 18 scripts, cero errores |
| Runtime, CA/TLS de app/worker, transporte Composer, configuración FPM y auditoría Composer | Aprobados por los controles actuales |
| SBOM y evaluación global de imágenes | **Incompletos / bloqueados** |
| Aceptación completa | **Código 1**, etapa funcional aprobada y seguridad rechazada |

El mensaje `FAIL: Mailpit did not receive this message within 5 seconds` aparece una vez dentro de la **prueba negativa con worker detenido**. En ese caso es la salida esperada; el script verifica el código específico, reinicia el worker y comprueba otra entrega.

## Pendientes que impiden el cierre

1. **SBOM de app y Nginx ausentes.** Scout 1.27.0 indexó los paquetes, pero terminó con `could not parse reference:` y no produjo los archivos SPDX. El evaluador reporta ambos controles como inconclusos. No equivale a ausencia de vulnerabilidades.
2. **Imágenes Redis y Mailpit con avisos bloqueantes según el evaluador.** Redis: `CVE-2026-4738`. Mailpit: `CVE-2026-78661`, `CVE-2026-107891` y `CVE-2026-4738`. Hay que remediar o evaluar individualmente la aplicabilidad para el digest exacto y repetir los controles. La imagen candidata de Mailpit no sustituyó la imagen predeterminada en Compose durante esta revisión.
3. **Procedencia de la versión nueva de Scout pendiente de revisión.** El host tiene 1.27.0; la política acepta la instalación previamente revisada de 1.24.0. La versión/commit/hash diferentes se rechazan conforme a esa política. Hay que revisar la instalación y actualizar la evidencia y las regresiones; cambiar el hash sin revisión no acredita el control.
4. **Triage de Go/sistema y libxml2 histórico pendiente.** `security-evaluate.php` mantiene una reserva explícita para esta evaluación individual. No se retiró ni se añadieron exclusiones generales para obtener un resultado verde.
5. **Cierre de OpenSpec pendiente.** El registro conserva 20 tareas marcadas y 13 pendientes. Algunas comprobaciones operativas ahora tienen evidencia nueva, pero no se archivó el cambio ni se presentó el conteo histórico como aceptación completa. Revisar las tareas con esta evidencia y cerrar solo después de que `make security` y `make acceptance` terminen en 0.
6. **Publicación pendiente.** El repositorio local está en `main`, sin commits ni `origin`. La cuenta y el repositorio destino los configurará el usuario según la guía enlazada abajo.

Las fases posteriores del roadmap (CI, concurrencia/idempotencia atómica, autenticación, integraciones adicionales y despliegue) conservan su estado. No se implementaron como parte de esta revisión final.

## Límites de lo comprobado

- Solo se ejecutó la plataforma nativa ARM64; no se ejecutó AMD64.
- El correo termina en Mailpit. No se probó un buzón SMTP externo.
- Slack real se ejercitó con transporte HTTP simulado; no se utilizaron credenciales ni se enviaron mensajes reales. Telegram sigue siendo fake.
- No se acredita carga, idempotencia concurrente, agotamiento real de reintentos ante un proveedor externo ni un despliegue productivo.

## Evidencia y reproducción

- [Guía para probarlo paso a paso](manual-testing.md).
- [Guía para crear el repositorio y publicarlo con otra cuenta](publish-repository.md).
- [Resultado estructurado](final-review-2026-11-12.json).
- Evidencia local, excluida de Git: `artifacts-security.final-review-20261112/`. Contiene logs anteriores y posteriores, aceptación, limpieza y los reportes originales de seguridad en `security/`. La salida de instalación/pruebas sobre la carpeta definitiva se conserva en `project-tests.log`; la validación de OpenSpec, en `openspec-validation.log`.

Para reproducir: `make setup`, `make test`, `make verify`, `make no-dev-check`, `make security-policy-test`, `make security`, `make acceptance`. Los dos últimos continúan bloqueando el cierre mientras existan los pendientes anteriores.
