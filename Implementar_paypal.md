Actúa como Arquitecto de Software Senior especializado en Laravel, bases de datos relacionales, sistemas académicos y pasarelas de pago PayPal.

Tu primera tarea NO es generar código.

Primero debes analizar completamente el proyecto existente y elaborar un plan técnico integral de implementación.

## Objetivo General

Implementar una pasarela de pago real mediante PayPal para el proceso de postulación CUP.

Actualmente ya existe un flujo de postulación y validación documental. La nueva funcionalidad debe integrarse respetando la lógica existente y minimizando cambios innecesarios.

Analiza el proyecto completo antes de proponer modificaciones.


## flujo requerido

### Postulación

* El estudiante continúa enviando documentación como actualmente.
* La documentación debe permanecer almacenada históricamente.
* Si el estudiante reprueba una gestión futura, NO debe volver a subir documentos ya aprobados anteriormente.

### Validación documental

* El administrador revisa la documentación.
* Puede aprobar o rechazar.

### Rechazo

* Debe registrarse el motivo de rechazo.
* El estudiante debe poder visualizar posteriormente las observaciones realizadas por el administrador.
* Debe existir un apartado visible para consultar observaciones y estado de postulaciones, puede acceder mediante su ci.

### Aprobación

* Cuando la documentación sea aprobada:

  * Se habilita automáticamente la opción de pago.
  * El estudiante puede acceder al pago desde su área correspondiente.

### Pago

* El pago debe ser REAL mediante PayPal.
* No debe ser un pago simulado.
* Debe registrar información real de la transacción.
* Debe almacenar:

  * ID de transacción PayPal.
  * Estado del pago.
  * Fecha y hora.
  * Monto.
  * Referencia de la gestión.
  * Usuario asociado.

### Gestiones futuras

* Cuando exista una nueva gestión:

  * El estudiante NO debe volver a cargar documentación previamente aprobada.
  * Debe reutilizarse la documentación validada.
  * Solo debe realizar nuevamente el pago correspondiente a la nueva gestión.
  * El sistema debe mantener historial completo de postulaciones y pagos por gestión.

---

## Consulta de estado

Analiza si es recomendable implementar un módulo o sección denominada:

"Estado de Postulación"

o

"Seguimiento de Postulación"

visible después del login.

Desde allí el estudiante debe poder visualizar:

* Estado actual de su postulación.
* Observaciones de rechazo.
* Historial de postulaciones.
* Historial de pagos.
* Estado de validación documental.
* Estado de pago.
* Acceso al pago cuando corresponda.

Determina la mejor ubicación dentro de la interfaz actual.

---

## Requisito de identificación

Analiza la lógica actual de autenticación.

Determina:

* Si el estudiante ya está asociado mediante CI.
* Si es necesario utilizar el CI para localizar postulaciones.
* Si es mejor utilizar usuario autenticado.
* Qué alternativa es más segura y consistente con la arquitectura actual.

No asumas nada sin analizar el código.

---

## PayPal

La implementación debe contemplar dos entornos:

### Sandbox

Para desarrollo y pruebas.

### Live

Para producción.

El sistema debe quedar preparado para cambiar entre ambos mediante variables de entorno.

Ejemplo esperado:

* PAYPAL_MODE=sandbox
* PAYPAL_MODE=live

Las credenciales NO deben estar hardcodeadas.

Debes identificar:

* Configuración necesaria.
* Servicios Laravel requeridos.
* Endpoints requeridos.
* Webhooks requeridos.
* Validaciones de seguridad requeridas.

La migración de Sandbox a Live debe requerir únicamente cambios de configuración y no modificaciones en la lógica de negocio.

---

## Compatibilidad con pagos simulados existentes

Existe la posibilidad de que actualmente haya registros de pagos simulados o procesos manuales.

Debes analizar:

* Cómo funcionan actualmente.
* Qué tablas utilizan.
* Qué datos almacenan.
* Si deben migrarse.
* Si deben mantenerse por compatibilidad.
* Si deben reemplazarse completamente.

Propón la alternativa más segura.

---

## Análisis requerido

Genera un informe detallado con:

### 1. Diagnóstico de la arquitectura actual

### 2. Módulos afectados

### 3. Cambios en frontend

### 4. Cambios en backend Laravel

### 5. Cambios en base de datos si son necesarios

### 6. Nuevas tablas necesarias

### 7. Nuevas columnas necesarias

### 8. Relaciones requeridas

### 9. Estados de postulación

### 10. Estados de pago

### 11. Flujo completo de negocio

### 12. Diseño de integración PayPal

### 13. Estrategia Sandbox y Live

### 14. Manejo de webhooks

### 15. Historial de gestiones

### 16. Historial documental

### 17. Historial de pagos

### 18. Riesgos técnicos

### 19. Riesgos de datos

### 20. Riesgos de seguridad

### 21. Plan de implementación paso a paso

### 22. Orden recomendado de desarrollo

### 23. Lista exacta de archivos a modificar

### 24. Lista exacta de migraciones necesarias

### 25. Estimación de impacto sobre el sistema existente

Importante:

NO generes código todavía.

Primero inspecciona el proyecto completo y entrega únicamente el plan técnico detallado basado en la estructura real encontrada en el código.
