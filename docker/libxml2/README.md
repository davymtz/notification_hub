# Backport temporal libxml2 — CVE-2026-86140

Estado: preparado; construcción y ejecución pendientes en el host. No demuestra que otros advisories de libxml2 estén corregidos.

## Fuentes y controles

- Base Alpine 3.24 de la misma imagen PHP fijada usada por la aplicación.
- [Receta Alpine 3.24](https://github.com/alpinelinux/aports/blob/3.24-stable/main/libxml2/APKBUILD): tarball 2.13.9 y ambos parches CVE-2026-6732, con sus SHA-512 publicados.
- [Corrección upstream](https://github.com/GNOME/libxml2/commit/d1686f91dbda141a752200419d35639fd6b38340): adaptación de contexto y espacios; misma lógica de límites.
- `APKBUILD` verifica los tres archivos remotos y el parche local antes de compilar. Una modificación de las URLs mutables de Alpine falla por checksum; revisar y fijar nuevos contenidos deliberadamente.
- Revisión local `2.13.9-r2000`, identificada como Notification Hub; no representa una publicación de Alpine. Se mantiene SONAME libxml2.so.2 y opciones legacy/lzma/zlib. Python se usa solo para pruebas en el builder, sin bindings en ejecución.
- Compilación sin root mediante abuild. Clave privada efímera dentro del builder descartado; se exportan APK, clave pública y SHA-256. Firma local acredita correspondencia de este build, no identidad del publicador upstream. No usar allow-untrusted.
- `make runtests` conserva las regresiones upstream y Alpine. La prueba de límites extrae la función del original y del código parcheado: exige fallo del original y éxito del parche con un buffer lógico de un byte y un byte guardián. No sustituye fuzzing/ASan ni garantiza ausencia de otros defectos.

## Construcción y comprobación

Desde la raíz, usar Compose: sus contextos adicionales suministran únicamente esta carpeta a ambos Dockerfiles. Requiere BuildKit y Compose con additional_contexts (>=2.17; host reporta v5.4.0).

```sh
docker compose build app nginx
docker compose up -d --force-recreate --wait --wait-timeout 180 app worker nginx
docker compose exec -T app apk info -v libxml2
docker compose exec -T worker apk info -v libxml2
docker compose exec -T nginx apk info -v libxml2
docker compose exec -T app php -r 'echo LIBXML_DOTTED_VERSION, PHP_EOL; $d=new DOMDocument(); if (!$d->loadXML("<root><ok/></root>")) exit(1);'
docker compose exec -T nginx nginx -t
docker compose exec -T app php artisan test
sh scripts/dev/verify.sh
sh scripts/dev/scan-images.sh
```

Esperado: compilación incluye `PASS: original bounds control fails; patched bounds control passes.`; libxml2-2.13.9-r2000 en tres servicios, DOM 2.13.9 y pruebas satisfactorias. Guardar log completo y nuevos IDs/SBOM/SARIF. El escáner puede seguir atribuyendo CVE por versión upstream: adjuntar la evidencia de código/paquete/binario y evaluar individualmente; no silenciar globalmente.

## Mantenimiento y retirada

Revisar semanalmente y ante avisos críticos. Responsable operativo: mantenedor del entorno del proyecto. Antes de cerrar, registrar quién asume esa responsabilidad y evidencia de build nativo. Revisar que ambos Dockerfiles conservan el mismo bloque builder. Los compiladores y Python no pasan a la imagen final; la clave privada puede permanecer en la caché local de BuildKit, por lo que no se usa para publicar ni firmar otros paquetes.

Cuando Alpine publique una revisión oficial corregida, retirar el builder y los contextos adicionales, reconstruir desde cero con la revisión oficial y repetir pruebas/escaneo. El r2000 local tiene prioridad sobre r3/r4 del mismo pkgver: no basta con apk upgrade; reconstruir sin este APK. Conservar registro del parche y evidencia histórica. No cerrar fase 1 hasta ejecutar los controles y resolver los demás hallazgos aplicables.
