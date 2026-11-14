# Proposal

## Why

El proyecto necesita una secuencia de trabajo verificable antes de ampliar sus canales. La API se consolidó en `project_back/`, separada de infraestructura y documentación, como base para un entorno reproducible.

## What Changes

- Reemplazar el esqueleto anterior de `project_back/` por la API Laravel existente en la raíz, incluidos configuración local, pruebas y lockfile.
- Agrupar infraestructura en `docker/`, ajustar Compose al backend y excluir secretos y artefactos del contexto Docker.
- Corregir el atributo XML preexistente inválido de PHPUnit (`testdox`) y ubicar `.editorconfig` en la raíz del backend.
- Documentar la estructura y los comandos de trabajo en README y `docs/structure.md`.
- Crear `docs/roadmap.md` en español con diagnóstico, fases, dependencias y criterios de salida.
- Detallar primero la estabilización local: Docker, dependencias PHP, SQLite, Redis, correo de prueba, worker y arranque documentado.
- Ordenar posteriormente calidad y CI, fiabilidad, seguridad, canales y operación.
- Identificar propuestas y riesgos sin presentar capacidades pendientes como implementadas.

## Capabilities

### New Capabilities

Ninguna. Este cambio entrega documentación y reorganización de archivos sin alterar el contrato de API; `skip_specs: true` evita introducir requisitos funcionales artificiales.

### Modified Capabilities

Ninguna. La ejecución de las fases requerirá cambios posteriores con sus propios requisitos.

## Impact

Se trasladan los archivos Laravel de la raíz a `project_back/` y Dockerfile a `docker/php/`; se actualizan contexto y montajes de Compose, exclusiones y documentación. El contenido anterior de `project_back/` se elimina por solicitud del usuario. Los comandos del host para Artisan y Composer deben ejecutarse desde `project_back/`; los comandos Make/Compose permanecen en la raíz. No cambia el contrato HTTP ni se incorporan dependencias. El resto de las fases del roadmap queda pendiente.
