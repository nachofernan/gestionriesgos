# Módulo de Auditoría — Estructura General

## Índice

1. [Visión General](#visión-general)
2. [Modelos y Base de Datos](#modelos-y-base-de-datos)
3. [Roles y autorización](#roles-y-autorización)
4. [Rutas](#rutas)
5. [Controladores](#controladores)
6. [Componentes Livewire](#componentes-livewire)
7. [Vistas](#vistas)
8. [Seeders y Factories](#seeders-y-factories)
9. [Tests](#tests)

---

## Visión General

El módulo gestiona el ciclo completo de auditoría de riesgos: identificación de riesgos, definición de controles, vinculación con objetivos estratégicos, planes de acción y tareas de seguimiento.

```
Riesgo ──── Tipo de Riesgo
  │         Estado de Riesgo
  │
  ├── (many-to-many) ──── Controles       (pivot: control_riesgo, campo: mitigacion)
  ├── (many-to-many) ──── Objetivos       (pivot: objetivo_riesgo)
  └── (many-to-many) ──── Planes de Acción (pivot: plan_accion_riesgo)
                               │
                          (many-to-many) ── Tareas  (pivot: plan_accion_tarea)
                                               │
                                          (morphMany) Actualizaciones
Riesgo, Control, Objetivo, PlanAccion, Tarea
    └── (morphMany) ──── Actualizaciones  (polimórfico: actualizable_type / actualizable_id)
```

Todos los modelos principales soportan **SoftDeletes** y adjuntos via **Spatie Media Library**.

---

## Modelos y Base de Datos

### Modelos (`app/Models/Auditoria/`)

| Modelo | Tabla | Descripción |
|---|---|---|
| `Riesgo` | `riesgos` | Entidad central. Tiene impacto, probabilidad y criticidad_alta. Calcula `valor_total` e `valor_residual` como accessors. `evaluacion` (json) guarda `{metodo, respuestas}` del último wizard aplicado (D-042). Los ambientales llevan `temporalidad`, `condicion` y `responsabilidad` (enums, D-043). `metodoEvaluacion()` sale del tipo; `reglaTipoRiesgo()` impide cambiar a un tipo de otro método. |
| `Control` | `controles` | Medidas de mitigación. Se valora con un cuestionario de 7 preguntas (D-051): `evaluacion` (json, `{respuestas: {id: 0|1}, procedimiento, sistemas[], sistema_otro, frecuencia}`, preguntas y listas en `config/control_preguntas.php`, cálculo en `EvaluacionControl`). `mitigacion_default` es lo que mitiga en cada riesgo: un hook `saving` lo deriva del cuestionario (el puntaje, 1-10, o 0 si es una expresión de deseo). Sin cuestionario conserva el número que traía. `puntaje` (accessor) y `clasificacion()` (< 4 deficiente, 4–6 moderado, 7+ suficiente). Sin cuestionario no se valida ni se aprueba (`motivosBloqueoValidacion()`). `registrarValoracion()` es el circuito del área; `reevaluar()`, el del auditor sobre lo aprobado, en el acto. `mitiga()` es la regla única de si descuenta del residual: sólo si está aprobado (D-034). `aplicarEvidencia()` es la regla única de los tramos de evidencia (`TRAMOS_EVIDENCIA`, hacia abajo, D-037). |
| `ControlRiesgo` | `control_riesgo` | Pivot de la relación control-riesgo (D-051): sólo `efectividad` (0-100, "Evidencia de cumplimiento", nace en 100). La relación no tiene número propio. `mitigacionEfectiva($valorControl)` es lo que descuenta la relación: el valor del control por el tramo de su evidencia. |
| `Testeo` | `testeos` | Testeo de la evidencia de cumplimiento de una relación (D-040): fecha, % antes/después, comentario, adjuntos. Append-only. Lo carga sólo el auditor y se aplica en el acto (`Testeo::registrar()`). |
| `Objetivo` | `objetivos` | Objetivos estratégicos. `fecha_objetivo` nullable, casteada a `date`. `peis` (boolean): si es true, requiere al menos un `PeisItem` asociado (ver validación en `ObjetivoController`). |
| `PeisItem` | `peis_items` | Catálogo fijo del Plan Estratégico de Integridad Sostenible (PEIS 1..5, sembrado por `PeisItemSeeder`). |
| `PlanAccion` | `planes_accion` | Agrupa riesgos y tareas. Se valora con un cuestionario (D-050): `evaluacion_estimada` y `evaluacion_final` (json, `{probabilidad: {id: 0|1}, impacto: {id: 0|1}}`, preguntas en `config/plan_preguntas.php`, cálculo en `EvaluacionPlan`). `reduccion_nominal` es la final si existe, si no la estimada (2 puntos por sí, hasta 10 por eje); `reduccion_efectiva` la aplica según el avance, eje por eje. `finalizado`: evaluación final y avance 100. Sin estimación no se valida ni se aprueba (`motivosBloqueoValidacion()`). Sin columna de fecha propia: `vencimiento` (accessor) es la fecha más próxima entre sus tareas pendientes (`porcentaje_avance` < 100, no "borrado"); `esta_vencido` (accessor) es `true` si esa fecha ya pasó. |
| `Tarea` | `tareas` | Unidad de trabajo. `fecha` casteada a `date`, `porcentaje_avance` (0-100). |
| `Actualizacion` | `actualizaciones` | Historial de actualizaciones polimórfico. Tiene `mensaje`, `data` (JSON) y `user_id`. Las aprobaciones llevan `comite_numero`/`comite_fecha` opcionales; una entrada `tipo = comite` con `comite_de_id` los carga o corrige después (D-039). |
| `EstadoRiesgo` | `estados_riesgo` | Catálogo: borrador, validado, activo, mitigado, eliminado. |
| `TipoRiesgo` | `tipos_riesgo` | Catálogo de categorías de riesgo. `metodo_evaluacion` (enum `MetodoEvaluacion`: `clasica` / `ambiental`) dice con qué wizard se evalúan sus riesgos (D-043). |
| `Sede` | `sedes` | Catálogo de sedes (`SedeSeeder`). Un riesgo tiene una o más (pivot `riesgo_sede`), D-029. Informativa: no interviene en la autorización. |
| `Dimension` | `dimensiones` | Catálogo de dimensiones del riesgo (`DimensionSeeder`), con ejemplos y `referencias` (`DimensionReferencia`, tabla `dimension_referencias`). Un riesgo tiene una o más (pivot `dimension_riesgo`); con dos o más, `riesgos.fundamento_dimension` es obligatorio. D-032, renombrada en D-035. Informativa. |
| `Area` | `areas` | Estructura organizacional jerárquica (`area_padre_id` auto-referencial). |
| `RevisionInicial` | `revisiones_iniciales` | **Transitorio (D-024).** Revisado y firma de la carga inicial, por elemento (morph `revisable`) y gerencia firmante. Ver la sección *Revisión de la carga inicial*. |

### Comportamiento automático

**`Riesgo` — observer inline (booted):** Al crear un riesgo sin `estado_riesgo_id`, busca automáticamente el estado "borrador" y lo asigna.

### Relaciones pivot

| Tabla pivot | Entidades | Campos extra |
|---|---|---|
| `control_riesgo` | Control ↔ Riesgo | `efectividad` (evidencia de cumplimiento, 0-100) |
| `objetivo_riesgo` | Objetivo ↔ Riesgo | — |
| `objetivo_peis_item` | Objetivo ↔ PeisItem | — |
| `plan_accion_riesgo` | PlanAccion ↔ Riesgo | — (la `mitigacion` manual se eliminó en D-050) |
| `plan_accion_tarea` | PlanAccion ↔ Tarea | — |

### Accessors calculados en Riesgo

- **`valor_total`**: `impacto + probabilidad` (0–20)
- **`mitigacion_controles`**: Σ mitigación de los controles **aprobados**. Cada uno aporta su `mitigacion_default` regulado por el tramo de la `efectividad` **de esa relación** (D-051): < 60 → 0 · 60–69 → 20% · 70–79 → 40% · 80–90 → 60% · 91–98 → 80% · 99–100 → 100%, hacia abajo y por cada relación antes de sumar (`ControlRiesgo::mitigacionEfectiva()`, `Control::aplicarEvidencia()`).
- **`mitigacion_planes`**: lo que reducen los planes **aprobados**, **por eje** (D-050). Cada plan aporta su `reduccion_efectiva`: la nominal de cada eje en proporción al promedio exacto de avance de sus tareas aprobadas, `floor(nominal × Σ avance / (cantidad × 100))` (`aplicarAvance()`, D-037). Se suman por eje y cada suma tiene tope en el impacto y la probabilidad del riesgo: `min(impacto, Σ imp) + min(probabilidad, Σ prob)` (`Riesgo::mitigacionPorEjes()`). Ej.: riesgo 5/5 con un plan 8 imp / 2 prob → 7.
- **`valor_residual`**: `valor_total − mitigacion_planes − mitigacion_controles` (mínimo 0). Los planes topean por eje y los controles restan de lo que queda.
- **`valor_residual_proyectado`** (borrador interno, D-044): igual que `valor_residual`, pero también descuentan los controles y planes en borrador **con** flag `interno` (`Control::mitigaProyectado()`, `PlanAccion::mitigaProyectado()`). Un plan cuenta sus tareas aprobadas e internas (`PlanAccion::tareasProyectadas()`, `avance_proyectado`). Los desgloses son `mitigacion_controles_proyectada` y `mitigacion_planes_proyectada`. No reemplaza al oficial.

---

## Roles y autorización

Los roles viven en spatie/laravel-permission (`HasRoles`, D-052) y el área en `users.area_id`. Un usuario puede tener más de un rol. Las Policies están en `app/Policies/Auditoria/`; spatie sólo dice qué roles tiene (`esGerente()`, `esComite()`, etc.).

| Rol | Ve | Propone / edita | Valida · aprueba | Crea · elimina |
|---|---|---|---|---|
| `empleado` | Lo validado o aprobado de todos, más todo lo de su subárbol | Sobre lo de su subárbol. Fuera de borrador, su propuesta nace en borrador | — | En su subárbol |
| `gerente` | Igual que el empleado | Igual. Fuera de borrador aplica directo | Valida lo de su subárbol | En su subárbol |
| `comite` | Sólo lo validado o aprobado | Aplica directo sobre lo aprobado | Aprueba, y rechaza lo validado | Ver D-006 |
| `gerente` + `comite` (D-052) | Lo validado o aprobado de todos, más los borradores de su subárbol | Como comité en cualquier área. Sobre lo aprobado de su subárbol elige: propuesta (nace validada) o establecer como comité (aplica directo, con N° y fecha). Recalcula y valora sólo como gerente, en su área | Valida lo de su subárbol; aprueba y rechaza lo validado de todos | En su subárbol |
| `auditor` | Igual que el empleado | **Sobre cualquier elemento validado o aprobado.** Su propuesta siempre nace en borrador. Aplica en el acto el testeo de evidencia (D-040) y la reevaluación del cuestionario de un control aprobado (`ControlPolicy::reevaluar`, D-051) | — | — |

**`update` y `proponer` (D-020).**
- `update` es mutar directo: el formulario de un borrador, un alta desde un bloque (nueva tarea), el avance de una tarea.
- `proponer` es originar una propuesta o una nota: fichas, bloques de relaciones, Conversación, recálculo y `ActualizacionController::store*`. Se cumple si hay `update`, o si es un auditor sobre un elemento validado o aprobado (`User::puedeProponerComoAuditor()`).
- En borrador las dos coinciden, así que las ramas de `sync` directo de los bloques siguen protegidas.

**Tareas creadas desde un plan (D-030).** `PlanAccionPolicy::crearTarea` (quien gestiona el plan, salvo
el auditor) habilita `GestionTareas::guardarNuevaTarea()`:
- la tarea admite **cualquier área**;
- nace validada sólo si la crea un gerente para su propia línea; si no, en borrador;
- queda asociada al plan en el acto, sin propuesta: el vínculo sigue a la tarea.

`TareaPolicy::view` y `comentar` también habilitan a quien gestiona un plan que contiene la tarea.
`update`, `validar` y `rechazar` siguen siendo del área de la tarea. Tests: `TareaDesdePlanTest`.

**Borrador interno (D-044).** Columna `interno` en los cinco elementos, sólo con sentido en borrador:
- `validar()` exige `! interno`;
- el flag se pone o se saca en el alta y en la edición del borrador (`update`);
- `InternoController@index` (`auditoria/interno`) es el panel del área;
- Pendientes, el contador del Panel y la cascada de validación excluyen lo interno.

Tests: `BorradorInternoTest`, `ProyeccionInternaTest`.

Un usuario con `area_id = null` es superusuario: `esGerente()` da `true` y ve y gestiona todo. Por eso el auditor lleva área propia.

Tests: `AuditorTest`.

### Revisión de la carga inicial (transitoria, D-024)

Pantalla `auditoria/revision-inicial` (`Livewire\Auditoria\RevisionInicial\Index`). Se apaga con `config('auditoria.revision_inicial.activa')`: apagada, desaparecen la ruta y el menú.

**Inconsistencias.** `Services\Auditoria\RevisionInicial\DetectorInconsistencias` lista las de cada elemento:
- un **error** bloquea la firma;
- una **advertencia** se firma con comentario.

**Quién hace qué** (`RevisionInicialPolicy`):
- **Revisado:** lo marca el responsable o quien gestiona el área dentro de la gerencia.
- **Firma:** la pone el gerente, con o sin revisado previo.
- **Responsable gerente:** si el responsable es gerente de esa gerencia, sólo esa persona revisa y firma.
- **Gerencia firmante:** la del área del elemento (también para el riesgo, D-028).
- **Comité y auditor:** miran, no revisan ni firman.

**Arreglos.** Se hacen en el show del elemento, con el circuito de propuestas. Cada ficha (las cinco) muestra la card `RevisionInicialPendientesFicha` (D-048): los hallazgos del detector con su acción (Recalcular o un ancla al bloque), los bloques afectados marcados en rojo o ámbar y, firmado, una sola línea. La ve todo el que ve el elemento. Desde la card también se marca revisado y se firma, o se valida y firma (D-049): `cambiosAValidar()` y `validarYFirmar()` viven en el modelo `RevisionInicial` y los usan la pantalla y la card.

Detalle en [updates/2026-10-06.md](updates/2026-10-06.md). Tests: `DetectorInconsistenciasTest`, `RevisionInicialTest`, `RevisionInicialPantallaTest`, `RevisionInicialFichaTest`.

---

## Rutas

Todas bajo prefijo `/auditoria` con middleware `auth`. Definidas en `routes/web.php`.

```
GET    /                                        → redirige a auditoria.riesgos.index

# Riesgos
GET    /auditoria/riesgos                       → RiesgoController@index
GET    /auditoria/riesgos/create                → RiesgoController@create
POST   /auditoria/riesgos                       → RiesgoController@store
GET    /auditoria/riesgos/{riesgo}              → RiesgoController@show
GET    /auditoria/riesgos/{riesgo}/edit         → RiesgoController@edit
PUT    /auditoria/riesgos/{riesgo}              → RiesgoController@update
POST   /auditoria/riesgos/{riesgo}/eliminacion  → RiesgoController@proponerEliminacion (D-047, sin destroy)

# Controles
GET/POST/PUT/DELETE /auditoria/controles/{...}  → ControlController (resource completo)

# Objetivos
GET/POST/PUT/DELETE /auditoria/objetivos/{...}  → ObjetivoController (resource completo)

# Planes de Acción  (param: {plane})
GET/POST/PUT/DELETE /auditoria/planes/{...}     → PlanAccionController (resource completo)

# Tareas
GET/POST/PUT/DELETE /auditoria/tareas/{...}     → TareaController (resource completo)

# Panel de Riesgos y reportes (D-033)
GET    /auditoria/panel                         → vista con <livewire:auditoria.panel-riesgos>
GET    /auditoria/panel/reporte/{reporte}       → ReporteController@exportar (gerencia|tipo|evolucion|planes → .xlsx)
POST   /auditoria/tareas/{tarea}/actualizaciones → ActualizacionTareaController@store
```

---

## Controladores

Ubicación: `app/Http/Controllers/Auditoria/`

Todos siguen el patrón CRUD estándar de Laravel. Se listan solo las particularidades de cada uno.

**Área y Responsable (D-022, D-023).** Control, Objetivo, PlanAccion y Tarea usan el trait
`Concerns\OpcionesAreaResponsable` en `create/store/edit/update`. El área es obligatoria y sale de
`User::idsAreasGestionables()`. En edición se suma el área actual. El responsable tiene que ser de
esa área, sus sub-áreas o sus ancestros hasta la primera gerencia (`Area::idsAreasDeResponsables()`). En edición se conserva el
actual mientras no cambie el área. La vista usa el partial `partials/select-area-responsable`, que
filtra el Responsable con Alpine al cambiar el Área. Tests: `AltaAreaResponsableTest`.

### `RiesgoController`

- **`store`**: Impacto y probabilidad salen del wizard, vía `App\Services\Auditoria\EvaluacionRiesgo`
  (reglas, cálculo y resumen de cada método; config en `riesgo_preguntas.php` y `riesgo_ambiental.php`).
  El método lo define el tipo elegido. `area_id` es obligatorio y de la línea del usuario. No asocia
  objetivos: se vinculan desde la ficha.
- **`recalcular` / `recalcularStore`**: el wizard del método del riesgo, precargado con su evaluación
  vigente. Cambiar sólo respuestas, aunque el número no se mueva, también es una propuesta (D-042).
- **`update`**: `area_id` es obligatorio y de la línea del usuario.
- **`proponerEliminacion`** (D-047): sobre un riesgo aprobado, observaciones obligatorias y un adjunto
  opcional. Crea una propuesta de eliminación (`Actualizacion::registrarEliminacion()`, un `cambio` con
  `data.eliminacion`) para el riesgo y otra por cada control o plan marcado, que tiene que estar entre
  los de una sola asociación (`Riesgo::dependientesEliminables()`). Al aprobarse,
  `Actualizacion::aplicarEliminacion()` vuelve a revisar `motivoBloqueoEliminacion()` y pasa la
  entidad a `borrado`. Un plan arrastra sus tareas que no están en otro plan (`PlanAccion::tareasQueCaen()`).

### `PlanAccionController`

- **`store` / `update`**: No tocan los riesgos asociados: se gestionan desde la ficha (`RiesgosPlan` / `GestionPlanes`).

### `TareaController`

- **`store`**: Al crear una tarea, registra automáticamente una `Actualizacion` inicial via `morphMany`.

### `ActualizacionTareaController`

- **`store(Request, Tarea)`**: Crea una `Actualizacion` polimórfica sobre la tarea (`$tarea->actualizaciones()->create(...)`) y actualiza `porcentaje_avance` en la tarea.
- Valida: `mensaje` (min 3 chars), `porcentaje` (0-100).

---

## Componentes Livewire

Ubicación: `app/Livewire/Auditoria/`

### `Dashboard`

Componente central heredado del diseño anterior (antes de la refactorización a MVC). Gestiona CRUD de todas las entidades desde un único componente con sistema de tabs y modal central.

**Props clave:**
- `$tabActiva` — tab visible: `riesgos`, `controles`, `objetivos`, `planes`, `tareas`
- `$modalAbierto`, `$modalTipo` — controlan qué formulario aparece en el modal
- `$form` — array genérico que recibe todos los campos del formulario activo
- `$idsParaAsociar` — checkboxes de asociaciones (controles a riesgos, tareas a planes, etc.)

**Flujo de guardado:** `guardar()` despacha al método privado correspondiente (`guardarRiesgo()`, `guardarControl()`, etc.) según `$modalTipo`.

### `PanelRiesgos` (Panel de Riesgos y reportes, D-007 y D-033)

Sólo lectura, con sesgo gerencial (`Riesgo::scopeDeCascadaArea`). Pestañas internas en `$pestana`
(`#[Url]`): `resumen` (KPIs inherente → residual, accesos a Pendientes y Vencimientos y matriz de
calor), `gerencia`, `tipo`, `evolucion` (con `$desde`/`$hasta`) y `planes`. Filtros comunes:
`$soloAprobados`, `$filtroTipo`, `$filtroRespuesta`. Vistas en `livewire/auditoria/panel-riesgos` +
`livewire/auditoria/panel/{pestaña}`.

Los números de los reportes salen de `app/Services/Auditoria/Reportes/`: `Reporte` (base: universo,
gerencia de un área, situación de plan, conteos) y `ReportePorGerencia`, `ReportePorTipo`,
`ReporteEvolucion`, `ReportePlanes`, con los filtros en `FiltrosReporte`. Los consumen la pestaña y
la descarga: `ReporteController@exportar` arma `Exports\Auditoria\ReporteExport` (un libro con
hojas `HojaExport`) con `maatwebsite/excel`. Tests: `ReportesTest`, `PanelRiesgosTest`.

---

### Componentes de Gestión (Show pages)

Patrón común para manejar relaciones many-to-many con edición inline:

```
app/Livewire/Auditoria/
├── Riesgo/Show/
│   ├── Concerns/PropuestasEnBloque.php — modo de cambio, propuestas pendientes y propuesta por elemento
│   ├── ResumenPropuestas.php   — banner con las propuestas pendientes del riesgo
│   ├── FichaRiesgo.php         — datos propios del riesgo, editables en el bloque
│   ├── InfoRiesgo.php          — tarjeta Valor: total, desglose de mitigación, residual
│   ├── GestionControles.php    — maneja controles de un riesgo (alta y baja; cada uno mitiga su valor según la evidencia, D-051)
│   ├── GestionObjetivos.php    — maneja objetivos de un riesgo (mínimo 1 requerido)
│   └── GestionPlanes.php       — maneja planes de acción de un riesgo
├── Control/Show/
│   ├── FichaControl.php        — nombre y descripción del control, editables en el bloque
│   ├── RiesgosControl.php      — riesgos que mitiga: lo que descuenta en cada uno, evidencia y testeos del auditor (D-040)
│   ├── ValorControl.php        — card Valor del control: cuestionario del área y reevaluación del auditor (D-051)
│   └── InfoControl.php         — tarjeta Datos del lateral
├── Objetivo/Show/
│   ├── FichaObjetivo.php       — datos del objetivo, editables en el bloque
│   └── InfoObjetivo.php        — tarjeta Fecha objetivo del lateral (clasificación y PEIS)
├── Tarea/Show/
│   ├── FichaTarea.php          — datos de la tarea (incluido el avance), editables en el bloque
│   └── InfoTarea.php           — tarjeta Avance del lateral (plazo, asignada a)
├── Actualizaciones/
│   ├── GestionActualizaciones.php — historial; variante 'completa' o 'timeline' (todas las pantallas de detalle)
│   └── Conversacion.php        — notas y documentos de cualquier entidad del ciclo
└── PlanAccion/Show/
    ├── FichaPlan.php           — datos del plan, editables en el bloque
    ├── GestionTareas.php       — tareas del plan, propuesta por elemento (D-018)
    ├── RiesgosPlan.php         — riesgos que mitiga (sólo lectura, se refresca con las tareas)
    ├── InfoPlan.php            — tarjeta Avance del lateral (mitigación, vencimiento)
    └── ValorPlan.php           — card Valor del plan: estimación y evaluación final por cuestionario (D-050)
```

**riesgo/show (rediseño, ver [D-016](DECISIONES.md#d-016) y [updates/2026-09-24](updates/2026-09-24.md)):**
cada bloque muestra arriba lo vigente y abajo las propuestas pendientes que lo afectan, que se
resuelven desde su tarjeta (evento `resolver-actualizacion` → `GestionActualizaciones`). Fuera de
borrador y del modo directo, Objetivos/Controles/Planes generan una propuesta por elemento
(`agregar` / `detach` / `actualizar`), no un `sync` del bloque.

**Patrón de estos componentes:**
1. `mount(Entidad $entidad)` — carga los items ya asociados en `$seleccionados`
2. Modo lectura por defecto; `activarEdicion()` habilita el modo edición
3. `abrirModal()` / `cerrarModal()` controlan un buscador para agregar items
4. `agregar(int $id)` / `quitar(int $id)` modifican `$seleccionados` en memoria
5. `guardar()` hace el `sync()` real contra la base de datos

`GestionControles` tiene la particularidad de que `$seleccionados` incluye el valor de `mitigacion` editable por item.

---

### Componentes de Búsqueda/Listado (Index pages)

Patrón común con `WithPagination`, 15 resultados por página:

```
app/Livewire/Auditoria/
├── Riesgo/Index/Search.php
├── Control/Index/Search.php
├── Objetivo/Index/Search.php
├── PlanAccion/Index/Search.php
└── Tarea/Index/Search.php
```

**Props comunes a todos:**
- `$search` — búsqueda por nombre
- `$filtroArea`, `$mostrarHijos` — filtra por área e incluye o no sub-áreas
- `$ordenarPor`, `$direccion` — ordenamiento de columnas

**Props adicionales en `Riesgo/Index/Search`:**
- `$filtroTipo` — por `tipo_riesgo_id`
- `$filtroEstado` — por `estado_riesgo_id`
- `$soloAlta` — filtra solo `criticidad_alta = true`
- `ordenar()` acepta columnas calculadas: `valor_total`, `valor_residual`

Todos los `updating*()` llaman a `$this->resetPage()` para evitar paginación inconsistente al filtrar.

---

## Vistas

```
resources/views/
├── auditoria/                          # Vistas MVC tradicionales
│   ├── riesgo/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── edit.blade.php
│   │   └── show.blade.php             # Embebe componentes Livewire GestionControles, etc.
│   ├── control/
│   │   └── {index, create, edit, show}.blade.php
│   ├── objetivo/
│   │   └── {index, create, edit, show}.blade.php
│   ├── planaccion/
│   │   └── {index, create, edit, show}.blade.php
│   └── tarea/
│       └── {index, create, edit, show}.blade.php
│
└── livewire/auditoria/                 # Vistas de componentes Livewire
    ├── dashboard.blade.php
    ├── riesgo/
    │   ├── index/search.blade.php
    │   └── show/
    │       ├── partials/{boton-editar, barra-edicion}.blade.php
    │       ├── resumen-propuestas.blade.php
    │       ├── ficha-riesgo.blade.php
    │       ├── info-riesgo.blade.php
    │       ├── gestion-areas.blade.php
    │       ├── gestion-controles.blade.php
    │       ├── gestion-objetivos.blade.php
    │       └── gestion-planes.blade.php
    ├── actualizaciones/
    │   ├── conversacion.blade.php
    │   ├── gestion-actualizaciones.blade.php           # variante 'completa'
    │   ├── gestion-actualizaciones-timeline.blade.php  # variante 'timeline'
    │   └── partials/{modal-nueva, actividad-modal}.blade.php
    ├── control/
    │   ├── index/search.blade.php
    │   └── show/{ficha-control, riesgos-control, info-control}.blade.php
    ├── objetivo/
    │   ├── index/search.blade.php
    │   └── show/{ficha-objetivo, info-objetivo}.blade.php
    ├── plan-accion/
    │   ├── index/search.blade.php
    │   └── show/{ficha-plan, gestion-tareas, riesgos-plan, info-plan}.blade.php
    └── tarea/
        ├── index/search.blade.php
        └── show/{ficha-tarea, info-tarea}.blade.php
```

Las vistas `show.blade.php` de Riesgo y PlanAccion embeben los componentes de gestión con `@livewire(...)`.

Componentes Blade del rediseño de riesgo/show (`resources/views/components/auditoria/`):
`bloque-riesgo` (contenedor vigente/propuesto), `propuesta-pendiente` (tarjeta de una propuesta, con
sus acciones en `auditoria/partials/propuesta-acciones`), `modal-buscar` y `spinner`.

---

## Seeders y Factories

### Seeders (`database/seeders/`)

Orden de ejecución (respeta dependencias de FK):

1. `EstadoRiesgoSeeder` — estados: borrador, validado, activo, mitigado, eliminado
2. `TipoRiesgoSeeder` — categorías de riesgo
3. `AreaSeeder` — estructura organizacional
4. `PeisItemSeeder` — catálogo PEIS 1..5
5. `RiesgoSeeder`
6. `ControlSeeder`
7. `ObjetivoSeeder`
8. `TareaSeeder`
9. `PlanAccionSeeder`

> `EstadoRiesgoSeeder` es el más crítico: el observer de `Riesgo` lo requiere para asignar el estado "borrador" al crear. Los tests lo seedean manualmente en `setUp()`.

### Factories (`database/factories/Auditoria/`)

| Factory | Modelo |
|---|---|
| `RiesgoFactory` | `Riesgo` — crea `TipoRiesgo` y `EstadoRiesgo` asociados |
| `PlanAccionFactory` | `PlanAccion` |
| `TareaFactory` | `Tarea` |
| `ControlFactory` | `Control` — sin cuestionario por defecto (número suelto); `valorado($puntaje, $operativo)` lo contesta |
| `TipoRiesgoFactory` | `TipoRiesgo` |
| `EstadoRiesgoFactory` | `EstadoRiesgo` |
| `ActualizacionTareaFactory` | `Actualizacion` — campos: `user_id`, `mensaje`, `data` |

---

## Tests

Ubicación: `tests/Feature/Auditoria/ModuloAuditoriaTest.php`

Suite de tests de integración con `RefreshDatabase`. El `setUp()` ejecuta `EstadoRiesgoSeeder` porque el observer de `Riesgo` lo necesita.

| Test | Qué verifica |
|---|---|
| `un_riesgo_se_crea_con_estado_borrador_por_defecto` | Observer asigna estado "borrador" automáticamente |
| `un_riesgo_puede_tener_muchos_planes_de_accion` | Relación many-to-many Riesgo ↔ PlanAccion |
| `un_plan_de_accion_puede_tener_muchos_riesgos` | Relación inversa PlanAccion ↔ Riesgo |
| `un_plan_de_accion_puede_tener_muchas_tareas` | Relación many-to-many PlanAccion ↔ Tarea |
| `una_tarea_puede_pertenecer_a_muchos_planes` | Relación inversa Tarea ↔ PlanAccion |
| `una_tarea_tiene_fecha_y_actualizaciones` | `fecha` cast a Carbon, `actualizaciones` polimórficas |
| `riesgo_tiene_mayor_criticidad` | Campo `mayor_criticidad` |
| `un_riesgo_puede_tener_muchos_controles_con_evidencia_en_el_pivot` | Pivot `control_riesgo` con campo `efectividad` |
| `objetivo_tiene_fecha_nullable` | `fecha_objetivo` nullable y cast a Carbon |

Las actualizaciones se crean via `$tarea->actualizaciones()->create([...])` (no factory directa) porque el modelo es polimórfico y requiere que el relationship setee `actualizable_type` / `actualizable_id`.
