# Roadmap — Módulo de Auditoría

Estado del trabajo pendiente. Se actualiza a medida que se completan o surgen ítems — no es un historial (para eso está [CHANGELOG.md](CHANGELOG.md)).

## Cronograma original — estado

Las 6 fases del cronograma inicial (`docs/CRONOGRAMA_ORIGINAL.txt`) están completas al 97% según el análisis del 2026-06-26 (`docs/ANALISIS_AVANCES_EJECUTIVO_2026-06-26.txt`). El único punto abierto del cronograma original es:

- [ ] **Reportes exportables a PDF/Excel** (Fase 5). Infraestructura de listados/filtros ya lista; falta la exportación en sí.

## Próximos pasos (fuera del cronograma original)

Identificados en el análisis de avances como expansión posible, sin fecha comprometida:

- [ ] Notificaciones automáticas (estimado original: 1 semana) — ej. avisar a un responsable cuando vence una tarea o cuando le asignan un riesgo
- [ ] Auditoría detallada por usuario (estimado original: 1 semana) — más allá del historial de `Actualizacion` ya existente
- [ ] **El comité no debería poder crear elementos** — hoy crea y al redirigir al show del borrador recién creado se come un 403. El `create()` de las 5 policies usa `puedeGestionarArea()` y el comité cuelga del área raíz, así que da `true`, pero `view()` le corta el borrador. Se resuelve junto con los permisos particulares en la migración del módulo al sistema real (decisión del 2026-07-17), no antes.

## En curso / recién cerrado

- [x] **Dashboard con gráficos y KPIs — primer pase** (2026-07-23) — ver [changelog](updates/2026-07-23.md). `PanelRiesgos`: panel de lectura con mapa de calor (impacto×probabilidad, riesgo inherente), dos rieles de cubitos 0-20 que comparan inherente vs residual, KPIs por criticidad residual y accesos a Pendientes/Vencimientos. El filtro de cascada quedó extraído a `Riesgo::scopeDeCascadaArea` reusable, como estaba previsto; toggle "solo aprobados" encendido por defecto; landing redirige al panel; se eliminó el `Dashboard` viejo de scaffolding. Continuación posible: más gráficos (tendencia, por tipo de riesgo) y migrar Vencimientos al mismo scope.
- [x] **Wizard de creación de riesgo con impacto/probabilidad calculados** (2026-07-03) — ver [changelog](updates/2026-07-03.md). Impacto/probabilidad dejan de cargarse a mano; objetivo y plan de acción pasan a ser requisito de validación, no de creación.
- [x] **Impacto/probabilidad ya no editables en la edición** (2026-07-03) — ver [changelog](updates/2026-07-03c.md). Sólo se recalculan repitiendo el wizard de preguntas, nunca cargándolos directo.
- [x] **Pantalla de Pendientes** (2026-07-03) — ver [changelog](updates/2026-07-03d.md). Qué tiene que validar/aprobar cada usuario, con acción inline por modal sin recargar el listado completo.
- [x] **Seeders con variedad real de estados** (2026-07-03) — ver [changelog](updates/2026-07-03e.md). Control/Objetivo/PlanAccion/Tarea ya no quedan todos "aprobado"; Riesgo siempre tiene `respuesta`, respetando los prerequisitos de validación existentes.
- [x] **Riesgo con múltiples gerencias** (2026-07-07) — ver [changelog](updates/2026-07-07.md). Un riesgo puede pertenecer a varias gerencias (`area_riesgo`) con permisos equivalentes; el creador deja de ser editable (siempre `auth()`). Por ahora solo en Riesgo — no descartado extenderlo a otras entidades si surge la necesidad.
- [x] **PDF de Pendientes** (2026-07-07 bis) — ver [changelog](updates/2026-07-07b.md). Botón para descargar en PDF el listado de la pantalla de Pendientes, para llevar impreso a una reunión.
- [x] **Reglas de negocio en el wizard de riesgo** (2026-07-17) — ver [changelog](updates/2026-07-17.md). Calificación cualitativa en el cálculo, área limitada a la línea del usuario, Corrupción sin compartir/aceptar, campo `fundamento` obligatorio para las respuestas que no reducen el riesgo.
- [x] **Pantalla de Vencimientos** (2026-07-17 bis) — ver [changelog](updates/2026-07-17b.md). Tareas ordenadas por fecha, agrupadas en vencidas / por vencer / en plazo / sin fecha. Sólo tareas: los planes no tienen fecha en el esquema (ver ídem en "Ideas abiertas").
- [x] **Vencimientos con sesgo gerencial** (2026-07-17 tris) — ver [changelog](updates/2026-07-17c.md). La pantalla filtra por la cascada del organigrama (área propia + sub-áreas) en vez del scope general `visiblePara()`; el comité ve todo. Sienta el criterio de sesgo gerencial para el dashboard.
- [x] **Gerencias explícitas sobre el árbol de áreas** (2026-07-17 quater) — ver [changelog](updates/2026-07-17d.md). Base de datos: `Area` lleva `tipo = gerencia` (enum `TipoArea`), y `Area::gerencia()` reemplaza la inferencia por profundidad. Solo modelo de datos; el siguiente paso es exponer/usar la marca en vistas, controladores y Policies.
- [x] **Gerencia resuelta en el pivot y la UI del Riesgo** (2026-07-17 quinquies) — ver [changelog](updates/2026-07-17e.md). Primer uso real de la marca: `area_riesgo` guarda área puntual + gerencia resuelta al crear; la sección "Gerencias" del show sólo gestiona gerencias y preserva el área puntual oculta. Sólo Riesgo (única entidad con pivot de áreas); Control/Objetivo/PlanAccion/Tarea usan `area_id` directo.
- [x] **Doble validación entre gerencias (y aplanado del acceso por gerencia)** (2026-07-21) — ver [changelog](updates/2026-07-21.md). Se descarta `gerencia_ajena`: todas las gerencias de un riesgo pesan igual. Compartir un riesgo se permite sólo tras validarlo y sólo a un gerente (así el borrador es siempre mono-gerencia). Un riesgo compartido exige que cada propuesta de cambio la validen todas las gerencias (tabla `validacion_gerencia`); un rechazo la tumba. Cubre campos, gerencias y asociaciones (controles/planes/objetivos); la regla vive en `Riesgo::cambioRequiereDobleValidacion()`. El proponente vota a favor al proponer y ya no puede re-validar/rechazar su propuesta (sólo cancelar). Con una gerencia rige el flujo de siempre; el comité queda afuera.

