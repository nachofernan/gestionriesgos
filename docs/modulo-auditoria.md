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
| `Riesgo` | `riesgos` | Entidad central. Tiene impacto, probabilidad y criticidad_alta. Calcula `valor_total` e `valor_residual` como accessors. |
| `Control` | `controles` | Medidas de mitigación. Tiene `mitigacion_default` (1-10). El valor real se guarda en el pivot con el riesgo. `propagarMitigacionDefault()` lleva el default a todas las asociaciones (opcional al cambiarlo, ver D-017). `pausado` (boolean): un control pausado no mitiga; `mitiga()` es la regla única (aprobado y no pausado, ver D-019). |
| `Objetivo` | `objetivos` | Objetivos estratégicos. `fecha_objetivo` nullable, casteada a `date`. `peis` (boolean): si es true, requiere al menos un `PeisItem` asociado (ver validación en `ObjetivoController`). |
| `PeisItem` | `peis_items` | Catálogo fijo del Plan Estratégico de Integridad Sostenible (PEIS 1..5, sembrado por `PeisItemSeeder`). |
| `PlanAccion` | `planes_accion` | Agrupa riesgos y tareas. Sin columna de fecha propia: `vencimiento` (accessor) es la fecha más próxima entre sus tareas pendientes (`porcentaje_avance` < 100, no "borrado"); `esta_vencido` (accessor) es `true` si esa fecha ya pasó. |
| `Tarea` | `tareas` | Unidad de trabajo. `fecha` casteada a `date`, `porcentaje_avance` (0-100). |
| `Actualizacion` | `actualizaciones` | Historial de actualizaciones polimórfico. Tiene `mensaje`, `data` (JSON) y `user_id`. |
| `EstadoRiesgo` | `estados_riesgo` | Catálogo: borrador, validado, activo, mitigado, eliminado. |
| `TipoRiesgo` | `tipos_riesgo` | Catálogo de categorías de riesgo. |
| `Area` | `areas` | Estructura organizacional jerárquica (`area_padre_id` auto-referencial). |

### Comportamiento automático

**`Riesgo` — observer inline (booted):** Al crear un riesgo sin `estado_riesgo_id`, busca automáticamente el estado "borrador" y lo asigna.

### Relaciones pivot

| Tabla pivot | Entidades | Campos extra |
|---|---|---|
| `control_riesgo` | Control ↔ Riesgo | `mitigacion` (nullable, sobreescribe `mitigacion_default`) |
| `objetivo_riesgo` | Objetivo ↔ Riesgo | — |
| `objetivo_peis_item` | Objetivo ↔ PeisItem | — |
| `plan_accion_riesgo` | PlanAccion ↔ Riesgo | — |
| `plan_accion_tarea` | PlanAccion ↔ Tarea | — |

### Accessors calculados en Riesgo

- **`valor_total`**: `impacto + probabilidad` (0–20)
- **`mitigacion_controles`**: Σ mitigación de los controles **aprobados**. Usa `pivot->mitigacion` si está definida, si no `mitigacion_default` del control.
- **`mitigacion_planes`**: Σ `pivot->mitigacion` de los planes **aprobados y al 100%** de avance.
- **`valor_residual`**: `valor_total − mitigacion_controles − mitigacion_planes` (mínimo 0).

---

## Roles y autorización

El rol vive en `users.rol` y el área en `users.area_id`. Las Policies están en `app/Policies/Auditoria/`.

| Rol | Ve | Propone / edita | Valida · aprueba | Crea · elimina |
|---|---|---|---|---|
| `empleado` | Lo validado o aprobado de todos, más todo lo de su subárbol | Sobre lo de su subárbol. Fuera de borrador, su propuesta nace en borrador | — | En su subárbol |
| `gerente` | Igual que el empleado | Igual. Fuera de borrador aplica directo, o vota si el riesgo es compartido | Valida lo de su subárbol | En su subárbol |
| `comite` | Sólo lo validado o aprobado | Aplica directo sobre lo aprobado | Aprueba, y rechaza lo validado | Ver D-006 |
| `auditor` | Igual que el empleado | **Sobre cualquier elemento validado o aprobado.** Su propuesta siempre nace en borrador y **no deja voto** | — | — |

**`update` y `proponer` (D-020).**
- `update` es mutar directo: el formulario de un borrador, un alta desde un bloque (nueva tarea), el avance de una tarea.
- `proponer` es originar una propuesta o una nota: fichas, bloques de relaciones, Conversación, recálculo y `ActualizacionController::store*`. Se cumple si hay `update`, o si es un auditor sobre un elemento validado o aprobado (`User::puedeProponerComoAuditor()`).
- En borrador las dos coinciden, así que las ramas de `sync` directo de los bloques siguen protegidas.

Un usuario con `area_id = null` es superusuario: `esGerente()` da `true` y ve y gestiona todo. Por eso el auditor lleva área propia.

Tests: `AuditorTest`.

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
DELETE /auditoria/riesgos/{riesgo}              → RiesgoController@destroy

# Controles
GET/POST/PUT/DELETE /auditoria/controles/{...}  → ControlController (resource completo)

# Objetivos
GET/POST/PUT/DELETE /auditoria/objetivos/{...}  → ObjetivoController (resource completo)

# Planes de Acción  (param: {plane})
GET/POST/PUT/DELETE /auditoria/planes/{...}     → PlanAccionController (resource completo)

# Tareas
GET/POST/PUT/DELETE /auditoria/tareas/{...}     → TareaController (resource completo)
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

- **`store`**: Impacto y probabilidad salen del wizard. `area_id` es obligatorio y de la línea del
  usuario. No asocia objetivos: se vinculan desde la ficha.
- **`update`**: `area_id` es obligatorio, de la línea del usuario o el que ya tenía.

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
│   ├── GestionAreas.php        — gerencias del riesgo
│   ├── GestionControles.php    — maneja controles de un riesgo (con mitigacion editable)
│   ├── GestionObjetivos.php    — maneja objetivos de un riesgo (mínimo 1 requerido)
│   └── GestionPlanes.php       — maneja planes de acción de un riesgo
├── Control/Show/
│   ├── FichaControl.php        — datos del control, editables en el bloque (con propagación del default)
│   ├── RiesgosControl.php      — riesgos que mitiga (sólo lectura)
│   └── InfoControl.php         — tarjeta Mitigación del lateral
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
    └── InfoPlan.php            — tarjeta Avance del lateral (mitigación, vencimiento)
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
| `ControlFactory` | `Control` |
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
| `un_riesgo_puede_tener_muchos_controles_con_mitigacion_pivot` | Pivot `control_riesgo` con campo `mitigacion` |
| `objetivo_tiene_fecha_nullable` | `fecha_objetivo` nullable y cast a Carbon |

Las actualizaciones se crean via `$tarea->actualizaciones()->create([...])` (no factory directa) porque el modelo es polimórfico y requiere que el relationship setee `actualizable_type` / `actualizable_id`.
