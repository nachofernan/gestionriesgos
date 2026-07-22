# Decisiones — Módulo de Auditoría

Bitácora **append-only** de decisiones de diseño y arquitectura, con el motivo y lo que se descartó.
Es la respuesta al *por qué* de cómo está armado el sistema. El *qué* está en
[`modulo-auditoria.md`](modulo-auditoria.md); el *cuándo* cambió, en [`CHANGELOG.md`](CHANGELOG.md).

**Regla:** no se reescribe el pasado. Una decisión que se revierte no se edita ni se borra: se agrega
una entrada nueva que la reemplaza, referenciando la que cae. Cada entrada lleva un id `D-NNN`
estable.

Esta bitácora arranca el 2026-07-22 al reorganizar el proyecto. Las decisiones anteriores a esa fecha
se reconstruyeron desde `docs/updates/` y el historial de git; el detalle completo de cada una está
en su entrada de changelog enlazada. De acá en más, toda decisión de diseño se anota primero acá.

---

## D-001 — Marco de trabajo de tres roles + estructura de documentación (2026-07-22)

**Decisión.** El proyecto adopta el marco de trabajo del proyecto "Consultorio": tres roles
(`auditoria-mentor` asesor de solo lectura, `auditoria-senior`, `auditoria-junior`), con la sesión
principal trabajando por defecto en stance de mentor (se conversa antes de codear). El `CLAUDE.md`
se reescribe con principio cero, axiomas de arquitectura numerados y una sección de "cómo se
pregunta". El testing pasa a dosificarse por zona (núcleo sagrado vs periferia) y checkpoint. La
documentación de decisiones vive en este archivo; `CHANGELOG.md` se mueve a `docs/`.

**Motivo.** Es una forma de trabajo que al usuario le viene rindiendo en otro proyecto, sobre todo en
gestión de tokens (testear y reportar dosificado) y en separar "pensar" de "ejecutar".

**Descartado.** Migrar los `docs/updates/` a un `BITACORA.md` único: se dejan como archivo histórico
y siguen funcionando como el detalle por fecha que indexa el changelog.

---

## D-002 — La mitigación del residual sólo cuenta lo aprobado (2026-07-21)

**Decisión.** El `valor_residual` de un riesgo descuenta sólo la mitigación de los controles en
estado `aprobado` y de los planes de acción al 100% de avance; el avance de un plan promedia sólo sus
tareas aprobadas. Un control en borrador/validado no baja el valor; un plan incompleto no descuenta.

**Motivo.** El residual tiene que reflejar mitigación real y vigente, no intención. Un control sin
aprobar todavía no protege de nada.

Fuente: [changelog 2026-07-21 (ter)](updates/2026-07-21c.md).

---

## D-003 — Doble validación entre gerencias; se aplana el acceso por gerencia (2026-07-21)

**Decisión.** Se descarta la distinción propia/ajena (`gerencia_ajena`): todas las gerencias de un
riesgo pesan igual. Un borrador es siempre mono-gerencia; compartir un riesgo con otra gerencia sólo
se permite tras validarlo y sólo a un gerente. Un riesgo compartido entre dos o más gerencias exige
que cada propuesta de cambio la validen todas (`validacion_gerencia`); un solo rechazo la tumba. La
regla vive centralizada en `Riesgo::cambioRequiereDobleValidacion()`. El comité queda afuera de la
doble validación.

**Motivo.** Un riesgo que afecta a varias gerencias no puede cambiarse por decisión de una sola. La
regla anterior (propia/ajena) agregaba complejidad sin reflejar esa realidad.

**Descartado.** El flag `gerencia_ajena` y la asimetría de permisos entre la gerencia de origen y las
demás.

Fuente: [changelog 2026-07-21](updates/2026-07-21.md).

---

## D-004 — La actualización de un riesgo es sólo mensaje + adjunto (2026-07-21)

**Decisión.** `camposEditables()` devuelve `[]` para un riesgo: el modal "Nueva Actualización" queda
sólo con Mensaje + Adjuntos. Impacto y probabilidad no se editan desde ahí.

**Motivo.** Impacto y probabilidad los calcula el wizard; dejarlos editar a mano en el modal los
desincronizaba del cálculo. Nombre/descripción se resolverán con otro mecanismo.

Fuente: [changelog 2026-07-21 (bis)](updates/2026-07-21b.md).

---

## D-005 — Impacto y probabilidad se calculan por wizard, no a mano (2026-07-03)

**Decisión.** Impacto y probabilidad salen de 5 preguntas guiadas por dimensión, no de carga libre.
En la edición son de solo lectura; para cambiarlos hay que "Recalcular" repitiendo el wizard. Objetivo
y plan de acción (si la respuesta es mitigar) pasan a ser requisito de validación, no de creación.

**Motivo.** La calificación de un riesgo tiene que ser reproducible y fundamentada, no un número
puesto a criterio de quien carga.

Fuente: [changelog 2026-07-03](updates/2026-07-03.md) y [2026-07-03 (tris)](updates/2026-07-03c.md).

---

## D-006 — El comité no debería poder crear elementos (pendiente, 2026-07-17)

**Decisión (diferida).** Hoy el comité puede crear un elemento y al redirigir al show del borrador
recién creado se come un 403 (el `create()` de las policies da `true` porque el comité cuelga del área
raíz, pero `view()` le corta el borrador ajeno). Se decidió **no** arreglarlo por separado: se resuelve
junto con los permisos particulares cuando el módulo migre al sistema real.

**Motivo.** Un parche aislado ahora se pisaría con el rediseño de permisos que viene con la migración.

Fuente: [ROADMAP.md](ROADMAP.md), ítem de próximos pasos.
