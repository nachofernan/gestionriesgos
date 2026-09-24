# Decisiones — Módulo de Auditoría

Bitácora **append-only** de decisiones de diseño y arquitectura, con el motivo y lo que se descartó.
Es la respuesta al *por qué* de cómo está armado el sistema. El *qué* está en
[`modulo-auditoria.md`](modulo-auditoria.md); el *cuándo* cambió, en [`CHANGELOG.md`](CHANGELOG.md).

**Regla:** no se reescribe el pasado. Una decisión que se revierte no se edita ni se borra: se agrega
una entrada nueva que la reemplaza, referenciando la que cae. Cada entrada lleva un id `D-NNN`
estable.

Esta bitácora arranca el 2026-07-22 al reorganizar el proyecto. Las decisiones anteriores a esa fecha
se reconstruyeron desde `docs/updates/` y el historial de git; el detalle completo de cada una está
en su entrada de changelog enlazada. De acá en más, toda decisión de diseño se anota primero acá.

---

## D-001 — Marco de trabajo de tres roles + estructura de documentación (2026-07-22)

**Decisión.** El proyecto adopta el marco de trabajo del proyecto "Consultorio": tres roles
(`auditoria-mentor` asesor de solo lectura, `auditoria-senior`, `auditoria-junior`), con la sesión
principal trabajando por defecto en stance de mentor (se conversa antes de codear). El `CLAUDE.md`
se reescribe con principio cero, axiomas de arquitectura numerados y una sección de "cómo se
pregunta". El testing pasa a dosificarse por zona (núcleo sagrado vs periferia) y checkpoint. La
documentación de decisiones vive en este archivo; `CHANGELOG.md` se mueve a `docs/`.

**Motivo.** Es una forma de trabajo que al usuario le viene rindiendo en otro proyecto, sobre todo en
gestión de tokens (testear y reportar dosificado) y en separar "pensar" de "ejecutar".

**Descartado.** Migrar los `docs/updates/` a un `BITACORA.md` único: se dejan como archivo histórico
y siguen funcionando como el detalle por fecha que indexa el changelog.

---

## D-002 — La mitigación del residual sólo cuenta lo aprobado (2026-07-21)

**Decisión.** El `valor_residual` de un riesgo descuenta sólo la mitigación de los controles en
estado `aprobado` y de los planes de acción al 100% de avance; el avance de un plan promedia sólo sus
tareas aprobadas. Un control en borrador/validado no baja el valor; un plan incompleto no descuenta.

**Motivo.** El residual tiene que reflejar mitigación real y vigente, no intención. Un control sin
aprobar todavía no protege de nada.

Fuente: [changelog 2026-07-21 (ter)](updates/2026-07-21c.md).

---

## D-003 — Doble validación entre gerencias; se aplana el acceso por gerencia (2026-07-21)

**Decisión.** Se descarta la distinción propia/ajena (`gerencia_ajena`): todas las gerencias de un
riesgo pesan igual. Un borrador es siempre mono-gerencia; compartir un riesgo con otra gerencia sólo
se permite tras validarlo y sólo a un gerente. Un riesgo compartido entre dos o más gerencias exige
que cada propuesta de cambio la validen todas (`validacion_gerencia`); un solo rechazo la tumba. La
regla vive centralizada en `Riesgo::cambioRequiereDobleValidacion()`. El comité queda afuera de la
doble validación.

**Motivo.** Un riesgo que afecta a varias gerencias no puede cambiarse por decisión de una sola. La
regla anterior (propia/ajena) agregaba complejidad sin reflejar esa realidad.

**Descartado.** El flag `gerencia_ajena` y la asimetría de permisos entre la gerencia de origen y las
demás.

Fuente: [changelog 2026-07-21](updates/2026-07-21.md).

---

## D-004 — La actualización de un riesgo es sólo mensaje + adjunto (2026-07-21)

**Decisión.** `camposEditables()` devuelve `[]` para un riesgo: el modal "Nueva Actualización" queda
sólo con Mensaje + Adjuntos. Impacto y probabilidad no se editan desde ahí.

**Motivo.** Impacto y probabilidad los calcula el wizard; dejarlos editar a mano en el modal los
desincronizaba del cálculo. Nombre/descripción se resolverán con otro mecanismo.

Fuente: [changelog 2026-07-21 (bis)](updates/2026-07-21b.md).

---

