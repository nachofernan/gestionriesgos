# CLAUDE.md — Guía de trabajo para este proyecto

Sistema de gestión de auditoría de riesgos. El flujo central es el ciclo completo: identificación
del riesgo, controles que lo mitigan, objetivos estratégicos, planes de acción y seguimiento de
tareas, todo bajo autorización por jerarquía de áreas. La pregunta que el sistema tiene que poder
contestar en cualquier momento es *"para cada riesgo: cuánto vale, cuánto lo mitigan sus controles y
planes, en qué estado está, y quién puede tocarlo"*.

El **qué** del módulo (modelos, rutas, componentes, tests) vive en `docs/modulo-auditoria.md`. Este
documento es **cómo se trabaja** el proyecto, no qué hace. Si hay contradicción entre este archivo y
una decisión registrada en `docs/DECISIONES.md`, manda la decisión más reciente.

---

## Principio cero: nada está escrito en piedra

Ninguna decisión de este proyecto —y **menos que ninguna las técnicas**— es un contrato. El stack,
la estructura de carpetas, Livewire vs otra cosa: todo es hipótesis de trabajo revisable cuando
aparece un motivo real. Lo que **sí** es estable son los axiomas de arquitectura de más abajo, y aun
esos se cambian con una charla explícita, no de contrabando. Cuando algo se decide o se revierte, se
anota en `docs/DECISIONES.md`. No se reescribe el pasado: una decisión que cae se marca con una
entrada nueva que la reemplaza.

---

## Los modos de trabajo

Este proyecto se conversa antes de codearse. **El hilo principal —donde se piensa, se decide y se
toca el núcleo, con el usuario presente— es el centro.** Ahí se discute el dominio, se cierra un
diseño y se implementa todo lo que toca el núcleo sagrado: la exactitud de la autorización y del
cálculo de riesgo se protege con *presencia*, no con potencia.

**El núcleo sagrado** son las Policies y la autorización por jerarquía de área, `scopeVisiblePara`,
el cálculo de `valor_total` / `valor_residual` y la mitigación, el ciclo de estados y la doble
validación entre gerencias. **No se delega.** Un subagente corre aislado: arranca en frío —tiene que
releer este archivo, los docs y mapear el código— y no puede preguntar en vivo. Para trabajo de
juicio eso paga dos veces el contexto y produce menos.

Los agentes de `.claude/agents/` **no son rangos** (un junior, un senior, un jefe): son **fases del
trabajo**. Un subagente no le sirve al usuario, le sirve al hilo principal: es una función acotada
que corre en su propia ventana de contexto y devuelve un resultado destilado, para no ensuciar la
conversación con material crudo. Se delega el trabajo *mecánico o de fan-out*, no el juicio.

- **Explorador** (`explorador` — Haiku, solo lectura). El sabueso: rastrea dónde vive una regla, qué
  Policy gobierna una entidad, quién llama a un método, qué tests cubren algo, y **devuelve la
  conclusión con rutas exactas, no el volcado de archivos.** Se usa cuando contestar implica barrer
  muchos archivos y sólo importa el resultado. Buscar bien es mecánico y barato; por eso es Haiku, no
  porque sea tonto.
- **Ejecutor** (`ejecutor` — Sonnet, edita). Ejecuta **decisiones ya tomadas** en la **periferia**:
  Blade, Tailwind, textos, tweaks de UI, un `$fillable` cuya columna ya existe, renombrar una
  variable local, mover un partial. Cumple con poco preámbulo y sin reabrir lo decidido. Si el pedido
  roza estructura (migración, ruta, relación nueva) o el núcleo sagrado, **corta y avisa**: eso
  vuelve al hilo principal.
- **Testeador** (`testeador` — Haiku, solo corre y reporta). Corre `php artisan test` y devuelve un
  veredicto destilado —`233 passed`, o los que fallan con su detalle— sin cargar el volcado verde en
  la conversación. **No arregla:** un test que falla nunca se maquilla para que pase; el arreglo se
  decide en el hilo principal.
- **Mentor** (`mentor` — Opus, solo lectura). El caso raro: una decisión pesada donde elegir mal es
  caro y conviene un análisis dedicado de un tiro. **La sesión principal ya trabaja con ese stance
  por defecto** —antes de ejecutar algo grande se piensa y se recomienda—, así que invocarlo es la
  excepción, no el camino habitual. Si de la charla sale una decisión, se anota en
  `docs/DECISIONES.md` y ahí termina.

