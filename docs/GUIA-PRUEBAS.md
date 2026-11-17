# Guía de pruebas del sistema de Auditoría de Riesgos

*Versión del 2026-11-17.*

## Para qué es esta guía

El sistema ya pasa cientos de pruebas automáticas que comprueban que **funciona**: que un
cálculo da lo que tiene que dar, que nadie ve lo que no debe, que un botón hace lo que dice.
Lo que esas pruebas no pueden decir es si el sistema **responde a cómo trabajan ustedes**. Eso
solo lo pueden decir quienes lo van a usar.

Por eso les pedimos que recorran el sistema con esta guía. No es un examen del sistema ni de
ustedes. Buscamos tres tipos de respuesta:

1. **Algo no funciona**: un error, una pantalla que no carga, un número que no cierra.
2. **Funciona, pero no es como trabajamos**: una regla que no corresponde, un paso de más, un
   dato que falta, un nombre que no se entiende.
3. **Funciona y está bien**: también nos sirve saberlo, para no tocarlo.

El **Manual de Usuario** (`MANUAL_USUARIO.md`) explica cada pantalla en detalle. Conviene
tenerlo a mano: esta guía remite a sus secciones (por ejemplo, "Manual §6.2").

---

## Antes de empezar

### El entorno de prueba

- Van a trabajar sobre un **entorno de prueba**, con una copia de los datos cargados. Pueden
  crear, cambiar, rechazar y eliminar lo que quieran: nada de lo que hagan afecta datos reales.
- Los datos del entorno pueden volver a cargarse de cero entre una ronda de pruebas y otra.

### Usuarios

Para recorrer todo, hace falta entrar con **distintos roles**. Sistemas les va a pasar las
cuentas. Lo ideal es tener:

| Rol | Cantidad sugerida | Para qué |
|---|---|---|
| Usuario de un área (empleado) | 2, de **gerencias distintas** | Crear elementos y proponer cambios |
| Gerente | 2: el de cada uno de esos empleados | Validar, y comprobar que un gerente no ve lo de otro |
| Comité | 1 | Aprobar y rechazar lo validado |
| Auditor | 1 | Testear evidencia, reevaluar controles, proponer cambios |

Muchas pruebas piden pasar de un rol a otro. Lo más cómodo es tener dos navegadores, o una
ventana normal y otra de incógnito, con un usuario distinto en cada uno.

En esta guía los llamamos **Empleado A** y **Gerente A** (una gerencia), **Empleado B** y
**Gerente B** (otra gerencia), **Comité** y **Auditor**.

### Cómo anotar lo que encuentren

Cada prueba tiene un código (A1, A2, B1…). Al final hay una **planilla de registro** con todos los
códigos. Para cada prueba anoten:

- **Resultado**: ✅ OK · ⚠️ Observación · ❌ No funciona · ⏭️ No probado.
- **Observación**, si la hay. Cuanto más concreta, mejor:
  - **qué hicieron**: pantalla, botón, con qué usuario;
  - **qué esperaban** que pasara;
  - **qué pasó**;
  - si pueden, una **captura de pantalla**.

  Ejemplo: *"B3 · Comité · Al aprobar el riesgo 'Fraude en compras' no me dejó cargar el N° de
  comité con letras (ej. 'C-12'). Esperaba poder."*

Si encuentran algo fuera de las pruebas, anótenlo igual en la sección **Otras observaciones** de
la planilla.

---

## Recorridos

Cada recorrido tiene un objetivo, los pasos y lo que deberían ver. Al final de cada uno hay
preguntas **para pensar**: no tienen respuesta correcta. Son decisiones que queremos que tomen
ustedes.

### A. Alta de un riesgo y su cuestionario