## D-005 — Impacto y probabilidad se calculan por wizard, no a mano (2026-07-03)

**Decisión.** Impacto y probabilidad salen de 5 preguntas guiadas por dimensión, no de carga libre.
En la edición son de solo lectura; para cambiarlos hay que "Recalcular" repitiendo el wizard. Objetivo
y plan de acción (si la respuesta es mitigar) pasan a ser requisito de validación, no de creación.

**Motivo.** La calificación de un riesgo tiene que ser reproducible y fundamentada, no un número
puesto a criterio de quien carga.

Fuente: [changelog 2026-07-03](updates/2026-07-03.md) y [2026-07-03 (tris)](updates/2026-07-03c.md).

---

## D-006 — El comité no debería poder crear elementos (pendiente, 2026-07-17)

**Decisión (diferida).** Hoy el comité puede crear un elemento y al redirigir al show del borrador
recién creado se come un 403 (el `create()` de las policies da `true` porque el comité cuelga del área
raíz, pero `view()` le corta el borrador ajeno). Se decidió **no** arreglarlo por separado: se resuelve
junto con los permisos particulares cuando el módulo migre al sistema real.

**Motivo.** Un parche aislado ahora se pisaría con el rediseño de permisos que viene con la migración.

Fuente: [ROADMAP.md](ROADMAP.md), ítem de próximos pasos.

---

## D-007 — Panel de Riesgos: dashboard de lectura con sesgo gerencial (2026-07-23)

**Decisión.** El dashboard de situación (`PanelRiesgos`) es de **lectura**: resume y linkea a
Pendientes y Vencimientos, no las reimplementa ni actúa sobre ellas. Su recorte de visibilidad usa un
scope nuevo y reusable, `Riesgo::scopeDeCascadaArea` (gerente = su gerencia y sub-áreas; comité = todo),
que **no** trata lo aprobado/validado como público a toda la organización. El panel **nunca** muestra
borradores (ni de la propia gerencia) ni borrados; un toggle "ver solo aprobados" —**encendido por
defecto**— alterna entre sólo aprobados y aprobados + validados. El mapa de calor grafica el riesgo
**inherente** (impacto × probabilidad); el corrimiento por mitigación se muestra aparte, en dos rieles
de cubitos 0-20 (inherente vs residual), no en el mapa. La landing (`/`, `/dashboard`) pasa a redirigir
al panel.

**Motivo.** (1) El sesgo gerencial ya era el criterio acordado en Vencimientos; extraerlo a un scope
sobre `Riesgo` era el segundo consumidor que lo justificaba. (2) El residual no tiene coordenadas
propias en la grilla 2D —sólo baja la suma—, así que la comparación antes/después va sobre un eje 0-20,
no sobre el mapa (idea del propio usuario). (3) "Solo aprobados" por defecto porque la foto de gestión
por defecto es la matriz consolidada; ver los validados es un paso opcional. (4) Se descartó reusar el
`Dashboard` viejo (scaffolding que escribe sin `authorize()` ni `Auth::id()`): es inseguro y de otra
naturaleza; queda para retirar aparte.

Fuente: [changelog 2026-07-23](updates/2026-07-23.md).

---

## D-008 — Los agentes son fases del trabajo, no rangos; el núcleo no se delega (2026-08-04)

