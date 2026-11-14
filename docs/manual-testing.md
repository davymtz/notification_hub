# Cómo probar Notification Hub por tu cuenta

Ejecuta los comandos en la terminal del host con Docker Desktop iniciado. No necesitas PHP ni Composer en macOS. Make es opcional; los equivalentes están en `Makefile`.

## 1. Preparar el proyecto

```sh
cd /Users/davidmartinez/Desktop/proyectos/NotificacionHub
make setup
docker compose ps
```

`setup` construye las imágenes, audita e instala el lockfile, ejecuta la suite, prepara SQLite y APP_KEY si faltan, migra y arranca los servicios. Conserva los archivos `.env` y los datos existentes. Si detecta una configuración incompatible, sigue el diagnóstico sin sobrescribir tu configuración.

Deben aparecer `app`, `nginx`, `redis` y `mailpit` saludables, y `worker` en ejecución. En los siguientes arranques basta `make up`.

## 2. Ejecutar las pruebas de aplicación

```sh
make test
```

La suite incluye contrato HTTP, validación, DTO, registro dinámico de canales y Slack con transporte HTTP simulado. Las pruebas de Slack no envían mensajes a una cuenta real. El resultado tras esta revisión debe ser 24 pruebas y 72 aserciones.

## 3. Comprobar salud y descubrir los puertos reales

```sh
NH_API_URL="http://$(docker compose port nginx 80)"
NH_MAIL_URL="http://$(docker compose port mailpit 8025)"
printf 'API: %s\nMailpit: %s\n' "$NH_API_URL" "$NH_MAIL_URL"
curl -i "$NH_API_URL/up"
curl -i "$NH_API_URL/"
```

Ambas rutas deben responder HTTP 200. `/` devuelve la identificación del servicio. Usa los puertos mostrados: el `.env` local puede configurar 8082 en lugar de 8080.

## 4. Enviar un correo local

```sh
NH_TEST_KEY="manual-$(date +%s)-$$"
curl -i "$NH_API_URL/v1/notifications/dispatch" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data "{\"event_type\":\"manual.test\",\"channels\":[\"email\"],\"idempotency_key\":\"$NH_TEST_KEY\",\"payload\":{\"email\":\"manual@example.test\",\"subject\":\"Prueba $NH_TEST_KEY\",\"message\":\"Correo de prueba local\"}}"
```

Espera HTTP 202, `status: queued`, `event_id` y `channels_queued: ["email"]`. Abre la URL de Mailpit mostrada en el paso 3 y busca el asunto de esta prueba. Deben coincidir el destinatario y el texto. Mailpit captura el correo dentro de Docker; no es una prueba de entrega a un buzón externo.

## 5. Probar duplicados

Repite exactamente el `curl` anterior, conservando `NH_TEST_KEY`. Espera HTTP 200, `status: duplicate` y `duplicate: true`, sin un segundo correo. Para un envío nuevo genera otra clave. Esta comprobación es secuencial; no acredita idempotencia bajo solicitudes concurrentes.

## 6. Probar un rechazo

```sh
curl -i "$NH_API_URL/v1/notifications/dispatch" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data '{"event_type":"manual.invalid","channels":["canal-inexistente"],"idempotency_key":"manual-invalid","payload":{"message":"No debe enviarse"}}'
```

Espera HTTP 422 y un error en `channels.0`. También debe dar 422 una solicitud con canal `email` que omita `payload.email`.

## 7. Ejecutar el E2E y las pruebas operativas

```sh
make verify
```

El script crea un backend, proyecto Compose, puertos y volumen Redis temporales. Comprueba entrega HTTP → Redis → worker → Mailpit, interfaz y recursos de Mailpit, puertos locales, tiempos efectivos, persistencia de SQLite/Redis/configuración al reiniciar, parada ordenada del worker, falta de entrega sin worker, detección de FPM detenido y recuperación. Al terminar elimina únicamente los recursos temporales que creó. Debe finalizar con código 0 y dos líneas finales `PASS`.

## 8. Comprobar la instalación sin herramientas de desarrollo

```sh
make no-dev-check
make security-policy-test
```

La primera instala `--no-dev` en otra copia temporal y comprueba arranque, ruta y canales sin PHPUnit. La segunda ejecuta fixtures que comprueban el rechazo de evidencia de seguridad insegura o incompleta.

## 9. Consultar los controles completos de aceptación

```sh
make security
make acceptance
```

Estos controles requieren acceso a los registros y servicios externos de auditoría. La aceptación incluye una copia limpia, dos ejecuciones de setup y los controles funcionales y de seguridad. Mientras siga pendiente la evaluación de imágenes/Go/sistema/libxml2, el resultado global será no cero aunque las pruebas funcionales pasen. Un fallo de consulta tampoco se considera una auditoría limpia. Revisa el informe de esta revisión antes de dar el proyecto por cerrado.

## 10. Diagnosticar y apagar

```sh
docker compose logs --tail=100 app worker nginx
make down
```

`make down` conserva los datos. No añadas `--volumes` a tu instancia habitual si quieres conservar Redis. Para retomar: `make up`.

Slack funciona en modo simulado mientras `SLACK_WEBHOOK_URL` esté vacío. Telegram es un canal fake. Las integraciones reales con credenciales y proveedores externos no quedan acreditadas por estas pruebas locales.