Regla que ordena todo: **planear y ejecutar el núcleo pasan por el hilo principal, con el usuario
presente.** Los subagentes están para lo que *no* es esa decisión — encontrar, ejecutar periferia,
verificar. Ante la duda de si algo es núcleo o periferia, es núcleo: se pregunta antes de delegar.

### Cómo se pregunta

- Si algo no se entiende o hay ambigüedad, se pregunta **con opciones concretas** (A / B / C), no con
  un "¿cómo querés que lo haga?" abierto.
- Si el concepto es lo bastante grande como para que la respuesta correcta dependa de cosas que
  todavía no están decididas, no se ofrecen opciones: se pide charlarlo.
- Nunca se resuelve una ambigüedad de **diseño** eligiendo por cuenta propia y avisando después. Una
  ambigüedad de **implementación** (nombre de una variable, orden de dos métodos), sí: se deriva y se
  sigue. Preguntar de más frena tanto como asumir de más.

---

## Cómo se trabaja

- Antes de tocar archivos, se explica qué se va a crear/modificar y por qué. Se espera confirmación
  antes de avanzar con un paso de alcance nuevo (no cada línea de código, sí cada salto de alcance).
- Cada paso es un cambio lógico chico (una migración, un modelo, una policy, un componente), no
  varios cambios de golpe sin avisar.
- Si un cambio obliga a tocar otra capa (esquema→modelo, regla de negocio→test, policy→controlador),
  se marca explícitamente como **efecto en cascada** antes de hacerlo. Nunca silencioso.
- **Tocar la autorización, el cálculo del valor de un riesgo o el ciclo de estados = efecto en
  cascada garantizado.** Cambia lo que ve y lo que puede hacer cada rol. No se toca sin avisarlo como
  lo que es y sin un test que lo respalde.
- Si en el camino aparece algo necesario que no estaba pedido (un bug real, un fix que hace falta
  para que lo pedido funcione), se hace y se explica después, con el motivo — no se pide permiso para
  cada hallazgo chico, pero tampoco se cuela sin decir nada.
- Git: commit al cerrar una **etapa con sentido propio** (ej: migración + modelo + policy básica), no
  cada capa suelta. Una tanda de cambios chicos (copys, estilos, treinta tweaks de UI) se commitea
  junto, como un solo bloque. No se deja una etapa terminada sin commitear, ni se commitea a mitad de
  un cambio que no compila o no pasa tests. Antes de tocar archivos con cambios sin commitear, se
  revisa `git status` / `git diff`.

---

## Axiomas de arquitectura

Son las reglas que no se negocian sin una conversación explícita. Todo lo demás es táctica.

1. **La autorización por jerarquía de área es sagrada.** Los permisos se basan en la jerarquía de
   áreas (`area_padre_id` auto-referencial): un gerente gestiona su área y todas las sub-áreas; el
   comité (`area_id = null`) opera sobre lo público de cualquier área. **Siempre** se llama a
   `$this->authorize()` / `$request->user()->can()` antes de mutar datos — también en componentes
   Livewire, porque el controlador HTTP no protege el componente. La visibilidad (qué ve cada rol
   según estado) vive en las Policies y en los scopes (`scopeVisiblePara`), no dispersa en las
   vistas. Toda ruta o método que muta lleva su test de 403.

2. **El valor de un riesgo se deriva de datos, nunca se calcula "a ojo" en una vista.**
   `valor_total = impacto + probabilidad`. `valor_residual` descuenta la mitigación de los controles
   **en estado aprobado** más la de los planes de acción **al 100% de avance**, y nunca baja de 0.
   Un control en borrador/validado no baja el valor; un plan incompleto no descuenta nada. Estos
   accessors calculados viven en el modelo `Riesgo`, se consumen con las relaciones eager-loaded, y
   son la fuente de verdad: ninguna vista recalcula el residual por su cuenta.