**Decisión.** Reemplaza a [D-001](#d-001--marco-de-trabajo-de-tres-roles--estructura-de-documentación-2026-07-22)
en lo que hace a los agentes. Los tres roles jerárquicos (mentor / senior / junior) se descartan y se
adopta el esquema de **fases**: `explorador` (Haiku, solo lectura — fan-out de búsqueda, devuelve la
conclusión con rutas, no el volcado), `ejecutor` (Sonnet — periferia con decisión ya tomada,
ex-`auditoria-junior`), `testeador` (Haiku — corre la suite y devuelve el veredicto destilado) y
`mentor` (Opus, solo lectura — la decisión pesada puntual, no el camino habitual). **Se elimina
`auditoria-senior`**: el trabajo que toca estructura o el núcleo sagrado vuelve al hilo principal, con
el usuario presente. Los nombres pierden el prefijo `auditoria-`.

De D-001 **sigue en pie** todo lo demás: principio cero, axiomas numerados, "cómo se pregunta",
testing dosificado por zona y checkpoint, y `DECISIONES.md` como bitácora append-only.

**Motivo.** Un subagente aislado arranca en frío: tiene que releer `CLAUDE.md`, los docs y mapear el
código antes de tocar nada, y no puede preguntar en vivo — cada ambigüedad es otra invocación que paga
el arranque de nuevo. Para trabajo de juicio eso cuesta el doble de contexto y produce menos que el
hilo principal, que ya lo tiene todo caliente. El subagente rinde donde el valor está en *filtrar
material crudo*: un `php artisan test` completo son miles de tokens de volcado verde que quedan en
contexto para siempre, y el fan-out de búsqueda otro tanto. De ahí que las dos incorporaciones sean
Haiku y de una sola función.

**Descartado.** (1) Dejar el senior como opción para tareas largas y mecánicas: si es mecánica la hace
el ejecutor, y si no lo es, se quiere al usuario presente. (2) Volver `DECISIONES.md` una referencia
viva reescribible, como en "Consultorio": el dominio de este proyecto *es* auditoría y el append-only
tiene sentido semántico; con 124 líneas todavía no pesa.

**Efecto en cascada anotado.** `CLAUDE.md`: se reescribe "Los modos de trabajo", el núcleo sagrado se
define una sola vez y ahí, `docs/updates/` deja de ser por sesión y pasa a ser sólo para el cambio que
mueve la arquitectura (el CHANGELOG queda como registro por defecto), los docblocks del núcleo pasan a
nombrar el test que los cubre, y se agrega una sección "Estado" para no tener que abrir ROADMAP y
`modulo-auditoria.md` en cada arranque.

---

## D-009 — El plan de acción es prerequisito del riesgo para mitigar, no al revés (2026-08-06)

**Decisión.** Un riesgo con respuesta "Reducir/Mitigar" no se valida con que **exista** un plan de
acción asociado: al menos uno de sus planes tiene que estar **validado** para poder validar el riesgo,
y al menos uno **aprobado** para poder aprobarlo (`Riesgo::motivosBloqueoValidacion()` /
`motivosBloqueoAprobacion()`, nuevo). Con varios planes asociados alcanza con que **uno** cumpla el
estado requerido — no exige que todos avancen juntos, igual que el residual descuenta la mitigación
plan por plan, no todo-o-nada. La cascada de validación (`ValidacionMasivaService`) reflejaba la
dirección opuesta: al validar/aprobar un Plan ofrecía sus Riesgos asociados como bloqueantes. Se
invierte: ahora es `analizarRiesgo()` quien ofrece el Plan como bloqueante (mismo trato que Objetivo,
no opcional como los Controles), y `analizarPlanAccion()` deja de depender del estado del Riesgo.

**Motivo.** No tiene sentido dar por válida la mitigación de un riesgo si el plan que la sostiene
sigue en borrador — un plan sin validar no es una mitigación creíble, es una promesa. La dirección
vieja de la cascada (Plan → exige Riesgo validado) no correspondía a ninguna regla de negocio
documentada; fue un bug de causalidad invertida (posible copy-paste del patrón objetivo→riesgo)
reportado por el usuario al notar que la pantalla de un plan le ofrecía aprobar riesgos.

**Descartado.** Exigir que **todos** los planes asociados estén en el estado requerido (no sólo uno):
más estricto, pero inconsistente con cómo ya se trata la mitigación real (aditiva por plan) y con el
mismo criterio ya usado para Objetivo (alcanza con uno válido).

**Efecto en cascada anotado.** `Riesgo.php`, `RiesgoController.php`, `ValidacionMasivaService.php`
(afecta también a Control/Objetivo/Tarea, que comparten el mismo servicio). Mismo criterio aplicado al
cálculo real del residual: `Riesgo::getValorResidualAttribute()` contaba un plan como mitigante con
sólo estar al 100% de avance (`estaCompleto()`), sin chequear su estado — un plan `validado` pero no
`aprobado` ya descontaba del residual, inconsistente con la regla nueva de arriba. Ahora exige también
`estado = aprobado`, igual que los controles. 13 tests nuevos en `PlanRequeridoParaMitigarTest`; se
actualizó un test de `RiesgoWizardTest` que codificaba la regla vieja.

**Adenda (mismo día).** Al probar el modal, el usuario detectó que la leyenda "Seleccioná al menos
uno" quedaba ambigua con dos familias de bloqueantes mezcladas (Objetivo y Plan), y que en los hechos
alcanzaba con dejar tildado el Plan para aprobar el riesgo sin tocar el Objetivo — el modal trataba
todos los bloqueantes como un pool único donde "cualquiera" cumplía el prerequisito, en vez de exigir
selección **por grupo**. Se corrigieron dos cosas: (1) `Riesgo::motivosBloqueoValidacion()` /
`motivosBloqueoAprobacion()` pasan a exigir también que el Objetivo esté validado/aprobado —no sólo
que exista—, simétrico a como ya quedó el Plan (cierra el hueco en la capa que de verdad protege, no
sólo en la UI); (2) `ValidacionCascadaModal::toggleSeleccion()` y `confirmar()` exigen al menos un
item seleccionado **por grupo** (`tipo`), y la vista separa la leyenda por grupo ("Objetivo: seleccioná
al menos uno" / "Plan de acción: seleccioná al menos uno"). 6 tests nuevos en
`CascadaGruposIndependientesTest`; 2 tests de `RiesgoWizardTest` actualizados (el objetivo de prueba
ahora necesita estado validado, no sólo existir).

Fuente: [changelog 2026-08-06](updates/2026-08-06.md).

---

## D-010 — El flag "Anticorrupción" de Objetivo pasa a ser "PEIS", con ítems obligatorios (2026-08-11)

**Decisión.** El boolean `anticorrupcion` de `Objetivo` se renombra a `peis` (Plan Estratégico de
Integridad Sostenible) — columna, modelo, controlador, Livewire y vistas, todo en el mismo golpe (sin
migración de rename aparte: se editó directo la migración original porque el proyecto está en etapa de
desarrollo verde, sin datos productivos que preservar). Cuando `peis` es true, el formulario de
creación/edición despliega un checklist obligatorio (mínimo 1) de un catálogo nuevo `PeisItem`
(tabla `peis_items` + pivot `objetivo_peis_item`, many-to-many con `Objetivo`), sembrado por
`PeisItemSeeder` con 5 ítems fijos (PEIS 1..5) y su descripción. La selección de ítems sólo es
editable mientras el objetivo está en borrador (create/edit); el flujo de "propuesta de cambio"
post-validación (`ActualizacionController::storeObjetivo`) sigue el campo escalar `peis` pero no
`peis_items`, porque ese controlador no maneja relaciones many-to-many para ningún campo todavía.

**Motivo.** Pedido directo del usuario: el concepto de negocio cambia de "anticorrupción" a PEIS, y
la nueva semántica exige que un objetivo PEIS declare qué ítems del plan cubre, no sólo el flag.

**Descartado.** (1) Dejar el nombre de columna/código como `anticorrupcion` y sólo cambiar la etiqueta
visible — se descartó por romper el axioma de nombrar el dominio en castellano de forma consistente
(el código quedaría hablando de un concepto que ya no existe). (2) Guardar los 5 ítems como lista
hardcodeada en PHP en vez de tabla catálogo — se descartó a favor de reusar el mismo patrón que
`Estado`/`TipoRiesgo` (catálogo con soft deletes), que permite editar la descripción de un ítem sin
deploy. (3) Extender ya mismo `ActualizacionController` para soportar sync de relaciones many-to-many
y así poder proponer cambios de `peis_items` post-validación — se dejó fuera de alcance porque sería el
primer caso de ese tipo en ese controlador y no estaba pedido; queda anotado como hueco conocido.

**Efecto en cascada anotado.** Migración `2024_01_01_000000_create_modulo_auditoria.php` (columna
renombrada + 2 tablas nuevas), `Objetivo.php` (fillable/casts/relación nueva), `PeisItem.php` (modelo
nuevo), `PeisItemSeeder` (nuevo, registrado en `DatabaseSeeder`), `ObjetivoController` (validación
condicional `required_if:peis,1` + sync del pivot en store/update, eager load en show/edit),
`ActualizacionController`, `GestionObjetivos.php` (Livewire), y las vistas
`objetivo/create|edit|show.blade.php`, `modal/detalle-objetivo.blade.php`,
`objetivo/index/search.blade.php`. Sin tests nuevos: no existía suite de `ObjetivoController` previa y
la regla de validación no es núcleo sagrado (no toca autorización, cálculo de riesgo ni ciclo de
estados) — la suite completa se corrió como checkpoint por tocar el modelo y el esquema base: 296
passed, 1 failed, y se confirmó (corriendo el mismo test contra el código previo al cambio) que la
falla es preexistente y no la causó este trabajo — ver nota en el changelog.

---

## D-011 — El vencimiento de un plan es la fecha pendiente más próxima, no la más lejana (2026-08-20)

**Decisión.** `PlanAccion` gana dos accessors: `vencimiento` (la fecha más próxima entre las tareas
todavía pendientes — `porcentaje_avance` < 100 — vigentes del plan, o `null` si no hay ninguna con
fecha) y `esta_vencido` (`true` si esa fecha ya pasó). Una tarea que llega al 100% deja de contar para
el vencimiento del plan aunque su fecha haya quedado en el pasado; una tarea en estado "borrado" nunca
cuenta. Los 5 lugares que mostraban esta información (show del plan, los dos listados de Planes y
Riesgos, y el modal de gestión de planes de un riesgo) pasan a consumir estos accessors en vez de
recalcular a mano.

**Motivo.** El cálculo anterior tomaba el `max(fecha)` entre *todas* las tareas del plan (vigentes,
sin filtrar por avance) y sólo evaluaba si esa fecha máxima había pasado. Eso significaba que un plan
con una tarea vencida no se marcaba como vencido si tenía otra tarea con fecha futura — la fecha
lejana "tapaba" a la vencida. El bug lo reportó el usuario: en el listado de tareas de un plan se veía
una tarea con el badge "Vencida" pero el plan en sí no se pintaba como vencido. La fecha que
efectivamente determina si el plan está atrasado es la del compromiso pendiente más próximo, no la del
más lejano.

**Descartado.** Agregar una columna `fecha_limite` propia al plan — sigue siendo la solución completa
para el aviso de inconsistencia "tarea vence después de su plan" que quedó pendiente en
[2026-07-17 (bis)](updates/2026-07-17b.md) y anotado en el ROADMAP, pero es un cambio de esquema más
grande y no lo pedía este bug. Este fix corrige la fórmula derivada dentro del esquema actual; el ítem
de ROADMAP sigue abierto.

Fuente: reporte directo del usuario en sesión, sin changelog largo asociado (no toca esquema,
autorización ni ciclo de estados).

---

## D-012 — "Evitar" deja de exigir fundamento (2026-09-15)

**Decisión.** `RespuestaRiesgo::exigenFundamento()` pasa de `[Compartir, Aceptar, Evitar]` a
`[Compartir, Aceptar]`. Un riesgo con respuesta "Evitar" ya no requiere justificar la elección con
texto en `fundamento`; se comporta igual que "Mitigar" en ese sentido.

**Motivo.** Decisión directa de negocio del usuario en sesión, sin justificación adicional registrada
más allá de que "Evitar" no debería forzar ese campo.

**Descartado.** Ninguna alternativa evaluada; es un cambio acotado de una línea en el enum, sin tocar
esquema (`fundamento` ya era `nullable`) ni el resto del ciclo de vida del riesgo.

Fuente: reporte directo del usuario en sesión, sin changelog largo asociado (no toca esquema,
autorización ni ciclo de estados).

---

## D-013 — `respuesta` pasa a ser obligatoria al crear/editar un riesgo (2026-09-15)

**Decisión.** `RiesgoController::reglaRespuesta()` cambia su regla de `nullable` a `required`, tanto en
`store()` como en `update()`. Ya no se puede crear ni editar (en borrador) un riesgo sin elegir una
`respuesta`. El esquema no cambia: la columna sigue `nullable` a nivel DB, sólo se endurece la
validación HTTP.

**Motivo.** El usuario notó que `respuesta` era opcional al crear un riesgo, y que además
`Riesgo::motivosBloqueoValidacion()`/`motivosBloqueoAprobacion()` no la exigían tampoco — un riesgo
podía llegar a `aprobado` con `respuesta = null`, saltándose en silencio todas las reglas que dependen
de ella (fundamento, el plan de acción obligatorio para "mitigar"). Se evaluó exigirla recién al
validar (mismo patrón que `objetivos`, ver comentario en `RiesgoController::store()`), pero se optó
por exigirla ya desde la creación: a diferencia de objetivos, `respuesta` es una decisión que el
usuario ya toma en el mismo paso 4 del wizard, no algo que se resuelve después.

**Cascada.** Rompía 6 tests que construían el payload del wizard sin `respuesta` esperando éxito:
5 en `RiesgoWizardTest` y 1 en `RiesgoAreaLineaTest` (ambos con un `datosWizard()` que ahora
default-ea `respuesta: mitigar`), más `RiesgoFundamentoTest::un_riesgo_sin_respuesta_no_exige_fundamento`,
reemplazado por `no_se_puede_crear_un_riesgo_sin_elegir_respuesta` (ya no existe el caso "sin
respuesta"). Los labels "Respuesta" en `create.blade.php`/`edit.blade.php` suman un asterisco estático,
igual que "Nombre".

**Descartado.** Exigir `respuesta` recién al salir de `borrador` (agregarla a
`motivosBloqueoValidacion()`), dejándola opcional en el form — el patrón que ya usa `objetivos`. Se
descartó porque el wizard ya la pide en el mismo paso que el resto de los datos de creación; no hay
razón de negocio para permitir un borrador sin respuesta todavía elegida.

Fuente: reporte directo del usuario en sesión, sin changelog largo asociado (toca el ciclo de estados
por el hueco en la validación, pero el cambio en sí es una regla HTTP de una línea).

---

## D-014 — Un riesgo se edita durante toda su vida; trazabilidad estructurada; mensajes sin ciclo (2026-09-22)

**Decisión.** Tres cambios relacionados sobre el ciclo de Actualizaciones, decididos juntos en la
misma charla:

1. **Reemplaza a D-004.** `camposEditables('riesgo')` deja de estar vacío: nombre, descripción,
   `respuesta`, `fundamento` y `tipo_riesgo_id` son editables vía el modal de Actualizaciones en
   cualquier estado del riesgo (incluido `aprobado`), no sólo en `borrador`. El wizard de
   impacto/probabilidad (`recalcular()`/`recalcularStore()`) también deja de estar duro a "sólo
   borrador": disponible en cualquier estado salvo `aprobado`, y nunca para el comité.
2. **El `estado` del riesgo nunca retrocede** por un cambio de campo posterior a su creación. Lo que
   entra al ciclo borrador→validado→aprobado es cada propuesta de cambio (`Actualizacion` tipo
   `'cambio'`), no el riesgo mismo. El estado inicial de una propuesta depende de quién la crea
   (`Actualizacion::estadoInicialParaCambio()`, ya existía como `estadoParaActualizacion()` privado en
   `GestionActualizaciones` para el resto de las entidades, ahora extraído y reusado también por
   `RiesgoController::recalcularStore()`): empleado arranca en borrador, gerente o comité saltean el
   borrador y arrancan validado, comité sobre una entidad ya aprobada arranca directo en aprobado. La
   doble validación de un riesgo multigerencia (D-003) no se toca: sigue mandando sin excepción por
   encima de esta regla.
3. **Trazabilidad estructurada de validar/aprobar/rechazar.** Se agregan columnas
   `validado_por_id`/`validado_en`, `aprobado_por_id`/`aprobado_en`, `rechazado_por_id`/`rechazado_en`
   tanto en `riesgos` (ciclo de vida propio del riesgo) como en `actualizaciones` (cada propuesta o
   mensaje individual). Antes lo único que existía era un nombre suelto en `data['activated_by']`
   (sin `user_id` ni timestamp), y `marcarRechazada()` ni siquiera recibía el usuario.
4. **Un mensaje puro (sin cambios de campo) no entra al ciclo de validación.** Se guarda con
   `estado_id = null` y `data = null`: no aparece en Pendientes, no ofrece validar/aprobar/rechazar,
   no bloquea otras operaciones por "propuesta pendiente". Antes `GestionActualizaciones::guardar()`
   igual le asignaba `data['tipo']='cambio'` y un estado por rol.

**Motivo.** El usuario anotó dos huecos ("Riesgo puede modificar su tipo de respuesta" y "las
actualizaciones de mensajes no se validan") que, charlados, resultaron ser la misma pregunta de fondo:
separar "dejar un mensaje" de "proponer un cambio", y dejar de tratar la edición de un riesgo como
algo limitado a su ventana de borrador. Además, no había forma de saber quién validó o aprobó algo más
allá de leer un mensaje en texto libre — un hueco real de auditoría en un sistema que se llama así.

**Descartado.** Que el `estado` del riesgo retroceda cuando alguien de menor jerarquía lo edita (la
formulación inicial del pedido); se descartó porque chocaba con el axioma de que un borrador es
siempre mono-gerencia (D-003) y porque el usuario aclaró en la charla que la baja de estado es de la
propuesta de cambio, no del riesgo.

Fuente: sesión 2026-09-22, sin changelog largo asociado — el detalle vive en los mensajes de commit de
cada uno de los 4 pasos (`feat(auditoria): trazabilidad estructurada...`, `fix(auditoria): un mensaje
sin cambios de campo...`, `feat(auditoria): riesgo editable durante todo su ciclo de vida...`,
`feat(auditoria): wizard de recalcular disponible fuera de borrador`).

---

## D-015 — Impacto y probabilidad también se editan vía Actualizaciones, no solo por el wizard (2026-09-23)

**Decisión.** El modal de Actualizaciones de un riesgo (`GestionActualizaciones::camposEditables('riesgo')`)
suma `impacto` y `probabilidad` a la lista de campos editables, con el mismo rango 0-10 que ya rige la
suma de las 5 preguntas del wizard (cada una 0-2, ver `camposNumericos()`). El ciclo es idéntico al de
cualquier otro campo: propuesta con diff antes/después, doble validación si el riesgo es multigerencia,
aplicación inmediata o pendiente según rol/estado, sin cambios en esa lógica. Actualiza el axioma 5 de
`CLAUDE.md` ("impacto y probabilidad no se cargan a mano"): deja de ser absoluto, pero conserva su
espíritu — el valor sigue siendo un entero 0-10 por dimensión, nunca texto libre ni fuera de rango.

**Motivo.** El wizard de recálculo obliga a repetir las 5 preguntas completas para corregir un solo
número mal cargado. Vía Actualizaciones se gana una corrección puntual con el mismo ciclo de
validación/auditoría que ya rige nombre/descripción/respuesta/fundamento/tipo de riesgo (D-014), sin la
fricción de rehacer todo el cuestionario.

**Descartado.** Un rango distinto a 0-10, o una validación separada de la de `camposNumericos()` — se
descartó por ser el mismo tope que ya rige en la creación y por mantener un solo lugar que gobierna
tanto el input como la regla de validación de todos los campos numéricos editables vía este modal.

**No cambia.** El wizard (`recalcular()`/`recalcularStore()`) sigue existiendo tal cual, para el alta y
para "recalcular" completo repitiendo las 5 preguntas. Esta vía es adicional, no un reemplazo.

Fuente: sesión 2026-09-23, con el usuario presente.

---

## D-016 — riesgo/show rediseñado: lo vigente arriba, lo propuesto abajo, una propuesta por elemento (2026-09-24)

**Estado.** En desarrollo en la rama `rediseno/riesgo-show`. Esta entrada registra lo que hay hasta
ahora; el diseño se acepta como base para replicarlo en el resto de las pantallas de detalle
(Control / Objetivo / Plan de Acción / Tarea). Lo que todavía no se revisó está en *Abierto*, al final.

**Decisión.**

1. **Pantalla.** Encabezado (código, estado, "Compartido", "Mayor criticidad", acciones de estado y
   eliminar en un menú), banner de propuestas pendientes (`ResumenPropuestas`), columna principal con
   la Ficha, los bloques Planes / Controles / Objetivos en pestañas y la Conversación, y un lateral
   fijo con Valor (`InfoRiesgo`), Gerencias y Actividad. Las pestañas son JS plano, no Alpine: un
   `x-data` alrededor de los componentes deja sus `wire:*` sin enganchar. Se ocultan con `hidden` y
   no con `@if`, así los tres siguen montados y escuchando eventos. Con un bloque en edición no se
   puede cambiar de pestaña.
2. **Lo vigente arriba, lo propuesto abajo.** Cada bloque (`x-auditoria.bloque-riesgo`) muestra lo
   que hoy forma parte del riesgo y, debajo y aparte, las propuestas que todavía no se aplicaron
   (`Actualizacion::scopePropuestasPendientes`). Cada propuesta se resuelve desde su tarjeta
   (`x-auditoria.propuesta-pendiente`): la tarjeta dispara `resolver-actualizacion`, que atiende
   `GestionActualizaciones`, y ahí se autoriza y se registra el voto. Las propuestas ya no se
   resuelven desde el historial. Mientras se edita, el bloque avisa qué va a pasar al guardar
   (`directo` / `propuesta` / `doble`, en `PropuestasEnBloque::modoCambio()`), con la misma regla que
   usa `guardar()`, para que el aviso y el guardado no se contradigan.
3. **Una propuesta por elemento** en Objetivos, Controles y Planes. Fuera del modo directo, cada alta,
   baja o cambio de mitigación es su propia `Actualizacion`, con una operación puntual (`agregar` /
   `detach` / `actualizar`) que aplica `Actualizacion::aplicarOperacionesPorElemento()`. En borrador
   y en el modo directo se sigue usando el `sync` del bloque.
4. **Una propuesta pendiente por elemento.** Un elemento de una relación, o un campo de la Ficha, que
   ya tiene una propuesta pendiente no se puede volver a proponer ni tocar en el bloque hasta que esa
   propuesta se resuelva.
5. **Ficha editable en el bloque.** Fuera de borrador, los datos propios del riesgo se editan en la
   Ficha, incluidos impacto y probabilidad (en línea con D-015). Se manda sólo lo que cambió, a
   `Actualizacion::registrarCambioCampos()`, lógica que antes estaba dentro de
   `GestionActualizaciones::guardar()` y ahora comparten los dos. En borrador se sigue editando con el
   formulario completo.
6. **Las notas se separan del historial.** `ConversacionRiesgo` junta los mensajes (notas sin ciclo,
   D-014, ahora creadas con `Actualizacion::registrarNota()`) y todos los archivos adjuntos del riesgo.
   La Actividad (`GestionActualizaciones` en variante `timeline`) muestra sólo el historial de
   cambios, de solo lectura. Su modal de detalle sí incluye las notas, filtrables. La variante
   `completa` queda como estaba para las otras pantallas.
7. **El comité puede rechazar una propuesta ya validada que espera su aprobación**
   (`ActualizacionPolicy::rechazar`). Es simétrico con `aprobar`: sobre lo que le llega, el comité
   aprueba o rechaza. En un riesgo compartido no deja voto en `validacion_gerencia`, porque no tiene
   gerencia. Toca autorización, así que lleva sus tests.
8. **Desglose del residual en el modelo.** Los accessors `mitigacion_controles` y `mitigacion_planes`
   separan lo que descuenta cada lado, y `valor_residual` se calcula a partir de ellos. La tarjeta
   Valor muestra el desglose sin recalcularlo (axioma 2).
9. **Reactividad.** Validar (aunque falten votos), rechazar y retirar una propuesta ahora disparan
   `riesgo-actualizado`, para que la tarjeta se redibuje. Los bloques de relaciones escuchan ese
   evento y se recargan, salvo que estén en edición, para no pisar lo que el usuario tiene a medio
   armar.

**Motivo.** Con la propuesta de bloque entero no se podía decidir elemento por elemento: un control
había que validarlo y otro no, pero la propuesta los llevaba juntos. Además, cambiar un elemento
arrastraba todo el bloque, y un cambio posterior no mostraba lo que ya se había propuesto antes. Poner
lo propuesto debajo de lo vigente, en el mismo bloque, hace visible en todo momento qué es el riesgo
hoy y qué está en discusión.

**Descartado.** Seguir con una sola propuesta por bloque (`sync` de la relación completa), por lo
dicho en *Motivo*.

**Abierto** (no decidido; se retoma en la rama):

- **Gerencias** sigue proponiéndose como bloque (`sync`), y todavía no se revisó cómo se comporta un
  riesgo multigerencia en el diseño nuevo.
- `GestionActualizaciones` escucha `resolver-actualizacion` a nivel global. Con dos instancias en la
  misma pantalla, la acción se ejecutaría dos veces. Hoy hay una sola por pantalla; al replicar el
  diseño hay que mantenerlo así o acotar el evento.
- `PropuestasEnBloque`, `bloque-riesgo` y `propuesta-pendiente` están escritos para Riesgo (namespace
  `Riesgo\Show\Concerns`, firmas con `Riesgo`, textos "del riesgo"). Si se generalizan o se copian
  para las otras entidades se decide al replicar.
- Las propuestas de bloque creadas antes del rediseño siguen apareciendo y aplicándose: la tarjeta
  lista todos sus elementos.

Fuente: sesión 2026-09-24. Detalle en [updates/2026-09-24.md](updates/2026-09-24.md).
