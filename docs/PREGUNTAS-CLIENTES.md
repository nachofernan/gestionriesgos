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
