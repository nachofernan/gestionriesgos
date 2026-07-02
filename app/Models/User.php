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
        return $this->rol === 'gerente' || !$this->area_id;
    }

    public function esEmpleado(): bool
    {
        return $this->rol === 'empleado';
    }

    public function esComite(): bool
    {
        return $this->rol === 'comite';
    }

    // Devuelve el área de nivel gerencia (hijo directo del área raíz).
    public function areaGerencia(): ?Area
    {
        if (!$this->area_id) return null;
        $area = $this->area;
        while ($area && $area->area_padre_id !== null) {
            $padre = Area::find($area->area_padre_id);
            if ($padre?->area_padre_id === null) return $area;
            $area = $padre;
        }
        return $area;
    }

    // El empleado puede ver elementos validados en toda su gerencia.
    public function puedeVerEnGerencia(mixed $areaId): bool
    {
        if (!$this->area_id || !$areaId) return true;
        $gerencia = $this->areaGerencia();
        return $gerencia?->esAncestroOIgual((int) $areaId) ?? false;
    }

    // El usuario puede gestionar una entidad del área dada.
    // Sin área propia = superusuario (acceso total).
    // Entidad sin área = cualquiera puede gestionarla.
    public function puedeGestionarArea(mixed $areaId): bool
    {
        if (!$this->area_id) {
            return true;
        }
        if (!$areaId) {
            return true;
        }

        return $this->area->esAncestroOIgual((int) $areaId);
    }
}
