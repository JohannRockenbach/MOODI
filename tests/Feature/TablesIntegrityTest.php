<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Integridad del saneamiento de IDs duplicados en `tables`.
 *
 * La BD dev llegó con filas duplicadas (misma `id` para dos mesas) porque
 * la tabla NO tenía primary key real: (2=11 y 36), (3=12 y 40), (4=13 y 5).
 * La migración fix_duplicate_ids_in_tables_table reasigna las filas nuevas,
 * sincroniza la secuencia y restaura la PK. Estos tests verifican que tras
 * RefreshDatabase la integridad queda garantizada.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Restaurant::factory()->create(['id' => 1]);
});

it('deja ids únicos y PK real en tables tras ejecutar la migración de saneamiento', function () {
    // RefreshDatabase corrió TOdas las migraciones (incluida la de saneamiento).
    // 1) El id es columna con PK real (antes NO existía ninguna constraint).
    expect(Schema::hasColumn('tables', 'id'))->toBeTrue();

    $primary = collect(Schema::getIndexes('tables'))
        ->firstWhere(fn (array $index) => ($index['primary'] ?? false) === true);

    expect($primary)->not->toBeNull()
        ->and($primary['columns'])->toContain('id');

    // 2) Las mesas creadas después de la migración tienen ids únicos.
    Table::factory()->create(['number' => 1, 'restaurant_id' => 1]);
    Table::factory()->create(['number' => 2, 'restaurant_id' => 1]);
    Table::factory()->create(['number' => 3, 'restaurant_id' => 1]);

    $ids = Table::pluck('id')->all();

    expect(count($ids))->toBe(count(array_unique($ids)));
});

it('impide reinsertar un id duplicado gracias a la PK restaurada', function () {
    $table = Table::factory()->create(['number' => 1, 'restaurant_id' => 1]);

    expect(fn () => Table::factory()->create(['id' => $table->id, 'number' => 999, 'restaurant_id' => 1]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});