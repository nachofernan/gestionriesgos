# CLAUDE.md — Guía de trabajo para este proyecto

## Stack

- **Laravel 11** + **PHP 8.2**
- **Livewire 4** para componentes reactivos
- **Alpine.js** para interactividad frontend liviana (modales, acordeones, toggles)
- **Tailwind CSS** para estilos
- **Spatie Media Library** para adjuntos
- **MySQL** vía XAMPP

---

## Entorno y comandos

- **SO / shell:** Windows + XAMPP, shell PowerShell. La DB es la **MySQL de XAMPP** — tiene que estar levantada para correr la app y los tests.
- **Tests:** `php artisan test` (todo) o `php artisan test --filter=<Nombre>` para acotar. En `phpunit.xml` la conexión de DB está comenteada, así que los tests **corren contra la MySQL real de XAMPP con `RefreshDatabase`**, no SQLite en memoria. Si fallan por no conectar, es que MySQL no está corriendo — no es un bug del test.
- **Formateo:** `./vendor/bin/pint` (Laravel Pint). Correr sobre lo tocado antes de commitear.
- **Assets:** `npm run dev` (watch) / `npm run build` (producción) para Tailwind.

---

## Agentes del proyecto

Hay dos subagentes definidos en `.claude/agents/`. Delegar según el peso de la tarea:

- **`auditoria-junior`** (Sonnet, sin Bash) — tareas mecánicas y acotadas que no tocan estructura: copy en vistas, ajustes de Blade/Tailwind, typos, agregar un campo a `$fillable` ya existente. No testea ni commitea. Si la tarea resulta ser estructural, la devuelve para el senior.
- **`auditoria-senior`** (Opus, todas las herramientas) — trabajo profundo que toca esquema, Policies, reglas de negocio o cruza capas. Investiga dependencias, implementa, corre tests + Pint y commitea. Como corre aislado, cuando hay ambigüedad real vuelve con las preguntas en su reporte en vez de asumir.

Nota: un subagente corre en contexto aislado y **no puede preguntarte en vivo** — el ida y vuelta con vos ocurre en el hilo principal, que es quien delega y releva las dudas que el subagente devuelve.

---

## Arquitectura general

Sistema de gestión de auditoría de riesgos. El flujo central es:

```
Riesgo → Controles (mitigación)
       → Objetivos estratégicos
       → Planes de Acción → Tareas → Actualizaciones
```

Todo bajo el namespace `Auditoria/` tanto en controladores como en Livewire y vistas.

### Capas

| Capa | Ubicación | Rol |
|---|---|---|
| Controladores MVC | `app/Http/Controllers/Auditoria/` | CRUD estándar + asociaciones |
| Componentes Livewire | `app/Livewire/Auditoria/` | Búsqueda/listado, gestión de relaciones, modales |
| Vistas Blade | `resources/views/auditoria/` | Vistas MVC estáticas |
| Vistas Livewire | `resources/views/livewire/auditoria/` | Vistas de componentes |
| Modelos | `app/Models/Auditoria/` | Eloquent con SoftDeletes |
| Policies | `app/Policies/Auditoria/` | Autorización por área jerárquica |

### Autorización

Los permisos se basan en una jerarquía de áreas (`area_padre_id` auto-referencial). Un gerente gestiona su área y todas las sub-áreas. El comité tiene `area_id = null` y puede operar en cualquier área. Siempre usar `$this->authorize()` o `$request->user()->can()` antes de mutar datos.

---

## Cómo trabajamos

- Antes de tocar archivos, se explica qué se va a crear/modificar y por qué. Se espera confirmación antes de avanzar con un paso de alcance nuevo (no hace falta re-confirmar cada línea de código, sí cada salto de alcance).
- Cada paso es un cambio lógico chico, no varios cambios de golpe sin avisar (ej: migración + modelo + vista + test en el mismo paso sin avisar, no).
- Si un cambio obliga a tocar otra capa del sistema (esquema → modelo, regla de negocio → test, policy → controlador, etc.), se marca explícitamente como efecto en cascada antes de hacerlo. Nunca silencioso.
- Si en el camino aparece algo necesario que no estaba pedido (un bug real, un fix que hace falta para que lo pedido funcione, una limpieza obligada), se hace y se explica después, con el motivo — no se pide permiso para cada hallazgo chico, pero tampoco se cuela sin decir nada.
- Uso de control de versiones semi-constante: al cerrar una etapa de trabajo con sentido propio (ej: migración + modelo + policy básica) se commitea. No se deja una etapa terminada sin commitear, pero tampoco se commitea a mitad de un cambio que no compila o no pasa tests.
- Cuando hay ambigüedad real sobre qué hacer (no derivable del código, del pedido, ni de una convención ya establecida en el proyecto), se pregunta en vez de asumir. Cuando la respuesta sí es derivable, se deriva y se sigue — preguntar de más frena tanto como asumir de más.

---

## Convenciones de código

### General

