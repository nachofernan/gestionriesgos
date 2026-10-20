# Manual de Usuario — Módulo de Auditoría de Riesgos

Este manual explica, paso a paso, cómo usar el módulo de Auditoría de Riesgos. Está pensado
para cualquier persona que use el sistema en su trabajo diario, sin necesidad de conocimientos
técnicos. No incluye capturas de pantalla: describe en palabras cada pantalla, cada botón y
cada opción, en el orden en que normalmente los vas a encontrar.

## Índice

1. [Ingresar al sistema y navegar](#1-ingresar-al-sistema-y-navegar)
2. [Roles y quién ve qué](#2-roles-y-quién-ve-qué)
3. [El ciclo de vida de todo elemento](#3-el-ciclo-de-vida-de-todo-elemento)
4. [Panel de Riesgos (pantalla de inicio)](#4-panel-de-riesgos-pantalla-de-inicio)
5. [Riesgos](#5-riesgos)
6. [Controles](#6-controles)
7. [Objetivos y catálogo PEIS](#7-objetivos-y-catálogo-peis)
8. [Planes de Acción](#8-planes-de-acción)
9. [Tareas](#9-tareas)
10. [Actualizaciones, propuestas de cambio y adjuntos](#10-actualizaciones-propuestas-de-cambio-y-adjuntos)
11. [Pendientes](#11-pendientes)
12. [Vencimientos](#12-vencimientos)
13. [Glosario rápido](#13-glosario-rápido)

---

## 1. Ingresar al sistema y navegar

Entrás al sistema con tu email y contraseña en la pantalla de login. Si los datos son
correctos, caés directamente en el **Panel** (el dashboard de riesgos). Si te equivocás, el
sistema te avisa y podés reintentar.

Arriba de cada pantalla hay una barra de navegación fija con estos accesos:

- **Panel** — el dashboard de inicio (ver sección 4).
- **Riesgos**, **Controles**, **Objetivos**, **Planes de Acción**, **Tareas** — el listado de
  cada una de esas entidades.
- **Vencimientos** — visible para cualquier usuario logueado (ver sección 12).
- **Pendientes** — visible solo si sos gerente o formás parte del comité (ver sección 11).
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

### Los cuatro roles

- **Comité**: no pertenece a ningún área puntual. Opera sobre lo público de toda la
  organización. Es quien da la aprobación final.
- **Gerente de área**: gestiona su área y todas las sub-áreas que cuelgan de ella. No ve ni
  gestiona la cascada de otro gerente.
- **Usuario de un área** (sin rol de gerente): gestiona lo que crea en su propio sector; su
  gerente ve y valida lo suyo.
- **Auditor**: tiene su propia área (por ejemplo, Auditoría Interna) pero su función es
  transversal. Ve lo validado y lo aprobado de **cualquier** área de la organización, igual que
  un usuario común vería lo suyo — pero no ve los borradores de áreas que no son la suya. Sobre
  cualquier elemento que ve (validado o aprobado, de cualquier gerencia) puede **proponer
  cambios**: campos de la ficha, altas y bajas en controles/objetivos/planes, y recalcular
  impacto/probabilidad. Esa propuesta siempre nace en borrador y la valida el gerente responsable
  del elemento — el auditor no puede validar, aprobar, rechazar ni eliminar nada, ni crear
  elementos nuevos. También puede dejar notas y adjuntos en la Conversación de cualquier elemento
  que ve, y cancelar sus propias propuestas mientras siguen en borrador.

### Qué ve cada uno, según el estado

La visibilidad de un riesgo, control, objetivo, plan o tarea depende de su **estado** (ver
sección 3) combinado con tu posición en el árbol de áreas:

| Estado | Quién lo ve |
|---|---|
| **Borrador** | Quien lo creó, y el gerente de esa cascada de áreas. Nadie más — ni siquiera el comité. |
| **Validado** | Todos dentro de esa misma gerencia (el gerente y sus sub-áreas), más el comité. |
| **Aprobado** | Toda la organización. |
| **Borrado** | Igual que un borrador: solo quien lo creó y su gerente. |

Ejemplo concreto: creás un riesgo en Contabilidad. Mientras esté en **borrador**, solo vos y el
gerente de Administración lo ven — ni el gerente de Producción ni el comité. Cuando el gerente
lo **valida**, lo empieza a ver también tu compañero de Recursos Humanos (misma gerencia) y el
comité. Cuando el comité lo **aprueba**, lo ve toda la organización.

### Elementos "sin área"

Un riesgo, control, objetivo, plan o tarea puede crearse sin asignarle un área. Mientras esté en
borrador, se comporta igual que si tuviera área: solo lo gestionan quien lo creó y su cadena de
gerentes — no cualquiera, ni el comité. Una vez validado o aprobado, se vuelve público como
cualquier otro elemento.

### Qué pasa si no tenés permiso

Si en un listado o en una ventana emergente (modal) ves un botón de **"Ver completo"** apagado
o directamente ausente, es porque no tenés acceso a ese elemento — normalmente porque está en
borrador y pertenece a otra área. Si intentás entrar por la fuerza (por ejemplo pegando un link
directo), el sistema te bloquea con un error de "No autorizado".

### Resumen de permisos por rol

| Acción | Usuario | Gerente | Comité | Auditor |
|---|---|---|---|---|
| Crear elementos | En su sector | En su área y sub-áreas | En cualquier área | No |
| Ver borradores | Los propios | Los de su cascada | Ninguno | Los de su propia área |
| Editar directo en borrador | Lo propio | Lo de su cascada | — | Lo propio (su área) |
| Proponer cambios sobre validado/aprobado | — | — | — | Sobre cualquier elemento, de cualquier área |
| Validar borradores | No | Sí, los de su cascada | No | No |
| Aprobar validados | No | No | Sí | No |
| Ver aprobados | Todos | Todos | Todos | Todos |
| Acceso a "Pendientes" | No | Sí | Sí | No |
| Acceso a "Vencimientos" | Sí (su área) | Sí (su cascada) | Sí (todo) | Sí (su área) |

---

## 3. El ciclo de vida de todo elemento

Riesgos, Controles, Objetivos, Planes de Acción y Tareas comparten el mismo ciclo de cuatro
estados. Entenderlo una vez alcanza para entender el sistema entero:

1. **Borrador** (gris) — recién creado. Se edita libremente mientras esté acá.
2. **Validado** (azul) — un gerente lo revisó y le dio el primer visto bueno.
3. **Aprobado** (verde) — el comité dio la aprobación final. Es la versión definitiva y en vivo.
4. **Borrado** (rojo) — se eliminó (borrado lógico: queda registrado pero deja de estar activo).

**Mientras un elemento está en Borrador**, lo editás directo con un botón **Editar**: cambiás
los campos que quieras y guardás.

**Una vez que pasó a Validado o Aprobado**, ya no se edita directo. Los cambios se registran
como una **propuesta** a través del historial de **Actualizaciones** (ver sección 10), que debe
volver a pasar por validación/aprobación según corresponda.

Cada pantalla de detalle tiene, según tu rol y el estado actual, botones para avanzar el ciclo:
**Validar** (borrador → validado, lo hace un gerente), **Aprobar** (validado → aprobado, lo hace
el comité) y **Eliminar** (pasa a borrado, pidiendo confirmación primero).

---

## 4. Panel de Riesgos (pantalla de inicio)

Es el dashboard que ves apenas entrás al sistema. Te da un panorama general, con sesgo según tu
rol: si sos gerente ves solo tu cascada de áreas; si sos del comité, ves toda la organización.
**Nunca muestra borradores** — aunque sean de tu propia área, esos se revisan en "Pendientes"
(sección 11), no acá.

Contiene:

- **Números clave (KPIs)**: cuántos riesgos en tu panorama son críticos, moderados o bajos, según
  su valor final (después de mitigar).
- **Comparación antes/después de mitigar**: dos filas de riesgos agrupados por su valor —
  arriba el valor "crudo" (sin controles ni planes), abajo el valor luego de aplicar controles
  aprobados y planes completos. Comparando ambas filas ves cuánto está bajando el riesgo en la
  práctica.
- **Matriz de calor**: la grilla clásica de impacto × probabilidad, coloreada, para ubicar de un
  vistazo dónde cae cada riesgo.
- **Accesos rápidos** a "Pendientes" (cuánto tenés para validar/aprobar) y "Vencimientos" (cuántas
  tareas están vencidas o por vencer).
- **Toggle "Ver solo aprobados"**: encendido por defecto, muestra solo el panorama consolidado
  (riesgos aprobados). Apagándolo, sumás también los validados — un panorama más amplio pero
  todavía no definitivo.

---

## 5. Riesgos

### 5.1. Listado de Riesgos

Columnas: **Nombre / Tipo** (clic para ir al detalle), **Estado** (badge de color), **Impacto /
Probabilidad**, **Total** (impacto + probabilidad, clasificado en verde/amarillo/rojo), **Residual**
(lo que queda después de mitigar), **Plan de acción** (badges con el % de avance de cada plan
asociado; si no tiene ninguno, dice **"Sin plan"**), **Usuario / Área**, y un botón **Gestionar**
por fila que lleva al detalle.

Filtros: **Buscar** (por nombre, en vivo), **Tipo**, **Estado**, **Área** (con checkbox
**Incluir sub-áreas**), **Solo mayor criticidad** (checkbox), y **Limpiar filtros**. Podés
ordenar haciendo clic en los encabezados de columna, incluidos Nombre, Estado, Total y Residual.

Botón **Nuevo Riesgo** arriba a la derecha abre el asistente de creación.

### 5.2. Crear un riesgo: el asistente de 4 pasos

No se cargan el impacto y la probabilidad a mano: se calculan respondiendo un cuestionario
guiado, con un indicador de progreso de 4 pasos.

**Paso 1 — Datos básicos**: **Nombre** (obligatorio), **Descripción** (opcional), **Tipo de
Riesgo** (obligatorio, desplegable).

**Paso 2 — Preguntas de probabilidad**: 2 o 3 preguntas (varían según el tipo de riesgo elegido),
cada una con varias opciones a elección. Las respuestas se suman y dan la **Probabilidad**
(0 a 10).

**Paso 3 — Preguntas de impacto**: mismo mecanismo, da el **Impacto** (0 a 10).

**Paso 4 — Respuesta, criticidad, objetivos y área**:
- Resumen visual de **Probabilidad**, **Impacto** y **Valor Total**, con su clasificación:
  **bajo** (0-9, verde), **moderado** (10-13, amarillo) o **crítico** (14-20, rojo).
- **Marcar como mayor criticidad**: checkbox que solo se habilita si el total llega a 14 o más.
- **Respuesta** (obligatorio): "Reducir / Mitigar", "Evitar", "Compartir" o "Aceptar" — el tipo de
  riesgo puede restringir cuáles están disponibles.
- **Fundamento**: obligatorio si elegiste "Compartir" o "Aceptar"; opcional para las otras dos.
- **Área** (obligatoria): elegís entre tu área y sus sub-áreas. Si sos del comité, podés elegir
  cualquier área de la organización. El objetivo se asocia después, desde el detalle del riesgo
  (ver 5.5), ya no desde este paso.
- **Sedes** (obligatorio, al menos una): La Plata, Mar del Plata, Villa Gesell, Mar de Ajó y
  Necochea. Marcás las que correspondan; el botón **Todas** las marca de una.

Al final, el botón **Crear Riesgo** guarda todo y te lleva directo al detalle. El riesgo nace en
estado **Borrador**.

### 5.3. Detalle de un riesgo

Es la pantalla central para gestionar un riesgo. Tiene dos columnas.

**Encabezado**: nombre, estado, tipo de riesgo, y los botones de acción que correspondan según
estado/rol — **Editar** (solo en borrador), **Eliminar**, y los de avance de ciclo (**Validar**,
**Aprobar**).

**Columna izquierda**:

- **Información**: descripción, dos cifras grandes con el **Valor Total** y el **Valor
  Residual** (se recalculan solos si cambiás controles o planes, sin recargar la página; el
  desglose muestra cuánto descuenta cada lado, controles vs. planes), y una ficha con Estado,
  Impacto, Probabilidad, Criticidad, Respuesta, Fundamento, Área, **Sedes** ("Todas las sedes"
  si corresponde a todas), Usuario que lo creó y fecha de creación. Ningún elemento del sistema lleva código: se identifica por su nombre en pantalla.
- **Objetivos**: los objetivos asociados (ver 5.5 y sección 7).
- **Controles de Mitigación**: los controles asociados, con su mitigación efectiva (ver 5.5 y
  sección 6).

**Columna derecha**:

- **Planes de Acción**: los planes asociados con su código, estado y % de avance, más un listado
  anidado de sus tareas (ver 5.5 y sección 8).
- **Historial de Actualizaciones**: todos los cambios, validaciones y propuestas
  registrados (ver sección 10).

### 5.4. Editar, recalcular y eliminar

**Editar** (solo disponible en borrador): cambiás Nombre, Descripción, Tipo de Riesgo, Respuesta,
Fundamento, criticidad, Área y Sedes. **Impacto y Probabilidad se muestran de solo lectura** — no se
tocan desde acá.

**Recalcular impacto y probabilidad**: en la pantalla de edición, debajo de esos dos campos
bloqueados, hay un link **"Recalcular impacto y probabilidad (vuelve a pasar el wizard)"**. Te
lleva a una versión corta del asistente (solo las preguntas de probabilidad e impacto, con tus
respuestas anteriores precargadas) y termina con un botón **Recalcular** que guarda los nuevos
valores sin tocar el estado del riesgo. Este recálculo está disponible en cualquier estado del
riesgo salvo **Aprobado**, y nunca para el comité.

**Corregir un solo número sin repetir el cuestionario**: fuera de borrador, además del wizard
completo de arriba, podés corregir Impacto o Probabilidad puntualmente desde la Ficha del riesgo
(ver 5.3) como un campo más — igual que Nombre o Respuesta —, con el mismo circuito de propuesta y
validación que el resto de los campos (ver sección 10). Siguen siendo un entero de 0 a 10, nunca
texto libre.

**Un riesgo se puede seguir editando toda su vida**, incluso ya Aprobado: la ficha (Nombre,
Descripción, Tipo de Riesgo, Respuesta, Fundamento, Área, Impacto, Probabilidad, Sedes) se edita
en cualquier estado desde el bloque de la Ficha, y el cambio se aplica directo o queda como
propuesta según tu rol y el estado (ver sección 10). Un cambio de sedes queda como una propuesta
aparte de los otros campos, y se valida o rechaza por separado. El **estado del riesgo nunca retrocede** por
un cambio posterior a su creación: lo que entra al ciclo borrador → validado → aprobado es cada
propuesta de cambio, no el riesgo en sí.

**Eliminar**: botón rojo en el detalle. Pide confirmación ("Esta acción no se puede deshacer") y
lo pasa a estado Borrado (borrado lógico: queda registrado, pero deja de estar activo).

### 5.5. Asociar controles, objetivos y planes de acción

Las tres secciones (Objetivos, Controles, Planes) del detalle del riesgo, una debajo de la otra,
funcionan igual. Cada una muestra primero lo que **hoy** forma parte del riesgo y,
debajo, aparte, las propuestas todavía no resueltas sobre esa misma sección.

1. Con el riesgo en **borrador**, el botón dice **Editar**; en otro estado dice **Proponer
   cambio**.
2. En modo edición, **Agregar [control/objetivo/plan]** abre un buscador en vivo — escribís el
   nombre y hacés clic en el resultado para incorporarlo a la lista.
3. Cada ítem agregado se puede sacar con una **X**.
4. Para Controles y Planes, además podés tocar el número de **mitigación** de cada ítem (el valor
   que efectivamente descuenta del riesgo — ver secciones 6 y 8 para el detalle).
5. **Guardar** aplica los cambios directo (en borrador, o si sos gerente/comité sobre lo que
   gestionás) o los deja como propuesta.

**Fuera de borrador, cada alta o baja es su propia propuesta**, no un combo de todo el bloque: se
valida, aprueba o rechaza una por una, desde su propia tarjeta debajo de la lista vigente — no
hace falta aceptar o rechazar todo junto. Mientras un ítem tiene una propuesta pendiente, no se
puede volver a proponer un cambio sobre él hasta que esa propuesta se resuelva.

**Objetivos tiene un mínimo obligatorio de 1**: si intentás sacar el último objetivo asociado,
el sistema no te deja y muestra "El riesgo debe tener al menos un objetivo asociado." Controles
y Planes no tienen mínimo — un riesgo puede no tener ninguno todavía.

---

## 6. Controles

### 6.1. Listado de Controles

Columnas: **Nombre**, **Estado**, **Descripción** (resumida), **Mitigación** (1 a 10),
**Riesgos** a los que está asociado, **Usuario / Área**. Filtros: **Buscar** por nombre, **Estado**,
**Área** (con **Incluir sub-áreas**), y **Limpiar filtros**. Se ordena por Nombre o Mitigación.
Botón **Ver** por fila y **Nuevo Control** arriba a la derecha.

### 6.2. Crear y editar un control

Campos: **Nombre** (obligatorio), **Descripción** (opcional), **Valor de Mitigación por
defecto** (obligatorio, de 1 a 10), **Área** (obligatoria, tu área o una sub-área; el comité puede
elegir cualquiera) y **Responsable** (opcional: un usuario del área elegida, sus sub-áreas, o
subiendo hasta la primera gerencia de esa línea — no cualquiera de la organización).

**Si cambiás el Valor de Mitigación por defecto** aparece la opción **"aplicar también a los N
riesgos asociados"**. Sin tildarla, el cambio solo afecta a los riesgos que se asocien de ahora
en más; cada riesgo ya asociado conserva su propio número. Tildada (pide confirmación), el nuevo
valor se aplica de una a **todos** los riesgos ya asociados al control, incluidos los de otras
áreas — queda registrado en el historial de cada riesgo
afectado.

Al lado del campo de mitigación aparece un cuadro de **Referencia de Valoración** que orienta
qué número elegir:

| Rango | Significado |
|---|---|
| **0** | No existe o no se identifica un control. |
| **1–3** | Control deficiente (documentación o cumplimiento incompleto). |
| **4–7** | Cumple 2 o más condiciones de eficiencia (documentado, operando, con evidencias). |
| **8–10** | Control suficiente: documentado, autorizado, operando, con evidencias y efectivo. |

Es orientativo, no una validación forzada del sistema.

**Editar** solo está disponible mientras el control esté en **Borrador** — botón **Editar** en el
detalle. Una vez validado, los cambios se proponen desde la Ficha del control (igual que en
Riesgos, ver 5.4 y sección 10).

### 6.2.1. Pausar un control

Un control puede marcarse **Pausado**, independiente de su estado. Un control pausado **deja de
mitigar**: aunque siga Aprobado, su valor no descuenta del residual de los riesgos a los que está
asociado mientras dure la pausa. La asociación y la mitigación cargada no se pierden — al
reanudarlo, vuelve a descontar como antes. Pausar/reanudar es un campo más de la ficha: pasa por
el mismo circuito de propuesta y validación que cualquier otro cambio (incluida la opción de
propagarlo a todos los riesgos asociados, con confirmación).

### 6.3. Detalle de un control

Muestra: la **mitigación por defecto** en grande, Estado, Área, quién lo registró y cuándo. Una
sección **Riesgos que mitiga** lista cada riesgo asociado con la mitigación *efectiva* para ese
riesgo en particular (que puede diferir de la mitigación por defecto — ver 6.4), más el Total y
el Residual de cada riesgo. Al costado, el **Historial de Actualizaciones**.

### 6.4. Cómo se asocia un control a un riesgo

**La asociación no se hace desde el Control: se hace desde el Riesgo**, en su sección "Controles
de Mitigación" (ver 5.5). Ahí, cada control agregado trae por defecto su "mitigación por
defecto", pero podés tocar ese número puntualmente para ese riesgo — el control en sí no cambia,
solo cambia cuánto mitiga *a ese riesgo específico*.

### 6.5. Eliminar un control

Botón **Eliminar** en el detalle, con confirmación. Al borrarlo (borrado lógico) se eliminan
también sus asociaciones a riesgos; esos riesgos se quedan sin esa mitigación puntual, aunque
conservan las demás.

---

## 7. Objetivos y catálogo PEIS

### 7.1. Listado de Objetivos

Columnas: **Nombre** (con badges **Estratégico** y/o **PEIS** debajo si aplican), **Estado**,
**Descripción**, **Fecha Objetivo**, **Riesgos** asociados, **Usuario / Área**. Filtros: **Buscar**,
**Estado**, **Área** (con **Incluir sub-áreas**), **Limpiar filtros**. Se ordena por Nombre o
Fecha Objetivo. Botón **Ver** por fila y **Nuevo Objetivo** arriba a la derecha.

### 7.2. Crear y editar un objetivo

Campos: **Nombre** (obligatorio), **Descripción** (opcional), **Fecha Objetivo** (con un
checkbox **No aplica** que la deshabilita si el objetivo no tiene plazo), **Estratégico**
(checkbox), **PEIS** (checkbox), **Área** (obligatoria, tu área o una sub-área; el comité puede
elegir cualquiera) y **Responsable** (opcional, se autocompleta con tu usuario: un usuario del
área elegida, sus sub-áreas, o subiendo hasta la primera gerencia de esa línea).

**Si marcás PEIS**, aparece una sección con los 5 ítems del catálogo, cada uno como checkbox:

| Ítem | Descripción |
|---|---|
| **PEIS 1** | Fortalecer la cultura de integridad y transparencia en todos los niveles de la organización. |
| **PEIS 2** | Prevenir, detectar y gestionar conflictos de interés en la toma de decisiones. |
| **PEIS 3** | Asegurar canales de denuncia accesibles, confidenciales y libres de represalias. |
| **PEIS 4** | Promover la debida diligencia en la relación con terceros, proveedores y socios de negocio. |
| **PEIS 5** | Garantizar el cumplimiento normativo y la mejora continua del sistema de integridad. |

Es un catálogo fijo: no se crean, editan ni borran ítems PEIS desde el sistema, solo se
seleccionan. **Si marcás PEIS y no elegís ningún ítem, el sistema no te deja guardar** — pide
al menos uno.

**Editar** solo está disponible en estado Borrador; después de validado, los cambios se
proponen vía Actualizaciones (sección 10). Si un objetivo tenía ítems PEIS seleccionados y
después desmarcás el checkbox PEIS, esos ítems se desasocian.

### 7.3. Detalle de un objetivo

Muestra Estado, Fecha objetivo (o "No aplica"), Área, quién lo creó, y — si es PEIS — la lista
de los ítems seleccionados. Una sección **Riesgos asociados** aclara que esa asociación se hace
desde cada riesgo, no desde acá, y lista los riesgos vinculados con su Total y Residual. Debajo,
si corresponde, los **Planes de acción vinculados** (a través de los riesgos de este objetivo)
con sus tareas. Al costado, el **Historial de Actualizaciones**.

### 7.4. Cómo se asocia un objetivo a un riesgo

Igual que Controles: la asociación vive en el detalle del **Riesgo**, sección "Objetivos" (ver
5.5). Recordá el mínimo de 1 objetivo por riesgo.

### 7.5. Eliminar un objetivo

Botón **Eliminar** en el detalle, con confirmación. Pasa a estado Borrado; las asociaciones a
riesgos se preservan mientras dure, pero su visibilidad queda restringida como cualquier
elemento no aprobado.

---

## 8. Planes de Acción

### 8.1. Listado de Planes de Acción

Columnas: **Nombre**, **Estado**, **Descripción**, **Riesgos** asociados, **Avance** (barra de
progreso: verde al 100%, ámbar si está en curso, guion si no tiene tareas aprobadas todavía),
**Vencimiento** (la fecha pendiente más próxima entre sus tareas sin completar; en rojo con
etiqueta **"Vencido"** si ya pasó), **Usuario / Área**. Filtros: **Buscar**, **Estado**, **Área**
(con **Incluir sub-áreas**), **Limpiar filtros**. Se ordena por Nombre. Botón **Ver** por fila y
**Nuevo Plan** arriba a la derecha.

### 8.2. Crear y editar un plan de acción

Campos: **Nombre** (obligatorio), **Descripción** (opcional), **Área** (obligatoria, tu área o
una sub-área; el comité puede elegir cualquiera) y **Responsable** (opcional: un usuario del área
elegida, sus sub-áreas, o subiendo hasta la primera gerencia de esa línea). Los riesgos que
mitiga este plan ya no se eligen acá: se asocian desde el detalle del riesgo (ver 5.5), igual que
los controles.

**Editar** solo está disponible en Borrador — botón **Editar** en el detalle. Después de
validado, los cambios se proponen desde la Ficha del plan (sección 10).

### 8.3. Cómo se calcula el % de avance de un plan

El **Avance General** de un plan es el promedio del **% de avance de sus tareas, pero solo de
las que están en estado Aprobado**. Las tareas en borrador o validado no entran en la cuenta
(aunque existan), y las que están en estado Borrado quedan afuera por completo. Si el plan no
tiene ninguna tarea aprobada todavía, el avance se muestra como un guion, no como 0%.

Ejemplos:
- 3 tareas aprobadas al 50%, 60% y 70% → avance del plan: 60%.
- 2 tareas en borrador + 1 aprobada al 80% → avance del plan: 80% (solo cuenta la aprobada).
- Ninguna tarea aprobada → avance del plan: — (sin datos).

### 8.4. Detalle de un plan de acción

Muestra Nombre, Estado, Descripción, la barra de **Avance General**, y una ficha con
Vencimiento, Área, Responsable y fecha de creación. A la izquierda, los **Riesgos** asociados
(clic para ver cada uno). A la derecha, el panel **Tareas del Plan** (ver 8.5) y el **Historial
de Actualizaciones** (sección 10).

### 8.5. Gestionar las tareas de un plan

En el panel "Tareas del Plan": con el plan en **borrador**, botón **Editar**; si ya está
validado/aprobado, **Proponer cambio**. En modo edición podés:

- **Agregar existente**: abre un buscador para incorporar una tarea ya creada.
- Cada tarea de la lista se puede quitar con una **X**.
- **Guardar** aplica los cambios.

**Nueva tarea** (botón en el encabezado del panel, si gestionás el plan): abre una ventana con
los mismos campos que el alta de una tarea: Nombre, Descripción, Fecha límite, Avance, **Área** y
Responsable. La tarea se crea y **queda agregada al plan en el acto**, sin propuesta aparte.

- **El área puede ser cualquiera**, aunque no sea la tuya. Ejemplo: en un plan de Medio Ambiente
  hace falta una capacitación de RRHH. La creás desde el plan con área RRHH, sin pedírsela a
  nadie.
- **Estado con el que nace**: si sos gerente y el área es de tu línea, nace **validada**. En
  cualquier otro caso nace en **borrador**, incluso si sos gerente de otra área. Ahí la valida el
  gerente del área de la tarea, y después la aprueba el comité como cualquier tarea.
- **Seguís viéndola** desde el plan y podés comentarla, aunque sea de otra área y esté en
  borrador. Editarla, validarla o rechazarla le corresponde al área de la tarea.
- Si la rechazan, desaparece del plan. Mientras no esté aprobada, **no cuenta para el avance**
  del plan.

**Fuera de borrador**, cada alta o baja de tarea es su propia propuesta — se valida/aprueba/
rechaza una por una, no todo el panel junto — y el avance del plan no se mueve hasta que la
propuesta se aplica. **En borrador**, o si sos gerente/comité operando directo sobre lo tuyo, el
panel se guarda de una, como siempre.

### 8.6. La columna "Plan de acción" en el listado de Riesgos

En el listado de Riesgos (sección 5.1) esta columna reemplazó a la vieja columna "Objetivos".
Muestra un badge por cada plan asociado al riesgo, con su % de avance: **verde** si el plan está
al 100%, **ámbar** si sigue en curso. Si el riesgo no tiene ningún plan asociado, en su lugar
aparece el texto **"Sin plan"**.

### 8.7. Eliminar un plan de acción

Botón **Eliminar** en el detalle, con confirmación. Pasa a Borrado (borrado lógico).

---

## 9. Tareas

### 9.1. Listado de Tareas

Columnas: **Nombre** (en rojo con etiqueta **"Vencida"** si corresponde), **Estado**,
**Descripción**, **Avance** (barra de progreso: roja si vencida, ámbar en curso, verde al 100%),
**Fecha** límite, **Planes** a los que pertenece (por nombre), **Usuario / Área**. Filtros:
**Buscar**, **Estado**, **Área** (con **Incluir sub-áreas**), **Limpiar filtros**. Se ordena por
Nombre, Avance o Fecha. Botón **Ver** por fila y **Nueva Tarea** arriba a la derecha.

### 9.2. Crear y editar una tarea

Campos: **Nombre** (obligatorio), **Descripción** (opcional), **Fecha límite** (opcional),
**Porcentaje de Avance** (slider de 0 a 100% de a 5%, arranca en 0%), **Área** (obligatoria, tu
área o una sub-área; el comité puede elegir cualquiera) y **Responsable** (opcional, se
autocompleta con tu usuario: un usuario del área elegida, sus sub-áreas, o subiendo hasta la
primera gerencia de esa línea).

**Editar** solo en Borrador. Después de validada, los cambios de avance y demás campos se
registran vía Actualizaciones (sección 10) — de hecho, para tareas es el mecanismo normal de
reportar avance una vez que ya están comprometidas.

### 9.3. Detalle de una tarea

Muestra Descripción, una barra grande de **Avance** con la leyenda "Completada" / "En Progreso" /
"Vencida" según corresponda, y una ficha con Estado, Área, Fecha límite, Responsable y fecha de
creación. Una sección **Planes de Acción** lista los planes que contienen esta tarea (o, si no
está en ninguno, sugiere asignarla desde un plan). Al costado, el **Historial de
Actualizaciones**.

### 9.4. Eliminar una tarea

Botón **Eliminar** en el detalle, con confirmación. Pasa a Borrado (borrado lógico).

---

## 10. Actualizaciones, propuestas de cambio y adjuntos

Este mecanismo es común a Riesgos, Controles, Objetivos, Planes de Acción y Tareas. La pantalla
de detalle de cada uno separa dos cosas que antes estaban mezcladas:

- **Conversación**: notas sueltas y adjuntos, sin ciclo de validación — para dejar un comentario
  o subir un archivo sin que eso dispare ninguna revisión.
- **Actividad**: el historial de solo lectura de los cambios efectivamente propuestos y
  resueltos (creación, cambio, validación, aprobación, rechazo), con su propio modal de detalle.

### Cuándo se usa cada uno

Mientras un elemento está en **Borrador**, se edita directo con el botón **Editar** (ver
secciones 5 a 9): ahí no hay propuesta, el cambio se aplica al toque. Una vez **Validado** o
**Aprobado**, ya no hay edición directa de los campos de la Ficha ni de las relaciones
(controles, objetivos, planes, tareas según el caso): cada cambio pasa por una propuesta.

- **Dejar una nota o un adjunto** (sin cambiar nada): se hace desde la **Conversación**, en
  cualquier estado. No aparece en Pendientes, no se valida ni se aprueba.
- **Cambiar un campo de la Ficha** (nombre, descripción, respuesta, fundamento, impacto,
  probabilidad, según la entidad): se edita directo en la Ficha, mandás sólo lo que cambiaste, y
  queda como propuesta (o se aplica directo si corresponde por tu rol).
- **Agregar o quitar un control / objetivo / plan / tarea**: se hace desde el bloque
  correspondiente (ver 5.5, 8.5), y **cada alta o baja es su propia propuesta** — se resuelve una
  por una, no todo el bloque junto.

### Qué pasa después de proponer un cambio

El estado de la propuesta depende de tu rol:

- Si sos gerente o comité operando sobre lo que gestionás, el cambio se aplica **directo**, sin
  pasar por propuesta.
- Si sos un usuario común, o un gerente proponiendo sobre algo fuera de su cascada, nace una
  propuesta en **borrador** y necesita que el gerente responsable la valide.
- Si sos **auditor**, tu propuesta sobre cualquier elemento que veas (validado o aprobado, de
  cualquier área) siempre nace en **borrador**, aunque el elemento sea de tu propia área.
- Mientras una propuesta está pendiente, quien la creó puede **Cancelarla** si todavía está en
  borrador; quien tiene permiso ve **Validar**, **Aprobar** o **Rechazar**. El comité también
  puede rechazar una propuesta ya validada que está esperando su aprobación.

### Adjuntos

Se arrastran o seleccionan desde la Conversación — PDF, Word, Excel, JPG o PNG, hasta 10 MB por
archivo, varios a la vez — y quedan como links descargables ahí mismo, junto con las notas.

---

## 11. Pendientes

Visible solo para **gerentes** y **comité**. Es tu bandeja de trabajo: todo lo que está
esperando tu revisión.

- **Si sos gerente**: ves, en "Para validar", todos los Riesgos, Controles, Objetivos, Planes de
  Acción y Tareas en **Borrador** de tu área y de las sub-áreas que dependen de ella, más las
  propuestas de cambio pendientes sobre elementos de tu cascada.
- **Si sos del comité**: ves, en "Para aprobar", todo lo que está en **Validado** en cualquier
  área de la organización, más las propuestas de cambio en ese mismo estado.

Podés **validar**, **aprobar** o **rechazar** cada ítem directamente desde ahí, sin tener que
entrar al detalle uno por uno. También hay un botón para exportar el listado a PDF, útil para
llevarlo a una reunión.

---

## 12. Vencimientos

Visible para cualquier usuario logueado. Muestra las **tareas con fecha comprometida** (en
estado Validado o Aprobado — los borradores no cuentan todavía), agrupadas en cuatro categorías:

- **Vencidas** — ya pasaron su fecha.
- **Por vencer** — vencen dentro de los próximos 30 días.
- **En plazo** — vencen después de esos 30 días.
- **Sin fecha** — están comprometidas pero no tienen fecha límite asignada.

El listado tiene el mismo sesgo por rol que el resto del sistema: gerentes ven su cascada de
áreas, el comité ve todo, y un usuario común ve las de su propia área. Cada fila muestra la
tarea, a qué plan de acción pertenece, su % de avance y su fecha.

---

## 13. Glosario rápido

- **Riesgo**: la entidad central. Tiene un Valor Total (impacto + probabilidad) y un Valor
  Residual (lo que queda después de descontar controles aprobados y planes completos).
- **Control**: una medida de mitigación, con un valor de mitigación de 1 a 10 que reduce el
  riesgo cuando se lo asocia. Puede **pausarse**: mientras está pausado, no mitiga.
- **Objetivo**: un objetivo estratégico al que se vincula un riesgo (mínimo uno por riesgo).
  Puede marcarse como PEIS, en cuyo caso exige elegir al menos un ítem del catálogo fijo de 5.
- **Plan de Acción**: agrupa tareas y riesgos; su % de avance sale del promedio de sus tareas
  aprobadas.
- **Tarea**: la unidad de trabajo dentro de un plan, con fecha límite y % de avance propio.
- **Actualización**: cada entrada de la Conversación o la Actividad de un elemento — una nota,
  un cambio propuesto, una validación, con o sin adjuntos.
- **Borrador / Validado / Aprobado / Borrado**: los cuatro estados por los que pasa todo
  elemento del sistema, y que determinan quién puede verlo y editarlo (ver secciones 2 y 3).
- **Sede**: la administración central (La Plata) o una de las generadoras de la costa. Cada
  riesgo corresponde a una, varias o todas. Es informativa: no cambia quién lo ve ni quién lo
  gestiona.
- **Auditor**: rol transversal que ve lo validado/aprobado de toda la organización y propone
  cambios sobre cualquier elemento, sin poder crear, validar, aprobar ni eliminar (ver sección
  2).
