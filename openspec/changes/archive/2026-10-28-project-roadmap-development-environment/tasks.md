# Tasks

## 1. Estructura del repositorio

- [x] 1.1 Sustituir el contenido anterior de project_back por la API raíz; verificar integridad de todos los archivos trasladados mediante SHA-256 y ausencia de Laravel duplicado en raíz.
- [x] 1.2 Mover Dockerfile a docker/php y ajustar contexto y montajes Compose a project_back; comprobar rutas existentes y document root coherente.
- [x] 1.3 Añadir exclusiones Docker y Git para secretos y artefactos del backend; verificar que .env no aparece entre archivos publicables.

- [x] 1.4 Corregir testdox en phpunit.xml y ubicar .editorconfig en la raíz del backend; verificar XML bien formado y archivos de configuración presentes.

## 2. Documentación y coherencia

- [x] 2.1 Documentar estructura y comandos en docs/structure.md y README; verificar enlaces y rutas.
- [x] 2.2 Actualizar roadmap, propuesta y diseño con la consolidación y sus límites; verificar fases y criterios de salida.
- [x] 2.3 Validar OpenSpec y estructura estática; registrar explícitamente las verificaciones de ejecución pendientes por ausencia de PHP, Composer y Docker.