- Escribir en **español**: nombres de variables, métodos, mensajes de validación, comentarios.
- Nombres de métodos y variables en **camelCase**. Clases en **PascalCase**. Tablas y columnas en **snake_case**.
- No añadir comentarios obvios. Solo comentar cuando el **por qué** no es evidente del código (una decisión no obvia, una limitación externa, un workaround puntual).
- Métodos nuevos o modificados de forma sustancial en controladores, componentes Livewire y modelos llevan un docblock breve si el nombre no alcanza para explicar para qué sirven y cómo se conectan con el resto (quién los llama, qué disparan, de qué dependen). No es un comentario de "qué hace la línea siguiente".
- No sobre-abstractar. Si algo se usa una o dos veces, escribirlo directo. Extraer solo cuando la duplicación es real y el patrón es estable. Tres líneas repetidas son mejores que una abstracción prematura.
- Sin código defensivo para casos que no pueden pasar. Validación en los bordes del sistema (input de usuario, form requests, respuesta de una API externa), no en cada método interno que ya confía en sus invariantes.
- Convenciones estándar de Laravel/Livewire, sin inventar estructura de carpetas ni patrones propios — todo donde el framework lo espera por defecto.

### Controladores

- Seguir el patrón resource de Laravel. Métodos extra (como `asociarControles`) van en el mismo controlador si son sobre la misma entidad.
- Validar con `$request->validate()` o Form Requests para casos complejos.
- En `show()`, hacer eager loading de todas las relaciones que la vista necesite. No lazy-load en vistas.

### Modelos

- Declarar `$fillable` explícitamente. No usar `$guarded = []`.
- Usar `$casts` para fechas y booleanos. No convertir manualmente en controladores.
- Accessors calculados (`valor_total`, `valor_residual`) van en el modelo.
- Los observers simples van en `booted()` con closures. Solo crear clases Observer separadas si la lógica es extensa.

### Livewire

- Componentes de búsqueda/listado: usar `WithPagination`, 15 items por página, `resetPage()` en cada filtro.
- Componentes de gestión de relaciones: patrón `$seleccionados` en memoria → `guardar()` hace el `sync()`.
- Siempre llamar `$this->authorize()` en métodos que mutan datos, incluso en Livewire (el HTTP controller no protege el componente).
- No mezclar lógica de negocio compleja en los componentes. Si crece, moverla al modelo o a un Action.

### Vistas Blade

- Usar `@livewire()` solo donde hace falta reactividad. El resto es Blade estático.
- Modales de resumen rápido: Alpine.js con `x-data` local y datos embebidos via `@js($data)`.
- El campo `puede_ver` (boolean computado server-side con `auth()->user()->can('view', $model)`) controla el botón de navegación en modales. Siempre computarlo en el controlador/componente, no en la vista.
- Partials reutilizables en `resources/views/auditoria/partials/`.

### Base de datos

- Siempre usar migraciones. No modificar esquema a mano.
- Las tablas pivot con campos extra (`control_riesgo.mitigacion`) se acceden via `pivot->campo` en el modelo.
- El seeder crítico es `EstadoRiesgoSeeder` — el observer de `Riesgo` lo requiere para asignar estado "borrador" al crear. Los tests lo ejecutan en `setUp()`.

---

## Tests

- Ubicación: `tests/Feature/Auditoria/`
- Usar `RefreshDatabase`. Ejecutar `EstadoRiesgoSeeder` en `setUp()` cuando se crean Riesgos.
- Tests de integración reales, no mocks de base de datos.
- Nombrar tests en español descriptivo: `un_riesgo_se_crea_con_estado_borrador_por_defecto`.
- Cubrir: camino feliz + casos de permisos (403 esperados).
- Toda funcionalidad nueva lleva al menos un test antes de considerarse terminada. Nada de "test pendiente para después": si no se puede testear en el momento (ej: depende de un servicio externo no disponible todavía), se dice explícitamente por qué, no se omite en silencio.

---

## Lo que no hacer

- No crear abstracciones de repositorios o servicios si no hay necesidad real.
- No agregar campos o lógica "por si acaso se necesita en el futuro".
- No duplicar validaciones entre controlador y componente Livewire — elegir uno o coordinarlos.
- No dejar `dd()`, `dump()` o `var_dump()` en commits.
- No usar `$guarded = []` en modelos.
- No hacer lazy-loading en vistas (`$model->relation` dentro de un `@foreach` sin eager load previo).

---

## Documentación del proyecto

- `docs/modulo-auditoria.md` — estructura completa del módulo (modelos, rutas, controladores, Livewire, tests).
- `docs/updates/YYYY-MM-DD.md` — log de cambios significativos. Crear uno nuevo por sesión de trabajo relevante.
- `CHANGELOG.md` (raíz) — índice cronológico de `docs/updates/`, un renglón por entrada con link. Actualizar cada vez que se crea un `docs/updates/YYYY-MM-DD.md` nuevo.
- `docs/ROADMAP.md` — trabajo pendiente (no es historial, eso es el changelog). Tachar/mover ítems a medida que se completan, agregar los que surjan. Actualizar la fecha de "última revisión" al final del archivo cuando se lo toca.