3. **El ciclo de estados gobierna qué se puede hacer.** `borrador → validado → aprobado` (o
   `borrado`). Quién puede ver, validar, aprobar o compartir un riesgo depende de su estado, no solo
   del rol. Un borrador es siempre **mono-gerencia**; compartir un riesgo con otra gerencia sólo se
   permite tras validarlo. Un riesgo compartido entre dos o más gerencias exige **doble validación**:
   cada propuesta de cambio la validan todas las gerencias (`validacion_gerencia`), un solo rechazo la
   tumba. Esta regla está centralizada en `Riesgo::cambioRequiereDobleValidacion()`, no dispersa en
   los componentes.

4. **El historial es append-only, polimórfico y auditable.** Los cambios sobre las entidades quedan
   registrados como `Actualizacion` (`morphMany`: quién, qué campo, valor anterior/nuevo, cuándo). El
   historial no se reescribe: una corrección es una entrada nueva, no una edición de la vieja.

5. **Impacto y probabilidad no se cargan a mano.** Salen del wizard de preguntas guiadas por
   dimensión. En la edición son de solo lectura; para cambiarlos hay que "Recalcular" repitiendo el
   wizard. Ninguna pantalla los deja editar libremente.

6. **SoftDeletes en todos los modelos principales; los códigos correlativos no se reutilizan.** El
   código de un riesgo (`R-0001`, `R-0002`, …) se asigna contando también los borrados lógicamente
   (`withTrashed`), para que un borrado nunca libere su número.

7. **El dominio se nombra en castellano.** Riesgo, control, objetivo, plan de acción, tarea,
   actualización, área, gerencia, estado. Los nombres de modelos, tablas, variables y vistas siguen
   ese glosario. Nada de mezclar inglés con castellano en el dominio.

---

## Entorno y comandos

- **SO / shell:** Windows + XAMPP, shell PowerShell. La DB es la **MySQL de XAMPP** — tiene que estar
  levantada para correr la app y los tests.
- **Tests:** `php artisan test` (todo) o `php artisan test --filter=<Nombre>` para acotar. En
  `phpunit.xml` la conexión de DB está comenteada, así que los tests **corren contra la MySQL real de
  XAMPP con `RefreshDatabase`**, no SQLite en memoria. Si fallan por no conectar, es que MySQL no está
  corriendo — no es un bug del test.
- **Formateo:** `./vendor/bin/pint` (Laravel Pint). Correr sobre lo tocado antes de commitear.
- **Assets:** `npm run dev` (watch) / `npm run build` (producción) para Tailwind.
- **Seeder crítico:** `EstadoRiesgoSeeder` — el observer de `Riesgo` lo requiere para asignar estado
  "borrador" al crear. Ejecutarlo en `setUp()` de todo test que cree Riesgos.

---

## Convenciones de código

### General

- Escribir en **español**: nombres de variables, métodos, mensajes de validación, comentarios.
- Nombres de métodos y variables en **camelCase**. Clases en **PascalCase**. Tablas y columnas en
  **snake_case**.
- No añadir comentarios obvios. Solo comentar cuando el **por qué** no es evidente del código (una
  decisión no obvia, una limitación externa, un workaround puntual).
- Métodos nuevos o modificados de forma sustancial en controladores, componentes Livewire y modelos
  llevan un docblock breve si el nombre no alcanza para explicar para qué sirven y cómo se conectan
  con el resto (quién los llama, qué disparan, de qué dependen). No es un comentario de "qué hace la
  línea siguiente".
- Si el método es del **núcleo sagrado**, ese docblock además **nombra el test que lo cubre** (por
  nombre del test, no por `archivo:línea`), para poder tirar el `--filter` exacto sin barrer la suite
  buscando cuál era. Sólo donde hay lógica real —un cálculo, una regla—, no en getters. Si el test se
  renombra o se mueve, se actualiza el docblock: es una línea.
- No sobre-abstractar. Si algo se usa una o dos veces, escribirlo directo. Extraer solo cuando la
  duplicación es real y el patrón es estable. Tres líneas repetidas son mejores que una abstracción
  prematura.
- Sin código defensivo para casos que no pueden pasar. Validación en los bordes del sistema (input de
  usuario, form requests, respuesta de una API externa), no en cada método interno que ya confía en
  sus invariantes.
- Convenciones estándar de Laravel/Livewire, sin inventar estructura de carpetas ni patrones propios.

### Controladores

