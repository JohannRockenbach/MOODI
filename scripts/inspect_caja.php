<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Caja;

$id = $argv[1] ?? null;
if (! $id) {
    echo "Usage: php scripts/inspect_caja.php <id>\n";
    exit(1);
}

$c = Caja::find($id);
if (! $c) {
    echo "Caja not found\n";
    exit(1);
}

echo "id: " . $c->id . PHP_EOL;
echo "initial_balance (raw): "; var_export($c->getAttributes()['initial_balance']); echo PHP_EOL;
echo "initial_balance (accessor): "; var_export($c->initial_balance); echo PHP_EOL;
echo "final_balance (raw): "; var_export($c->getAttributes()['final_balance']); echo PHP_EOL;
echo "final_balance (accessor): "; var_export($c->final_balance); echo PHP_EOL;
echo "total_sales (raw): "; var_export($c->getAttributes()['total_sales']); echo PHP_EOL;
echo "total_sales (accessor): "; var_export($c->total_sales); echo PHP_EOL;
echo "sales sum: "; var_export($c->sales()->sum('total_amount')); echo PHP_EOL;
