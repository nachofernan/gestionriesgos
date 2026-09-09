# Reconstrucción de la carga inicial (Excel → seeders)

Este documento analiza los 14 CSV en `docs/datos/` (exportados del Excel armado por auditoría) contra
el esquema real del módulo (migraciones + modelos) y deja registrado qué se puede cargar directo, qué
necesita transformación, qué falta, qué sobra y qué se tuvo que inventar — para que sirva de base a los
seeders que se escriban después. **No es un changelog de código**: acá no se tocó ningún seeder,
migración ni modelo. Es insumo para la próxima etapa.

Decisiones ya charladas con el usuario y que rigen todo lo que sigue:

1. Los registros migrados (riesgos, controles, objetivos, planes) arrancan en estado **`aprobado`**
   (representan controles y planes que ya funcionan hoy en la empresa, no un borrador a revisar).
2. `plan_accion_riesgo.mitigacion` sin dato en el Excel se deja en **0 / sin valor** — no se inventa un
   número que toque el cálculo del residual. Queda pendiente de carga manual vía UI.
3. No se agrega columna `codigo` a `areas`. El mapeo código-Excel → `Area` es solo un array interno del
   seeder, sin cambio de esquema.
4. **CR (Comité de Riesgo)** se crea como `Area` real, raíz del árbol (`area_padre_id = null`,
   `tipo = Gerencia`), igual al patrón que ya existe en `database/seeders/AreaSeeder.php:15` (nodo
   "Comité de Riesgo" con Lucía). **Munafo = Lucía**, `rol = comite`, `area_id` = CR. Todas las
   gerencias del Excel cuelgan de CR.

---

## 1. Resumen ejecutivo

| CSV | Tabla(s) destino | Veredicto |
|---|---|---|
| `UsuariosAreas.csv` | `areas`, `users` | Con transformación — jerarquía a reconstruir por prefijo de código, CR como raíz nueva |
| `AreaRiesgo.csv` | — (valida `riesgos.area_id`) | Redundante, no alimenta tabla propia |
| `TipoRiesgos.csv` | `tipos_riesgo` | Directo, salvo el tipo "estratégico" que falta |
| `Riesgos.csv` | `riesgos` | Con transformación — área/usuario en texto, tipo "estratégico" sin catálogo, sin estado |
| `Controles.csv` | `controles` | Con transformación — área/usuario en texto, `mitigacion_default` vacío en ~46% de filas |
| `ControlRiesgo.csv` | `control_riesgo` (pivot) | Con transformación + duplicados exactos a limpiar |
| `Objetivos.csv` | `objetivos` | Con transformación — área/usuario en texto |
| `ObjetivoRiesgo.csv` | `objetivo_riesgo` (pivot) | Directo, con muchas filas sin `riesgo_id` (se omiten) |
| `PeisItems.csv` | `peis_items` | Reemplaza el contenido genérico del seeder actual, tabla ya existe |
| `ObjetivoPeisItem.csv` | `objetivo_peis_item` (pivot) | Directo |
| `PlanesAccion.csv` | `planes_accion` | Con transformación fuerte — mismo `id` repetido por cada riesgo asociado, hay que deduplicar |
| `PlanAccionRiesgo.csv` | `plan_accion_riesgo` (pivot) | Es la fuente real del vínculo plan↔riesgo; `mitigacion` casi siempre vacía |
| `Tareas.csv` | `tareas` | Con transformación — área/usuario en texto |
| `PlanAccionTarea.csv` | `plan_accion_tarea` (pivot) | Directo, 1 tarea = 1 plan en la práctica |

---

## 2. Jerarquía de áreas reconstruida

`UsuariosAreas.csv` (29 filas) no trae `area_padre_id`: la jerarquía hay que inferirla del prefijo del
código (`GAYFFT` cuelga de `GAYFF`, que cuelga de `GAYF`) y de la columna `Gerencia` (`si`/`no`, que
mapea a `areas.tipo = Gerencia` vs. sub-área sin tipo). Árbol reconstruido, con CR como raíz agregada
(no viene de un prefijo, viene de la decisión 4):