**Objetivo:** comprobar que el cuestionario mide el riesgo como ustedes lo medirían.
**Usuario:** Empleado A. **Manual:** §4 y §6.2.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| A1 | Riesgos → **Nuevo Riesgo**. Paso 1: nombre, tipo **no ambiental**, una dimensión. | Al elegir una sola dimensión no se pide fundamento. |
| A2 | Volver y marcar **dos dimensiones**. Intentar avanzar sin fundamento. | Pide el fundamento de las dimensiones. |
| A3 | Paso 2: responder las 5 preguntas de probabilidad. Paso 3: las 10 de impacto (sí/no, doble materialidad). | Desde el paso 2, el panel derecho muestra el resumen en vivo con los subtotales. |
| A4 | Paso 4: revisar el valor total y su clasificación. Si da 14 o más, marcar **mayor criticidad**. | Bajo 0–9, moderado 10–13, crítico 14–20. La mayor criticidad solo se habilita desde 14. |
| A5 | Elegir respuesta **Aceptar** y no escribir fundamento. | No deja crear sin fundamento. |
| A6 | Completar área, sedes (probar **Todas**) y **Crear Riesgo**. | Va al detalle, en estado Borrador. La Ficha muestra la **Evaluación vigente** con sus respuestas. |
| A7 | Crear otro riesgo de tipo **Ambiental**. | El paso 1 pide la caracterización (temporalidad, condición, responsabilidad). Los pasos 2 y 3 muestran la matriz ambiental. La probabilidad sale siempre par. |
| A8 | En el riesgo de A6, **Editar** → **Recalcular**. Cambiar una sola respuesta. | El cuestionario arranca con las respuestas anteriores cargadas. Al guardar, el número cambia en el acto (es borrador). |
| A9 | En la edición, intentar cambiar el tipo del riesgo clásico a Ambiental. | No se ofrece: solo aparecen tipos del mismo método. |

**Para pensar:**
- Piensen tres riesgos reales que conozcan bien y cárguenlos. ¿El valor total que da el sistema
  coincide con lo que ustedes dirían? Si no, ¿qué pregunta lo desvía?
- ¿Las preguntas se entienden igual en todas las áreas? ¿Falta o sobra alguna?
- ¿Los umbrales bajo / moderado / crítico (9 y 13) son los correctos?
- ¿Las dimensiones y sus referencias alcanzan para clasificar?

### B. Ciclo completo: de borrador a aprobado

**Objetivo:** recorrer el camino normal de un riesgo con todo lo que necesita.
**Usuarios:** Empleado A → Gerente A → Comité. **Manual:** §3.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| B1 | Empleado A: crear un **objetivo**, un **control** y un **plan de acción** en su área. | Los tres nacen en Borrador. El control y el plan no piden valor al crearse. |
| B2 | En el riesgo de A6 (respuesta Reducir / Mitigar), asociar el objetivo, el control y el plan desde sus bloques. | Se agregan con el buscador. El valor del riesgo todavía no baja: nada está aprobado. |
| B3 | Gerente A: abrir el riesgo y tocar **Validar**. | La ventana de cascada ofrece validar también el objetivo, el control y el plan. Avisa que el control no tiene cuestionario y el plan no tiene valor estimado. |
| B4 | Empleado A: contestar el **cuestionario del control** (card Valor del control) y el **valor estimado del plan** (card Valor del plan). Crear dos tareas en el plan. | El control muestra puntaje y clasificación. El plan muestra cuánto reduce el impacto y la probabilidad. |
| B5 | Gerente A: **Validar** el riesgo con su cascada. | Todo pasa a Validado. Aparece en Pendientes del Comité. |
| B6 | Gerente B: buscar ese riesgo. | No lo ve: es de otra gerencia. Lo va a ver cuando esté aprobado. |
| B7 | Comité: desde **Pendientes**, **Aprobar** el riesgo y su cascada, con **N° de comité** y **Fecha**. | Todo pasa a Aprobado. La ficha muestra el comité junto al estado. |
| B8 | Comité: en otro elemento aprobado sin comité, cargar el comité después desde la ficha. | Queda registrado. En la Actividad se ve como una entrada nueva. |
| B9 | Mirar el **Valor del riesgo** ahora. | Descuentan el control y el plan aprobados. El plan descuenta según el avance de sus tareas **aprobadas**. |

**Para pensar:**
- ¿Es correcto que un riesgo a mitigar no se valide sin plan? ¿Y que todo riesgo necesite un
  objetivo?
- ¿La ventana de cascada ayuda o confunde?
- ¿El N° y la fecha de comité alcanzan para identificar la sesión, o hace falta registrar las
  sesiones del comité como tales (con fecha y acta)?

### C. Quién ve qué

