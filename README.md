# Notification Hub API

Microservicio de notificaciones multicanal construido con Laravel 12.

## Organización del repositorio

- `project_back/`: aplicación Laravel, Composer, pruebas y configuración local `.env`.
- `docker/php/` y `docker/nginx/`: infraestructura de PHP y Nginx.
- `docker-compose.yml` y `Makefile`: comandos desde la raíz del repositorio.
- `docs/`: [estructura y convenciones](docs/structure.md) y [roadmap](docs/roadmap.md).
- `openspec/`: propuestas, diseños y seguimiento de cambios.

Guías de esta revisión: [pruebas paso a paso](docs/manual-testing.md),
[publicación con otra cuenta](docs/publish-repository.md) y
[resultado de la revisión final](docs/final-review-2026-11-12.md).

## Descripción

Notification Hub centraliza el envío de notificaciones a múltiples canales (Slack, Email, Telegram, etc.) mediante un único endpoint HTTP RESTful. Implementa el patrón Strategy + Registry para máxima extensibilidad.

## Arquitectura

### Patrón Strategy + Registry

- **NotificationChannel (Interface)**: Contrato común para todos los canales
- **NotificationRegistry**: Resuelve implementaciones vía Container de Laravel (sin switch/factory)
- **NotificationRouterService**: Itera channels y despacha un Job por cada uno
- **SendNotificationJob**: Job asíncrono (ShouldQueue) con reintentos configurables
- **NotificationDTO**: DTO tipado que fluye entre capas

### Flujo de Request

```
POST /v1/notifications/dispatch
    ↓
DispatchNotificationRequest (validación)
    ↓
DispatchNotificationAction
    ↓
NotificationRouterService
    ↓
SendNotificationJob (por cada canal)
    ↓
NotificationChannel::send() (vía Registry)
```

### Extensibilidad

Agregar un nuevo canal requiere **solo**:

1. Crear clase implementando `NotificationChannel`
2. Registrar en `AppServiceProvider`:
   ```php
   $registry->register('nuevo_canal', NuevoCanalNotifier::class);
   ```

No se modifica Controller, Router, Action ni Job.

## Canales Soportados

| Canal     | Implementación | Estado |
|-----------|----------------|--------|
| email     | EmailNotifier  | ✅     |
| slack     | SlackNotifier  | ✅     |
| telegram  | TelegramNotifier | ✅ (fake) |

## Requisitos

- Docker & Docker Compose
- Make (opcional, para comandos cortos)

## Instalación

```sh
make setup
# Equivalente sin Make:
# sh scripts/dev/setup.sh
```

La preparación conserva `.env`, APP_KEY y datos existentes. Configuración Laravel en
`project_back/.env`; puertos Compose en `.env` de la raíz. Para este equipo:
`HTTP_PORT=8082` y `MAIL_UI_PORT=8025`. No copiar valores sobre archivos existentes.
El setup muestra los puertos realmente publicados. Mailpit captura correo local.

Arranques posteriores: `make up`; parada conservando datos: `make down`.
No se ejecutan migraciones ni se regenera la clave al arrancar. Consulte
[entorno y comandos equivalentes](docs/development.md).

La integración funcional está verificada en ARM64. La aceptación completa de
seguridad sigue pendiente; `make security` informa controles inconclusos y termina
con error mientras falten evidencias. No se considera una imagen productiva.

## Uso del Endpoint

### POST /v1/notifications/dispatch

**Request:**

```json
{
  "event_type": "user.created",
  "channels": ["email", "slack"],
  "idempotency_key": "unique-request-id",
  "payload": {
    "email": "user@example.com",
    "title": "Welcome",
    "message": "Thanks for joining!"
  }
}
```

**Response (202 Accepted):**

```json
{
  "status": "queued",
  "event_id": "uuid",
  "channels_queued": ["email", "slack"]
}
```

**Response (422 Unprocessable Entity):**

```json
{
  "message": "The selected channels.0 is invalid.",
  "errors": {
    "channels.0": ["The selected channels.0 is invalid."]
  }
}
```

### Ejemplos con curl

```bash
# Email notification
curl -X POST http://localhost:8080/v1/notifications/dispatch \
  -H "Content-Type: application/json" \
  -d '{
    "event_type": "user.created",
    "channels": ["email"],
    "idempotency_key": "test-1",
    "payload": {
      "email": "test@example.com",
      "subject": "Welcome!",
      "message": "Thanks for registering."
    }
  }'

# Slack notification
curl -X POST http://localhost:8080/v1/notifications/dispatch \
  -H "Content-Type: application/json" \
  -d '{
    "event_type": "alert.triggered",
    "channels": ["slack"],
    "idempotency_key": "test-2",
    "payload": {
      "title": "System Alert",
      "message": "CPU usage above 90%"
    }
  }'

# Multi-channel
curl -X POST http://localhost:8080/v1/notifications/dispatch \
  -H "Content-Type: application/json" \
  -d '{
    "event_type": "order.shipped",
    "channels": ["email", "slack", "telegram"],
    "idempotency_key": "test-3",
    "payload": {
      "email": "customer@example.com",
      "title": "Order Shipped",
      "message": "Your order #12345 has been shipped"
    }
  }'
```

## Testing

```bash
# Ejecutar todos los tests
make test

# O directamente
docker compose exec app php artisan test
```

### Tests Incluidos

1. **Feature Test**: POST válido → 202 + Jobs dispatcheados
2. **Validation Test**: Canal no soportado → 422
3. **Extensibility Test**: Registrar canal fake dinámicamente → funciona sin tocar Router/Controller

## Queue Worker

Compose mantiene el worker en ejecución. Para consultar su actividad:

```bash
# Consultar el worker que ya administra Compose
make queue

# Equivalente
docker compose logs -f worker
```

## Configuración de Slack

Para integración real con Slack, configurar en `project_back/.env`:

```env
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

Sin configurar, SlackNotifier funciona en modo mock (solo log).

## Decisiones de Diseño

### 1. Registry via Container
Permite resolver notifiers vía DI de Laravel. Al agregar un canal, solo se modifica AppServiceProvider.

### 2. Job por Canal
Cada canal tiene su propio Job. Si un canal falla, no afecta a los demás. Reintentos configurables por Job.

### 3. DTO Inmutable
NotificationDTO es readonly. Evita mutaciones accidentales entre capas.

### 4. Idempotencia vía Cache
Usa Redis/cache para deduplicar requests con misma idempotency_key. TTL de 24 horas.

### 5. Response 202 Inmediato
No espera a que los Jobs terminen. El cliente solo sabe que se encolaron las notificaciones.

## Estructura de Archivos

Dentro de `project_back/`:

```
app/
├── Actions/
│   └── DispatchNotificationAction.php
├── Contracts/
│   └── NotificationChannel.php
├── DTO/
│   └── NotificationDTO.php
├── Http/
│   ├── Controllers/V1/
│   │   └── NotificationController.php
│   └── Requests/
│       └── DispatchNotificationRequest.php
├── Jobs/
│   └── SendNotificationJob.php
├── Providers/
│   └── AppServiceProvider.php
└── Services/
    ├── NotificationRegistry.php
    ├── NotificationRouterService.php
    └── Notifiers/
        ├── EmailNotifier.php
        ├── SlackNotifier.php
        └── TelegramNotifier.php
```

## Fuera de Alcance (mencionado en spec)

- Tabla `notification_logs` para trazabilidad
- Rate limiting por canal
- Webhook/callback de confirmación

## Licencia

MIT