## Ideas abiertas / a definir

Estos ítems surgieron en el camino pero no tienen alcance ni prioridad definida todavía — antes de tomarlos hay que confirmar con el usuario:

- Reportes PDF/Excel: falta definir qué formato de reporte (por riesgo individual, consolidado por área, por plan de acción) y si el motivo de exportación es auditoría interna o para terceros.
- Notificaciones: falta definir canal (email, in-app, ambos) y qué eventos disparan aviso.
- Vencimiento propio del Plan de Acción: hoy `planes_accion` no tiene columna de fecha, así que la pantalla de Vencimientos lista sólo tareas y no existe el aviso de "esta tarea vence después de su plan". Si se decide que el plan tenga fecha propia, la cascada es: migración (`fecha_limite` nullable) → modelo (`$fillable` + `$casts`) → formularios create/edit → show del plan → factory y seeders → fila de plan en Vencimientos → aviso no bloqueante al asociar tareas en `GestionTareas` + marca de inconsistencia en el show del plan. Derivarla del `max(fecha)` de las tareas no sirve: haría el aviso imposible por construcción.
- Pendientes: aprobación/validación en bloque (varios ítems a la vez con un modal previo que liste todo lo que se va a hacer). Se evaluó al construir la pantalla de Pendientes y se decidió no hacerlo todavía — requiere resolver el análisis de prerequisitos bloqueantes/opcionales por cada ítem seleccionado, no sólo por uno.
- Recuperación de tareas soft-deleted: las tareas rechazadas (estado `borrado`) siguen siendo encontrables desde el listado de Tareas filtrando por estado (ver [2026-07-21c](updates/2026-07-21c.md)), pero las tareas eliminadas con `destroy()` (soft-delete) no son visibles en ninguna vista. Si se quiere una papelera / `withTrashed` para recuperarlas, falta definir alcance.

---

*Última revisión: 2026-07-23.*
