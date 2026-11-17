# Manual de Usuario — Módulo de Auditoría de Riesgos

Este manual explica, paso a paso, cómo usar el módulo de Auditoría de Riesgos. Está pensado
para cualquier persona que use el sistema en su trabajo diario, sin necesidad de conocimientos
técnicos. No incluye capturas de pantalla: describe en palabras cada pantalla, cada botón y
cada opción, en el orden en que normalmente los vas a encontrar.

*Última revisión: 2026-11-17.*

## Índice

1. [Ingresar al sistema y navegar](#1-ingresar-al-sistema-y-navegar)
2. [Roles y quién ve qué](#2-roles-y-quién-ve-qué)
3. [El ciclo de vida de todo elemento](#3-el-ciclo-de-vida-de-todo-elemento)
4. [Cómo se calcula el valor de un riesgo](#4-cómo-se-calcula-el-valor-de-un-riesgo)
5. [Panel de Riesgos y reportes](#5-panel-de-riesgos-y-reportes)
6. [Riesgos](#6-riesgos)
7. [Controles](#7-controles)
8. [Objetivos y catálogo PEIS](#8-objetivos-y-catálogo-peis)
9. [Planes de Acción](#9-planes-de-acción)
10. [Tareas](#10-tareas)
11. [Notas, documentos, actividad y propuestas de cambio](#11-notas-documentos-actividad-y-propuestas-de-cambio)
12. [Borrador interno y pantalla Interno](#12-borrador-interno-y-pantalla-interno)
13. [Pendientes](#13-pendientes)
14. [Vencimientos](#14-vencimientos)
15. [Revisión inicial](#15-revisión-inicial)
16. [Glosario rápido](#16-glosario-rápido)

---

## 1. Ingresar al sistema y navegar

Entrás al sistema con tu email y contraseña en la pantalla de login. Si los datos son
correctos, caés directamente en el **Panel** (el panel de riesgos). Si te equivocás, el
sistema te avisa y podés reintentar.

Arriba de cada pantalla hay una barra de navegación fija con estos accesos:

- **Panel** — la pantalla de inicio, con el resumen y los reportes (ver sección 5).
- **Objetivos**, **Riesgos**, **Controles**, **Planes de Acción**, **Tareas** — el listado de
  cada una de esas entidades.
- **Vencimientos** — visible para cualquier usuario logueado (ver sección 14).
- **Interno** — la gestión interna de tu área (ver sección 12). No aparece para el comité.
- **Pendientes** — visible solo si sos gerente o formás parte del comité (ver sección 13).
- **Revisión inicial** — mientras dure la revisión de los datos migrados (ver sección 15).
- Tu nombre, arriba a la derecha, y junto a él el botón **Salir** para cerrar sesión.

No hay una pantalla de "mi perfil" editable dentro del módulo: tus datos (nombre, área, rol)
los administra otra parte del sistema.

---

## 2. Roles y quién ve qué

Antes de entrar en el detalle de cada pantalla conviene entender esto, porque determina qué vas
a ver en cada listado y qué botones te van a aparecer.

### La organización es un árbol de áreas

Cada área depende de un área padre, hasta llegar al comité en la raíz. Por ejemplo:

```
Comité (raíz)
├── Gerencia Administración
│   ├── Sector Contabilidad
│   └── Sector Recursos Humanos
└── Gerencia Producción
    ├── Planta A
    └── Planta B
```

Cada elemento (riesgo, control, objetivo, plan o tarea) pertenece a **una sola área**. No hay
elementos compartidos entre gerencias.

### Los cuatro roles

- **Comité**: no pertenece a ningún área puntual. Opera sobre lo público de toda la
  organización. Es quien da la aprobación final y quien rechaza lo validado que le llega.
- **Gerente de área**: gestiona su área y todas las sub-áreas que cuelgan de ella. No ve ni
  gestiona la cascada de otro gerente. Valida los borradores de su cascada.
- **Usuario de un área** (sin rol de gerente): gestiona lo que crea en su propio sector; su
  gerente ve y valida lo suyo.
- **Auditor**: tiene su propia área (por ejemplo, Auditoría Interna) pero su función es
  transversal. Ve lo validado y lo aprobado de **cualquier** área de la organización, pero no ve
  los borradores de áreas que no son la suya. Sobre lo que ve puede:
  - **proponer cambios** en la ficha y en las relaciones, y recalcular impacto y probabilidad.
    Su propuesta siempre nace en borrador y la valida el gerente responsable del elemento;
  - **testear la evidencia de cumplimiento** de un control en cada riesgo que mitiga (ver 7.5);
  - **reevaluar el cuestionario** de un control aprobado (ver 7.4).

  Los testeos y las reevaluaciones se aplican en el acto, sin pasar por el gerente ni por el
  comité. El auditor no puede validar, aprobar, rechazar ni eliminar nada, ni crear elementos
  nuevos. Puede dejar notas y documentos en cualquier elemento que ve y cancelar sus propias
  propuestas mientras siguen en borrador.

### Qué ve cada uno, según el estado

La visibilidad de un elemento depende de su **estado** (ver sección 3) combinado con tu
posición en el árbol de áreas:

| Estado | Quién lo ve |
|---|---|
| **Borrador** | Quien lo creó, y el gerente de esa cascada de áreas. Nadie más, ni siquiera el comité. |
| **Validado** | Todos dentro de esa misma gerencia (el gerente y sus sub-áreas), más el comité y los auditores. |
| **Aprobado** | Toda la organización. |
| **Borrado** | Igual que un borrador: solo quien lo creó y su gerente. |

Ejemplo concreto: creás un riesgo en Contabilidad. Mientras esté en **borrador**, solo vos y el
gerente de Administración lo ven, ni el gerente de Producción ni el comité. Cuando el gerente
lo **valida**, lo empieza a ver también tu compañero de Recursos Humanos (misma gerencia), el
comité y los auditores. Cuando el comité lo **aprueba**, lo ve toda la organización.

Hay una excepción: quien gestiona un plan de acción ve **todas las tareas de ese plan**, en
cualquier estado y de cualquier área, y puede comentarlas (ver 9.6).

### Qué pasa si no tenés permiso

Si en un listado o en una ventana emergente ves un botón de **"Ver completo"** apagado o
directamente ausente, es porque no tenés acceso a ese elemento, normalmente porque está en
borrador y pertenece a otra área. Si intentás entrar por la fuerza (por ejemplo pegando un link
directo), el sistema te bloquea con un error de "No autorizado".

### Resumen de permisos por rol

| Acción | Usuario | Gerente | Comité | Auditor |
|---|---|---|---|---|
| Crear elementos | En su sector | En su área y sub-áreas | En cualquier área | No |
| Ver borradores | Los propios | Los de su cascada | Ninguno | Los de su propia área |
| Editar directo en borrador | Lo propio | Lo de su cascada | — | — |
| Proponer cambios sobre validado/aprobado | Sobre lo de su área | Sobre lo de su cascada (se aplica directo o nace validado) | Se aplica directo sobre lo aprobado | Sobre cualquier elemento, nace en borrador |
| Validar borradores | No | Sí, los de su cascada | No | No |
| Aprobar validados | No | No | Sí | No |
| Rechazar | No | Borradores de su cascada | Lo validado | No |
| Proponer la eliminación de un riesgo aprobado | Sí | Sí | Se aplica directo | No |
| Testear evidencia / reevaluar un control aprobado | No | No | No | Sí |
| Acceso a "Pendientes" | No | Sí | Sí | No |
| Acceso a "Interno" | Sí | Sí | No | Sí |
| Acceso a "Vencimientos" | Sí (su área) | Sí (su cascada) | Sí (todo) | Sí (su área) |

---

## 3. El ciclo de vida de todo elemento

Riesgos, Controles, Objetivos, Planes de Acción y Tareas comparten el mismo ciclo de cuatro
estados:

1. **Borrador** (gris): recién creado. Se edita libremente mientras esté acá.
2. **Validado** (azul): un gerente lo revisó y le dio el primer visto bueno.
3. **Aprobado** (verde): el comité dio la aprobación final. Es la versión definitiva y vigente.
4. **Borrado** (rojo): se rechazó o se eliminó. Queda registrado pero deja de estar activo.

El **estado nunca retrocede**. Un elemento aprobado no vuelve a borrador cuando alguien propone
un cambio: lo que pasa por el ciclo borrador → validado → aprobado es **cada propuesta de
cambio**, no el elemento en sí (ver sección 11).

### Botones del ciclo

Cada pantalla de detalle muestra, según tu rol y el estado actual:

- **Validar** (borrador → validado, lo hace un gerente).
- **Aprobar** (validado → aprobado, lo hace el comité). Al aprobar se pueden cargar el
  **N° de comité** y la **Fecha del comité** que lo dictaminó. Los dos son opcionales y la fecha
  no puede ser futura.
- **Rechazar**: pide siempre un motivo (ver 11.5).
- **Eliminar** o **Proponer eliminación**, según la entidad (ver 6.8).

### La validación en cascada

Al tocar **Validar** o **Aprobar** se abre una ventana que muestra también los elementos
relacionados que se pueden validar o aprobar junto con él. Por ejemplo, al validar un riesgo
se ofrecen su objetivo, sus planes y sus controles en borrador. Si algo impide la validación,
la ventana lo explica.

### Qué hace falta para validar

| Elemento | Requisito |
|---|---|
| Riesgo | Al menos un objetivo validado o aprobado. Si la respuesta es "Reducir / Mitigar", además un plan de acción validado o aprobado. |
| Control | El cuestionario contestado (card **Valor del control**, ver 7.3). |
| Plan de acción | El valor estimado cargado (card **Valor del plan**, ver 9.3). |
| Cualquiera | No estar marcado como **Interno** (ver sección 12). |

Los mismos requisitos valen al aprobar, también desde Pendientes y desde la ventana de cascada.
En la cascada, el elemento que no cumple queda en su estado y el resto sigue.

### El comité de una aprobación

El número y la fecha de comité quedan guardados en la aprobación, y la ficha los muestra junto
al estado. Si se aprobó sin cargarlos, o se cargaron mal, el comité puede **cargarlos o
corregirlos después** desde la ficha del elemento o desde el detalle de esa aprobación en la
Actividad. La corrección queda como una entrada nueva: la original no se modifica.

---

## 4. Cómo se calcula el valor de un riesgo

Conviene entender esto antes de ver las pantallas, porque los números aparecen en casi todas.

**Valor total** = impacto + probabilidad. Cada uno va de 0 a 10, así que el total va de 0 a 20.
Impacto y probabilidad no se cargan a mano: salen del cuestionario del riesgo (ver 6.2).

| Total | Clasificación |
|---|---|
| 0 a 9 | **Bajo** (verde) |
| 10 a 13 | **Moderado** (amarillo) |
| 14 a 20 | **Crítico** (rojo) |

**Valor residual** = lo que queda después de mitigar. Se calcula en dos pasos.

**Primero los planes de acción.** Solo cuentan los planes **aprobados**.
- Cada plan tiene un valor que dice cuánto reduce el impacto y cuánto reduce la probabilidad
  (ver 9.3).
- Mientras se ejecuta, reduce en proporción al avance de sus tareas aprobadas. Un plan que reduce
  4 de impacto y está al 50% reduce 2.
- Los planes se suman eje por eje, y **cada eje tiene un tope**: no se puede bajar más impacto que
  el impacto del riesgo, ni más probabilidad que su probabilidad. Un plan que reduce 8 de impacto
  sobre un riesgo de impacto 5 reduce 5.

**Después los controles.** Solo cuentan los controles **aprobados**.
- Cada control tiene un valor, de 1 a 10, que sale de su cuestionario y es el mismo en todos los
  riesgos (ver 7.3).
- En cada riesgo, ese valor se regula según la **evidencia de cumplimiento** que el auditor
  midió en esa relación (ver 7.5).
- Lo que descuentan los controles se resta del total, sin separar ejes.

**El residual nunca baja de 0.**

**Redondeo hacia abajo.** Todas las proporciones se redondean hacia abajo, así que solo al 100%
se mitiga el valor entero. Un plan al 99% o una evidencia de 98 no mitigan todo.

**Lo que no cuenta:**
- un control o un plan en borrador o validado;
- un control que es una expresión de deseo (ver 7.3);
- un plan sin valor cargado;
- las tareas que no están aprobadas.

La ficha del riesgo muestra el desglose completo: impacto, probabilidad, total, cuánto descuentan
los controles aprobados, cuánto los planes aprobados y el residual.

---

## 5. Panel de Riesgos y reportes

Es la pantalla que ves apenas entrás al sistema. Te da un panorama general con sesgo según tu
rol: si sos gerente ves tu cascada de áreas, y si sos del comité ves toda la organización.
**Nunca muestra borradores ni borrados.**

### Filtros comunes

Arriba hay filtros que aplican a todas las pestañas: **Solo aprobados** (encendido por defecto;
apagándolo sumás los validados), **tipo de riesgo**, **respuesta** y **Limpiar filtros**.

### Pestañas

- **Resumen**:
  - cuántos riesgos hay en tu vista;
  - cuántos son críticos, moderados y bajos, **inherente → residual** (antes y después de mitigar);
  - cuántos tienen plan vencido y cuántos no tienen plan;
  - accesos a Pendientes y a Vencimientos;
  - la **Matriz de calor**: la grilla impacto × probabilidad. Al lado se muestra una simulación de
    cómo queda después de mitigar. Es solo visual: el residual real es el de cada riesgo.
- **Por gerencia** y **Por tipo**: tablas que cruzan cantidad de riesgos, tipo, dimensión y
  gerencia, con criticidad inherente y residual y la situación de plan (sin plan, vigente o
  vencido). Un riesgo con varias dimensiones cuenta en cada una, así que esas columnas no suman
  el total.
- **Evolución**: elegís un rango **Desde / hasta** y ves los aprobados al inicio, los aprobados y
  rechazados en el período, y los aprobados al final, por tipo y por gerencia. También el avance de
  cada plan al inicio y al final del rango. Los elementos de la carga inicial no tienen fecha de
  aprobación: cuentan como previos al período y la pantalla avisa cuántos son.
- **Planes y tareas**: los planes por gerencia, con los riesgos que mitiga cada uno, si están en
  fecha o vencidos y si tienen avance.

Cada pestaña de reporte tiene el botón **Descargar Excel**, que baja lo mismo que ves en pantalla
con los filtros activos. El Excel de Planes y tareas suma además el detalle de las tareas.

---

## 6. Riesgos

### 6.1. Listado de Riesgos

Columnas: **Nombre / Tipo** (clic para ir al detalle), **Estado**, **Impacto / Probabilidad**,
**Total**, **Residual**, **Plan de acción** (un badge por plan con su % de avance: verde al 100%,
ámbar en curso; si no tiene ninguno dice **"Sin plan"**), **Usuario / Área**, y un botón
**Gestionar** por fila. Los riesgos internos llevan su badge junto al nombre.

Filtros: **Buscar** (por nombre, en vivo), **Tipo**, **Estado**, **Área** (con **Incluir
sub-áreas**), **Solo mayor criticidad** y **Limpiar filtros**. Se ordena haciendo clic en los
encabezados (Nombre, Estado, Total, Residual).

El botón **Nuevo Riesgo**, arriba a la derecha, abre el asistente de creación.

### 6.2. Crear un riesgo: el asistente de 4 pasos

Impacto y probabilidad no se cargan a mano: se calculan respondiendo un cuestionario guiado. El
**tipo de riesgo** define qué cuestionario se usa: el **clásico** o la **matriz ambiental**
(para los riesgos de tipo Ambiental). A la derecha, un panel de referencia explica los tipos de
riesgo y las dimensiones. Desde el paso 2 ese panel pasa a mostrar un **resumen en vivo** de lo
que vas cargando, con el detalle de cada pregunta desplegable.

**Paso 1: Datos básicos.**
- **Nombre** (obligatorio).
- **Descripción** (opcional).
- **Tipo de Riesgo** (obligatorio).
- **Dimensión** (al menos una): Derechos humanos y estándares laborales, Medioambiente,
  Anticorrupción y Gobernanza. Si elegís dos o más, tenés que escribir el **fundamento** de por
  qué. El panel de referencia muestra ejemplos y principios de cada dimensión.
- Si el tipo es Ambiental, la **caracterización**:
  - **Temporalidad**: Pasado, Actual o Futuro.
  - **Condición**: Normal o Contingencia.
  - **Responsabilidad**: Directa o Indirecta.

  Es obligatoria e informativa: no cambia el valor.

**Paso 2: Probabilidad.**
- **Clásico**: 5 preguntas, cada una con tres opciones que valen 0, 1 o 2. Por ejemplo, si ya
  ocurrió, si hay condiciones que lo favorecen o cuán expuestos estamos. La suma da la
  probabilidad (0 a 10).
- **Ambiental**: **Control** y **Frecuencia**, cada uno de 1, 3 o 5 puntos. La suma da la
  probabilidad (2 a 10, siempre par).

**Paso 3: Impacto.**
- **Clásico**: 10 preguntas de sí/no, de 1 punto cada una, en dos grupos:
  - **Materialidad de impacto**: salud y seguridad, derechos humanos y laborales, ambiente,
    terceros y comunidad, impactos extendidos;
  - **Materialidad financiera**: operación, pérdidas y costos, imagen y reputación,
    incumplimiento, ingresos y mercados.

  La suma da el impacto (0 a 10).
- **Ambiental**: **Medio** sobre el que ocurre (1 a 3), **Naturaleza** del contaminante (1 a 3) y
  **Magnitud** del impacto (Bajo, Moderado, Severo o Crítico: 1 a 4). Las seis variables de
  magnitud se muestran como guía, sin puntaje. Después hay 20 casillas de materialidad (10 de
  impacto y 10 financieras): ninguna marcada suma 0, de 1 a 10 marcadas suman 1, y más de 10
  suman 2. El impacto nunca pasa de 10.

**Paso 4: Respuesta y área.**
- Resumen de **Probabilidad**, **Impacto** y **Valor Total**, con su clasificación.
- **Marcar como mayor criticidad**: solo se habilita si el total llega a 14 o más.
- **Respuesta** (obligatoria): "Reducir / Mitigar", "Evitar", "Compartir" o "Aceptar". El tipo
  de riesgo puede restringir cuáles están disponibles.
- **Fundamento**: obligatorio si elegiste "Compartir" o "Aceptar".
- **Área** (obligatoria): tu área o una de sus sub-áreas. El comité puede elegir cualquiera.
- **Sedes** (al menos una): Administración Central - La Plata, 9 de Julio - Mar del Plata,
  Oscar Smith - Villa Gesell, Mar de Ajó y Necochea. El botón **Todas** las marca de una.
- **Interno** (sin marcar): ver sección 12.

El botón **Crear Riesgo** guarda todo y te lleva al detalle. El riesgo nace en **Borrador**. El
objetivo, los controles y los planes se asocian después, desde el detalle (ver 6.7).

### 6.3. Detalle de un riesgo

Es la pantalla central para gestionar un riesgo.

**Encabezado**: nombre, estado, badge de **Interno** o **Mayor criticidad** si corresponde,
tipo, área y gerencia, y los botones del ciclo (ver sección 3).

Debajo, si la revisión inicial está activa, la card **Para la revisión inicial** (ver sección 15).

Bloques de la pantalla:

- **Valor del riesgo**: el **Residual** en grande, con su clasificación y el total del que
  parte, y el desglose: Impacto + Probabilidad = Valor total − Controles aprobados − Planes
  aprobados = Residual. Mientras editás controles o planes, muestra cómo quedaría con lo que estás
  editando. Si el riesgo todavía tiene el número de la carga inicial, avisa **"Valor de la carga
  inicial"** con un acceso a **Recalcular**. En un borrador interno muestra además el **Residual
  proyectado** (ver sección 12).
- **Ficha del riesgo**: descripción, estado, comité de la aprobación, respuesta, fundamento,
  dimensiones, sedes, caracterización (si es ambiental), área, usuario y fecha de creación. Incluye
  la **Evaluación vigente**: las respuestas del cuestionario con las que se llegó al impacto y la
  probabilidad actuales. Si no hay respuestas registradas, lo dice y sugiere recalcular.
- **Objetivos**, **Controles de mitigación** y **Planes de acción**: lo asociado al riesgo (ver
  6.7).
- **Notas y documentos** y **Actividad** (ver sección 11).

Ningún elemento del sistema lleva código: se identifica por su nombre en pantalla.

### 6.4. Editar un riesgo en borrador

**Editar** (solo en borrador): cambiás Nombre, Descripción, Tipo de Riesgo, Dimensiones y su
fundamento, Respuesta, Fundamento, mayor criticidad, Área, Sedes, caracterización e **Interno**.
**Impacto y Probabilidad se muestran de solo lectura.**

El tipo de riesgo solo se puede cambiar por otro del **mismo método**. Un riesgo clásico no pasa
a Ambiental ni al revés, porque sus números quedarían calculados con el cuestionario equivocado.
Si el tipo estaba mal, se rechaza y se crea de nuevo.

### 6.5. Recalcular impacto y probabilidad

La única forma de cambiar impacto o probabilidad es **Recalcular**. Está en la pantalla de
edición y en el bloque Valor del riesgo. Abre una versión corta del asistente: solo las preguntas
de probabilidad e impacto del método que corresponde al tipo, **con las respuestas vigentes
precargadas**. Corregir un número es cambiar una respuesta. El botón final **Recalcular** guarda.

- En **borrador** se aplica en el acto.
- En **validado o aprobado** es una propuesta, con el circuito de siempre:
  - la de un usuario o un auditor nace en borrador y la valida el gerente;
  - la del gerente nace validada;
  - sobre un riesgo aprobado, el número y las respuestas cambian recién cuando el comité aprueba
    la propuesta.

  Aunque el número quede igual, cambiar respuestas también es una propuesta.
- El **comité no recalcula**: aprueba o rechaza lo que le proponen.

### 6.6. Cambios en la ficha fuera de borrador

Un riesgo se puede seguir editando toda su vida, desde el bloque **Ficha del riesgo**: Nombre,
Descripción, Tipo de Riesgo (del mismo método), Respuesta, Fundamento y caracterización. El
cambio se aplica directo o queda como propuesta según tu rol y el estado (ver sección 11).

Hay dos cambios que van **cada uno en su propia propuesta**, que se valida o rechaza por separado:
- las **sedes**;
- las **dimensiones**, junto con su fundamento.

### 6.7. Asociar objetivos, controles y planes

Los bloques Objetivos, Controles de mitigación y Planes de acción funcionan igual. Cada uno
muestra primero lo que **hoy** forma parte del riesgo y, debajo, las propuestas todavía no
resueltas sobre ese bloque.

1. Con el riesgo en **borrador**, el botón dice **Editar**. En otro estado dice **Proponer
   cambio**.
2. En modo edición, **Agregar control** / **Agregar objetivo** / **Agregar plan de acción**
   abre un buscador en vivo: escribís el nombre y hacés clic en el resultado.
3. Cada ítem se puede sacar con una **X**.
4. **Guardar** aplica los cambios directo en borrador. Fuera de borrador, se aplican o quedan
   como propuesta según tu rol (ver 11.4).

Lo que mitiga cada control o plan **no se carga en la relación**: sale del valor del control y
del valor del plan (ver 7.3 y 9.3). En cada ítem se ve cuánto descuenta hoy de este riesgo.

**Fuera de borrador, cada alta o baja es su propia propuesta.** Se valida, aprueba o rechaza una
por una, desde su tarjeta debajo de la lista vigente. Mientras un ítem tiene una propuesta
pendiente, no se puede proponer otro cambio sobre él.

**Objetivos tiene un mínimo de 1**: si intentás sacar el último, el sistema muestra "El riesgo
debe tener al menos un objetivo asociado." Controles y Planes no tienen mínimo.

### 6.8. Eliminar un riesgo

- **En borrador o validado**, un riesgo se saca **rechazándolo** con motivo (ver 11.5).
- **Aprobado**, se elimina con una **propuesta**, desde el menú de **Más acciones** del encabezado →
  **Proponer eliminación**. La ventana pide:
  - **Observaciones** (obligatorias): por qué se elimina.
  - **Adjunto** (opcional).
  - **Eliminar también** los controles y planes del riesgo que estén aprobados y no se asocien a
    ningún otro riesgo. Los demás aparecen deshabilitados con el motivo. Cada uno que marques
    queda como **una propuesta aparte**: el comité puede rechazar la del riesgo y aprobar la del
    control, o al revés. Un plan que se elimina arrastra sus tareas que no están en otro plan.

La propuesta sigue el circuito de siempre: la de un usuario nace en borrador y la valida su
gerente, la del gerente nace validada, y cuando la aprueba el comité el riesgo pasa a **Borrado**.
**Mientras tanto el riesgo sigue aprobado y mitigando.** Si la carga el comité, se aplica en el
acto. Solo puede haber una propuesta de eliminación a la vez, y el auditor no puede proponerla.

Antes de aplicarla, el sistema vuelve a revisar. Si en el medio un control o un plan quedó
asociado a otro riesgo, esa eliminación no se aplica.

---

## 7. Controles

### 7.1. Listado de Controles

Columnas: **Nombre**, **Estado**, **Descripción**, **Mitigación** (el valor del control),
**Riesgos** asociados y **Usuario / Área**. Filtros: **Buscar**, **Estado**, **Área** (con
**Incluir sub-áreas**) y **Limpiar filtros**. Se ordena por Nombre o Mitigación. Botón **Ver** por
fila y **Nuevo Control** arriba a la derecha.

### 7.2. Crear y editar un control

Campos:
- **Nombre** (obligatorio).
- **Descripción** (opcional).
- **Área** (obligatoria): tu área o una sub-área. El comité puede elegir cualquiera.
- **Responsable** (opcional): un usuario del área elegida o de sus sub-áreas, o de las áreas de
  arriba hasta la primera gerencia de esa línea.
- **Interno** (ver sección 12).

El valor del control **no se carga en el formulario**: sale del cuestionario, que se contesta
desde la ficha una vez creado.

**Editar** solo está disponible en borrador. Después, Nombre y Descripción se cambian desde la
**Ficha del control** con el circuito de propuestas (ver sección 11).

### 7.3. El valor del control: card «Valor del control»

El valor del control sale de un cuestionario de 7 preguntas de sí/no:

| Pregunta | Sí | No |
|---|---|---|
| ¿Está operativo hoy o es una expresión de deseo? | 1 | 0 |
| ¿Está documentado en un procedimiento, instructivo o anexo? (pide cuál) | 2 | 0 |
| ¿Ocurre dentro de un sistema propio o de terceros? (pide cuál o cuáles, o «otro») | 3 | 1 (es manual) |
| ¿Tiene identificado un responsable? | 1 | 0 |
| ¿Ocurre con una frecuencia determinada? (pide cuál) | 1 | 0 |
| Una vez que operó, ¿deja evidencia de su realización? | 1 | 0 |
| ¿Le permitió prevenir o detectar errores en el proceso? | 1 | 0 |

El **puntaje** va de 1 a 10 y es lo que el control mitiga en cada riesgo. Se clasifica así:

| Puntaje | Clasificación |
|---|---|
| Menos de 4 | **Deficiente** (rojo) |
| 4 a 6 | **Moderado** (amarillo) |
| 7 o más | **Suficiente** (verde) |

**Expresión de deseo.** Si la primera respuesta es «No, es una expresión de deseo», el
cuestionario se contesta igual y el puntaje se muestra, pero el control **mitiga 0** y queda
deficiente.

La card muestra el puntaje, la clasificación y cada respuesta con sus puntos. El botón cambia
según el caso:
- **Contestar cuestionario**, si todavía no tiene;
- **Editar respuestas**, en borrador. Se guarda en el acto;
- **Proponer cambio de respuestas**, fuera de borrador. Queda como propuesta y el valor no cambia
  hasta que se aplica. El comité no valora: aprueba lo que le proponen.

Mientras hay una propuesta sobre el cuestionario esperando, no se puede proponer otra.

**Sin cuestionario el control no se valida ni se aprueba.** Los controles anteriores al
cuestionario conservan su número viejo («Número anterior al cuestionario») y mitigan con él hasta
que alguien lo conteste.

### 7.4. Reevaluación del auditor

Con el control **aprobado**, un auditor ve el botón **Reevaluar (auditoría)**. Contesta el mismo
cuestionario, con un **comentario** obligatorio y archivos opcionales, y la reevaluación **se
aplica en el acto**, sin pasar por el gerente ni el comité. Queda en la Actividad del control. No
se puede reevaluar mientras haya una propuesta pendiente sobre el cuestionario.

### 7.5. Riesgos que mitiga y evidencia de cumplimiento

El bloque **Riesgos que mitiga** lista cada riesgo asociado con:
- cuánto le descuenta el control (por ejemplo "−4 de 7" cuando la evidencia no aplica el valor
  entero);
- el total y el residual del riesgo;
- la **Evidencia** de cumplimiento en esa relación y qué porcentaje aplica;
- el **último testeo**: fecha, autor, cambio y comentario;
- el acceso a **Ver los N testeos**.

Si el control se asocia a riesgos de otras áreas que no podés ver, se avisa cuántos son.

**La evidencia se mide en cada riesgo**, no en el control, porque el mismo control puede
cumplirse bien en un proceso y mal en otro. Va de 0 a 100 y aplica el valor del control por
tramos:

| Evidencia | Aplica del valor del control |
|---|---|
| Menos de 60 | 0% |
| 60 a 69 | 20% |
| 70 a 79 | 40% |
| 80 a 90 | 60% |
| 91 a 98 | 80% |
| 99 a 100 | 100% |

Ejemplo: un control de valor 7 con evidencia 75 aplica el 40%: descuenta 2 (2,8 redondeado hacia
abajo).

Una relación que nunca se testeó muestra "—" en la evidencia, pero para el cálculo vale 100.

**Testear.** Solo el **auditor** cambia la evidencia, con el botón **Testear** de cada riesgo. El
control tiene que estar aprobado y el riesgo validado o aprobado, de cualquier área. El testeo
pide:
- **Fecha del testeo** (no futura);
- **Evidencia** (0–100 %);
- **Comentario** (obligatorio);
- archivos (opcionales).

**Se aplica en el acto**, sin gerente ni comité. Si el porcentaje no cambia, queda como
**confirmación**. Un testeo vale para una sola relación: si el mismo informe sirve para varios
riesgos, se carga en cada uno.

### 7.6. Cómo se asocia un control a un riesgo

**La asociación se hace desde el Riesgo**, en su bloque "Controles de mitigación" (ver 6.7). El
control no elige sus riesgos.

### 7.7. Eliminar un control

Botón **Eliminar control** en el detalle, con confirmación. Pasa a Borrado y se eliminan también
sus asociaciones a riesgos. Esos riesgos pierden esa mitigación y conservan las demás.

---

## 8. Objetivos y catálogo PEIS

### 8.1. Listado de Objetivos

Columnas: **Nombre** (con badges **Estratégico** y/o **PEIS** si aplican), **Estado**,
**Descripción**, **Fecha Objetivo**, **Riesgos** asociados y **Usuario / Área**. Filtros:
**Buscar**, **Estado**, **Área** (con **Incluir sub-áreas**) y **Limpiar filtros**. Se ordena
por Nombre o Fecha Objetivo. Botón **Ver** por fila y **Nuevo Objetivo** arriba a la derecha.

### 8.2. Crear y editar un objetivo

Campos:
- **Nombre** (obligatorio).
- **Descripción** (opcional).
- **Fecha Objetivo**, con un checkbox **No aplica** si el objetivo no tiene plazo.
- **Estratégico** (checkbox).
- **PEIS** (checkbox).
- **Área** (obligatoria): tu área o una sub-área. El comité puede elegir cualquiera.
- **Responsable** (opcional, se autocompleta con tu usuario).
- **Interno** (ver sección 12).

**Si marcás PEIS**, aparecen los 5 ítems del catálogo:

| Ítem | Descripción |
|---|---|
| **PEIS 1** | Fortalecer la cultura de integridad y transparencia en todos los niveles de la organización. |
| **PEIS 2** | Prevenir, detectar y gestionar conflictos de interés en la toma de decisiones. |
| **PEIS 3** | Asegurar canales de denuncia accesibles, confidenciales y libres de represalias. |
| **PEIS 4** | Promover la debida diligencia en la relación con terceros, proveedores y socios de negocio. |
| **PEIS 5** | Garantizar el cumplimiento normativo y la mejora continua del sistema de integridad. |

Es un catálogo fijo: los ítems solo se seleccionan. **Si marcás PEIS y no elegís ningún ítem, el
sistema no te deja guardar.** Si después desmarcás PEIS, los ítems se desasocian.

**Editar** solo en borrador. Después, los cambios se proponen desde la **Ficha del objetivo**.

### 8.3. Detalle de un objetivo

Muestra la ficha (estado, fecha objetivo o "No aplica", área, quién lo creó, ítems PEIS), los
**Riesgos asociados** con su total y residual, los **Planes de acción vinculados** (los planes de
los riesgos de este objetivo) con sus tareas, y las Notas y documentos y la Actividad.

### 8.4. Cómo se asocia un objetivo a un riesgo

Desde el **Riesgo**, en su bloque "Objetivos" (ver 6.7). Recordá el mínimo de 1 objetivo por
riesgo.

### 8.5. Eliminar un objetivo

Botón **Eliminar objetivo** en el detalle, con confirmación. Pasa a Borrado.

---

## 9. Planes de Acción

### 9.1. Listado de Planes de Acción

Columnas: **Nombre**, **Estado**, **Descripción**, **Riesgos** asociados, **Avance** (verde al
100%, ámbar en curso, guion si no tiene tareas aprobadas), **Vencimiento** (la fecha pendiente
más próxima entre sus tareas sin completar, en rojo con **"Vencido"** si ya pasó) y **Usuario /
Área**. Filtros: **Buscar**, **Estado**, **Área** (con **Incluir sub-áreas**) y **Limpiar
filtros**. Botón **Ver** por fila y **Nuevo Plan** arriba a la derecha.

### 9.2. Crear y editar un plan de acción

Campos:
- **Nombre** (obligatorio).
- **Descripción** (opcional).
- **Área** (obligatoria): tu área o una sub-área. El comité puede elegir cualquiera.
- **Responsable** (opcional).
- **Interno** (ver sección 12).

Los riesgos que mitiga se asocian desde el detalle de cada riesgo (ver 6.7). El valor del plan
se carga desde la ficha, una vez creado (ver 9.3).

**Editar** solo en borrador. Después, los cambios se proponen desde la **Ficha del plan**.

### 9.3. El valor del plan: card «Valor del plan»

El valor de un plan sale de un cuestionario de 10 preguntas de sí/no:
- **5 de reducción de la probabilidad**: si elimina o reduce las causas, reduce la exposición,
  incorpora controles preventivos, reduce la discrecionalidad o separa funciones, o establece
  seguimiento y alertas tempranas;
- **5 de reducción del impacto**: si reduce la cantidad de afectados, la gravedad sobre las
  personas, las pérdidas económicas, las consecuencias sobre la continuidad operativa, o las
  consecuencias legales y reputacionales.

**Cada «sí» reduce 2 puntos su eje**, hasta 10. El resultado es un par: cuánto reduce el impacto
y cuánto la probabilidad.

Se contesta en dos momentos:

1. **Valor estimado** ("¿Se espera que el plan, una vez implementado en su totalidad, logre…?").
   Se carga desde la card una vez creado el plan. **Es obligatorio para validar y aprobar el
   plan.**
2. **Evaluación final** ("¿El plan implementado en su totalidad logró…?"). Se habilita con el plan
   **aprobado y al 100%**. Da el valor real y **reemplaza al estimado**. Con la evaluación final
   aplicada y todas las tareas al 100%, el plan queda **finalizado**.

La card muestra el **Estimado**, el **Final** y lo que **Descuenta hoy**: el valor en proporción
al avance de sus tareas aprobadas. En cada riesgo, eso tiene tope en su impacto y su
probabilidad (ver sección 4).

- En **borrador** el valor se guarda en el acto.
- **Fuera de borrador** es una propuesta: la valida el gerente y, sobre un plan aprobado, la
  aprueba el comité. No mueve el residual hasta entonces. Mientras hay una propuesta pendiente
  sobre el valor, no se puede proponer otra.
- El **comité no valora**: aprueba lo que le proponen.

Los planes anteriores al cuestionario **no tienen valor y mitigan 0** hasta que alguien cargue
la estimación.

### 9.4. Cómo se calcula el % de avance de un plan

El **avance** es el promedio del % de avance de sus tareas **aprobadas**. Las tareas en borrador
o validadas no cuentan, y las borradas quedan afuera. Sin tareas aprobadas, el avance se muestra
como un guion.

- 3 tareas aprobadas al 50%, 60% y 70% → 60%.
- 2 tareas en borrador + 1 aprobada al 80% → 80%.

Para la mitigación se usa el promedio exacto, no el redondeado que se muestra. Tareas al 100,
100 y 99 se ven como 100%, pero el plan todavía no mitiga su valor entero.

### 9.5. Detalle de un plan de acción

Muestra el encabezado con estado, la card **Para la revisión inicial** si corresponde, la
**Ficha del plan** (vencimiento, área, responsable, fecha de creación), el **Avance del plan**,
el **Valor del plan**, los **Riesgos que mitiga** (con lo que descuenta en cada uno), las
**Tareas**, y Notas y documentos y Actividad.

### 9.6. Gestionar las tareas de un plan

En el bloque **Tareas**: con el plan en **borrador**, botón **Editar**; si ya está validado o
aprobado, **Proponer cambio**. En modo edición:
- **Agregar tarea**: un buscador para incorporar una tarea ya creada.
- Cada tarea se puede quitar con una **X**.
- **Guardar** aplica los cambios.

**Fuera de borrador**, cada alta o baja de tarea es su propia propuesta, y el avance del plan no se
mueve hasta que se aplica.

**Nueva tarea** (si gestionás el plan) abre una ventana con el formulario completo de una tarea.
La tarea se crea y **queda agregada al plan en el acto**:
- **El área puede ser cualquiera**, aunque no sea la tuya. Ejemplo: un plan de Medio Ambiente
  necesita una capacitación de RRHH. La creás desde el plan con área RRHH.
- **Estado con el que nace**: si sos gerente y el área es de tu línea, nace **validada**. En
  cualquier otro caso, o si la marcás como interna, nace en **borrador**, y la valida el gerente
  del área de la tarea.
- **Seguís viéndola** desde el plan y podés comentarla. Editarla, validarla o rechazarla le
  corresponde al área de la tarea.
- Si la rechazan, desaparece del plan. Mientras no esté aprobada, no cuenta para el avance.

La Actividad del plan registra cuando una de sus tareas se valida, se aprueba o se rechaza (con
quién y el motivo). La entrada de una tarea creada desde el plan muestra su estado actual.

### 9.7. Eliminar un plan de acción

Botón **Eliminar plan** en el detalle, con confirmación. Pasa a Borrado. Un plan que es el único
plan vigente de un riesgo que se mitiga no se puede rechazar: el sistema avisa cuál es el riesgo.

---

## 10. Tareas

### 10.1. Listado de Tareas

Columnas: **Nombre** (en rojo con **"Vencida"** si corresponde), **Estado**, **Descripción**,
**Avance** (rojo si vencida, ámbar en curso, verde al 100%), **Fecha** límite, **Planes** a los
que pertenece y **Usuario / Área**. Filtros: **Buscar**, **Estado**, **Área** (con **Incluir
sub-áreas**) y **Limpiar filtros**. Se ordena por Nombre, Avance o Fecha. Botón **Ver** por fila
y **Nueva Tarea** arriba a la derecha.

### 10.2. Crear y editar una tarea

Campos:
- **Nombre** (obligatorio).
- **Descripción** (opcional).
- **Fecha límite** (opcional).
- **Porcentaje de Avance**: de 0 a 100%, de a 5%.
- **Área** (obligatoria).
- **Responsable** (opcional, se autocompleta con tu usuario).
- **Interno** (ver sección 12).

**Editar** solo en borrador. Después, el avance y los demás campos se cambian desde la **Ficha de
la tarea** con el circuito de propuestas. Para las tareas, ese es el mecanismo normal de reportar
avance.

### 10.3. Detalle de una tarea

Muestra el **Avance** con la leyenda "Completada", "En Progreso" o "Vencida", la ficha (estado,
área, fecha límite, responsable, creación), los **Planes de acción** que la contienen y Notas y
documentos y Actividad.

### 10.4. Eliminar una tarea

Botón **Eliminar tarea** en el detalle, con confirmación. Pasa a Borrado.

---

## 11. Notas, documentos, actividad y propuestas de cambio

Este mecanismo es común a los cinco elementos. La pantalla de detalle separa dos cosas.

### 11.1. Notas y documentos

Un espacio con dos pestañas:
- **Notas**: observaciones, avances, constancias o pedidos de información.
- **Documentos**: los archivos adjuntos (PDF, Word, Excel, JPG o PNG, hasta 10 MB cada uno,
  varios a la vez).

Una nota o un documento no cambian nada del elemento, no pasan por validación y no aparecen en
Pendientes. Se pueden dejar en cualquier estado.

### 11.2. Actividad

El historial de solo lectura de todo lo que pasó con el elemento: creación, cambios propuestos,
validaciones, aprobaciones (con su comité), rechazos (con su motivo), recálculos, valoraciones,
testeos y reevaluaciones. Cada entrada abre un detalle con el antes y el después. El historial no
se reescribe: una corrección es siempre una entrada nueva.

### 11.3. Cuándo un cambio es una propuesta

Mientras un elemento está en **Borrador**, se edita directo y el cambio se aplica al toque. Una
vez **Validado** o **Aprobado**, cada cambio de campos o de relaciones pasa por una propuesta:

- **Cambiar un campo de la Ficha**: se edita en el bloque de la Ficha. Mandás solo lo que
  cambiaste.
- **Agregar o quitar un objetivo, control, plan o tarea**: desde el bloque correspondiente. Cada
  alta o baja es su propia propuesta.
- **Recalcular el riesgo** o **cambiar el cuestionario** de un control o de un plan: cada uno es
  su propia propuesta.

### 11.4. Qué pasa después de proponer

El estado con el que nace la propuesta depende de tu rol:
- **Gerente sobre lo que gestiona**: si el elemento está validado, el cambio se aplica directo.
  Si está aprobado, nace validado y espera la aprobación del comité.
- **Comité**: su cambio se aplica directo y queda registrado como aprobación suya.
- **Usuario común, o gerente proponiendo fuera de su cascada**: nace en **borrador** y la valida
  el gerente responsable.
- **Auditor**: nace siempre en **borrador**, aunque el elemento sea de su propia área.

Mientras una propuesta está pendiente:
- quien la creó puede **Cancelarla** si sigue en borrador;
- quien tiene permiso ve **Validar**, **Aprobar** o **Rechazar**;
- el comité puede rechazar una propuesta validada que espera su aprobación.

### 11.5. Rechazar

- **Todo rechazo pide un motivo.** Sin motivo no se puede confirmar. El motivo queda en la
  Actividad.
- **Quién rechaza un elemento**: el gerente, mientras está en borrador; el comité, cuando ya está
  validado y le llegó para aprobar. El rechazo es definitivo: el elemento pasa a **Borrado**.
- **Lo que no se puede rechazar**: un plan o un objetivo que es el único vigente de un riesgo
  validado o aprobado. El sistema avisa cuál es el riesgo: primero hay que resolver ese riesgo.

---

## 12. Borrador interno y pantalla Interno

Algunas áreas gestionan muchos riesgos y solo llevan algunos al comité. Para eso, cualquier
elemento en borrador puede marcarse como **Interno**: queda como gestión propia del área, fuera
del circuito del comité.

- La opción **Interno** está en el alta y en la edición del borrador, y viene sin marcar. Poner o
  sacar la marca queda en la Actividad.
- Un elemento interno lleva un badge en fichas, listados y bloques de relaciones.
- **No se valida con la marca puesta.** Para llevarlo al circuito normal se desmarca desde la
  edición. Desmarcarlo no valida nada: solo lo deja en la bandeja del gerente.
- **No aparece en Pendientes** ni en el contador del Panel. El comité no lo ve en ningún lado.
- Se puede relacionar con elementos no internos sin restricción.

### Valores proyectados

Un riesgo interno muestra, además del residual oficial, un **Residual proyectado**. En el
proyectado mitigan:
- los controles y planes aprobados, como en el oficial;
- los controles y planes en borrador **internos**.

No mitigan los borradores sin la marca ni los validados. Lo mismo vale para el **avance
proyectado** de un plan, que suma sus tareas aprobadas y las internas. **El proyectado nunca
reemplaza al valor oficial**: es una herramienta de trabajo del área.

### La pantalla Interno

Desde el menú **Interno** ves la gestión interna de las áreas que gestionás:
- los **Riesgos internos**, ordenados por residual proyectado, el más alto primero;
- los **Planes de acción internos**, con su avance proyectado;
- los **Vencimientos de tareas internas**.

---

## 13. Pendientes

Visible solo para **gerentes** y **comité**. Es tu bandeja de trabajo.

- **Si sos gerente**: ves, para validar, los Riesgos, Controles, Objetivos, Planes y Tareas en
  **Borrador** de tu área y sub-áreas, más las propuestas de cambio pendientes sobre elementos de
  tu cascada. Lo interno no aparece.
- **Si sos del comité**: ves, para aprobar, todo lo **Validado** de cualquier área, más las
  propuestas en ese estado.

Podés **validar**, **aprobar** o **rechazar** cada ítem desde ahí, sin entrar al detalle. Al
aprobar podés cargar el N° y la fecha del comité. Todo rechazo pide motivo. El botón de PDF
exporta el listado para llevarlo a una reunión.

---

## 14. Vencimientos

Visible para cualquier usuario logueado. Muestra las **tareas con fecha comprometida** (validadas
o aprobadas; los borradores no cuentan), agrupadas en:

- **Vencidas**: ya pasaron su fecha.
- **Por vencer**: vencen dentro de los próximos 30 días.
- **En plazo**: vencen después de esos 30 días.
- **Sin fecha**: comprometidas pero sin fecha límite.

Tiene el mismo sesgo por rol que el resto: gerentes ven su cascada, el comité ve todo y un usuario
común ve las de su área. Cada fila muestra la tarea, su plan, el % de avance y la fecha.

---

## 15. Revisión inicial

Los datos que se llevaban en Excel y Redmine se cargaron en el sistema como aprobados. Antes de dar
el sistema por "en régimen", cada gerencia **revisa y firma** lo que le corresponde. Esta parte es
transitoria y **todavía se está ajustando**: qué es obligatorio y cómo se tratan los datos
migrados puede cambiar.

- La pantalla **Revisión inicial** (menú) muestra el avance por gerencia y la lista de elementos
  con sus inconsistencias, en pestañas por tipo de elemento.
- Cada ficha muestra la card **Para la revisión inicial** con lo que le falta. Cada hallazgo tiene
  su acción: **Recalcular** para un riesgo sin evaluación, o un link al bloque donde se arregla,
  que aparece marcado en rojo o ámbar.
- Las inconsistencias son de tres tipos: **error** (bloquea la firma), **advertencia** (se firma
  con comentario) y **en curso** (el arreglo ya está validado y espera al comité: no bloquea).
- El usuario del área o el coordinador **Marca revisado**. El gerente **Firma**, o **Valida y
  firma** de una vez si su equipo propuso cambios.
- Una vez firmado, la card queda reducida a "Revisión inicial firmada por… el…".

El detalle completo (quién hace qué, catálogo de errores y advertencias, "Validar y firmar") está
en la **Guía de la revisión inicial** (`GUIA-REVISION-INICIAL.md`).

---

## 16. Glosario rápido

- **Riesgo**: la entidad central. Tiene un Valor Total (impacto + probabilidad) y un Valor
  Residual (lo que queda después de mitigar con planes y controles aprobados).
- **Método de evaluación**: el cuestionario que define impacto y probabilidad: clásico o matriz
  ambiental. Lo decide el tipo de riesgo.
- **Evaluación vigente**: las respuestas del cuestionario con las que se llegó al impacto y la
  probabilidad actuales de un riesgo.
- **Dimensión**: la clasificación del riesgo según su naturaleza (derechos humanos, medioambiente,
  anticorrupción, gobernanza). Un riesgo tiene una o más. Es informativa.
- **Sede**: la administración central o una de las generadoras. Un riesgo corresponde a una,
  varias o todas. Es informativa.
- **Control**: una medida de mitigación. Su valor (1 a 10) sale de su cuestionario y es el mismo
  en todos sus riesgos. Solo descuenta una vez aprobado.
- **Expresión de deseo**: un control que todavía no opera. Mitiga 0.
- **Evidencia de cumplimiento**: cuánto se cumple un control en un riesgo puntual, de 0 a 100.
  Define qué parte del valor del control aplica, por tramos. La mide el auditor.
- **Testeo**: la medición de la evidencia que carga el auditor, con fecha, comentario y archivos.
- **Reevaluación**: el cuestionario de un control aprobado que contesta el auditor y se aplica en
  el acto.
- **Objetivo**: un objetivo estratégico al que se vincula un riesgo (mínimo uno por riesgo).
  Puede ser PEIS, y entonces exige al menos un ítem del catálogo fijo de 5.
- **Plan de Acción**: agrupa tareas y mitiga riesgos. Su valor estimado o final dice cuánto
  reduce el impacto y la probabilidad, y mitiga en proporción a su avance.
- **Plan finalizado**: un plan con la evaluación final aplicada y todas sus tareas al 100%.
- **Tarea**: la unidad de trabajo dentro de un plan, con fecha límite y % de avance propio.
- **Propuesta**: un cambio sobre algo validado o aprobado, que pasa por validación y aprobación
  antes de aplicarse.
- **Borrador / Validado / Aprobado / Borrado**: los cuatro estados de todo elemento, que
  determinan quién puede verlo y editarlo (ver secciones 2 y 3).
- **Interno**: un borrador que el área gestiona sin llevarlo al comité. Tiene valores
  proyectados.
- **Comité de una aprobación**: el número y la fecha de la sesión del comité que aprobó algo.
- **Auditor**: rol transversal que ve lo validado y aprobado de toda la organización, propone
  cambios, testea la evidencia y reevalúa controles, sin poder crear, validar, aprobar ni eliminar.
