# Propuesta: Borrador interno

> Estado: **decidida e implementada** ([D-044](DECISIONES.md#d-044)). Lo que sigue es la propuesta
> original; donde difiera, manda D-044.

## El problema

El módulo se pensó para el **comité de riesgos**: todo riesgo nace en borrador, lo valida el
gerente de su área y lo aprueba el comité. El camino natural de cualquier riesgo termina en el
comité.

Medio Ambiente trabaja distinto, y lo hace así desde hace años. Gestiona muchos riesgos propios,
pero **sólo una parte llega al comité**: los que involucran a otras áreas o los que alcanzan un
nivel crítico. Al resto lo gestiona el área sola.

Hoy el sistema no da lugar a esa gestión interna. Si un área carga todos sus riesgos:

- la bandeja de **Pendientes** del gerente se llena de elementos que nunca piensa subir;
- en borrador, los controles y planes **no mitigan**, así que el residual queda igual al valor
  total y el avance de los planes no se ve;
- el **Panel de Riesgos** y **Vencimientos** no muestran borradores, así que el área no tiene dónde
  seguirlos.

## La propuesta en una línea

Un **flag "Interno"** que cualquier elemento en borrador puede llevar. Funciona como un candado que
lo deja en un **borrador proyectivo**: el área lo gestiona como si estuviera vigente, sin que entre
al circuito del comité.

## Reglas

1. **Todo elemento puede llevarlo:** riesgo, control, objetivo, plan de acción y tarea.
2. **Sólo existe en borrador.** Un elemento validado o aprobado no puede tener flag.
3. **Para validar hay que quitarlo.** Al quitar el flag, el elemento entra a la bandeja de
   Pendientes del gerente y sigue el ciclo de siempre (`borrador → validado → aprobado`).
4. **Lo pone o lo saca quien gestiona el borrador**, es decir, cualquiera que hoy lo puede ver y
   editar. El flag no da permisos nuevos: quitarlo no valida nada, sólo pone el elemento en la
   bandeja del gerente.
5. **En el formulario de creación** aparece la opción "Interno", **sin marcar** por defecto. Un
   elemento con flag lleva un **badge "Interno"** en todas las pantallas donde aparece.
6. **El comité no ve los flags.** Un elemento interno es un borrador de otra área, que el comité ya
   hoy no ve. No hace falta ocultar nada nuevo.
7. **Sirve a todas las áreas.** Lo pide Medio Ambiente, pero no está atado a su método de evaluación
   ni a su área. Cualquier área puede usarlo, por ejemplo para cargar lo que todavía está armando
   sin llenarle la bandeja al gerente.
8. **Queda en el historial.** Poner o quitar el flag se registra como cualquier otra edición del
   borrador: quién, cuándo y de qué a qué.

## El cálculo proyectivo

El flag sólo se combina con otros flags. Un elemento interno muestra cuánto **valdría** si lo que
el área considera vigente ya lo estuviera: sus controles y planes internos.

### Qué mitiga en la proyección de un riesgo interno

| Control o plan relacionado | ¿Mitiga en la proyección? |
|---|---|
| Aprobado | Sí, igual que hoy |
| Borrador **con** flag | **Sí**: el área lo considera vigente en su gestión interna |
| Borrador **sin** flag | No: está en camino a validarse y todavía no vale nada |
| Validado | No, igual que hoy |

### El avance proyectado de un plan interno

Se calcula con sus tareas **con flag**, más las aprobadas si tiene. Una tarea validada o en
borrador sin flag **se puede relacionar igual**, porque el plan sigue siendo un borrador, pero no
suma ni cambia el avance proyectado.

### Ejemplo

Un riesgo interno con valor total **16** tiene relacionados estos elementos:

| Elemento | Estado | Mitigación | Oficial | Proyectado |
|---|---|---|---|---|
| Control A | Aprobado | 3 | −3 | −3 |
| Control B | Borrador con flag | 4 | — | −4 |
| Control C | Borrador sin flag | 2 | — | — |
| Plan P | Borrador con flag | 4 (nominal) | — | −3 |
| **Residual** | | | **13** | **6** |

El plan P tiene tres tareas:

- una tarea con flag al 50%;
- una tarea con flag al 100%;
- una tarea validada al 0%, que no cuenta.

Su avance proyectado es el 75%, así que mitiga 4 × 75% = 3, redondeado hacia abajo como en el
cálculo de siempre (D-037).

### Lo que no cambia

- El **residual oficial** se calcula igual que hoy. Lo ven el comité, el Panel de Riesgos y los
  reportes.
- El residual y el avance proyectados se muestran **sólo** en los elementos internos y en la
  pantalla interna del área, siempre señalados como proyección.
- El ciclo de estados, los permisos y la visibilidad entre áreas.

## Qué gana cada uno

- **El área (Medio Ambiente y cualquier otra):** una matriz propia completa. Puede evaluar con su
  método, relacionar controles, planes y tareas, seguir el avance proyectado, ver los vencimientos
  y llevar el historial de cambios. Es una planilla con esteroides dentro del mismo sistema.
- **El gerente:** la bandeja de Pendientes muestra sólo lo que el área decidió subir. Cuando un
  riesgo interno crece, ya está cargado y con historia: se le quita el flag y se valida.
- **El comité y auditoría:** la matriz del comité no se ensucia con riesgos chicos, y lo que llega
  pasa por el mismo ciclo y los mismos controles de siempre.

## Pantalla interna del área

Es una vista propia, separada del Panel de Riesgos:

- los elementos internos del área y de sus sub-áreas;
- el valor total y el residual proyectado de cada riesgo interno;
- el avance proyectado de los planes internos;
- los vencimientos de las tareas internas.

## Cómo crece un riesgo interno

1. El área decide que el riesgo tiene que ir al comité porque involucra a otras áreas o porque se
   volvió crítico.
2. Le quita el flag al riesgo y a lo que su validación exige: el objetivo y, si la respuesta es
   "Reducir/Mitigar", el plan de acción.
3. Esos elementos aparecen en la bandeja del gerente, que los valida.
4. El comité los recibe y los aprueba, como cualquier otro.

## Fuera de alcance (se conversa aparte)

- **Rechazo del comité.** Hoy, si el comité rechaza algo validado, queda borrado de forma
  definitiva. Se propone que vuelva a borrador, para que el área decida si le da la baja final o lo
  edita y vuelve a recorrer el camino. Ese cambio no tiene relación con el flag.

## A tener en cuenta

- **Qué escala al comité lo decide el área.** El comité no se entera de lo interno, así que el
  criterio de qué subir queda en manos del gerente de cada área. Es parte del diseño y conviene que
  auditoría lo sepa al aprobar la propuesta.
- **El residual proyectado no es el oficial.** Las pantallas tienen que dejar clara la diferencia
  para que nadie tome una proyección por un valor aprobado.

---

## Anexo técnico (alcance estimado)

| Pieza | Cambio |
|---|---|
| Esquema | Columna `interno` (boolean, por defecto `false`) en riesgos, controles, objetivos, planes de acción y tareas. Los borradores actuales quedan sin flag y nada cambia para ellos. |
| Regla de validación | Un elemento con flag no se puede validar. Se agrega a los bloqueos de validación de las cinco entidades, y al validar o al salir de borrador el flag no puede quedar puesto. |
| Pendientes | La bandeja del gerente excluye los elementos con flag. |
| Cálculo | Nuevos accessors en el modelo para el residual proyectado del riesgo y el avance proyectado del plan, según la tabla de arriba. El residual oficial no cambia. Es una ampliación del axioma 2 y se registra en `DECISIONES.md`. |
| Formularios | Opción "Interno" sin marcar en la creación de las cinco entidades, y la posibilidad de ponerlo o quitarlo mientras el elemento esté en borrador. |
| Vistas | Badge "Interno" en listados, fichas y selectores de relaciones. |
| Historial | Poner o quitar el flag se registra como una edición del borrador. |
| Pantalla nueva | Panel interno del área: riesgos internos con residual proyectado, planes con avance proyectado y vencimientos de tareas internas. |
| Tests | Un borrador con flag proyecta y uno sin flag no; un validado no proyecta; una tarea sin flag no cambia el avance proyectado; no se puede validar con flag; los elementos con flag no aparecen en Pendientes; el comité no ve elementos internos; el residual oficial no cambia. |

Los permisos no cambian: el flag lo gestiona quien ya puede editar el borrador (`update` de cada
Policy).
