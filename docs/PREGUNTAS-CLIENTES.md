# Preguntas para los clientes

Dudas de dominio abiertas para la próxima reunión. Cuando una se contesta, se tacha acá y, si cierra
una decisión, se anota en `DECISIONES.md`.

---

## ¿Impacto y probabilidad se pueden cargar a mano, o sólo desde el cuestionario?

*Anotada el 2026-09-29.*

La regla original es que impacto y probabilidad salen **sólo** del cuestionario de preguntas guiadas
y que, para cambiarlos, hay que volver a pasar el cuestionario ("Recalcular"). Pero hoy, al proponer
un cambio desde la ficha del riesgo, impacto y probabilidad aparecen como números editables a mano,
con el cuestionario como alternativa ("o recalcular con el cuestionario").

Hoy conviven las dos formas y hay que elegir una:

- **Sólo cuestionario.** El valor siempre se puede rastrear hasta las respuestas y nadie lo ajusta a
  ojo. Contra: corregir un punto obliga a repetir las diez preguntas.
- **También a mano.** Es más ágil para correcciones chicas. Contra: el valor puede no coincidir con
  ninguna combinación de respuestas, y hay que definir quién puede hacerlo y si exige un fundamento.

**El auditor también pasa por esta pregunta.** El rol auditor ([D-020](DECISIONES.md#d-020)) puede
proponer cambios de impacto y probabilidad de dos formas: desde la ficha, con los números, o
repitiendo el cuestionario. En los dos casos la propuesta nace en borrador y la valida el gerente
responsable. Si la respuesta es "sólo cuestionario", el auditor queda limitado a esa vía como todos.
Falta confirmar si al auditor, justamente porque revisa, le conviene una regla distinta: por ejemplo,
que sólo pueda usar el cuestionario, así cada corrección que propone queda fundamentada en las
respuestas.

---

## Un control puede pausarse, y cambiar su mitigación por defecto pisa a todos sus riesgos

*Anotada el 2026-09-29. Ya implementado para mostrarlo en vivo ([D-017](DECISIONES.md#d-017), [D-019](DECISIONES.md#d-019)); falta confirmar que la regla es la que quieren.*

Dos reglas nuevas sobre los controles, que pueden no ser las correctas:

**1. Cambiar la mitigación por defecto de un control lo lleva a todos los riesgos asociados.**
Al cambiar el valor por defecto aparece la opción "aplicar también a los N riesgos asociados". Si se
tilda (y se confirma en un aviso), el control pasa a valer eso en **todos** sus riesgos, aunque
alguno tuviera cargado un valor distinto a propósito. El cambio se completa cuando se aprueba la
propuesta.

- ¿Está bien pisar valores que alguien ajustó a mano para un riesgo puntual, o esos ajustes deberían
  respetarse?
- ¿Alcanza con que lo apruebe quien gestiona el control, o los riesgos compartidos entre gerencias
  deberían votar el cambio (hoy no lo votan)?

**2. Un control puede quedar "pausado".**
Un control pausado sigue asociado a sus riesgos y conserva su valor, pero **deja de bajar el valor
residual** hasta que se lo reanude. Pausar y reanudar es una propuesta más, con validación y
aprobación.

- ¿Existe en la práctica la idea de "pausar" un control, o lo que pasa es otra cosa (por ejemplo, un
  control que se da de baja, o que deja de aplicar sólo a un riesgo)?
- ¿Un control pausado debería seguir contando de alguna forma (por ejemplo, avisar que el riesgo
  quedó más expuesto), o sólo dejar de descontar?
- ¿Pausar debe pasar por aprobación, o basta con que lo haga quien gestiona el control?

---

## Hay riesgos aprobados que hoy no podrían aprobarse

*Anotada el 2026-09-30, a partir de la carga inicial.*

Los 160 riesgos de la carga inicial entran **aprobados**, pero muchos no cumplen las reglas que el
sistema exige para validar uno nuevo:

- **120 riesgos no tienen ningún objetivo asociado.** Hoy el objetivo es obligatorio para validar.
- **64 riesgos con respuesta "Reducir / Mitigar" no tienen plan de acción.** Hoy el plan es
  obligatorio para validar un riesgo que se mitiga ([D-009](DECISIONES.md#d-009)).

Las opciones son:

- **Completarlos.** Cada gerencia asocia objetivos y planes a sus riesgos. ¿Quién lo hace y con qué
  plazo?
- **Aceptarlos como están.** Quedan como histórico y la regla se aplica sólo a lo nuevo. Contra: el
  panel va a mostrar riesgos "mitigados" sin ningún plan que los mitigue.
- **La regla es demasiado estricta.** ¿El objetivo tiene que ser obligatorio, o hay riesgos que no
  cuelgan de ningún objetivo?

Relacionado: **49 de los 78 objetivos** no tienen ningún riesgo asociado. ¿Son objetivos sin riesgos
identificados todavía, o faltan asociaciones en la planilla?

---

## Sectores cargados sin ninguna persona asignada

*Anotada el 2026-09-30.*

Once sectores no tienen ningún usuario: Tesorería, Compras, Contabilidad, Impuestos, Presupuesto,
Cobranzas, Cuentas a Pagar, Automotores, Comercio Exterior y los cuatro sectores de Comercialización
y Despacho (COG, Combustible, Economía Energética, SMEC). Entre todos tienen **231 elementos**, sólo
Tesorería 56. El responsable de esos elementos es siempre el coordinador o el gerente de arriba.

- ¿Hay gente en esos sectores que vaya a usar el sistema? Si la hay, falta darla de alta.
- Si no, ¿los sectores son sólo una forma de clasificar los riesgos, y quien responde es siempre la
  coordinación? En ese caso conviene saberlo, porque hoy un sector se comporta como un área con
  permisos propios.

---

## Fortalecimiento Institucional mitiga riesgos de todas las gerencias

*Anotada el 2026-09-30.*

Los 24 controles de Fortalecimiento Institucional están asociados a **97 riesgos de otras cinco
gerencias**: Administración y Finanzas (31), Medio Ambiente, Seguridad e Higiene (25), Asuntos
Legales (23), RRHH (10) y Producción (8). Pasa lo mismo con 19 asociaciones de sus planes de acción.

Hoy el riesgo pertenece sólo a su gerencia: Fortalecimiento Institucional no aparece como gerencia
del riesgo ni vota sus cambios. Pero cuando cambia o se pausa uno de sus controles, el valor residual
de riesgos ajenos cambia.

- ¿Fortalecimiento Institucional es un área transversal, dueña de controles que aplican a toda la
  empresa (por ejemplo, del programa de integridad)?
- Si un control suyo cambia, ¿la gerencia dueña del riesgo afectado debería enterarse o aprobarlo?
- Al revés: ¿la gerencia dueña del riesgo puede desasociar un control de Fortalecimiento
  Institucional sin consultarle?

---

## Planes con tareas a cargo de otra gerencia

*Anotada el 2026-09-30.*

En 24 casos, una tarea está a cargo de una gerencia distinta de la del plan al que pertenece. Por
ejemplo, planes de Producción con tareas de Administración y Finanzas o de Comercialización y
Despacho.

- ¿Es normal que un plan reparta tareas entre gerencias?
- Si es así, ¿quién valida el avance de esa tarea: la gerencia del plan o la de la tarea? Hoy cada
  tarea la valida quien gestiona su área.

---

## ¿Hasta dónde puede "subir" el responsable de un elemento?

*Anotada el 2026-09-30. Ya implementado ([D-022](DECISIONES.md#d-022)); falta confirmar el límite.*

El responsable de un control, objetivo, plan o tarea tiene que ser alguien del área del elemento, de
un sector de abajo o de un área superior. Así, una coordinación puede nombrar responsable a su
gerente. Hoy "superior" llega hasta arriba de todo, así que también se puede nombrar al **Comité de
Riesgo**.

- ¿Tiene sentido que el Comité sea responsable de un control o una tarea, o el tope debería ser la
  gerencia?