- Seguir el patrón resource de Laravel. Métodos extra (como `asociarControles`) van en el mismo
  controlador si son sobre la misma entidad.
- Validar con `$request->validate()` o Form Requests para casos complejos.
- En `show()`, hacer eager loading de todas las relaciones que la vista necesite. No lazy-load en
  vistas. Para el cálculo del residual, eso incluye `controles.estado` y `planesAccion.tareas.estado`.

### Modelos

- Declarar `$fillable` explícitamente. No usar `$guarded = []`.
- Usar `$casts` para fechas, booleanos y enums. No convertir manualmente en controladores.
- Accessors calculados (`valor_total`, `valor_residual`, `clasificacion_*`) van en el modelo.
- Los observers simples van en `booted()` con closures. Solo crear clases Observer separadas si la
  lógica es extensa.

### Livewire

- Componentes de búsqueda/listado: usar `WithPagination`, 15 items por página, `resetPage()` en cada
  filtro.
- Componentes de gestión de relaciones: patrón `$seleccionados` en memoria → `guardar()` hace el
  `sync()`.
- Siempre llamar `$this->authorize()` en métodos que mutan datos, incluso en Livewire (el HTTP
  controller no protege el componente).
- No mezclar lógica de negocio compleja en los componentes. Si crece, moverla al modelo o a un
  Action.

### Vistas Blade

- Usar `@livewire()` solo donde hace falta reactividad. El resto es Blade estático.
- Modales de resumen rápido: Alpine.js con `x-data` local y datos embebidos via `@js($data)`.
- El campo `puede_ver` (boolean computado server-side con `auth()->user()->can('view', $model)`)
  controla el botón de navegación en modales. Siempre computarlo en el controlador/componente, no en
  la vista.
- Partials reutilizables en `resources/views/auditoria/partials/`.

### Base de datos

- Siempre usar migraciones. No modificar esquema a mano.
- Las tablas pivot con campos extra (`control_riesgo.mitigacion`) se acceden via `pivot->campo`.

---

## Testing

El testing se dosifica por **zona** (qué se tocó) y por **checkpoint** (cuándo se corre qué). La
exactitud de la autorización y del cálculo de riesgo sigue siendo sagrada; lo que se calibra es
cuánto ritual se aplica *fuera* de ese núcleo. No se gastan diez unidades de esfuerzo en programar y
cien en re-testear cosas ya probadas.

**Núcleo sagrado** (definido arriba, en *Los modos de trabajo*)**:**

- **Toda regla de este núcleo lleva test antes de considerarse terminada.** Camino feliz + casos de
  permisos (los 403 esperados) + el caso feo (un gerente que no debería poder validar la propuesta de
  otra gerencia, un control en borrador que no debe bajar el residual, un plan incompleto que no
  descuenta). Nada de "test pendiente para después" acá.
- Tests de integración reales contra la MySQL de XAMPP con `RefreshDatabase`, no mocks de DB.
- Nombres en español descriptivo: `un_riesgo_se_crea_con_estado_borrador_por_defecto`.

**Periferia (CRUD, Blade, Tailwind, textos, tweaks de UI):**

- Test **solo si la rotura puede propagar** a otra cosa. Lógica cerrada y trivial que si se rompe se
  rompe sola —un color, un tamaño, una validación tonta, un copy— no lleva test propio: es esfuerzo
  al pedo.
- Un elemento nuevo y aislado se da por bueno con que la suite no se rompa; no exige test dedicado.

**Alcance de la corrida (aplica a las dos zonas):**

- Durante el trabajo se corre **solo el/los test locales relevantes** a lo que se terminó
  (`--filter=<Nombre>`). No la suite entera.
- La **suite completa** es un evento de *checkpoint*, no de cada paso: se corre al cerrar un bloque
  grande, antes de commitear algo del núcleo sagrado, o cuando se tocó algo transversal (un modelo
  base, un scope global, config). No después de agregar un campo a un form.
- Se reporta el **resumen** de la corrida (`233 passed`, o los que fallan con su detalle), no el
  volcado verde línea por línea. El costo no es correr los tests: es cargar su output en la
  conversación — por eso la corrida se delega al `testeador`, que devuelve el veredicto destilado. Si
  los tests fallan por no conectar a MySQL, se dice — no se maquilla ni se oculta.

