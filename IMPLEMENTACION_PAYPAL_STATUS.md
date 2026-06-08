# Estado de Implementación: Pasarela de Pago PayPal

## Fase actual
**Fase 5: Pruebas y despliegue final** - *En proceso*

## Fases completadas
* **Fase 1:** Gestiones Futuras (Clonación de Docs en Repostulación) - Completada.
* **Fase 2:** Setup PayPal Backend (Variables `.env`, configuración, `PayPalService`) - Completada.
* **Fase 3:** Endpoints de Pago (`PayPalController` y Webhooks seguros) - Completada.
* **Fase 4:** Estado de Postulación y Botón de Pago (Frontend Vue.js) - Completada.
* **Fase 4.1:** Ajuste de flujo: Validación Documental → PayPal (visibilidad condicional) - Completada.

## Fases pendientes
1. ~~**Fase 1:** Gestiones Futuras (Clonación de Docs en Repostulación).~~
2. ~~**Fase 2:** Setup PayPal Backend (Variables `.env`, configuración, `PayPalService`).~~
3. ~~**Fase 3:** Endpoints de Pago (`PayPalController` y Webhooks seguros).~~
4. ~~**Fase 4:** Estado de Postulación y Botón de Pago (Frontend Vue.js).~~
5. **Fase 5:** Pruebas y despliegue final.

## Archivos modificados
* `app/Services/PortalPostulante/RepostulacionService.php`
* `config/services.php`
* `.env.example`
* `.env`
* `app/Services/PortalPostulante/PayPalService.php` (Nuevo archivo)
* `app/Http/Controllers/PortalPostulante/PayPalController.php` (Nuevo archivo)
* `routes/web.php`
* `bootstrap/app.php`
* `resources/js/api/pagos.js`
* `resources/js/components/PayPalCheckout.vue` (Nuevo archivo)
* `resources/js/pages/reportes_exportaciones/DashboardPostulante.vue`
* `app/Http/Controllers/ReportesExportaciones/DashboardController.php` (Fase 4.1)

## Migraciones creadas
* Ninguna.

## Problemas encontrados
* Ninguno.

## Decisiones técnicas tomadas
* Utilización de la tabla `pagos` existente, diferenciando mediante la columna `metodo = 'paypal'`.
* Integración exclusiva mediante Webhooks para confirmación final del pago (aunque se provee idempotencia en el método `captureOrder` para mejorar UX).
* Clonación física de registros de documentos en base de datos para la funcionalidad de gestiones futuras sin duplicar archivos (Implementado en `RepostulacionService`).
* Configuración de credenciales PayPal abstraída en el array de `services` nativo de Laravel.
* Exclusión del Webhook de PayPal de la validación CSRF en `bootstrap/app.php`.
* Carga del SDK de PayPal mediante script dinámico en el componente `PayPalCheckout.vue` para optimizar el bundle de Vue y usar credenciales expuestas en Vite (`VITE_PAYPAL_CLIENT_ID`).
* Reutilización completa de los estados existentes de `InscripcionState` sin crear nuevos. El flujo se blinda por máquina de estados: `prepostulado` → (admin valida) → `documentos_aprobados` → (pago PayPal) → `pagado`.
* Se expusieron `validacion_documental` y `documentos` (con observaciones) en la respuesta del endpoint `/api/postulante/academico` para que el frontend muestre detalles de rechazo/observación sin necesidad de endpoints adicionales.

## Próximos pasos
* Ejecutar pruebas de flujo completo (Sandbox → Webhook → Estado en BD).
* Documentar el procedimiento de configuración del Webhook ID en el Dashboard de desarrollador de PayPal.

