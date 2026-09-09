# Reconstrucción — conclusión de la ejecución

Continuación de `docs/reconstruccion.md` (el análisis previo) y de las decisiones cerradas sobre cada
punto abierto. Este documento cubre **lo que pasó al construir y correr el seeder real**: qué se
resolvió tal cual se charló, qué bug apareció recién al ejecutar contra la base, y los números finales
verificados en la MySQL de XAMPP después de `migrate:fresh` + la carga.

No es un manual de uso: el código vive en `database/seeders/CargaInicial/` (`CargaInicialSeeder` como
orquestador), separado de los seeders de prueba existentes, que no se tocaron. Se corre con:

```
php artisan db:seed --class="Database\Seeders\CargaInicial\CargaInicialSeeder"
```

pensado para una base recién migrada — si se corre dos veces sobre la misma base sin `migrate:fresh`
en el medio, duplica filas (a propósito no se hizo idempotente para Riesgo/Control/Objetivo/PlanAccion/
Tarea, no tiene sentido para una carga que corre una sola vez).

---

## 1. Resolución de los puntos abiertos de `reconstruccion.md`

| Punto | Resolución aplicada |
|---|---|
| Coordinadores sin cuarta categoría de rol | Confirmado que **no era una inconsistencia real**: `User::puedeGestionarArea()` cascada autorización por árbol de áreas (`Area::esAncestroOIgual`), no por `rol`. Biasi/Aieta/Berri/Uva/Torres, cargados como `empleado` con su área de coordinación como home, ya gestionan su propio cluster de sub-áreas sin necesitar un rol nuevo. Se revisó el código (`app/Models/User.php`) para confirmarlo antes de descartar el punto. |
| `ALE` → `GAL` | Aplicado vía `MapeoCargaInicial::CORRECCIONES_CODIGO_AREA`. |
| `GAYFFZ` (Cobranzas) huérfana | Se creó igual, confirmado en la base: **0 riesgos** le apuntan. Queda sin actividad, tal como se anticipó. |
| Email/password `apellido@example.com` / `password` | Aplicado literal, igual que `AreaSeeder.php`. |
| Código de área (`codigo`) | No se agregó a `areas`. El mapeo quedó 100% interno en `MapeoCargaInicial::AREAS`. |
| Riesgo id 69, tipo "estrategico" | Reasignado a **Operacional** por indicación de auditoría. Confirmado en la base: `R-0069` (código real asignado) tiene `tipo_riesgo → Operacional`. |
| Riesgos sin impacto/probabilidad | Quedaron en 0/0. Son exactamente **8**: `R-0108` (COVID) y `R-0139` a `R-0145` (COVID + hallazgos de auditoría interna sin puntuar). |
| Riesgo id 25 (no existe en `Riesgos.csv`) | Confirmado: no aparece en ningún otro CSV (`AreaRiesgo`, `ControlRiesgo`, `ObjetivoRiesgo`, `PlanAccionRiesgo`) — hueco real del export, sin rastro en ningún lado. |
| Controles con `nombre == descripcion` | Se les vació la `descripcion`. Resultó ser el **100%** de los 173 controles (no "la mayoría" como se estimó antes de correrlo: los 173 tenían nombre y descripción idénticos). |
| Controles sin `mitigacion_default` | Quedaron en `0` explícito (no en el default de esquema, que es `1`). |
| Duplicados en `control_riesgo` | Se detectaron y descartaron **4** pares exactos repetidos (de 265 filas del CSV bajaron a 261 reales). |
| Planes de acción duplicados por fila | Confirmado el mecanismo que explicó el usuario: el Excel repite la fila del plan una vez por cada riesgo asociado. Se dedupicó por `id`, quedando los **62** planes reales; el vínculo real se tomó de `PlanAccionRiesgo.csv`. |
| `plan_accion_riesgo.mitigacion` | Los **82** pares quedaron en `0`. Confirmado el efecto: solo **9** planes de acción ya están al 100% de avance pero sin mitigación cargada — son los primeros candidatos a completar manualmente desde la UI. |
| Tareas sin avance / sin fecha | `porcentaje_avance = 0`, `fecha = null`. Sin acción adicional, el sistema lo permite. |
| Riesgos compartidos entre gerencias | Ninguno en esta carga — `area_riesgo` quedó poblado únicamente por el mecanismo automático de `Riesgo::booted()` (área propia + gerencia resuelta), no por datos del Excel. |

