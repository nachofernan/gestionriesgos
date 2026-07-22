# Sistema de Gestión de Auditoría

Aplicación web para la gestión del ciclo completo de auditoría de riesgos: identificación, control, objetivos estratégicos, planes de acción y seguimiento de tareas.

## Stack

- **Laravel 11** / PHP 8.2
- **Livewire 4** — componentes reactivos (búsqueda, gestión de relaciones)
- **Alpine.js** — interactividad liviana (modales, acordeones)
- **Tailwind CSS**
- **Spatie Media Library** — adjuntos en modelos
- **MySQL** (XAMPP en desarrollo)

## Flujo central

```
Riesgo ──── Tipo / Estado
  ├── (m:m) Controles       (pivot: mitigacion por riesgo)
  ├── (m:m) Objetivos
  └── (m:m) Planes de Acción
                └── (m:m) Tareas
                              └── (morphMany) Actualizaciones

Riesgo, Control, Objetivo, PlanAccion, Tarea
    └── (morphMany) Actualizaciones   (historial polimórfico)
```

Todos los modelos principales usan **SoftDeletes**. Las actualizaciones son polimórficas (`actualizable_type` / `actualizable_id`).

## Estructura del proyecto

```
app/
  Http/Controllers/Auditoria/   # Controladores MVC (CRUD + asociaciones)
  Livewire/Auditoria/           # Componentes Livewire (Search, Gestión, Modales)
  Models/Auditoria/             # Eloquent models
  Policies/Auditoria/           # Autorización por área jerárquica

resources/views/
  auditoria/                    # Vistas Blade MVC
  livewire/auditoria/           # Vistas de componentes Livewire

database/
  migrations/
  seeders/                      # EstadoRiesgoSeeder es crítico (observer lo requiere)
  factories/Auditoria/

tests/Feature/Auditoria/        # Tests de integración
docs/                           # Documentación del módulo y log de cambios
```

## Instalación

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
```

## Tests

```bash
php artisan test
# o un suite específico:
php artisan test tests/Feature/Auditoria/
```

## Autorización

Los permisos se basan en una jerarquía de áreas. Un gerente gestiona su área y todas las sub-áreas. El comité (`area_id = null`) puede operar en cualquier área. Las policies se encuentran en `app/Policies/Auditoria/`.

## Documentación

- [`docs/modulo-auditoria.md`](docs/modulo-auditoria.md) — arquitectura completa, modelos, rutas, componentes Livewire y tests.
- [`docs/DECISIONES.md`](docs/DECISIONES.md) — bitácora de decisiones de diseño y arquitectura, con el motivo de cada una.
- [`docs/CHANGELOG.md`](docs/CHANGELOG.md) — índice cronológico de cambios significativos.
- [`docs/updates/`](docs/updates/) — detalle de cada cambio por fecha.
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — trabajo pendiente.
- [`CLAUDE.md`](CLAUDE.md) — guía de convenciones y reglas de trabajo para el proyecto.