```
CR   Comité de Riesgo                                    (Gerencia, Munafo/Lucía → rol comite)
├─ CYD    Comercialización y Despacho                     (Gerencia, Doncheff)
│  ├─ CYDC  Comercialización y Despacho Combustible        (Doncheff)
│  ├─ CYDS  Comercialización y Despacho SMEC               (Doncheff)
│  ├─ CYDG  Comercialización y Despacho COG                (Doncheff)
│  └─ CYDE  Comercialización y Despacho Economía Energética (Doncheff)
├─ PRO    Gerencia de Producción                           (Gerencia, Grassi) — sin sub-áreas
├─ GAL    Gerencia de Asuntos Legales                      (Gerencia, Langus) — sin sub-áreas
├─ GAYF   Gerencia de Administración y Finanzas            (Gerencia, Piris)
│  ├─ GAYFF   Coord. Financiera                             (Biasi)
│  │  ├─ GAYFFT  Tesorería                                  (Biasi)
│  │  ├─ GAYFFI  Impuestos                                  (Biasi)
│  │  ├─ GAYFFC  Contabilidad                               (Biasi)
│  │  ├─ GAYFFZ  Cobranzas                                  (Biasi)  ← huérfana, ver más abajo
│  │  └─ GAYFFP  Presupuesto                                (Biasi)
│  ├─ GAYFS   Coord. Sistemas                                (Aieta) — sin sub-áreas
│  └─ GAYFA   Coord. Administrativa                          (Berri)
│     ├─ GAYFAC   Compras                                    (Berri)
│     ├─ GAYFACP  Cuentas a Pagar                             (Berri)
│     ├─ GAYFACE  Comercio Exterior                           (Berri)
│     └─ GAYFAA   Automotores                                 (Berri)
├─ RRHH   Coord. Gral. de RRHH                              (Gerencia, Pasquale) — sin sub-áreas
├─ OYM    Organización y Métodos                            (Gerencia, Stefanelli) — sin sub-áreas
├─ MASH   Medio Ambiente, Seguridad e Higiene                (Gerencia, Fasano)
│  ├─ MASHS   Seguridad e Higiene                             (Uva)
│  └─ MASHMA  Medio Ambiente                                  (Torres)
├─ UAI    Auditoría Interna al PI                           (Gerencia, Castiglioni) — sin sub-áreas
├─ PRE    Prensa                                            (Gerencia, Aversa) — sin sub-áreas
└─ FC     Fortalecimiento Institucional                     (Gerencia, Mercapidez) — sin sub-áreas
```

29 áreas del Excel + CR = 30 áreas a crear.

**A diferencia de lo que se pensó antes de leer el CSV con atención**: `GAYFF` y `GAYFA` sí tienen fila
propia en `UsuariosAreas.csv` (no hay que inventar el nombre) — el nombre completo que trae el Excel es
literalmente "Gerencia de Adm. y Fin. Coord. Financiera" / "...Coord. Administrativa"; se acorta a
"Coord. Financiera" / "Coord. Administrativa" en el árbol de arriba para que se lea, pero el `nombre`
real a cargar es el que trae el Excel tal cual.

**Puntos a revisar:**

- **`GAYFFZ` (Cobranzas) es una sub-área huérfana**: aparece en `UsuariosAreas.csv` pero ningún riesgo,
  control, objetivo, plan de acción ni tarea del resto de los CSV la referencia. Se crea igual (viene
  del Excel), documentado como sin actividad en esta carga.
- **`ALE` no existe en `UsuariosAreas.csv`**, pero aparece como `area_id` en varias filas de
  `PlanesAccion.csv` (planes 20, 21) y de `Tareas.csv` (tareas 35, 38), siempre con responsable
  `Langus` — que es la responsable de `GAL`. Se interpreta como error de tipeo por `GAL` y se corrige en
  la carga (ambigüedad de implementación, no de diseño: se deriva y se sigue).
