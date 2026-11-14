# Notification Hub — Spec de Ejecución

## Contexto
Prueba técnica: microservicio Notification Hub en Laravel que centraliza el
envío de notificaciones a múltiples canales (Slack, Email, extensible a
Telegram/SMS/Push) vía un único endpoint HTTP. Patrón: Strategy + Registry
resuelto por container de Laravel, DTO tipado, Jobs asíncronos (ShouldQueue),
un Job por canal. Deadline: **[completar hora exacta]**.

## Objetivo
Exponer `POST /v1/notifications/dispatch`, validar el request, despachar un
Job por canal solicitado, y que cada Job resuelva su Notifier vía Registry y
lo envíe (o simule el envío) al proveedor externo, con reintentos y manejo de
fallos parciales.

---

## Requisitos funcionales (atómicos, en orden de ejecución)

### 1. Contrato base y validación
- [ ] `POST /v1/notifications/dispatch` acepta JSON: `event_type` (string),
      `channels` (array), `idempotency_key` (string), `payload` (object).
- [ ] Form Request valida: `event_type` requerido, `channels` array no vacío
      con valores dentro de un enum de canales soportados, `payload`
      requerido según reglas mínimas (ej. `email` si el canal `email` está
      presente).
- [ ] Canal no soportado o payload incompleto → 422 con detalle del error.

### 2. DTO
- [ ] Crear DTO (`NotificationDTO` o similar) tipado que represente el
      request validado. Nada de arrays sueltos entre capas.

### 3. Action
- [ ] `DispatchNotificationAction`: recibe el request validado, construye el
      DTO, delega al Router. El Controller solo orquesta HTTP (no lógica de
      negocio).

### 4. Router + Registry
- [ ] `NotificationRouterService`: itera `channels` del DTO y por cada uno
      resuelve el Notifier vía `NotificationRegistry` y despacha un Job.
- [ ] `NotificationRegistry`: resuelve implementaciones vía container de
      Laravel (binding por canal), no switch/factory con condicionales.
- [ ] Interface `NotificationChannel` con método `send()` — contrato común
      para todos los canales.

### 5. Jobs
- [ ] `SendNotificationJob implements ShouldQueue`: un Job por canal, recibe
      el DTO y el nombre del canal, resuelve el Notifier del Registry y
      llama `send()`.
- [ ] `retryUntil()` / `backoff()` configurados (ej. 3 intentos, backoff
      10s/30s/60s).
- [ ] `failed()` implementado — al agotar reintentos cae en `failed_jobs`.

### 6. Notifiers concretos
- [ ] `SlackNotifier implements NotificationChannel` — integración real o
      mockeada según lo que permita el tiempo (documentar cuál se eligió).
- [ ] `EmailNotifier implements NotificationChannel` — vía Mail de Laravel.

### 7. Endpoint response
- [ ] Response 202 inmediato con `status`, `event_id`, `channels_queued`,
      sin esperar a que los Jobs terminen.

### 8. Idempotencia (si el tiempo alcanza)
- [ ] Antes de encolar, checar `idempotency_key` contra cache/Redis; si ya
      existe, no reencolar y responder igual (o con flag `duplicate: true`).

### 9. Extensibilidad demostrable
- [ ] Agregar un canal nuevo (ej. `TelegramNotifier` fake) debe requerir
      solo: crear la clase + un binding en el Registry. Nada más se toca.
      Esto es lo que vas a demostrar con el test de extensibilidad (#11).

---

## Testing (mínimo para defender la prueba)
- [ ] Feature test: `POST /dispatch` con canales válidos → 202 +
      `Queue::fake()` + `assertPushed()` por cada canal.
- [ ] Unit test de validación: canal no soportado → 422.
- [ ] Unit test de extensibilidad: registrar un canal fake en tiempo de
      test, verificar que se resuelve sin tocar Router/Controller.

## Fuera de alcance (mencionar en el README, no implementar salvo que sobre tiempo)
- Tabla `notification_logs` para trazabilidad de estado por canal.
- Rate limiting por canal.
- Webhook/callback de confirmación de envío.

---

## Criterios de aceptación finales
- [ ] `docker compose up` levanta todo sin pasos manuales extra.
- [ ] Request válido → 202 y Jobs visibles en `queue:work` o en logs.
- [ ] Request inválido → 422 con mensaje claro.
- [ ] Agregar un canal nuevo no requiere tocar Controller/Router/Action.
- [ ] Los 3 tests de la sección Testing pasan (`php artisan test`).
- [ ] README corto: cómo correr, cómo probar el endpoint (curl/Postman),
      decisiones de diseño en 3-4 bullets (mismo trade-off del doc original).

## Restricciones
- No modificar la config de Docker ya provista salvo que sea estrictamente
  necesario (documentar el cambio si ocurre).
- Seguir la arquitectura descrita (Strategy + Registry + Action + Job por
  canal) — es lo que se está evaluando, no un CRUD simple.