**Objetivo:** comprobar que cada uno ve solo lo que corresponde.
**Usuarios:** todos. **Manual:** §2.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| C1 | Empleado A crea un riesgo y lo deja en Borrador. Buscarlo con Gerente A, Gerente B, Comité y Auditor. | Solo lo ven Empleado A y Gerente A. |
| C2 | Gerente A lo valida. Buscarlo con los demás. | Lo ven además el Comité, los auditores y los demás usuarios de la gerencia A. Gerente B y Empleado B todavía no. |
| C3 | Comité lo aprueba. | Lo ve toda la organización. |
| C4 | Con Gerente B, pegar en el navegador el link de un borrador de la gerencia A. | "No autorizado". |
| C5 | Empleado A: intentar validar algo propio. | No tiene el botón. |
| C6 | Auditor: intentar crear un riesgo o validar algo. | No puede. |
| C7 | Entrar a **Pendientes** con Empleado A y con Auditor. | No tienen acceso. |

**Para pensar:**
- ¿Es correcto que el Comité no vea los borradores?
- ¿Algún rol necesita ver algo que hoy no ve, o al revés?

### D. El valor de los controles

**Objetivo:** validar el cuestionario del control y la evidencia de cumplimiento.
**Usuarios:** Empleado A, Gerente A, Comité, Auditor. **Manual:** §7.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| D1 | Contestar el cuestionario de un control en borrador con distintas combinaciones. | Puntaje de 1 a 10. Deficiente menos de 4, moderado 4–6, suficiente 7 o más. "Sí" en documentado pide el procedimiento; en sistema, cuál; en frecuencia, cuál. |
| D2 | Contestar «No, es una expresión de deseo» en la primera pregunta. | El puntaje se muestra, pero el control mitiga 0. |
| D3 | En un control **aprobado**, Empleado A: **Proponer cambio de respuestas**. | Queda como propuesta. El valor del control no cambia hasta que se aprueba. |
| D4 | Mientras esa propuesta está pendiente, Auditor: intentar **Reevaluar (auditoría)**. | No se puede hasta que se resuelva la propuesta. |
| D5 | Resolver la propuesta. Auditor: **Reevaluar (auditoría)** con comentario y un archivo. | Se aplica en el acto. Queda en la Actividad del control. |
| D6 | Auditor: en **Riesgos que mitiga**, tocar **Testear** en un riesgo. Cargar evidencia 75, comentario y un archivo. | Se aplica en el acto. Esa relación aplica el 40% del valor del control. El residual del riesgo cambia. Los demás riesgos del control no cambian. |
| D7 | Testear la misma relación con el mismo porcentaje. | Queda como "Confirma". |
| D8 | Mirar una relación nunca testeada. | Muestra "—" en la evidencia. |
| D9 | Asociar el mismo control a dos riesgos de distinto impacto. | El control descuenta el mismo valor en los dos, salvo que su evidencia sea distinta. |

**Para pensar:**
- Carguen controles reales que conozcan. ¿La clasificación (deficiente, moderado, suficiente)
  coincide con su juicio?
- ¿Los puntos de cada pregunta están bien? (por ejemplo, ¿un sistema vale 3?)
- ¿Los tramos de evidencia (menos de 60 → 0%, 60–69 → 20%, … 99–100 → 100%) son los que
  aplicarían en una auditoría?
- ¿La lista de sistemas está completa?
- ¿Está bien que una relación sin testear cuente como 100% para el cálculo?
- Hoy un control resta del total. Los planes, en cambio, restan por separado del impacto y de la
  probabilidad. ¿Tendría sentido que los controles también actúen por eje?

### E. Planes de acción y tareas

**Objetivo:** validar el valor del plan, el avance y las tareas de otras áreas.
**Usuarios:** Empleado A, Gerente A, Gerente B, Comité. **Manual:** §9 y §10.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| E1 | En un plan en borrador, cargar el **valor estimado**. | Cada «sí» reduce 2 su eje. La card muestra Estimado y "Descuenta hoy". |
| E2 | Sin valor estimado, intentar validar el plan. | No deja. |
| E3 | Desde el plan, **Nueva tarea** con área de **otra gerencia** (la B). | La tarea queda en el plan, en Borrador. La valida Gerente B, no Gerente A. Empleado A la sigue viendo desde el plan. |
| E4 | Gerente A: **Nueva tarea** con un área de su propia línea. | Nace validada. |
| E5 | Gerente B rechaza la tarea de E3 con motivo. | Desaparece del plan. La Actividad del plan registra el rechazo con el motivo. |
| E6 | Con un plan aprobado, aprobar tareas con distintos avances y mirar el % del plan. | El avance es el promedio de las tareas aprobadas. Las no aprobadas no cuentan. |
| E7 | Mirar el residual del riesgo con el plan al 50%. | El plan descuenta la mitad de su valor, redondeado hacia abajo, con tope en el impacto y la probabilidad del riesgo. |
| E8 | Llevar todas las tareas al 100% (por propuesta, aprobada). Cargar la **evaluación final**. | Solo se habilita con el plan aprobado y al 100%. Al aplicarse, reemplaza al estimado y el plan queda finalizado. |
| E9 | Revisar **Vencimientos** con una tarea vencida. | Aparece en Vencidas. El plan muestra "Vencido" en el listado. |

