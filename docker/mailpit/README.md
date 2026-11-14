# Mailpit: reconstrucción temporal de seguridad

Estado: preparado; construcción/pruebas pendientes. Compose todavía usa la imagen
upstream fijada. La reconstrucción no se activa hasta revisar su salida.

- Builder Go 1.26.10 por índice autenticado facilitado por el host; manifiestos
  AMD64/ARM64 registrados. Node/npm/git solo en builder, desde Alpine 3.24; versiones
  efectivas se exportan. No se instalan en el host ni backend Laravel.
- Mailpit v1.32.0 se descarga como módulo Go desde proxy.golang.org, verificado por
  sum.golang.org con la clave incorporada en el Go fijado. Sum/GoModSum de fuente y
  lockfiles quedan en la imagen. No se desactiva sumdb ni se usa acceso VCS alternativo.
- Solo x/net cambia a v0.62.0. Go build/test usan -mod=readonly para rechazar cambios
  adicionales no revisados. Se mantiene la interfaz compilando los assets del mismo
  código mediante package-lock, npm ci sin hooks y audit antes de scripts propios.
- Alto/crítico en npm, fallo de fuente, pruebas o versión esperada bloquea build.
  Se conserva la herramienta de licencias v2.0.1 usada por upstream. GO-2026-5932
  se comprueba mediante ausencia del paquete OpenPGP y subpaquetes en el grafo real.
- Runtime: misma base Mailpit fijada, binario nuevo y evidencias; usuario 65534.
  La firma/origen de módulos y el escaneo final no son prueba de build reproducible.
- La construcción exporta resultados en /usr/share/notification-hub/mailpit-build.
  Si hace falta otro cambio de lockfile o de frontend, revisar el fallo antes de
  ampliar actualizaciones. No omitir auditorías/pruebas para completar el build.

Desde la raíz:

```sh
docker build --progress=plain -t notification-hub-mailpit:candidate docker/mailpit
```

No sustituye contenedores existentes. Tras construir: verificar versión, readyz,
interfaz, entrega aislada y nuevos SBOM/SARIF antes de activar en Compose. Registrar
ID y huellas; retirar mantenimiento local al publicar upstream un release corregido.

Fuentes:
https://raw.githubusercontent.com/axllent/mailpit/v1.32.0/Dockerfile
https://raw.githubusercontent.com/axllent/mailpit/v1.32.0/go.mod
https://raw.githubusercontent.com/golang/net/v0.62.0/go.mod
https://go.dev/doc/devel/release#go1.26.10

## Candidata construida en ARM64

ID recibido: sha256:1612ed48a57c21dbc83489911eb22d6c450864a22baf2485036113d2d85f0f52.
Para comprobar el binario, interfaz, entrega aislada y generar SPDX/SARIF:

```sh
sh scripts/dev/verify-mailpit-candidate.sh
```

Una salida 2 al finalizar indica hallazgos del escáner por revisar. La salida PASS
funcional se emite por separado. No activa la candidata en el desarrollo.

## Empaquetado final en revisión

La primera candidata pasó todas las comprobaciones funcionales. Scout atribuyó dos
versiones x/net a /mailpit. El Dockerfile ahora copia el filesystem fusionado a una
etapa final limpia, sin distribuir la capa con el binario sustituido. Mantiene CA,
bibliotecas, symlinks y configuración de arranque explícita. La comprobación exige
el mismo SHA-256 del binario probado; cambia el empaquetado, no el binario.
Reconstruir la etiqueta candidata y repetir verify-mailpit-candidate.sh. No se
suprimen hallazgos ni se activa el servicio hasta revisar el nuevo reporte.
