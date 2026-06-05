# Plan de implementacion: validacion documental, pago y Libelula

## Objetivo

Definir como se implementara el flujo de pago del CUP FICCT usando una pasarela externa, preferentemente Libelula, dejando claro que esta sera una fase final del sistema.

Antes de implementar la pasarela se priorizara la carga e insercion de datos base para evitar problemas en pruebas, asignaciones, horarios, cupos, postulantes y estados del proceso.

## Decision principal

No se debe cobrar al postulante al momento de enviar la postulacion.

El pago se habilita solo despues de que administracion valide los documentos del postulante.

Esto evita devoluciones masivas cuando un postulante:

- sube documentos incorrectos;
- sube documentos borrosos;
- no cumple requisitos;
- adjunta un archivo que no corresponde;
- tiene observaciones en carnet, libreta, titulo de bachiller u otros requisitos.

## Flujo aprobado

```text
1. Postulante envia formulario y documentos
2. Sistema registra la postulacion
3. Administrador revisa documentos
4. Si hay observaciones, se devuelve al postulante para corregir
5. Si todo esta aprobado, se habilita el pago
6. Sistema genera deuda/link/QR en Libelula
7. Postulante paga mediante Libelula
8. Libelula confirma el pago al sistema
9. Sistema marca la inscripcion como pago confirmado
10. Sistema genera numero de registro y contrasena temporal
11. Sistema envia credenciales al Gmail del postulante
12. Postulante inicia sesion y puede cambiar contrasena
```

## Estados sugeridos

### Inscripcion

```text
prepostulado
documentos_pendientes
documentos_aprobados
pago_pendiente
pago_confirmado
inscrito
rechazado
```

### Orden de pago

```text
pendiente
pagado
vencido
anulado
error
```

## Bandejas del sistema

### Bandeja de validacion documental

Uso: administracion.

Debe mostrar postulantes con documentos pendientes, observados o rechazados.

El administrador revisa:

- carnet de identidad;
- libreta o titulo de bachiller;
- documentos obligatorios del proceso;
- archivos adjuntos correctos y legibles.

Resultado:

- si observa, el postulante corrige;
- si rechaza, no se habilita pago;
- si aprueba, se habilita generacion de pago.

### Bandeja de pago

Uso: cajero, administrador o sistema automatico.

Debe mostrar:

- postulantes con documentos aprobados;
- estado de pago;
- link de pago;
- QR si Libelula lo devuelve;
- fecha de vencimiento;
- identificador de transaccion.

### Portal del postulante

Despues de documentos aprobados, el postulante debe ver:

```text
Tus documentos fueron aprobados.
Pago pendiente.
Paga aqui:
[Boton Pagar con Libelula]
[QR de pago]
```

Despues del pago:

```text
Pago confirmado.
Tus credenciales fueron enviadas a tu Gmail registrado.
```

## Funcionamiento esperado de Libelula

Libelula funciona como intermediario entre el sistema CUP, el estudiante y los medios de pago.

El sistema registra una deuda en Libelula con:

- identificador de deuda;
- monto;
- concepto;
- datos del postulante;
- correo del postulante;
- fecha de vencimiento;
- URL de confirmacion o callback.

Libelula devuelve datos como:

- link de pasarela;
- identificador de transaccion;
- QR simple, si esta habilitado;
- estado de deuda o pago.

El postulante paga mediante los canales habilitados, por ejemplo:

- QR simple;
- tarjeta;
- banco;
- otros medios que Libelula tenga configurados para la institucion.

## Cuenta bancaria e institucion

Para usar Libelula, la institucion debe afiliarse como comercio, empresa o entidad.

Normalmente se requiere:

- datos legales de la institucion;
- NIT, si corresponde;
- cuenta bancaria nacional de destino;
- correo administrativo;
- credenciales de API;
- configuracion de URLs de retorno o callback;
- acuerdo de comisiones/liquidaciones.

El flujo financiero esperado es:

```text
Postulante paga
    |
    v
Libelula procesa/recauda
    |
    v
Libelula confirma al sistema CUP
    |
    v
Libelula liquida a la cuenta bancaria de la institucion
```

## Tabla sugerida: ordenes_pago

```text
id
inscripcion_id
proveedor
identificador_deuda
id_transaccion
monto
moneda
estado
url_pasarela
qr_url
fecha_vencimiento
pagado_en
created_at
updated_at
```

Ejemplo:

```text
proveedor: libelula
monto: 200.00
moneda: BOB
estado: pendiente
```

## Variables de entorno sugeridas

```env
LIBELULA_APP_KEY=
LIBELULA_BASE_URL=
LIBELULA_CALLBACK_URL=
CUP_MONTO_INSCRIPCION=200
```

## Endpoints sugeridos

```text
POST /api/inscripciones/{id}/orden-pago
GET  /api/postulante/pago
GET  /api/pagos/libelula/exitoso
POST /api/pagos/libelula/conciliar
```

## Servicio sugerido

```text
LibelulaPaymentService

- registrarDeuda(inscripcion)
- consultarEstado(ordenPago)
- procesarCallback(request)
- anularOrden(ordenPago)
```

## Regla de credenciales

El sistema solo debe emitir numero de registro y contrasena cuando:

```text
documentos_aprobados + pago_confirmado
```

El numero de registro:

- no se modifica;
- debe ser unico;
- debe estar formado solo por digitos;
- se guarda como string para evitar perder ceros o formato.

La contrasena temporal:

- debe ser fuerte;
- debe enviarse al Gmail registrado;
- el usuario puede cambiarla desde su panel;
- tambien puede recuperarla con la opcion "Olvide mi contrasena".

## Orden de implementacion recomendado

### Fase 1: datos base

Antes de Libelula se generaran datos controlados para:

- gestiones;
- carreras;
- cupos por carrera;
- materias CUP;
- aulas;
- grupos;
- docentes;
- horarios;
- postulantes;
- documentos;
- evaluaciones;
- resultados;
- asignaciones.

Esta fase sirve para probar el sistema sin depender todavia de la pasarela.

### Fase 2: pago simulado

Crear la estructura de ordenes de pago, pero permitir confirmacion manual o simulada.

Esto permite probar:

- documentos aprobados;
- generacion de orden;
- estado pago pendiente;
- pago confirmado;
- emision de credenciales.

### Fase 3: Libelula como prueba unica

Integrar Libelula solo como prueba final.

Objetivo:

- generar una deuda real o de sandbox;
- obtener link/QR;
- confirmar pago;
- recibir callback;
- marcar la inscripcion como pagada.

### Fase 4: produccion

Solo despues de probar correctamente:

- credenciales API;
- cuenta bancaria;
- callbacks;
- conciliacion;
- vencimientos;
- estados de error;
- comprobantes.

## Recomendacion final

La pasarela de pago debe quedar como ultima implementacion.

Primero se debe tener el sistema funcionando con datos insertados y pago simulado. Despues se conecta Libelula como una sola prueba controlada, para evitar errores de flujo, devoluciones innecesarias o pagos generados antes de validar documentos.