**Para pensar:**
- ¿Las 10 preguntas del plan describen bien cómo un plan reduce un riesgo?
- ¿Está bien que un plan mitigue en proporción a su avance, o debería mitigar recién al terminar?
- ¿El plan debería tener su propia fecha de vencimiento? Hoy se deduce de sus tareas.
- ¿Está bien que una tarea de otra área la valide el gerente de esa área y no el del plan?

### F. Cambios sobre lo aprobado

**Objetivo:** comprobar el circuito de propuestas, rechazos y eliminaciones.
**Usuarios:** Empleado A, Gerente A, Comité, Auditor. **Manual:** §6.5, §6.8 y §11.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| F1 | Empleado A: en un riesgo aprobado, cambiar la descripción desde la Ficha. | Queda como propuesta en borrador. El riesgo sigue aprobado y con la descripción vieja. |
| F2 | Gerente A la valida. Comité la aprueba. | Recién ahí cambia. La Actividad muestra el antes y el después. |
| F3 | Empleado A: en un riesgo aprobado, **Recalcular**. | Queda como propuesta. El número no cambia hasta que el Comité la aprueba. |
| F4 | Comité: intentar recalcular un riesgo. | No puede. |
| F5 | Empleado A: en un riesgo aprobado, quitar un control y agregar otro. | Son dos propuestas separadas. Se pueden aprobar o rechazar por separado. |
| F6 | Gerente A rechaza una propuesta sin escribir motivo. | No deja. Con motivo, queda en la Actividad. |
| F7 | Auditor: proponer un cambio en un riesgo de cualquier área. | Nace en borrador y lo valida el gerente de esa área. |
| F8 | Empleado A: en un riesgo aprobado, **Más acciones → Proponer eliminación**. Marcar un control que solo está en ese riesgo. | Pide observaciones. Los controles y planes compartidos con otros riesgos aparecen deshabilitados. |
| F9 | Comité: rechazar la eliminación del riesgo y aprobar la del control. | El riesgo sigue. El control pasa a Borrado. |
| F10 | Comité: intentar rechazar un plan que es el único plan vigente de un riesgo a mitigar. | No deja y dice cuál es el riesgo. |

**Para pensar:**
- ¿El circuito (empleado → gerente → comité) para cada cambio sobre lo aprobado es el adecuado, o
  hay cambios menores que no deberían pasar por el comité?
- ¿Está bien que el rechazo sea definitivo (el elemento pasa a Borrado), o a veces debería volver
  al área para corregirse?
- Hoy controles, objetivos, planes y tareas se eliminan directo, con una confirmación. Solo el
  riesgo pasa por propuesta. ¿Debería ser igual para todos?

### G. Borrador interno

**Objetivo:** evaluar si la gestión interna sirve para las áreas con muchos riesgos propios.
**Usuarios:** Empleado A, Gerente A, Comité. **Manual:** §12.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| G1 | Crear un riesgo y un control marcados como **Interno**. Asociarlos. | Llevan el badge. El riesgo muestra un **Residual proyectado** donde descuenta el control interno. El residual oficial no cambia. |
| G2 | Gerente A: buscar el riesgo en Pendientes. Intentar validarlo. | No aparece en Pendientes y no se puede validar con la marca. |
| G3 | Entrar a **Interno** en el menú. | Riesgos internos ordenados por residual proyectado, planes internos y vencimientos de tareas internas. |
| G4 | Comité: buscar el riesgo interno. | No lo ve en ningún lado. |
| G5 | Quitar la marca desde la edición. | Pasa a la bandeja del gerente como un borrador normal. |

