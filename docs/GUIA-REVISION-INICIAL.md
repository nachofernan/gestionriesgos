# Revisión de la carga inicial — Ayuda memoria

Todo lo que se llevaba en Excel y en Redmine ya está cargado en el sistema. Antes de dar el sistema
por "en régimen", cada gerencia **revisa y firma** lo que le corresponde. La pantalla **Revisión
inicial** sirve para eso, y se retira cuando el salto se da por terminado. Las firmas quedan
guardadas como registro.

Se revisan uno por uno los **riesgos, controles, objetivos, planes de acción y tareas**.

---

## Quién hace qué

| Rol | Qué puede hacer |
|---|---|
| **Empleado / coordinador** | Ve los elementos de su área y aquellos de los que es responsable. Los **marca como revisados**, con un comentario opcional. Si encuentra algo mal, propone el cambio desde la pantalla del elemento. |
| **Gerente** | **Firma** los elementos de su gerencia. No "revisa": firma o no firma. Puede firmar aunque nadie de su equipo haya revisado antes. |
| **Comité de Riesgo y Auditoría** | Ven el avance de **todas** las gerencias, en solo lectura. No revisan ni firman. |

- **Si el responsable de un elemento es el gerente**, nadie más lo revisa: lo firma sólo esa persona.
- Cada elemento lo firma **la gerencia de su área**.

---

## Qué bloquea, qué advierte y qué deja pasar

Para cada elemento, el sistema muestra sus inconsistencias con tres colores:

| Color | Significa | ¿Se puede firmar? |
|---|---|---|
| 🔴 **Error** | Algo que tiene que estar y falta. | **No**, hasta arreglarlo. |
| 🟡 **Advertencia** | Algo raro, que puede estar bien. | **Sí**, con un comentario obligatorio que explique por qué se acepta. |
| 🔵 **En curso** | Un error o una advertencia cuyo arreglo ya fue **validado** y espera la aprobación del Comité. | **Sí**, sin comentario. La firma deja constancia del arreglo pendiente. |

### Errores (bloquean la firma)

| Elemento | Error |
|---|---|
| Cualquiera | No tiene área asignada. |
| Riesgo | No tiene ningún objetivo asociado. |
| Riesgo | La respuesta es *Reducir / Mitigar* y no tiene plan de acción. |
| Riesgo | La respuesta es *Aceptar* o *Compartir* y no tiene fundamento. |
| Objetivo | Es PEIS y no tiene ítems PEIS. |
| Tarea | No pertenece a ningún plan de acción. |

### Advertencias (se firman con comentario)

| Elemento | Advertencia |
|---|---|
| Cualquiera | No tiene responsable. |
| Riesgo | El impacto o la probabilidad están en 0. |
| Riesgo | No tiene controles asociados. |
| Control | La mitigación por defecto está en 0. |
| Control | Mitiga 0 en alguno de sus riesgos. |
| Control | No mitiga ningún riesgo. |
| Objetivo | No tiene riesgos asociados. |
| Plan de acción | No tiene riesgos asociados. |
| Plan de acción | No tiene tareas. |
| Tarea | No tiene fecha. |
| Tarea | Está vencida y su avance es menor al 100%. |

*Este catálogo es la propuesta inicial. Si hace falta, se ajusta.*

---

## Cómo se arregla algo

Desde la pantalla de revisión, el nombre de cada elemento lleva a su ficha. El arreglo se hace ahí,
**con el circuito de siempre**:

1. Un empleado o coordinador propone el cambio. Su propuesta queda en **borrador**.
2. El gerente la **valida**.
3. Si el elemento ya estaba aprobado, el Comité la **aprueba** y recién ahí se aplica.

**Para firmar no hace falta esperar al Comité.** Si el cambio que arregla un error ya está validado,
ese error pasa a **en curso** (azul) y deja de bloquear.

**Las propuestas en borrador no cuentan como arreglo** hasta que el gerente las valida.

---

## "Validar y firmar"

Cuando alguien del equipo propuso cambios sobre un elemento y todavía nadie los validó, la fila lo
avisa ("✎ N cambio(s) propuesto(s) sin validar"). El gerente ve el botón **Validar y firmar** en
lugar de **Firmar**:

- La ventana muestra **qué cambió y quién lo propuso**.
- Al confirmar, se **validan todos** esos cambios y se **firma** en un solo paso.
- **Es todo o nada.** Si aun validando los cambios quedaría un error, o hay advertencias y falta el
  comentario, no se valida ni se firma nada.
- **Mientras haya cambios sin validar, no se puede firmar a secas.** Si el gerente no está de
  acuerdo con alguno, lo rechaza desde la ficha del elemento y después firma.

---

## Qué pasa después de firmar

- **La firma queda.** Si el elemento cambia después, la firma no se anula. Si ese cambio genera una
  inconsistencia nueva, la pantalla la sigue mostrando.
- **Si el Comité rechaza un arreglo** con el que se había firmado, la firma también queda, y el error
  vuelve a aparecer en la pantalla.
- **Cada firma registra:**
  - quién firmó y cuándo;
  - el comentario;
  - qué advertencias se aceptaron;
  - qué arreglos estaban pendientes de aprobación.

---

## La pantalla

- **Avance por gerencia:** cuántos elementos están firmados, revisados (sin firmar) y pendientes, y
  cuántos tienen errores.
- **Pestañas:** Riesgos, Controles, Objetivos, Planes de acción y Tareas.
- **Filtros:**
  - **Revisión:** *Pendientes* (es el que viene seleccionado, e incluye lo revisado sin firmar) o
    *Firmados*.
  - **Inconsistencias:** con errores, sólo advertencias o sin inconsistencias.
  - **Gerencia.**
  - **Búsqueda por nombre.**
