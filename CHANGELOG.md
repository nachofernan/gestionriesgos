# Changelog

Índice cronológico (más reciente primero) de las actualizaciones significativas del módulo de auditoría. Cada entrada es un resumen de 1-2 líneas con link al detalle completo en `docs/updates/`.

Para el detalle de arquitectura y estado general del módulo, ver `docs/modulo-auditoria.md`. Para el trabajo pendiente, ver `docs/ROADMAP.md`.

---

- **2026-07-17** — [Reglas de negocio en el wizard de riesgo](docs/updates/2026-07-17.md): el wizard muestra la calificación cualitativa (bajo/moderado/crítico) junto al cálculo; el área se limita a la línea del usuario (ni hermanas ni primas); un riesgo de tipo Corrupción no puede compartirse ni aceptarse (flag `restringe_respuesta`); campo `fundamento` obligatorio para compartir/aceptar/evitar.
- **2026-07-14 (bis)** — [Mitigación de planes al 100% en el valor residual](docs/updates/2026-07-14b.md): la relación plan↔riesgo lleva un valor de mitigación en el pivot (`plan_accion_riesgo.mitigacion`) que descuenta del residual del riesgo sólo cuando el plan llega al 100% de avance. Editable vía propuestas de cambio, al estilo de los controles.
- **2026-07-14** — [Adjuntos en el historial de actualizaciones](docs/updates/2026-07-14.md): al crear una actualización (en cualquiera de los 5 elementos) se pueden subir archivos, y descargarlos desde el historial. Descarga controlada por ruta, autorizada con el `view` de la entidad.
- **2026-07-07 (bis)** — [PDF de Pendientes](docs/updates/2026-07-07b.md): botón para descargar en PDF el listado de qué hay para validar/aprobar, para llevar impreso a una reunión. Se instaló `barryvdh/laravel-dompdf`.
- **2026-07-07** — [Riesgo con múltiples gerencias](docs/updates/2026-07-07.md): un riesgo puede pertenecer a varias gerencias (`area_riesgo`), todas con los mismos permisos de gestión (`RiesgoPolicy`, visibilidad y componente Livewire nuevo desde el show). El creador del riesgo deja de ser editable, siempre es `auth()`.
- **2026-07-03 (quinquies)** — [Seeders con variedad real de estados](docs/updates/2026-07-03e.md): Control/Objetivo/PlanAccion/Tarea dejan de quedar todos "aprobado" y Riesgo siempre tiene `respuesta`, respetando `motivosBloqueoValidacion()` (objetivo obligatorio, plan obligatorio si es "mitigar").
- **2026-07-03 (cuater)** — [Pantalla de Pendientes](docs/updates/2026-07-03d.md): qué tiene que validar/aprobar cada usuario (entidades y cambios propuestos), con acción inline vía modal sin recargar el listado. De paso, se corrigió un bug preexistente donde el modal de validación en cascada nunca redirigía de verdad.
- **2026-07-03 (tris)** — [Impacto/probabilidad ya no se editan a mano](docs/updates/2026-07-03c.md): en la edición pasan a ser de solo lectura; para cambiarlos hay que "Recalcular", que vuelve a pasar el wizard de preguntas.
- **2026-07-03 (bis)** — [Fixes del wizard de riesgo](docs/updates/2026-07-03b.md): corrige un crash al ver el historial de un riesgo recién creado (arrays mezclados en `campos`) y hace que el área se autocomplete con la del usuario, igual que el responsable.
- **2026-07-03** — [Wizard de creación de riesgo](docs/updates/2026-07-03.md): impacto/probabilidad ahora se calculan de 5 preguntas guiadas por dimensión en vez de cargarse libremente; objetivo y plan de acción (si aplica) pasan a ser obligatorios para validar, no para crear.
- **2026-05-28** — [Brecha de autorización en actualizaciones](docs/updates/2026-05-28.md): un gerente podía validar/rechazar propuestas de otras gerencias. Corregido en `ActualizacionPolicy` y `GestionActualizaciones`, con 23 tests nuevos.
- **2026-05-27b** — [Modales de resumen en vistas show](docs/updates/2026-05-27b.md): los elementos relacionados abren un modal Alpine con datos y estado en vez de navegar directo, evitando 403 cuando el usuario no tiene permiso sobre el hijo.
- **2026-05-27** — [Visibilidad jerárquica por rol y estado](docs/updates/2026-05-27.md): reglas de qué ve cada rol (comité/gerente/empleado) según estado del elemento, con tests completos y corrección de una brecha de seguridad en creación de riesgos/planes.
- **2026-05-26** — [Roles, estados unificados y flujo de aprobación](docs/updates/2026-05-26.md): columna `rol` en usuarios, estados comunes a todas las entidades, historial de actualizaciones con validación por rol para proponer/aplicar cambios.
- **2026-05-22** — [Nomenclatura, criticidad y UX](docs/updates/2026-05-22.md): `criticidad_alta` → `mayor_criticidad`, nuevos atributos en objetivos, indicadores de vencimiento y mejoras varias de UX.
