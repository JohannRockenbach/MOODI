<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasCobroRapido;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

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
    use HasCobroRapido;

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

    // Secciones del catálogo: el dueño distingue SOLO Comida y Bebidas
    // (pestañas). Las SECCIONES por categoría (agrupado del catálogo y del
    // ticket) se derivan del nombre de categoría normalizado; orden fijo:
    // Bebidas → Papas → Hamburguesas → Entradas → Postres → Otros.
    public const SECTION_TODO = 'todo';
    public const SECTION_FOOD = 'comida';
    public const SECTION_DRINKS = 'bebidas';

    public const SECTION_BEBIDAS = 'bebidas';
    public const SECTION_PAPAS = 'papas';
    public const SECTION_HAMBURGUESAS = 'hamburguesas';
    public const SECTION_ENTRADAS = 'entradas';
    public const SECTION_POSTRES = 'postres';
    public const SECTION_OTROS = 'otros';

    /**
     * Orden fijo de secciones derivadas de la categoría real del producto.
     * Se usa tanto para agrupar el catálogo como el ticket de la comanda.
     */
    public const CATALOG_SECTIONS = [
        ['key' => 'bebidas', 'label' => '🥤 Bebidas'],
        ['key' => 'papas', 'label' => '🍟 Papas'],
        ['key' => 'hamburguesas', 'label' => '🍔 Hamburguesas'],
        ['key' => 'entradas', 'label' => '🥗 Entradas y Picadas'],
        ['key' => 'postres', 'label' => '🍰 Postres'],
        ['key' => 'otros', 'label' => '🍽️ Otros'],
    ];

    // Mesa seleccionada: si llega por ?table_id= (desde el Mapa de Mesas)
    // queda bloqueada y NO se puede cambiar ni pasar a Para Llevar.
    public ?int $selectedTableId = null;

    public bool $tableLockedFromMap = false;

    public string $orderType = self::TYPE_SALON;

    // Filtros del catálogo
    public string $search = '';

    public string $activeSection = self::SECTION_TODO; // 'todo' | 'comida' | 'bebidas'

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
     * Contadores de productos disponibles por sección (Comida | Bebidas | Todo).
     * Reemplaza al carrusel de categorías: el dueño distingue SOLO dos grupos.
     */
    public function getSectionCountsProperty(): array
    {
        $available = Product::where('restaurant_id', 1)->where('is_available', true);

        return [
            self::SECTION_TODO => (clone $available)->count(),
            self::SECTION_FOOD => (clone $available)->whereDoesntHave('category', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('name', 'ilike', '%bebida%')
                ->orWhere('name', 'ilike', '%cerveza%')))->count(),
            self::SECTION_DRINKS => (clone $available)->whereHas('category', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('name', 'ilike', '%bebida%')
                ->orWhere('name', 'ilike', '%cerveza%')))->count(),
        ];
    }

    /**
     * Productos disponibles del restaurante 1 según sección y filtros activos.
     * El mapeo se hace por nombre de categoría normalizado: contiene
     * 'bebida'/'cerveza' → Bebidas; el resto → Comida.
     */
    public function getCatalogProductsProperty(): Collection
    {
        $query = Product::with('category')
            ->where('restaurant_id', 1)
            ->where('is_available', true);

        if ($this->activeSection !== self::SECTION_TODO) {
            $isDrink = fn ($q) => $q->where(fn ($qq) => $qq
                ->where('name', 'ilike', '%bebida%')
                ->orWhere('name', 'ilike', '%cerveza%'));

            if ($this->activeSection === self::SECTION_DRINKS) {
                $query->whereHas('category', $isDrink);
            } else {
                $query->whereDoesntHave('category', $isDrink);
            }
        }

        if ($this->promoOnly) {
            $query->where('is_temporal', true);
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
     * Productos del catálogo (YA filtrados por sección activa/búsqueda/promo)
     * agrupados en las secciones derivadas de su categoría real.
     *
     * Devuelve, en el orden fijo de CATALOG_SECTIONS y omitiendo las vacías:
     * [['key' => 'bebidas', 'label' => '🥤 Bebidas', 'count' => N, 'products' => Collection<Product>], ...].
     * La pestaña activa (Todos/Comida/Bebidas) sigue actuando como filtro
     * global ANTES del agrupado: con "Bebidas" solo queda la sección bebidas,
     * con "Comida" las secciones no-bebida y con "Todos" todas.
     */
    public function getCatalogSectionsProperty(): Collection
    {
        $products = $this->catalogProducts;

        return collect(self::CATALOG_SECTIONS)
            ->map(function (array $section) use ($products): ?array {
                $sectionProducts = $products
                    ->filter(fn (Product $product) => self::sectionKeyFor($product->category?->name ?? '') === $section['key'])
                    ->values();

                if ($sectionProducts->isEmpty()) {
                    return null;
                }

                return [
                    'key' => $section['key'],
                    'label' => $section['label'],
                    'count' => $sectionProducts->count(),
                    'products' => $sectionProducts,
                ];
            })
            ->filter()
            ->values();
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

    public function setSection(string $section): void
    {
        if (! in_array($section, [self::SECTION_TODO, self::SECTION_FOOD, self::SECTION_DRINKS], true)) {
            return;
        }

        $this->activeSection = $section;
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
            // Sección derivada de la categoría real: se guarda en el ítem para
            // agrupar el ticket por sección sin consultas extra.
            'section_key' => self::sectionKeyFor($product->category?->name ?? ''),
            'section_label' => self::sectionLabelFor(self::sectionKeyFor($product->category?->name ?? '')),
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
    // COBRO RÁPIDO (trait HasCobroRapido — modal en la misma página, F2)
    // ─────────────────────────────────────────────────────────────

    /**
     * El cobro terminó con éxito: la comanda en curso ya se cobró (y se creó
     * el Order correspondiente) → limpiar el carrito para no duplicar nada en
     * la próxima comanda. El modal vive en la MISMA página (trait): el evento
     * 'comanda-cobrada' lo dispara el trait tras cobrar.
     */
    #[On('comanda-cobrada')]
    public function onComandaCobrada(): void
    {
        $this->clearCart();
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Sección derivada del nombre de categoría normalizado (misma normativa
     * que categoryEmoji). Producto SIN categoría → 'otros'.
     */
    public static function sectionKeyFor(string $categoryName): string
    {
        $name = mb_strtolower($categoryName);

        return match (true) {
            str_contains($name, 'bebida'), str_contains($name, 'cerveza') => 'bebidas',
            str_contains($name, 'papa'), str_contains($name, 'frita'), str_contains($name, 'fritas') => 'papas',
            str_contains($name, 'hamburg'), str_contains($name, 'smash') => 'hamburguesas',
            str_contains($name, 'entrada'), str_contains($name, 'picada') => 'entradas',
            str_contains($name, 'postre') => 'postres',
            default => 'otros',
        };
    }

    public static function sectionLabelFor(string $sectionKey): string
    {
        foreach (self::CATALOG_SECTIONS as $section) {
            if ($section['key'] === $sectionKey) {
                return $section['label'];
            }
        }

        return '🍽️ Otros';
    }

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