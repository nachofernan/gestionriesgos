# Módulo de Auditoría — Estructura General

## Índice

1. [Visión General](#visión-general)
2. [Modelos y Base de Datos](#modelos-y-base-de-datos)
3. [Rutas](#rutas)
4. [Controladores](#controladores)
5. [Componentes Livewire](#componentes-livewire)
6. [Vistas](#vistas)
7. [Seeders y Factories](#seeders-y-factories)
8. [Tests](#tests)

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
| `Control` | `controles` | Medidas de mitigación. Tiene `mitigacion_default` (1-10). El valor real se guarda en el pivot con el riesgo. |
| `Objetivo` | `objetivos` | Objetivos estratégicos. `fecha_objetivo` nullable, casteada a `date`. `peis` (boolean): si es true, requiere al menos un `PeisItem` asociado (ver validación en `ObjetivoController`). |
| `PeisItem` | `peis_items` | Catálogo fijo del Plan Estratégico de Integridad Sostenible (PEIS 1..5, sembrado por `PeisItemSeeder`). |
| `PlanAccion` | `planes_accion` | Agrupa riesgos y tareas. Código secuencial automático (PA-0001, PA-0002…). Sin columna de fecha propia: `vencimiento` (accessor) es la fecha más próxima entre sus tareas pendientes (`porcentaje_avance` < 100, no "borrado"); `esta_vencido` (accessor) es `true` si esa fecha ya pasó. |
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
- **`valor_residual`**: `valor_total − Σ mitigaciones de controles` (mínimo 0). Usa `pivot->mitigacion` si está definida, sino `mitigacion_default` del control.

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
POST   /auditoria/riesgos/{riesgo}/controles    → RiesgoController@asociarControles
POST   /auditoria/riesgos/{riesgo}/objetivos    → RiesgoController@asociarObjetivos

# Controles
GET/POST/PUT/DELETE /auditoria/controles/{...}  → ControlController (resource completo)

# Objetivos
GET/POST/PUT/DELETE /auditoria/objetivos/{...}  → ObjetivoController (resource completo)

# Planes de Acción  (param: {plane})
GET/POST/PUT/DELETE /auditoria/planes/{...}     → PlanAccionController (resource completo)
POST   /auditoria/planes/{plane}/tareas         → PlanAccionController@asociarTareas

# Tareas
GET/POST/PUT/DELETE /auditoria/tareas/{...}     → TareaController (resource completo)
POST   /auditoria/tareas/{tarea}/actualizaciones → ActualizacionTareaController@store
```

---

## Controladores

Ubicación: `app/Http/Controllers/Auditoria/`

Todos siguen el patrón CRUD estándar de Laravel. Se listan solo las particularidades de cada uno.

### `RiesgoController`

- **`store` / `update`**: Valida `impacto` y `probabilidad` (0-10), `tipo_riesgo_id`, `area_id`, `user_id`.
- **`asociarControles(Request, Riesgo)`**: Recibe array de `controles` con `{id, mitigacion}` y hace `sync()` con los datos del pivot.
- **`asociarObjetivos(Request, Riesgo)`**: Valida que venga al menos 1 objetivo. Hace `sync()`.

### `PlanAccionController`

- **`create`**: Llama a `generarCodigo()` privado que genera el siguiente código secuencial (PA-0001, PA-0002…).
- **`store`**: Crea el plan y luego sincroniza los `riesgo_ids` pasados en la request.
- **`asociarTareas(Request, PlanAccion)`**: Hace `sync()` de las tareas al plan.

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
│   ├── GestionControles.php    — maneja controles de un riesgo (con mitigacion editable)
│   ├── GestionObjetivos.php    — maneja objetivos de un riesgo (mínimo 1 requerido)
│   └── GestionPlanes.php       — maneja planes de acción de un riesgo
└── PlanAccion/Show/
    └── GestionTareas.php       — maneja tareas de un plan
```

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
    │       ├── gestion-controles.blade.php
    │       ├── gestion-objetivos.blade.php
    │       └── gestion-planes.blade.php
    ├── control/index/search.blade.php
    ├── objetivo/index/search.blade.php
    ├── plan-accion/
    │   ├── index/search.blade.php
    │   └── show/gestion-tareas.blade.php
    └── tarea/index/search.blade.php
```

Las vistas `show.blade.php` de Riesgo y PlanAccion embeben los componentes de gestión con `@livewire(...)`.

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
| `RiesgoFactory` | `Riesgo` — genera código `R-XXXX`, crea `TipoRiesgo` y `EstadoRiesgo` asociados |
| `PlanAccionFactory` | `PlanAccion` — código `PA-XXXX` |
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
| `riesgo_tiene_codigo_y_criticidad` | Campos `codigo` y `criticidad_alta` |
| `un_riesgo_puede_tener_muchos_controles_con_mitigacion_pivot` | Pivot `control_riesgo` con campo `mitigacion` |
| `objetivo_tiene_fecha_nullable` | `fecha_objetivo` nullable y cast a Carbon |

Las actualizaciones se crean via `$tarea->actualizaciones()->create([...])` (no factory directa) porque el modelo es polimórfico y requiere que el relationship setee `actualizable_type` / `actualizable_id`.
