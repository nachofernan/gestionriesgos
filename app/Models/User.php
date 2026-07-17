<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Auditoria\Area;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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
        'rol',
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

    public function esGerente(): bool
    {
        return $this->rol === 'gerente' || ! $this->area_id;
    }

    public function esEmpleado(): bool
    {
        return $this->rol === 'empleado';
    }

    public function esComite(): bool
    {
        return $this->rol === 'comite';
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