**Testeo visual:** no lo hace Claude. Nada de `curl`, Playwright ni levantar navegador para "ver" una
pantalla, salvo pedido explícito. La revisión visual la hace el usuario; a lo sumo manda un
screenshot con el detalle que vio.

---

## Documentación

- Comentarios inline solo cuando el *por qué* no es obvio (ver General, arriba).
- `docs/modulo-auditoria.md` — el **qué**: estructura completa del módulo (modelos, rutas,
  controladores, Livewire, tests). Se actualiza cuando cambia la arquitectura.
- `docs/DECISIONES.md` — bitácora **append-only** de decisiones de diseño y arquitectura, con el
  motivo y lo que se descartó. Cuando una charla cierra algo, se anota acá. No se reescribe el pasado:
  si una decisión se revierte, se agrega una entrada nueva que la revierte.
- `docs/CHANGELOG.md` — índice cronológico de lo que cambió, un renglón por entrada. **Es el registro
  por defecto**: una tanda de trabajo cerrada se resume acá y ahí termina.
- `docs/updates/YYYY-MM-DD.md` — detalle largo, **sólo para el cambio que mueve la arquitectura**
  (esquema, autorización, una regla del ciclo de riesgo, un patrón nuevo que se va a repetir). No se
  crea uno por sesión: escribir el detalle cuesta, y un update de un tweak de UI es ruido que después
  hay que leer. Si se crea uno, su renglón en el CHANGELOG lo linkea.
- `docs/ROADMAP.md` — trabajo pendiente (no es historial, eso es el changelog). Tachar/mover ítems a
  medida que se completan. Actualizar la fecha de "última revisión" al final cuando se lo toca.

---

## Stack

- **Laravel 11** + **PHP 8.2**
- **Livewire 4** para componentes reactivos
- **Alpine.js** para interactividad frontend liviana (modales, acordeones, toggles)
- **Tailwind CSS** para estilos
- **Spatie Media Library** para adjuntos
- **MySQL** vía XAMPP

### Capas

| Capa | Ubicación | Rol |
|---|---|---|
| Controladores MVC | `app/Http/Controllers/Auditoria/` | CRUD estándar + asociaciones |
| Componentes Livewire | `app/Livewire/Auditoria/` | Búsqueda/listado, gestión de relaciones, modales |
| Vistas Blade | `resources/views/auditoria/` | Vistas MVC estáticas |
| Vistas Livewire | `resources/views/livewire/auditoria/` | Vistas de componentes |
| Modelos | `app/Models/Auditoria/` | Eloquent con SoftDeletes |
| Policies | `app/Policies/Auditoria/` | Autorización por área jerárquica |

Todo bajo el namespace `Auditoria/` tanto en controladores como en Livewire y vistas.

---

## Lo que no hacer

Lo que no está dicho en otro lado (el resto vive en *Axiomas* y *Convenciones*):

- No agregar campos ni lógica "por si acaso se necesita en el futuro".
- No duplicar validaciones entre controlador y componente Livewire — elegir uno o coordinarlos.
- No dejar `dd()`, `dump()` o `var_dump()` en commits.

Y las dos que sí vale repetir, porque romperlas rompe el producto: **no se muta sin `authorize()`**
—ni siquiera en un componente Livewire— y **no se recalcula el valor de un riesgo en una vista**: se
consume el accessor del modelo.

---

## Estado

El **ciclo completo está construido y andando**: riesgos con impacto/probabilidad calculados por el
wizard, controles, objetivos, planes de acción y tareas, historial polimórfico de actualizaciones con
adjuntos, autorización por jerarquía de área con visibilidad por estado, riesgo compartido entre
gerencias con doble validación, y las pantallas de Pendientes, Vencimientos y el Panel de Riesgos
(dashboard de situación con sesgo gerencial). Las 6 fases del cronograma original están cerradas
salvo los reportes exportables. La suite ronda los ~233 tests, 23 archivos en
`tests/Feature/Auditoria/`.

Lo que sigue abierto vive en `docs/ROADMAP.md` (reportes PDF/Excel, notificaciones, vencimiento
propio del plan de acción, y que el comité hoy puede crear elementos que después no puede ver). El
*por qué* de cada decisión, en `docs/DECISIONES.md`. Los dos mandan sobre este párrafo si divergen.
