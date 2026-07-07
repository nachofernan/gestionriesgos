# Roadmap — Módulo de Auditoría

Estado del trabajo pendiente. Se actualiza a medida que se completan o surgen ítems — no es un historial (para eso está [CHANGELOG.md](../CHANGELOG.md)).

## Cronograma original — estado

Las 6 fases del cronograma inicial (`docs/CRONOGRAMA_ORIGINAL.txt`) están completas al 97% según el análisis del 2026-06-26 (`docs/ANALISIS_AVANCES_EJECUTIVO_2026-06-26.txt`). El único punto abierto del cronograma original es:

- [ ] **Reportes exportables a PDF/Excel** (Fase 5). Infraestructura de listados/filtros ya lista; falta la exportación en sí.

## Próximos pasos (fuera del cronograma original)

Identificados en el análisis de avances como expansión posible, sin fecha comprometida:

- [ ] Dashboard con gráficos y KPIs (estimado original: 2-3 semanas)
- [ ] Notificaciones automáticas (estimado original: 1 semana) — ej. avisar a un responsable cuando vence una tarea o cuando le asignan un riesgo
- [ ] Auditoría detallada por usuario (estimado original: 1 semana) — más allá del historial de `Actualizacion` ya existente

## En curso / recién cerrado

- [x] **Wizard de creación de riesgo con impacto/probabilidad calculados** (2026-07-03) — ver [changelog](updates/2026-07-03.md). Impacto/probabilidad dejan de cargarse a mano; objetivo y plan de acción pasan a ser requisito de validación, no de creación.
- [x] **Impacto/probabilidad ya no editables en la edición** (2026-07-03) — ver [changelog](updates/2026-07-03c.md). Sólo se recalculan repitiendo el wizard de preguntas, nunca cargándolos directo.
- [x] **Pantalla de Pendientes** (2026-07-03) — ver [changelog](updates/2026-07-03d.md). Qué tiene que validar/aprobar cada usuario, con acción inline por modal sin recargar el listado completo.
- [x] **Seeders con variedad real de estados** (2026-07-03) — ver [changelog](updates/2026-07-03e.md). Control/Objetivo/PlanAccion/Tarea ya no quedan todos "aprobado"; Riesgo siempre tiene `respuesta`, respetando los prerequisitos de validación existentes.
- [x] **Riesgo con múltiples gerencias** (2026-07-07) — ver [changelog](updates/2026-07-07.md). Un riesgo puede pertenecer a varias gerencias (`area_riesgo`) con permisos equivalentes; el creador deja de ser editable (siempre `auth()`). Por ahora solo en Riesgo — no descartado extenderlo a otras entidades si surge la necesidad.
- [x] **PDF de Pendientes** (2026-07-07 bis) — ver [changelog](updates/2026-07-07b.md). Botón para descargar en PDF el listado de la pantalla de Pendientes, para llevar impreso a una reunión.

## Ideas abiertas / a definir

Estos ítems surgieron en el camino pero no tienen alcance ni prioridad definida todavía — antes de tomarlos hay que confirmar con el usuario:

- Reportes PDF/Excel: falta definir qué formato de reporte (por riesgo individual, consolidado por área, por plan de acción) y si el motivo de exportación es auditoría interna o para terceros.
- Notificaciones: falta definir canal (email, in-app, ambos) y qué eventos disparan aviso.
- Pendientes: aprobación/validación en bloque (varios ítems a la vez con un modal previo que liste todo lo que se va a hacer). Se evaluó al construir la pantalla de Pendientes y se decidió no hacerlo todavía — requiere resolver el análisis de prerequisitos bloqueantes/opcionales por cada ítem seleccionado, no sólo por uno.

---

*Última revisión: 2026-07-07.*
