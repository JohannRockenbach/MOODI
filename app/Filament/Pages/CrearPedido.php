<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Punto de Venta Mozo — Crear Pedido Rápido (TPV de toma de comanda).
 *
 * El mozo arma la comanda desde un catálogo táctil y la envía a cocina
 * creando un Order real (status=pending, stock_deducted=false) con sus
 * ítems en order_product. Al ser de salón, la mesa se ocupa con la máquina
 * de estados Table::occupy() (que nunca pisa una mesa ya ocupada).
 */
class CrearPedido extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string $view = 'filament.pages.crear-pedido';

    protected static ?string $title = 'Crear Pedido';

    protected static ?string $navigationLabel = 'Crear Pedido';

    protected static ?string $navigationGroup = 'Operaciones del Salón';

    protected static ?int $navigationSort = 3;

    /**
     * Valores de modalidad EXACTOS que usa el sistema (columna orders.type).
     * Delivery queda fuera de este TPV (se gestiona desde OrderResource).
     */
    public const TYPE_SALON = 'salon';
    public const TYPE_TAKEAWAY = 'para_llevar';

    // Mesa seleccionada: si llega por ?table_id= (desde el Mapa de Mesas)
    // queda bloqueada y NO se puede cambiar ni pasar a Para Llevar.
    public ?int $selectedTableId = null;

    public bool $tableLockedFromMap = false;

    public string $orderType = self::TYPE_SALON;

    // Filtros del catálogo
    public string $search = '';

    public ?int $activeCategory = null; // null = Todos

    public bool $promoOnly = false; // Solo productos temporales (ofertas anti-desperdicio)

    // Carrito de la comanda: [product_id, name, price, qty, note]
    public array $items = [];

    // Nota general a cocina (orders.notes) y nota por ítem (modal)
    public string $kitchenNote = '';

    public ?int $editingItemIndex = null;

    public string $itemNote = '';

    // Modal "Cambiar Mesa"
    public bool $showTableModal = false;

    public ?int $newTableId = null;

    public function mount(): void
    {
        $tableId = (int) request()->query('table_id', 0);

        if ($tableId > 0) {
            $table = Table::where('restaurant_id', 1)->find($tableId);

            if ($table) {
                $this->selectedTableId = $table->id;
                $this->tableLockedFromMap = true; // 🔒 Bloqueada desde el mapa
                $this->orderType = self::TYPE_SALON;
            }
        }
    }

    public static function canAccess(): bool
    {
        // El TPV de toma de pedido es operativo de mozos: super_admin y Mozo.
        // Cajero y Cocinero NO toman comandas desde aquí.
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['super_admin', 'Mozo']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // CATÁLOGO
    // ─────────────────────────────────────────────────────────────

    /**
     * Categorías reales con productos disponibles (para el carrusel),
     * asignando emoji por convención de nombre. No existe campo emoji.
     */
    public function getCategoriesProperty(): Collection
    {
        $counts = Product::where('restaurant_id', 1)
            ->where('is_available', true)
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        if ($counts->isEmpty()) {
            return collect();
        }

        return Category::whereIn('id', $counts->keys())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'emoji' => self::categoryEmoji($category->name),
                'count' => (int) ($counts[$category->id] ?? 0),
            ])
            ->values();
    }

    /**
     * Productos disponibles del restaurante 1 según filtros activos.
     */
    public function getCatalogProductsProperty(): Collection
    {
        $query = Product::with('category')
            ->where('restaurant_id', 1)
            ->where('is_available', true);

        if ($this->promoOnly) {
            $query->where('is_temporal', true);
        }

        if ($this->activeCategory) {
            $query->where('category_id', $this->activeCategory);
        }

        $term = trim($this->search);

        if ($term !== '') {
            // PLU: búsqueda por código (#id) cuando el término empieza con #.
            if (preg_match('/^#(\d+)$/', $term, $matches)) {
                $query->where('id', (int) $matches[1]);
            } else {
                $query->where(fn ($q) => $q
                    ->where('name', 'ilike', "%{$term}%")
                    ->orWhere('description', 'ilike', "%{$term}%"));
            }
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Stock real por producto (evita N+1 en la vista: se calcula una vez).
     * Productos sin receta → stock almacenado; con receta → cuello de
     * botella de ingredientes (Product::realStock()).
     */
    public function getStockMapProperty(): array
    {
        return $this->catalogProducts
            ->mapWithKeys(fn (Product $product) => [$product->id => (int) $product->real_stock])
            ->all();
    }

    /**
     * Top 5 de ventas reales (order_product no cancelado) para el badge
     * "Top Ventas". Es dato calculado del sistema, no inventado.
     */
    public function getTopSellerIdsProperty(): array
    {
        return DB::table('order_product')
            ->join('orders', 'orders.id', '=', 'order_product.order_id')
            ->where('orders.restaurant_id', 1)
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_product.product_id, SUM(order_product.quantity) as sold')
            ->groupBy('order_product.product_id')
            ->orderByDesc('sold')
            ->limit(5)
            ->pluck('product_id')
            ->all();
    }

    public function getSelectedTableProperty(): ?Table
    {
        return $this->selectedTableId
            ? Table::where('restaurant_id', 1)->find($this->selectedTableId)
            : null;
    }

    /**
     * Mesas del restaurante 1 para el selector/modal (con etiqueta de estado).
     */
    public function getTablesProperty(): Collection
    {
        return Table::where('restaurant_id', 1)
            ->orderBy('number')
            ->get()
            ->map(fn (Table $table) => [
                'id' => $table->id,
                'number' => $table->number,
                'location' => $table->location,
                'status_label' => match ($table->status) {
                    Table::STATUS_OCCUPIED => 'Ocupada',
                    Table::STATUS_RESERVED => 'Reservada',
                    Table::STATUS_MAINTENANCE => 'Mantenimiento',
                    default => 'Disponible',
                },
            ]);
    }

    public function getTotalAvailableCountProperty(): int
    {
        return Product::where('restaurant_id', 1)->where('is_available', true)->count();
    }

    public function getMozoNameProperty(): string
    {
        return Auth::user()?->name ?? '—';
    }

    // ─────────────────────────────────────────────────────────────
    // FILTROS
    // ─────────────────────────────────────────────────────────────

    public function setCategory(?int $categoryId): void
    {
        $this->activeCategory = $categoryId;
    }

    public function togglePromo(): void
    {
        $this->promoOnly = ! $this->promoOnly;
    }

    // ─────────────────────────────────────────────────────────────
    // MESA
    // ─────────────────────────────────────────────────────────────

    public function selectTable(?int $tableId): void
    {
        if ($this->tableLockedFromMap) {
            return;
        }

        $this->selectedTableId = null;

        if ($tableId) {
            $table = Table::where('restaurant_id', 1)->find($tableId);
            $this->selectedTableId = $table?->id;
        }
    }

    public function openTableModal(): void
    {
        if ($this->tableLockedFromMap) {
            return;
        }

        $this->newTableId = $this->selectedTableId;
        $this->showTableModal = true;
    }

    public function confirmTableChange(): void
    {
        $this->selectTable($this->newTableId);
        $this->showTableModal = false;
    }

    public function setOrderType(string $type): void
    {
        if (! in_array($type, [self::TYPE_SALON, self::TYPE_TAKEAWAY], true)) {
            return;
        }

        // 🔒 Desde el mapa la modalidad queda fija en Salón.
        if ($this->tableLockedFromMap && $type === self::TYPE_TAKEAWAY) {
            return;
        }

        $this->orderType = $type;
    }

    // ─────────────────────────────────────────────────────────────
    // CARRITO
    // ─────────────────────────────────────────────────────────────

    public function getSubtotalProperty(): float
    {
        return (float) array_sum(array_map(
            fn (array $item) => (float) $item['price'] * (int) $item['qty'],
            $this->items
        ));
    }

    public function getItemCountProperty(): int
    {
        return (int) array_sum(array_map(fn (array $item) => (int) $item['qty'], $this->items));
    }

    /**
     * Total de la comanda. El sistema no calcula IVA por ítem (orders no
     * tiene columna de impuestos) → subtotal = total.
     */
    public function getTotalProperty(): float
    {
        return $this->subtotal;
    }

    public function addItem(int $productId): void
    {
        $product = Product::where('restaurant_id', 1)
            ->where('is_available', true)
            ->find($productId);

        if (! $product) {
            Notification::make()
                ->warning()
                ->title('Producto no disponible')
                ->body('El producto ya no está disponible para la venta.')
                ->send();

            return;
        }

        $stock = (int) $product->real_stock;

        if ($stock <= 0) {
            Notification::make()
                ->warning()
                ->title('Sin stock')
                ->body("**{$product->name}** está agotado. No se puede agregar a la comanda.")
                ->send();

            return;
        }

        // Si ya está en la comanda, sumar una unidad (máximo el stock real).
        foreach ($this->items as &$item) {
            if ((int) $item['product_id'] === $product->id) {
                if ((int) $item['qty'] < $stock) {
                    $item['qty']++;
                }

                return;
            }
        }
        unset($item);

        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'qty' => 1,
            'note' => '',
        ];
    }

    public function incrementQty(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $product = Product::find($this->items[$index]['product_id']);
        $stock = $product ? (int) $product->real_stock : 0;

        if ((int) $this->items[$index]['qty'] < max(1, $stock)) {
            $this->items[$index]['qty']++;
        }
    }

    public function decrementQty(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['qty']--;

        if ((int) $this->items[$index]['qty'] <= 0) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function removeItem(int $index): void
    {
        if (isset($this->items[$index])) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function clearCart(): void
    {
        $this->items = [];
        $this->kitchenNote = '';
        $this->editingItemIndex = null;
        $this->itemNote = '';
    }

    // Nota por ítem (reemplaza al personalizador de modificadores: no hay
    // lógica de modificadores con cargo en el sistema; la nota viaja a cocina).
    public function openNoteModal(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->editingItemIndex = $index;
        $this->itemNote = $this->items[$index]['note'] ?? '';
    }

    public function saveItemNote(): void
    {
        if ($this->editingItemIndex === null || ! isset($this->items[$this->editingItemIndex])) {
            return;
        }

        $this->items[$this->editingItemIndex]['note'] = trim($this->itemNote);
        $this->editingItemIndex = null;
        $this->itemNote = '';
    }

    public function cancelNoteModal(): void
    {
        $this->editingItemIndex = null;
        $this->itemNote = '';
    }

    // ─────────────────────────────────────────────────────────────
    // CREACIÓN DEL PEDIDO REAL
    // ─────────────────────────────────────────────────────────────

    public function submitOrder(): void
    {
        if (empty($this->items)) {
            $this->addError('items', 'Agregá al menos un producto a la comanda.');

            return;
        }

        if ($this->orderType === self::TYPE_SALON && ! $this->selectedTableId) {
            $this->addError('selectedTableId', 'Seleccioná una mesa para el pedido de salón.');

            return;
        }

        // Re-validar disponibilidad y stock real de cada ítem antes de crear.
        foreach ($this->items as $index => $item) {
            $product = Product::where('restaurant_id', 1)->find($item['product_id']);

            if (! $product || ! $product->is_available) {
                $this->addError("items.{$index}", "{$item['name']} ya no está disponible.");

                return;
            }

            if ((int) $product->real_stock < (int) $item['qty']) {
                $this->addError("items.{$index}", "Stock insuficiente para **{$item['name']}** (disponible: {$product->real_stock}).");

                return;
            }
        }

        try {
            $order = DB::transaction(function (): Order {
                $data = [
                    'restaurant_id' => 1,
                    'waiter_id' => Auth::id(),
                    'status' => 'pending',
                    'type' => $this->orderType,
                    'stock_deducted' => false, // El sistema descuenta FEFO al cobrar, no al tomar la comanda.
                    'notes' => trim($this->kitchenNote) !== '' ? trim($this->kitchenNote) : null,
                ];

                if ($this->orderType === self::TYPE_SALON) {
                    $data['table_id'] = $this->selectedTableId;
                }

                $order = Order::create($data);

                $order->orderProducts()->createMany(
                    array_map(
                        fn (array $item) => [
                            'product_id' => $item['product_id'],
                            'quantity' => $item['qty'],
                            'price' => $item['price'],
                            'notes' => trim($item['note'] ?? '') !== '' ? trim($item['note']) : null,
                        ],
                        $this->items
                    )
                );

                // Máquina de estados: solo avanza available/reserved → occupied.
                // Si la mesa ya está ocupada, el pedido se suma y no se pisa nada.
                if ($this->orderType === self::TYPE_SALON) {
                    $order->table?->occupy();
                }

                return $order;
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Error al crear el pedido')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Pedido enviado a cocina')
            ->body("Pedido **#{$order->id}** creado correctamente.")
            ->send();

        $this->clearCart();
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    public static function categoryEmoji(string $categoryName): string
    {
        $name = mb_strtolower($categoryName);

        return match (true) {
            str_contains($name, 'hamburg') => '🍔',
            str_contains($name, 'papa') => '🍟',
            str_contains($name, 'bebida'), str_contains($name, 'cerveza') => '🥤',
            str_contains($name, 'postre') => '🍰',
            str_contains($name, 'temporal') => '✨',
            str_contains($name, 'entrada'), str_contains($name, 'picada') => '🥗',
            default => '🍽️',
        };
    }

    public static function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}