- **Autoridad de los "coordinadores" (Biasi, Aieta, Berri, Uva, Torres) sobre sus sub-áreas**: hoy el
  sistema tiene 3 roles (`comite`, `gerente`, `empleado`); solo `gerente` cascada autorización sobre
  `area_padre_id` y sus hijas. Biasi/Aieta/Berri/Uva/Torres no son responsables de una `Gerencia` (su
  área tiene `tipo = null`), pero en los datos aparecen gestionando activamente varias sub-áreas propias
  (ej. Biasi con Tesorería/Impuestos/Contabilidad/Presupuesto). Con el modelo de roles actual, si se los
  carga como `empleado` de su área de coordinación, **no** verían ni gestionarían las sub-áreas por
  debajo salvo que la Policy diga lo contrario — lo cual puede no reflejar el uso real. **No se resuelve
  acá**: queda para la próxima charla si hace falta un 4º rol o si alcanza con `empleado` + visibilidad
  manual.

---

## 3. Usuarios

18 personas distintas aparecen como `user_id` (apellido, a veces con variación de mayúsculas — ej.
`MErcapidez` vs `Mercapidez`, se normaliza a una sola) en el conjunto de los 14 CSV:

| Apellido | Área asociada | Rol propuesto | Origen |
|---|---|---|---|
| Munafo (Lucía) | CR | `comite` | `UsuariosAreas.csv` |
| Doncheff | CYD | `gerente` | `UsuariosAreas.csv` |
| Grassi | PRO | `gerente` | `UsuariosAreas.csv` |
| Langus | GAL | `gerente` | `UsuariosAreas.csv` |
| Piris | GAYF | `gerente` | `UsuariosAreas.csv` |
| Pasquale | RRHH | `gerente` | `UsuariosAreas.csv` |
| Stefanelli | OYM | `gerente` | `UsuariosAreas.csv` |
| Fasano | MASH | `gerente` | `UsuariosAreas.csv` |
| Castiglioni | UAI | `gerente` | `UsuariosAreas.csv` |
| Aversa | PRE | `gerente` | `UsuariosAreas.csv` |
| Mercapidez | FC | `gerente` | `UsuariosAreas.csv` (normaliza `MErcapidez`) |
| Biasi | GAYFF | `empleado` (ver punto de "coordinadores" arriba) | `UsuariosAreas.csv` |
| Aieta | GAYFS | `empleado` (ídem) | `UsuariosAreas.csv` |
| Berri | GAYFA | `empleado` (ídem) | `UsuariosAreas.csv` |
| Uva | MASHS | `empleado` (ídem) | `UsuariosAreas.csv` |
| Torres | MASHMA | `empleado` (ídem) | `UsuariosAreas.csv` |
| Nicolini | RRHH | `empleado` | Solo aparece en `Tareas.csv` (tareas 58, 60), no es responsable de área |
| Martin | GAL | `empleado` | Solo aparece en `Tareas.csv` (tareas 67, 69), no es responsable de área |

**Datos inventados** (no vienen en ningún CSV, obligatorios por schema): `email` y `password`. Se
propone reutilizar el mismo patrón ya validado en `AreaSeeder.php:50-51`:
`strtolower($apellido).'@example.com'` + `Hash::make('password')`. No es un patrón nuevo inventado para
esta carga, es el que ya usa el seeder existente.

---

## 4. Riesgos (`Riesgos.csv`, 161 filas con datos, ids 1-163 con huecos)

Columnas del CSV: `id;codigo;nombre;descripcion;impacto;probabilidad;respuesta;fundamento;tipo_riesgo_id;area_id;user_id;mayor_criticidad`.

Mapeo contra `$fillable` de `Riesgo` (`codigo, nombre, descripcion, impacto, probabilidad,
mayor_criticidad, respuesta, fundamento, tipo_riesgo_id, estado_id, user_id, area_id`): **calza casi 1
a 1**, salvo:

- `codigo` viene vacío en el CSV → se deja que lo autogenere `Riesgo::booted()` (`R-0001…`), tal como ya
  hace el modelo hoy.
- `area_id` y `user_id` vienen como texto (código de área / apellido) → hay que resolverlos contra el
  mapeo de la sección 2 y 3.
