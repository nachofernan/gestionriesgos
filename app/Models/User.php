<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Auditoria\Area;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'area_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Los roles viven en spatie (D-052) y un usuario puede tener más de uno
     * (gerente + comité). Sin área propia (superusuario) cuenta como gerente.
     */
    public function esGerente(): bool
    {
        return $this->hasRole('gerente') || ! $this->area_id;
    }

    public function esEmpleado(): bool
    {
        return $this->hasRole('empleado');
    }

    public function esComite(): bool
    {
        return $this->hasRole('comite');
    }

    /**
     * Comité sin rol de gerente: el que sólo ve lo público de las áreas ajenas.
     * Quien tiene los dos roles (D-052) ve además los borradores de su subárbol.
     * Test: con_doble_rol_ve_lo_publico_y_sus_borradores_pero_no_borradores_ajenos.
     */
    public function esSoloComite(): bool
    {
        return $this->esComite() && ! $this->hasRole('gerente');
    }

    /**
     * Alcance para operar sobre una entidad del área dada (update, delete,
     * aprobar): la jerarquía de puedeGestionarArea() o, con rol de comité,
     * cualquier área (D-052). Validar, crear y rechazar borradores siguen atados
     * a la jerarquía: el rol de comité no agranda lo que se hace como gerente.
     * Tests: con_doble_rol_aprueba_lo_validado_de_otra_gerencia,
     * con_doble_rol_no_valida_un_borrador_de_otra_gerencia.
     */
    public function puedeOperarEnArea(mixed $areaId): bool
    {
        return $this->esComite() || $this->puedeGestionarArea($areaId);
    }

    /**
     * Si $user actúa como gerente sobre algo del área dada: tiene el rol y el
     * área cae en su jerarquía. Separa lo que hace como gerente de lo que hace
     * como comité (que no recalcula ni valora, D-046) cuando tiene los dos roles.
     */
    public function actuaComoGerenteEn(mixed $areaId): bool
    {
        // hasRole y no esGerente(): un comité sin área no es superusuario-gerente acá.
        return $this->hasRole('gerente') && $this->puedeGestionarArea($areaId);
    }

    /**
     * Auditor (D-020): ve como un empleado de su área, pero puede proponer
     * cambios sobre cualquier elemento validado o aprobado (ver proponer() en
     * las Policies). Sus propuestas nacen en borrador y no deja voto de gerencia.
     */
    public function esAuditor(): bool
    {
        return $this->hasRole('auditor');
    }

    /**
     * Regla común del proponer() de las Policies: el auditor propone sobre lo
     * validado o aprobado de cualquier área, nunca sobre un borrador ajeno.
     * Tests: el_auditor_puede_proponer_sobre_lo_validado_y_aprobado_de_otra_gerencia,
     * el_auditor_no_puede_proponer_sobre_un_borrador_de_otra_gerencia.
     */
    public function puedeProponerComoAuditor(?string $estadoEntidad): bool
    {
        return $this->esAuditor() && in_array($estadoEntidad, ['validado', 'aprobado']);
    }

    /**
     * Gerencia del usuario: la primera área marcada como tipo Gerencia subiendo
     * desde su área propia (ver Area::gerencia()). Sin área propia devuelve null.
     */
    public function areaGerencia(): ?Area
    {
        return $this->area?->gerencia();
    }

    /**
     * IDs de las áreas que el usuario puede elegir al asignar un área a una
     * entidad: la propia y sus descendientes, nunca hermanas ni primas. Es la
     * misma definición que aplica puedeGestionarArea() uno a uno; se usa para
     * armar los selects de área y validarlos (ver RiesgoController::create/store).
     * Sin área propia (superusuario), todas.
     */
    public function idsAreasGestionables(): array
    {
        if (! $this->area_id) {
            return Area::pluck('id')->all();
        }

        return $this->area->obtenerIdsSubarbol();
    }

    // El usuario puede gestionar una entidad del área dada.
    // Sin área propia = superusuario (acceso total).
    // Entidad sin área = cualquiera puede gestionarla.
    public function puedeGestionarArea(mixed $areaId): bool
    {
        if (! $this->area_id) {
            return true;
        }
        if (! $areaId) {
            return true;
        }

        return $this->area->esAncestroOIgual((int) $areaId);
    }
}
