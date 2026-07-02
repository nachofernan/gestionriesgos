<?php

namespace App\Providers;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Policies\Auditoria\ActualizacionPolicy;
use App\Policies\Auditoria\ControlPolicy;
use App\Policies\Auditoria\ObjetivoPolicy;
use App\Policies\Auditoria\PlanAccionPolicy;
use App\Policies\Auditoria\RiesgoPolicy;
use App\Policies\Auditoria\TareaPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;

class AppServiceProvider extends AuthServiceProvider
{
    protected $policies = [
        Riesgo::class        => RiesgoPolicy::class,
        Control::class       => ControlPolicy::class,
        Objetivo::class      => ObjetivoPolicy::class,
        PlanAccion::class    => PlanAccionPolicy::class,
        Tarea::class         => TareaPolicy::class,
        Actualizacion::class => ActualizacionPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