**Para pensar:**
- ¿Les sirve? ¿Qué áreas lo usarían?
- ¿El residual proyectado se entiende, o confunde con el oficial?

### H. Panel y reportes

**Objetivo:** comprobar que los reportes responden lo que el Comité necesita.
**Usuarios:** Comité y Gerente A. **Manual:** §5.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| H1 | Comparar el **Resumen** del Comité con el de Gerente A. | El Comité ve toda la organización. El gerente ve su cascada. |
| H2 | Apagar **Solo aprobados**. | Se suman los validados. Nunca aparecen borradores. |
| H3 | Revisar **Por gerencia** y **Por tipo**. Bajar el **Excel** de cada uno. | El Excel tiene lo mismo que la pantalla, con los filtros aplicados. |
| H4 | **Evolución**: elegir un rango que incluya las aprobaciones que hicieron en B y F. | Los aprobados y rechazados del período coinciden con lo que hicieron. |
| H5 | **Planes y tareas**: comparar con los planes que conocen. Bajar el Excel. | El Excel suma el detalle de tareas. |
| H6 | En la **Matriz de calor**, ubicar riesgos que conozcan. | Cada riesgo cae en su celda de impacto × probabilidad. |

**Para pensar:**
- ¿Estos reportes son los que se presentan en el Comité? ¿Qué falta?
- ¿Hace falta una versión en PDF además del Excel?
- ¿Qué gráfico o indicador les gustaría ver en el Resumen?

### I. Pendientes, Vencimientos, notas y documentos

**Usuarios:** Gerente A, Comité, Empleado A. **Manual:** §11, §13 y §14.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| I1 | Gerente A: revisar **Pendientes**. Validar y rechazar desde ahí. | Todos los borradores de su cascada y las propuestas pendientes. |
| I2 | Bajar el **PDF** de Pendientes. | El mismo listado, listo para imprimir. |
| I3 | Empleado A: dejar una **nota** y subir un **documento** en un elemento aprobado. | No genera propuesta ni aparece en Pendientes. |
| I4 | Revisar **Vencimientos** con cada rol. | Vencidas, por vencer (30 días), en plazo y sin fecha, según el área de cada uno. |

**Para pensar:**
- ¿Haría falta que el sistema avise (por mail u otro medio) cuando algo espera su validación o
  cuando una tarea está por vencer? ¿Qué avisos y a quién?
- ¿Por vencer a 30 días está bien?

### J. Revisión inicial

**Objetivo:** evaluar cómo se revisan y firman los datos migrados. **Esta parte está abierta**: lo
que opinen acá define cómo sigue.
**Usuarios:** Empleado A, Gerente A, Comité. **Manual:** §15 y la *Guía de la revisión inicial*.

| Código | Qué hacer | Qué deberían ver |
|---|---|---|
| J1 | Abrir la pantalla **Revisión inicial**. Recorrer el avance por gerencia y las pestañas. | Cada elemento con sus errores (rojo), advertencias (ámbar) y arreglos en curso (azul). |
| J2 | Abrir la ficha de un riesgo de la carga inicial. | La card **Para la revisión inicial** dice que falta recalcular. El bloque Valor avisa "Valor de la carga inicial". |
| J3 | Empleado A: recalcular ese riesgo y **Marcar revisado**. | El recálculo queda como propuesta. El elemento figura como revisado. |
| J4 | Gerente A: **Validar y firmar** desde la ficha. | Valida el recálculo y firma en un paso. Si queda un error, no hace nada. |
| J5 | Gerente A: firmar un elemento con advertencias. | Pide comentario obligatorio. |

**Para pensar** (esto es lo más importante de este bloque):
- ¿Qué debería **bloquear** la firma y qué solo advertir? Por ejemplo, hoy un control sin
  cuestionario o un plan sin valor estimado solo advierten.
- ¿Les sirve más revisar y corregir lo migrado elemento por elemento, o un **"reinicio"**? En un
  reinicio se conservarían solo los nombres y las relaciones (qué control mitiga qué riesgo, qué
  plan tiene qué tareas), y cada área completaría de cero los cuestionarios, valores y respuestas.
- ¿Cómo se debería forzar que los controles existentes contesten su cuestionario?

---