- `tipo_riesgo_id` viene casi siempre como número (1, 3, 4, 5, 6, 7 — correlativo con
  `TipoRiesgos.csv`), **excepto la fila del riesgo id 69** ("Falta de alineación entre el presupuesto y
  los objetivos estratégicos"), que trae el valor literal `estrategico`. No existe ese tipo en
  `TipoRiesgos.csv` (que solo tiene 7: Operacional, Económico, Cumplimiento, Ambiental, Seguridad e
  Higiene, Financiero, Corrupción). **No se resuelve acá** si hay que agregar un 8º tipo "Estratégico" o
  reasignarlo a uno existente (ej. Económico) — queda para la próxima charla porque toca un catálogo
  base.
- Ningún riesgo del Excel usa `tipo_riesgo_id = 2` (Económico) — dato observado, no es un problema, solo
  se deja anotado por si es señal de que falta algo.
- `estado_id`: no viene en el CSV → se setea `aprobado` para toda la carga (decisión 1).
- `respuesta`: viene como texto libre (`mitigar`, `mitigar ` con espacio, `aceptar`) — hay que
  `trim()`/normalizar contra el enum `RespuestaRiesgo` (`Mitigar|Evitar|Compartir|Aceptar`). Ningún
  riesgo usa `Evitar` ni `Compartir` en este dataset.
- `mayor_criticidad`: siempre `NO` en las 161 filas — directo a `false`.
- Algunas filas traen `impacto`/`probabilidad` vacíos (riesgos 111, 142, 143, 144, 145, 146, 147, 148 —
  todos del área MASHS/MASHMA/OYM/UAI, varios relacionados a COVID-19 o a hallazgos sin puntuar). Como
  el schema no permite null (`default 0`), quedan en 0/0 si no se decide otra cosa — a revisar si esos
  8 riesgos deberían tener un valor real o si están bien como están (riesgos "viejos"/desactivados en la
  práctica).
- **IDs no correlativos**: el CSV salta del id 24 al 26 (no existe el 25) y hay otros huecos menores. No
  es un error de carga, el Excel nunca tuvo esa fila. El `id` real en la tabla `riesgos` no tiene que
  coincidir con el `id` del CSV — lo que sí tiene que preservarse es el mapeo id-CSV → riesgo real, para
  que los pivots (`ControlRiesgo.csv`, `ObjetivoRiesgo.csv`, `PlanAccionRiesgo.csv`, `AreaRiesgo.csv`)
  puedan resolver correctamente contra quién es quién.

---

## 5. Controles (`Controles.csv`, 173 filas)

Columnas: `id;nombre;descripcion;mitigacion_default;area_id;user_id`. `nombre` y `descripcion` son
idénticos en el 100% de las filas (el Excel repite el mismo texto en ambas columnas) — se carga tal
cual, es así como vino.

- `mitigacion_default`: vacío en las primeras ~80 filas (controles 1-80, área `GAYFFT`/`GAYFFI`/
  `GAYFFC`/`GAYFFP`), con valor explícito (0 a 9) a partir del control 81 en adelante (áreas
  `GAYFACE`, `GAYFAA`, `UAI`, `OYM`) — coincide con que la migración tiene `default 1`, así que los
  controles sin valor caen directo al default de esquema sin que haya que inventar nada.
- `area_id`/`user_id` en texto → mismo mapeo de las secciones 2 y 3. Todos los códigos de área que
  aparecen en `Controles.csv` existen en `UsuariosAreas.csv` — no hay huérfanos acá.
- No hay `estado_id` en el CSV → `aprobado` para todos (decisión 1).

---

## 6. `control_riesgo` (`ControlRiesgo.csv`, ~265 filas)

Columnas: `control_id;riesgo_id;mitigacion`. `mitigacion` viene vacía en la enorme mayoría (usa
`mitigacion_default` del control, vía `withPivot`); solo un puñado de filas trae override explícito
(ej. `control_id=81;riesgo_id=87;mitigacion=4`).

**Duplicados exactos detectados** (mismo `control_id`+`riesgo_id` repetido dos o más veces) — hay que
deduplicar antes de insertar, porque `control_riesgo` tiene `unique(control_id, riesgo_id)`:

- `control_id=157, riesgo_id=136` aparece 2 veces.
- `control_id=135, riesgo_id=86` aparece 3 veces.
- Es probable que haya más — al momento de escribir el seeder conviene deduplicar por `(control_id,
  riesgo_id)` de forma genérica en vez de listar cada caso a mano.

---

## 7. Objetivos (`Objetivos.csv`, 78 filas) y `objetivo_riesgo` (`ObjetivoRiesgo.csv`, 78 filas)

`Objetivos.csv`: `id;nombre;descripcion;fecha_objetivo;estrategico;peis;area_id;user_id`. Mapeo directo
salvo `area_id`/`user_id` en texto (mismo tratamiento) y `estado_id` ausente (`aprobado`, decisión 1).
`fecha_objetivo` viene vacía en las 78 filas → queda `null`. `estrategico` es `NO` en las 78 filas;
`peis` varía (`SI`/`NO`) — directo a boolean.

`ObjetivoRiesgo.csv` (`objetivo_id;riesgo_id`): de las 78 filas, solo **24** traen un `riesgo_id`
efectivo — el resto tiene la columna vacía (el objetivo no tiene ningún riesgo asociado todavía en este
dataset). Se insertan solo las filas con dato; las vacías se omiten sin generar una pivot row.

---

## 8. PEIS (`PeisItems.csv`, `ObjetivoPeisItem.csv`)

`PeisItem`/`objetivo_peis_item` **ya existen en el código** (migración, modelo, seeder) — no es una
tabla nueva a crear. Lo que hay que hacer es **reemplazar el contenido** de `PeisItemSeeder`, que hoy
carga 10 filas genéricas ("PEIS 1".."PEIS 10"), por los 10 textos reales de `PeisItems.csv`
(`id;nombre;descripcion`, ej. `nombre="Eje I- OG: Mejorar la eficacia del SGAC"`,
`descripcion="OE 1: Recertificar exitosamente el SGAC..."`).

`ObjetivoPeisItem.csv` (`objetivo_id;peis_item_id`, 72 filas) mapea 1 a 1 contra el pivot existente, sin
transformación — todos los `peis_item_id` referenciados (1-10) existen en `PeisItems.csv`.

---

## 9. Planes de acción (`PlanesAccion.csv`) y `plan_accion_riesgo` (`PlanAccionRiesgo.csv`)

`PlanesAccion.csv` trae columnas `id;nombre;descripcion;area_id;user_id;;riesgo id;;;;` — **el mismo
`id` de plan se repite en varias filas**, una por cada riesgo que ese plan mitiga (ej. el plan 13
"Integridad en las contrataciones" aparece en 8 filas, una por cada uno de los 8 riesgos que mitiga).
Esto significa que **no hay que crear una fila de `planes_accion` por cada fila del CSV**: hay que
deduplicar por `id` (62 planes distintos, ids 1-62) y usar la repetición solo para armar el pivot
`plan_accion_riesgo` — que además ya viene resuelto aparte en `PlanAccionRiesgo.csv`
(`plan_accion_id;riesgo_id;mitigacion`), que es la fuente que conviene usar como pivot real (los pares
que trae coinciden con los de `PlanesAccion.csv`, así que son consistentes entre sí).

**El propio Excel deja notas de que la carga está incompleta**, literales en la columna extra de
`PlanesAccion.csv`:

- Plan 13 ("Integridad en las contrataciones"): *"completar con todos los riegos del plan 869/1871"*.
- Plan 22 ("Integridad, ética, procedimientos, capacitación"): *"Completar con todos los riesgos del
  plan 1866"*.

Esto no es algo que haya que inventar ni resolver acá — se deja tal cual, como nota explícita de que
esos dos planes tienen vínculos a riesgos pendientes de completar por el negocio.

`codigo` de `planes_accion` (NOT NULL, único) no viene en ningún CSV → se genera igual que ya lo hace
`PlanAccionController::generarCodigo()` (`PA-0001…`), no hay que inventar formato nuevo.

`plan_accion_riesgo.mitigacion`: prácticamente vacía en todo `PlanAccionRiesgo.csv` (de ~83 filas, solo
la fila `control...` — en realidad son las filas de `plan_accion_riesgo`, no de control — no trae
`mitigacion` en ninguna). Por la decisión 2, **queda en 0/sin valor**, documentado como pendiente de
carga manual: **todos los pares `(plan_accion_id, riesgo_id)`** de `PlanAccionRiesgo.csv` van a entrar
sin mitigación real, es decir que ningún plan de acción va a descontar nada del `valor_residual` hasta
que alguien cargue el número real desde la UI.

---

## 10. Tareas (`Tareas.csv`, 179 filas) y `plan_accion_tarea` (`PlanAccionTarea.csv`, 179 filas)

`Tareas.csv`: `id;nombre;descripcion;fecha;porcentaje_avance;area_id;user_id;;plan id;;;`. Mapeo directo
salvo área/usuario en texto y `estado_id` ausente (`aprobado`, decisión 1). La columna `plan id` que
trae el propio `Tareas.csv` es redundante con `PlanAccionTarea.csv` (los pares coinciden en todos los
casos verificados) — se usa `PlanAccionTarea.csv` como fuente del pivot, igual que con planes/riesgos.

- `fecha` viene vacía en casi todas las filas, salvo un bloque de tareas del plan 13 que trae
  `31/12/2026` — formato `dd/mm/yyyy`, hay que parsearlo al castear a `date`.
- `porcentaje_avance` viene vacío en un bloque de tareas de RRHH (ids ~93-124, planes 36-47) → cae al
  default de esquema (`0`).
- En este dataset, cada tarea pertenece a exactamente un plan (relación de hecho 1:1 vía el pivot,
  aunque el schema permite muchos-a-muchos) — no hay ningún caso de una tarea compartida por dos planes.

---

## 11. `AreaRiesgo.csv` — redundante, no genera tabla propia

`AreaRiesgo.csv` (`area_id;riesgo_id`) tiene exactamente una fila por cada riesgo de `Riesgos.csv`, con
el mismo `area_id` que ya trae la columna `area_id` de `Riesgos.csv` — ningún `riesgo_id` aparece dos
veces con áreas distintas. La tabla `area_riesgo` (pivot que existe en el esquema para compartir un
riesgo con gerencias adicionales, más allá de su `area_id` principal) **no tiene con qué poblarse** con
este dataset: no hay evidencia de ningún riesgo compartido entre gerencias en la carga inicial. Se usa
`AreaRiesgo.csv` únicamente para **validar cruzado** que el `area_id` de cada fila de `Riesgos.csv` es
correcto, no como fuente de datos nueva.

---

## 12. Temas abiertos para la próxima charla (no resueltos en este documento)

1. **Tipo de riesgo "estratégico"** (riesgo id 69 en `Riesgos.csv`): agregar un 8º `TipoRiesgo` o
   reasignar a uno existente.
2. **Rol de los "coordinadores"** (Biasi, Aieta, Berri, Uva, Torres) sobre sus sub-áreas: si alcanza con
   `empleado` o hace falta repensar el esquema de roles para que tengan autoridad de gestión sobre su
   propio cluster de sub-áreas sin ser `gerente` de una `Gerencia` completa.
3. **8 riesgos sin `impacto`/`probabilidad`** (111, 142-148): confirmar si van con 0/0 o si son datos
   pendientes de completar por el negocio.
4. **Planes 13 y 22**: el propio Excel dice que faltan riesgos por vincular ("plan 869/1871",
   "plan 1866") — pendiente de que el negocio complete esa lista.
5. **`plan_accion_riesgo.mitigacion` en 0 para toda la carga**: en algún momento alguien va a tener que
   cargar esos valores manualmente desde la UI para que el `valor_residual` de esos riesgos baje —
   convendría armar un listado o reporte de "planes aprobados y completos sin mitigación cargada" para
   priorizar esa carga manual.
6. **`GAYFFZ` (Cobranzas)**: confirmar si de verdad no tiene actividad todavía o si falta algo del Excel
   para esa área.
