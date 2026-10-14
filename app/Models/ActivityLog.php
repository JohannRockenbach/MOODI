<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría de acciones críticas (TAREA 2.1).
 *
 * Solo lectura: lo escribe AuditService (fallo silencioso) y lo consulta la
 * página Auditoria (solo super_admin). Nunca debe editarse ni borrarse.
 */
class ActivityLog extends Model
{
    // Solo created_at: no hay updated_at para un registro inmutable.
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'context',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}