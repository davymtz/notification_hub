# Dependencias Laravel — revisión inicial

Revisión estática, resolución Composer, instalación con dependencias de desarrollo y suite completa verificadas según ejecución confirmada por el usuario. El script también ejecutó composer why para Guzzle, Mockery y Collision. La instalación sin require-dev y la integración aislada también pasaron según salida del usuario. La actualización de SBOM/auditorías de imagen permanece pendiente. No se eliminarán dependencias transitivas obligatorias de Laravel.

| Paquete directo | Decisión | Evidencia / verificación pendiente |
| --- | --- | --- |
| php | Conservar | Runtime; imagen verificada en 8.4.27 |
| laravel/framework | Conservar y actualizar dentro de 12.x, mínimo 12.69 | Core de la API; auditoría inicial reportó 3 avisos |
| guzzlehttp/guzzle | Conservar | SlackNotifier usa Http de Laravel; transporte HTTP necesario |
| laravel/tinker | Retirado del manifiesto/lockfile | No hay uso de consola Tinker en código, pruebas o flujo documentado |
| laravel/sail | Retirado del manifiesto/lockfile | El entorno usa Dockerfile y Compose propios, sin comandos Sail |
| spatie/laravel-ignition | Retirado del manifiesto/lockfile | Diagnóstico opcional; únicas referencias directas en config/ignition.php; retirar esa configuración |
| fakerphp/faker | Retirado del manifiesto/lockfile | Sin factories/seeders ni pruebas que lo usen en el árbol actual |
| laravel/pint | Conservar en desarrollo | Formateador previsto en roadmap |
| phpunit/phpunit | Conservar en desarrollo | Suite existente de regresión |
| mockery/mockery | Conservar en desarrollo | Infraestructura de mocks del testing Laravel; verificar composer why y suite |
| nunomaduro/collision | Conservar en desarrollo | Comando artisan test y diagnóstico de pruebas; verificar ejecución |

`composer-remediate.sh` retira candidatos mediante Composer, actualiza selectivamente los cinco paquetes vulnerables y sus dependencias necesarias y no instala vendor. Primero se ejecuta el solver con plugins/scripts desactivados; después validate y audit. Si falla resolución/auditoría, restaura composer.json y composer.lock anteriores. La configuración Ignition solo se retira tras una auditoría satisfactoria. Se desactivan los plugins mediante allow-plugins=false: no se necesita Pest ni descubrimiento HTTP mediante plugin en el flujo actual; comprobar instalación posterior sin habilitarlos globalmente.

Estos cambios todavía requieren revisar el diff real, composer why, instalación limpia y pruebas. El script no actualiza automáticamente todos los paquetes del proyecto ni desactiva bloqueos de seguridad.

## Actualización adicional de seguridad

La siguiente auditoría detectó CVE-2026-45070 en symfony/mime 7.4.15. Se prepara una actualización transitoria en la rama 7.4, mínimo 7.4.22 según el reporte Composer. No se añade Mime como dependencia directa del manifiesto ni se modifica manualmente el lockfile; la restricción temporal del solver se aplica en composer-security-update.sh. Pendiente de resolver, auditar e instalar.