## Ejercicios de cálculo

Estos casos sirven para verificar a mano que el sistema calcula como esperan. Armen cada caso en
el sistema y comparen el resultado con la cuenta. La regla completa está en el Manual §4.

Recordatorio de la regla:

1. **Planes aprobados**: el valor del plan × el avance de sus tareas aprobadas, hacia abajo, eje
   por eje. La suma de cada eje no pasa del impacto o la probabilidad del riesgo.
2. **Controles aprobados**: el valor del control × el porcentaje del tramo de evidencia, hacia
   abajo. Se resta del total.
3. El residual no baja de 0.

**Caso 1: un plan a medio camino y un control.**
- Riesgo: impacto 6, probabilidad 5 → total **11** (moderado).
- Plan aprobado con valor estimado de impacto −6 (3 «sí») y de probabilidad −4 (2 «sí»). Tiene
  dos tareas aprobadas, al 40% y al 60%: avance 50%.
  - Impacto: 6 × 50% = 3. Probabilidad: 4 × 50% = 2. El plan descuenta **5**.
- Control aprobado con puntaje 7 y evidencia 75 en este riesgo. El tramo 70–79 aplica el 40%:
  7 × 40% = 2,8 → descuenta **2**.
- Residual: 11 − 5 − 2 = **4** (bajo).

**Caso 2: el tope por eje.**
- Riesgo: impacto 3, probabilidad 8 → total **11**.
- Plan aprobado al 100% con valor de impacto −10 (5 «sí») y de probabilidad −2 (1 «sí»).
  - Impacto: reduciría 10, pero el riesgo tiene 3 → **3**. Probabilidad: **2**. El plan descuenta
    **5**, no 12.
- Residual: 11 − 5 = **6**.

**Caso 3: casi completo no es completo.**
- Riesgo: impacto 4, probabilidad 4 → total **8**.
- Plan aprobado con valor de impacto −4. Tiene tres tareas aprobadas al 100%, 100% y 97%. La
  pantalla muestra el avance como 99%.
  - Impacto: 4 × 99% = 3,96 → descuenta **3**.
- Residual: 8 − 3 = **5**.

**Caso 4: los tramos de evidencia.**
Un control aprobado de puntaje 10, en riesgos distintos:

| Evidencia | Tramo | Descuenta |
|---|---|---|
| 59 | 0% | 0 |
| 60 | 20% | 2 |
| 85 | 60% | 6 |
| 95 | 80% | 8 |
| 98 | 80% | 8 |
| 99 | 100% | 10 |

**Caso 5: el cuestionario del control.**
- Respuestas: operativo sí (1), documentado sí (2), sistema no, es manual (1), responsable sí (1),
  frecuencia no (0), evidencia sí (1), previno o detectó errores no (0).
- Puntaje **6** → moderado.
- Con la primera respuesta en «expresión de deseo», el puntaje sería 5, pero el control
  **mitiga 0**.

**Caso 6: lo que no cuenta.**
- El mismo riesgo del caso 1, pero con el control **validado** (no aprobado): el control no
  descuenta. El residual es 11 − 5 = **6**.
- El plan con sus tareas en **borrador**: el plan no descuenta. El residual es 11 − 2 = **9**.

**Caso 7: el piso.**
- Riesgo: impacto 2, probabilidad 2 → total **4**. Control aprobado de puntaje 10, sin testear
  (vale 100%).
- 4 − 10 sería negativo: el residual es **0**.

**Para pensar:**
- ¿El orden (primero planes, después controles) es el correcto?
- ¿Está bien redondear siempre hacia abajo?
- ¿El caso 2 les parece razonable? Un plan muy fuerte en impacto no puede compensar con
  probabilidad.

---

## Cosas que ya sabemos

Para que no pierdan tiempo reportándolas, esto ya está identificado:

- **El Comité puede crear elementos, pero después no los ve** (el borrador recién creado le da
  "No autorizado"). Se va a resolver cuando se definan los permisos finales.
- **Controles, objetivos, planes y tareas se eliminan directo**, con una confirmación. Solo el
  riesgo pasa por propuesta (ver pregunta en F).
- **Los controles de la carga inicial** no tienen cuestionario: conservan su número anterior
  hasta que alguien lo conteste.
