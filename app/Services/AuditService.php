<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Registro central de auditoría de acciones críticas (TAREA 2.1 / OBJ-09).
 *
 * Es el ÚNICO punto que escribe en activity_log. FALLO SILENCIOSO a propósito
 * (regla del plan): si el insert falla (DB caída, tabla con problemas, etc.)
 * la operación de negocio ORIGINAL nunca se rompe — se loggea un warning.
 *
 * El insert corre en su propia transacción anidada (savepoint): si falla
 * dentro de una transacción del llamador, el savepoint limpia el estado
 * abortado de Postgres y la transacción del llamador sigue válida.
 */
class AuditService
{
    public static function record(string $action, ?Model $subject = null, array $context = [], ?User $user = null): void
    {
        try {
            $user ??= Auth::user();
            $request = request();

            DB::transaction(function () use ($action, $subject, $context, $user, $request): void {
                ActivityLog::create([
                    'user_id' => $user?->getKey(),
                    'action' => $action,
                    'subject_type' => $subject ? get_class($subject) : null,
                    'subject_id' => $subject?->getKey(),
                    'context' => $context === [] ? null : $context,
                    'ip_address' => $request?->ip(),
                    'created_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('AuditService: no se pudo registrar la acción de auditoría', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}