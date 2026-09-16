<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saneamiento de datos: `tables` quedó SIN primary key real (los constraints
 * originales de create_tables_table se perdieron en el estado dev), lo que
 * permitió insertar filas con el mismo `id`:
 *
 *   - id 2: mesas 11 (2025-11-19, original) y 36 (2026-09-15, nueva)
 *   - id 3: mesas 12 (2025-11-19, original) y 40 (2026-09-15, nueva)
 *   - id 4: mesas 13 (2025-11-19, original) y 5  (2026-09-15, nueva)
 *
 * Los pedidos/reservas con table_id IN (2,3,4) son TODOS anteriores a las
 * mesas duplicadas (fechas 2025-11 → 2026-06), por lo que pertenecen a las
 * mesas originales 11/12/13. NO se tocan las FKs: las nuevas 36/40/5 no
 * tienen historial. Esta migración:
 *
 *   1. Reasigna las filas duplicadas (las más nuevas) a ids NUEVOS únicos
 *      por encima de MAX(id) actual, sin tocar las filas existentes.
 *   2. Sincroniza la secuencia `tables_id_seq` al nuevo máximo.
 *   3. Agrega la PRIMARY KEY real sobre `id` si la tabla no la tiene.
 *   4. Restaura el unique (restaurant_id, number) declarado por la migración
 *      original si también se perdió.
 *
 * Es idempotente: sobre una BD fresh (tests) no hay duplicados y la PK/unique
 * ya existen, por lo que no hace nada.
 */
return new class extends Migration
{
    /**
     * Piso del rango de ids "corregidos" (MAX(id) legítimo antes del bug).
     * Usado en down() para detectar los ids reasignados por esta migración.
     */
    private const LEGACY_MAX_ID = 14;

    public function up(): void
    {
        // 1) Reasignar ids duplicados: cada grupo conserva la fila MÁS ANTIGUA
        //    (la original) y las filas más nuevas pasan a ids por encima del
        //    máximo actual, garantizando unicidad sin tocar filas existentes.
        $duplicates = DB::table('tables')
            ->select('id', 'number', 'created_at')
            ->orderBy('id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('id')
            ->filter(fn ($rows) => $rows->count() > 1);

        if ($duplicates->isNotEmpty()) {
            $nextId = ((int) DB::table('tables')->max('id')) + 1;

            foreach ($duplicates as $rows) {
                // La primera fila del grupo (created_at menor) conserva el id.
                $rows->skip(1)->each(function ($row) use (&$nextId) {
                    DB::table('tables')
                        ->where('id', $row->id)
                        ->where('number', $row->number)
                        ->update(['id' => $nextId]);

                    $nextId++;
                });
            }

            // 2) Secuencia al nuevo máximo (garantiza próximos inserts únicos).
            $this->syncSequence();
        }

        // 3) Garantizar PRIMARY KEY real sobre `id` si no existe.
        $hasPrimaryKey = collect(Schema::getIndexes('tables'))
            ->contains(fn (array $index) => ($index['primary'] ?? false) === true);

        if (! $hasPrimaryKey) {
            Schema::table('tables', function (Blueprint $table) {
                $table->primary('id');
            });

            // La PK llegó tarde: la secuencia pudo quedar por debajo del
            // máximo (ej: último id asignado a mano). Sincronizar siempre.
            $this->syncSequence();
        }

        // 4) Restaurar el unique (restaurant_id, number) de la tabla original
        //    si también se perdió (los números son únicos por restaurante).
        if (! Schema::hasIndex('tables', ['restaurant_id', 'number'])) {
            Schema::table('tables', function (Blueprint $table) {
                $table->unique(['restaurant_id', 'number']);
            });
        }
    }

    public function down(): void
    {
        // Revertir SOLO la reasignación de ids (si los destinos originales
        // quedaron libres). La PRIMARY KEY y el unique no se eliminan: son
        // garantías de integridad restauradas, no datos reversibles.
        //
        // Nota: como la reasignación es un saneamiento de datos, down() no es
        // un espejo perfecto de up(): si los ids 2/3/4 ya están ocupados por
        // otras filas, deja las mesas reasignadas donde están y sincroniza la
        // secuencia. El caso típico de rollback (migrate:rollback local) se
        // cubre porque la secuencia queda consistente con los datos.
        $mapping = [
            ['number' => '36', 'original_id' => 2],
            ['number' => '40', 'original_id' => 3],
            ['number' => '5', 'original_id' => 4],
        ];

        foreach ($mapping as $entry) {
            $moved = DB::table('tables')
                ->where('number', $entry['number'])
                ->where('id', '>', self::LEGACY_MAX_ID)
                ->first();

            $slotFree = ! DB::table('tables')->where('id', $entry['original_id'])->exists();

            if ($moved && $slotFree) {
                DB::table('tables')
                    ->where('id', $moved->id)
                    ->update(['id' => $entry['original_id']]);
            }
        }

        $this->syncSequence();
    }

    private function syncSequence(): void
    {
        DB::statement(
            "SELECT setval(pg_get_serial_sequence('tables', 'id'), (SELECT COALESCE(MAX(id), 1) FROM tables))"
        );
    }
};