---

## 2. Bug encontrado al ejecutar (no visible en el análisis en papel)

**`PeisItemCargaInicialSeeder` colapsó los 10 PEIS reales en solo 4.** La primera versión reusaba el
patrón del seeder viejo (`updateOrCreate` por `nombre`), pero en el Excel el campo `nombre` es el
**Eje** (ej. "Eje I- OG: Mejorar la eficacia del SGAC"), que se repite en 2 o 3 filas que representan
objetivos estratégicos distintos dentro de ese eje (la `descripcion`, "OE 1", "OE 2", "OE 3"...). Al
crear por `nombre`, cada fila nueva pisaba la `descripcion` de la anterior con el mismo eje. Se detectó
recién al contar filas reales en la base (4 en vez de 10) — el CSV en papel no lo delataba tan claro.
Se corrigió a `create()` directo (cada fila del CSV es un `PeisItem` propio) y se volvió a correr todo
desde cero. Quedó anotado como recordatorio: **cuando el dato real no tiene una clave natural obvia,
no asumir que el patrón de un seeder de prueba aplica igual.**

---

## 3. Números finales (verificados contra la MySQL de XAMPP después de `migrate:fresh` + la carga)

| Tabla | Filas |
|---|---|
| `areas` | 29 (CR + 28 gerencias/sub-áreas — la cuenta de "29 + CR = 30" del documento anterior estaba mal: CR ya era una de las 29 filas de `UsuariosAreas.csv`, no una fila extra) |
| `users` | 18 (1 `comite`, 10 `gerente`, 7 `empleado`) |
| `tipos_riesgo` | 7 |
| `riesgos` | 160 (`R-0001`…`R-0160`) |
| `controles` | 173 |
| `control_riesgo` | 261 |
| `objetivos` | 78 |
| `objetivo_riesgo` | 42 (no 24 como se había estimado a ojo en el análisis en papel) |
| `peis_items` | 10 |
| `objetivo_peis_item` | 72 |
| `planes_accion` | 62 (`PA-0001`…`PA-0062`) |
| `plan_accion_riesgo` | 82 |
| `tareas` | 179 |
| `plan_accion_tarea` | 179 |
| `area_riesgo` (automático, vía `Riesgo::booted()`) | 266 |

Todas las cifras de pivots coinciden exactamente con la cantidad de filas de datos de cada CSV fuente,
salvo donde se documentó una razón puntual (duplicados descartados en `control_riesgo`, filas sin
`riesgo_id` omitidas en `objetivo_riesgo`, deduplicación de `planes_accion`).

**Efecto real sobre `valor_residual`**: de los 160 riesgos, solo **15** tienen hoy un `valor_residual`
menor a su `valor_total` — son los que tienen al menos un control con `mitigacion_default` real cargado
en el Excel (los controles a partir del id 81, área Comercio Exterior/Automotores/Auditoría
Interna/Organización y Métodos en adelante). Los otros 145 riesgos, aunque tengan controles y planes
asociados, no bajan su residual todavía: o el control no traía `mitigacion_default` (quedó en 0), o el
plan de acción no trae `mitigacion` (decisión de negocio, punto pendiente de carga manual). Esto no es
un error de la carga — es exactamente lo que se decidió: no inventar números que toquen el cálculo
sagrado.

---

## 4. Qué es esto y qué no es

Como dijo el usuario: **esto es un acercamiento**, no la versión final de los datos. Sirve como modelo
de cómo se cargan y qué forma tienen los datos reales de la empresa — de acá para adelante, auditoría,
los responsables de área y el negocio en general van a ir refinando esta base (completar mitigaciones
de planes, cargar impacto/probabilidad de los 8 riesgos que quedaron en 0/0, revisar los coordinadores
sin gerencia formal, etc.), y probablemente ese trabajo derive en una segunda vuelta de este mismo
proceso: nuevo Excel, nuevo análisis, ajustes al seeder.

Los seeders de prueba (`database/seeders/*.php`, fuera de `CargaInicial/`) siguen intactos y se pueden
seguir usando para tests — no fueron tocados en ningún momento de este trabajo.
