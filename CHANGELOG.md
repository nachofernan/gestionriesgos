# Changelog

Índice cronológico (más reciente primero) de las actualizaciones significativas del módulo de auditoría. Cada entrada es un resumen de 1-2 líneas con link al detalle completo en `docs/updates/`.

Para el detalle de arquitectura y estado general del módulo, ver `docs/modulo-auditoria.md`. Para el trabajo pendiente, ver `docs/ROADMAP.md`.

---

- **2026-07-03 (bis)** — [Fixes del wizard de riesgo](docs/updates/2026-07-03b.md): corrige un crash al ver el historial de un riesgo recién creado (arrays mezclados en `campos`) y hace que el área se autocomplete con la del usuario, igual que el responsable.
- **2026-07-03** — [Wizard de creación de riesgo](docs/updates/2026-07-03.md): impacto/probabilidad ahora se calculan de 5 preguntas guiadas por dimensión en vez de cargarse libremente; objetivo y plan de acción (si aplica) pasan a ser obligatorios para validar, no para crear.
- **2026-05-28** — [Brecha de autorización en actualizaciones](docs/updates/2026-05-28.md): un gerente podía validar/rechazar propuestas de otras gerencias. Corregido en `ActualizacionPolicy` y `GestionActualizaciones`, con 23 tests nuevos.
- **2026-05-27b** — [Modales de resumen en vistas show](docs/updates/2026-05-27b.md): los elementos relacionados abren un modal Alpine con datos y estado en vez de navegar directo, evitando 403 cuando el usuario no tiene permiso sobre el hijo.
- **2026-05-27** — [Visibilidad jerárquica por rol y estado](docs/updates/2026-05-27.md): reglas de qué ve cada rol (comité/gerente/empleado) según estado del elemento, con tests completos y corrección de una brecha de seguridad en creación de riesgos/planes.
- **2026-05-26** — [Roles, estados unificados y flujo de aprobación](docs/updates/2026-05-26.md): columna `rol` en usuarios, estados comunes a todas las entidades, historial de actualizaciones con validación por rol para proponer/aplicar cambios.
- **2026-05-22** — [Nomenclatura, criticidad y UX](docs/updates/2026-05-22.md): `criticidad_alta` → `mayor_criticidad`, nuevos atributos en objetivos, indicadores de vencimiento y mejoras varias de UX.