- **Los planes de la carga inicial no tienen valor estimado** y por eso hoy **no descuentan
  nada**: el residual de esos riesgos es más alto que antes, hasta que se valoren.
- **Los riesgos de la carga inicial no tienen evaluación**: muestran "Valor de la carga inicial"
  hasta que se recalculen.
- **No hay notificaciones** por mail ni dentro del sistema todavía.
- **El texto de referencia de la dimensión Gobernanza** llegó cortado.

---

## Preguntas que necesitamos que nos respondan

Además de lo que surja en los recorridos, hay decisiones pendientes que dependen de ustedes. Las
juntamos acá para que puedan contestarlas en un solo lugar.

| # | Pregunta | Recorrido |
|---|---|---|
| P1 | ¿Las preguntas y los puntajes del cuestionario del riesgo (clásico y ambiental) reflejan cómo evalúan? | A |
| P2 | ¿Los umbrales bajo / moderado / crítico son los correctos? | A |
| P3 | ¿Los puntos del cuestionario del control y su clasificación son los correctos? | D |
| P4 | ¿Los tramos de evidencia son los correctos? ¿Una relación sin testear debería valer 100%? | D |
| P5 | ¿Los controles deberían reducir por eje (impacto y probabilidad), como los planes? | D |
| P6 | ¿El plan debería mitigar en proporción a su avance o solo al terminar? | E |
| P7 | ¿El plan necesita su propia fecha de vencimiento? | E |
| P8 | ¿Qué cambios sobre lo aprobado deberían pasar por el Comité y cuáles no? | F |
| P9 | ¿El rechazo debe ser definitivo o devolver el elemento al área? | F |
| P10 | ¿La eliminación de controles, objetivos, planes y tareas debería pasar por propuesta, como la del riesgo? | F |
| P11 | ¿Los reportes del Panel son los que necesita el Comité? ¿Hace falta PDF? | H |
| P12 | ¿Hace falta registrar las sesiones del Comité (fecha, acta) como algo propio? | B, H |
| P13 | ¿Qué avisos automáticos necesitan, a quién y por qué medio? | I |
| P14 | En la revisión inicial, ¿qué debería bloquear la firma y qué solo advertir? | J |
| P15 | ¿Revisar lo migrado uno por uno, o reiniciar y conservar solo nombres y relaciones? | J |
| P16 | ¿Cómo se fuerza que los controles y planes existentes se valoren? | J |

---

## Planilla de registro

Copien esta tabla (o impriman esta página) y completen una fila por prueba.

**Probado por:** ______________________ **Rol(es) usados:** ______________________ **Fecha:** __________

| Código | Resultado (✅ ⚠️ ❌ ⏭️) | Observación |
|---|---|---|
| A1 | | |
| A2 | | |
| A3 | | |
| A4 | | |
| A5 | | |
| A6 | | |
| A7 | | |
| A8 | | |
| A9 | | |
| B1 | | |
| B2 | | |
| B3 | | |
| B4 | | |
| B5 | | |
| B6 | | |
| B7 | | |
| B8 | | |
| B9 | | |
| C1 | | |
| C2 | | |
| C3 | | |
| C4 | | |
| C5 | | |
| C6 | | |
| C7 | | |
| D1 | | |
| D2 | | |
| D3 | | |
| D4 | | |
| D5 | | |
| D6 | | |
| D7 | | |
| D8 | | |
| D9 | | |
| E1 | | |
| E2 | | |
| E3 | | |
| E4 | | |
| E5 | | |
| E6 | | |
| E7 | | |
| E8 | | |
| E9 | | |
| F1 | | |
| F2 | | |
| F3 | | |
| F4 | | |
| F5 | | |
| F6 | | |
| F7 | | |
| F8 | | |
| F9 | | |
| F10 | | |
| G1 | | |
| G2 | | |
| G3 | | |
| G4 | | |
| G5 | | |
| H1 | | |
| H2 | | |
| H3 | | |
| H4 | | |
| H5 | | |
| H6 | | |
| I1 | | |
| I2 | | |
| I3 | | |
| I4 | | |
| J1 | | |
| J2 | | |
| J3 | | |
| J4 | | |
| J5 | | |
| Cálculo 1–7 | | |

**Otras observaciones** (lo que encontraron fuera de las pruebas):

| Pantalla | Qué pasó / qué cambiarían |
|---|---|
| | |
| | |
| | |
