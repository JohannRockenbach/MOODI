<div>
    @if ($open)
        {{-- Overlay del modal de cobro (estilo Stitch del POS, tokens de crear-pedido).
             Include compartido: CrearPedido y TableMap usan el trait HasCobroRapido
             (misma página, SIN dispatch entre componentes). --}}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm dark:bg-[#0b0e15]/80"
            wire:click.self="cancelar"
        >
            <div class="flex max-h-[92vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-2xl dark:bg-[#1c2026]">

                {{-- ══════════════ HEADER DEL MODAL ══════════════ --}}
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-[#272a31]">
                    <div class="flex min-w-0 items-center gap-4">
                        @if (($this->account['is_takeaway'] ?? false))
                            <span class="shrink-0 rounded-xl bg-amber-500 px-5 py-3 font-mono text-2xl font-black text-white shadow-md shadow-amber-500/20 dark:bg-[#fbbc48] dark:text-[#422c00]">🥡</span>
                            <div class="min-w-0">
                                <div class="text-2xl font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">{{ $this->account['display_label'] ?? 'Para llevar' }}</div>
                                <div class="mt-0.5 text-base text-slate-500 dark:text-[#c1c6d5]">
                                    {{ ($this->account['has_draft'] ?? false) ? 'Comanda en curso — sin enviar a cocina' : 'Pedido para llevar' }}
                                    @if ($this->account['has_draft'] ?? false)
                                        <span class="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-3 py-0.5 text-base font-bold text-amber-700 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48]">
                                            <x-heroicon-o-bolt class="h-4 w-4" /> EN CURSO
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @elseif ($this->account)
                            <span class="shrink-0 rounded-xl bg-amber-500 px-5 py-3 font-mono text-2xl font-black text-white shadow-md shadow-amber-500/20 dark:bg-[#fbbc48] dark:text-[#422c00]">#{{ $this->account['number'] }}</span>
                            <div class="min-w-0">
                                <div class="text-2xl font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">{{ $this->account['zone_label'] }} · {{ $this->account['location'] }}</div>
                                <div class="mt-0.5 flex items-center gap-2 text-base text-slate-500 dark:text-[#c1c6d5]">
                                    <span>{{ $this->account['status_label'] }} · Mesa {{ $this->account['number'] }}</span>
                                    @if ($this->account['has_draft'] ?? false)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-3 py-0.5 text-base font-bold text-amber-700 dark:bg-[#fbbc48]/15 dark:text-[#fbbc48]">
                                            <x-heroicon-o-bolt class="h-4 w-4" /> COMANDA EN CURSO
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="shrink-0 rounded-xl bg-amber-500 px-5 py-3 text-2xl font-black text-white shadow-md shadow-amber-500/20 dark:bg-[#fbbc48] dark:text-[#422c00]"><x-heroicon-o-banknotes class="h-8 w-8" /></div>
                            <div class="min-w-0">
                                <div class="text-2xl font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">Cobrar Cuenta</div>
                                <div class="mt-0.5 text-base text-slate-500 dark:text-[#c1c6d5]">Elegí la cuenta a cobrar</div>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        @if ($this->account)
                            <div class="hidden items-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 dark:bg-[#0b0e15] md:flex">
                                <x-heroicon-o-user-circle class="h-5 w-5 text-slate-500 dark:text-[#c1c6d5]" />
                                <span class="text-base font-bold text-slate-900 dark:text-[#e0e2ec]">Mozo: <span class="font-semibold text-amber-600 dark:text-[#fbbc48]">{{ $this->account['waiter_name'] ?? '—' }}</span></span>
                            </div>
                            <span class="rounded-full bg-slate-100 px-4 py-1.5 font-mono text-base font-bold text-slate-600 dark:bg-[#0b0e15] dark:text-[#c1c6d5]">⏱ {{ $this->account['elapsed'] ?? '—' }}</span>
                        @endif
                        <button
                            wire:click="cancelar"
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]"
                            title="Cerrar (Esc)"
                        >
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {{-- ══════════════ SELECTOR INTERNO DE CUENTAS (sin selección) ══════════════ --}}
                @if (! $this->account)
                    <div class="flex flex-col gap-5 overflow-y-auto px-6 py-6 [scrollbar-width:thin]">
                        @if ($this->chargeableTables->isNotEmpty() || $this->takeawayOrders->isNotEmpty())
                            <div class="flex items-center gap-2.5 rounded-xl bg-slate-100 px-4 py-3 dark:bg-[#181c22]">
                                <x-heroicon-o-hand-raised class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                                <span class="text-base font-bold uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">Elegí la cuenta a cobrar</span>
                            </div>

                            @if ($this->chargeableTables->isNotEmpty())
                                <div>
                                    <h3 class="mb-3 flex items-center gap-2 text-lg font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">
                                        <x-heroicon-o-table-cells class="h-5 w-5 text-amber-600 dark:text-[#fbbc48]" />
                                        Mesas con cuenta abierta
                                        <span class="rounded-full bg-amber-500/10 px-3 py-1 font-mono text-base font-bold text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">{{ $this->chargeableTables->count() }}</span>
                                    </h3>
                                    <div class="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-5">
                                        @foreach ($this->chargeableTables as $table)
                                            <button
                                                wire:key="ct-{{ $table['id'] }}"
                                                wire:click="selectAccountTable({{ $table['id'] }})"
                                                class="flex flex-col items-center gap-2 rounded-2xl border-2 border-transparent bg-slate-50 px-3 py-6 text-center transition-all hover:border-amber-500 hover:bg-slate-100 active:scale-[0.97] dark:bg-[#181c22] dark:hover:border-[#fbbc48] dark:hover:bg-[#272a31]"
                                            >
                                                <span class="font-mono text-5xl font-black leading-none text-slate-900 dark:text-[#e0e2ec]">{{ $table['number'] }}</span>
                                                <span class="text-lg font-semibold text-slate-500 dark:text-[#c1c6d5]">{{ $table['zone_label'] }}</span>
                                                <span class="flex items-center gap-1.5 rounded-full bg-amber-500/15 px-3 py-1 text-base font-bold text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">
                                                    <x-heroicon-o-shopping-bag class="h-4 w-4" />
                                                    {{ $table['orders_count'] }} {{ $table['orders_count'] === 1 ? 'comanda' : 'comandas' }}
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($this->takeawayOrders->isNotEmpty())
                                <div>
                                    <h3 class="mb-3 flex items-center gap-2 text-lg font-black uppercase tracking-wide text-slate-900 dark:text-[#e0e2ec]">
                                        <span class="text-2xl leading-none">🥡</span> Pedidos para llevar
                                        <span class="rounded-full bg-amber-500/10 px-3 py-1 font-mono text-base font-bold text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">{{ $this->takeawayOrders->count() }} {{ $this->takeawayOrders->count() === 1 ? 'pendiente' : 'pendientes' }}</span>
                                    </h3>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        @foreach ($this->takeawayOrders as $order)
                                            <button
                                                wire:key="tk-{{ $order['id'] }}"
                                                wire:click="selectAccountOrder({{ $order['id'] }})"
                                                class="flex items-center gap-3 rounded-2xl border-2 border-transparent bg-slate-50 p-4 text-left transition-all hover:border-amber-500 hover:bg-slate-100 active:scale-[0.97] dark:bg-[#181c22] dark:hover:border-[#fbbc48] dark:hover:bg-[#272a31]"
                                            >
                                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 font-mono text-xl font-black text-amber-700 dark:bg-[#fbbc48]/10 dark:text-[#fbbc48]">#{{ $order['id'] }}</span>
                                                <span class="min-w-0 flex-grow">
                                                    <span class="block truncate text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">Mozo: {{ $order['waiter_name'] ?? '—' }}</span>
                                                    <span class="mt-0.5 block text-sm text-slate-500 dark:text-[#c1c6d5]">{{ $order['items_count'] }} {{ $order['items_count'] === 1 ? 'ítem' : 'ítems' }} · {{ $order['created_time'] }} hs</span>
                                                </span>
                                                <span class="shrink-0 font-mono text-xl font-black text-amber-600 dark:text-[#fbbc48]">${{ $this->money($order['subtotal']) }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center dark:border-[#414753]">
                                <x-heroicon-o-check-circle class="mx-auto mb-3 h-14 w-14 text-emerald-500" />
                                <p class="text-xl font-bold text-slate-700 dark:text-[#e0e2ec]">No hay cuentas pendientes</p>
                                <p class="mt-1 text-base text-slate-500 dark:text-[#c1c6d5]">Todas las cuentas están cobradas o no hay comandas activas.</p>
                            </div>
                        @endif
                    </div>
                @else
                    {{-- ══════════════ CUENTA: DETALLE + PAGO (todo dentro del modal) ══════════════ --}}
                    <div class="flex min-h-0 flex-col gap-5 overflow-y-auto px-6 py-6 [scrollbar-width:thin]">

                        {{-- ─────────── BLOQUES DE ÍTEMS (Comanda en curso / Comandas enviadas) ─────────── --}}
                        <div class="flex max-h-[38vh] flex-col gap-4 overflow-y-auto pr-1 [scrollbar-width:thin]">
                            @foreach ($this->account['orders'] as $orderBlock)
                                <div wire:key="blk-{{ $orderBlock['id'] ?? 'draft' }}" class="rounded-2xl bg-slate-50 p-4 dark:bg-[#181c22]">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex min-w-0 items-center gap-2">
                                            @if ($orderBlock['is_draft'] ?? false)
                                                <span class="flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-1 text-base font-black text-white dark:bg-[#fbbc48] dark:text-[#422c00]">
                                                    <x-heroicon-o-bolt class="h-4 w-4" /> COMANDA EN CURSO
                                                </span>
                                            @else
                                                <span class="rounded-lg bg-slate-100 px-3 py-1 font-mono text-base font-bold text-amber-600 dark:bg-[#0b0e15] dark:text-[#fbbc48]">#{{ $orderBlock['id'] }}</span>
                                                <span class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-[#c1c6d5]">Comanda enviada</span>
                                            @endif
                                            <span class="font-mono text-base font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $orderBlock['created_time'] }}</span>
                                            @if ($orderBlock['waiter_name'])
                                                <span class="hidden text-base text-slate-500 dark:text-[#c1c6d5] sm:inline">· {{ $orderBlock['waiter_name'] }}</span>
                                            @endif
                                        </div>
                                        <span class="shrink-0 font-mono text-xl font-bold text-slate-900 dark:text-[#e0e2ec]">${{ $this->money($orderBlock['subtotal']) }}</span>
                                    </div>

                                    <div class="mt-3 flex flex-col gap-2">
                                        @foreach ($orderBlock['items'] as $item)
                                            <div class="flex items-start justify-between gap-3 rounded-xl bg-white px-4 py-3 dark:bg-[#1c2026]">
                                                <div class="min-w-0 flex-grow">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xl leading-none">{{ $item['emoji'] }}</span>
                                                        <span class="font-mono text-lg font-bold text-amber-600 dark:text-[#fbbc48]">{{ $item['quantity'] }}x</span>
                                                        <span class="text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $item['name'] }}</span>
                                                    </div>
                                                    @if (trim((string) $item['notes']) !== '')
                                                        <p class="mt-1 flex items-center gap-1 pl-8 text-sm text-amber-600 dark:text-[#fbbc48]">
                                                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                            {{ $item['notes'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                                <span class="shrink-0 font-mono text-lg font-bold text-slate-900 dark:text-[#e0e2ec]">${{ $this->money($item['subtotal']) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- ─────────── PANEL DE PAGO (dentro del modal) ─────────── --}}
                        <div class="flex flex-col gap-4 rounded-2xl bg-slate-50 p-4 dark:bg-[#181c22]">

                            {{-- Subtotal --}}
                            <div class="flex items-center justify-between rounded-xl bg-white px-4 py-3 dark:bg-[#1c2026]">
                                <span class="text-base text-slate-500 dark:text-[#c1c6d5]">Subtotal ({{ $this->account['items_count'] }} {{ $this->account['items_count'] === 1 ? 'ítem' : 'ítems' }})</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-[#e0e2ec]">${{ $this->money($this->accountSubtotal()) }}</span>
                            </div>

                            {{-- Descuentos activos --}}
                            <div>
                                <label class="mb-2 flex items-center gap-1.5 text-base font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">
                                    <x-heroicon-o-tag class="h-4 w-4 text-amber-600 dark:text-[#fbbc48]" />
                                    Descuentos (opcional)
                                </label>
                                @if ($this->activeDiscounts->isNotEmpty())
                                    <div class="flex max-h-36 flex-col gap-2 overflow-y-auto pr-1 [scrollbar-width:thin]">
                                        @foreach ($this->activeDiscounts as $discount)
                                            <label wire:key="disc-{{ $discount->id }}" class="flex cursor-pointer items-center gap-3 rounded-xl bg-white p-3 transition-colors hover:bg-slate-100 dark:bg-[#1c2026] dark:hover:bg-[#272a31]">
                                                <input
                                                    type="checkbox"
                                                    wire:click="toggleDiscount({{ $discount->id }})"
                                                    @checked(in_array($discount->id, $this->selectedDiscounts, true))
                                                    class="h-5 w-5 rounded border-slate-300 text-amber-500 focus:ring-amber-500 dark:border-[#414753] dark:bg-[#0b0e15]"
                                                >
                                                <div class="min-w-0 flex-1">
                                                    <div class="truncate text-base font-bold text-slate-900 dark:text-[#e0e2ec]">{{ $discount->name }}</div>
                                                    <div class="text-sm text-slate-500 dark:text-[#c1c6d5]">
                                                        {{ $discount->type === 'percentage' ? $discount->value.' %' : '$'.$this->money($discount->value) }}
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="rounded-xl bg-white py-3 text-center text-base italic text-slate-400 dark:bg-[#1c2026] dark:text-[#c1c6d5]/70">
                                        No hay descuentos disponibles
                                    </div>
                                @endif

                                @if ($this->discountAmountValue() > 0)
                                    <div class="mt-2 flex items-center justify-between rounded-lg bg-amber-500/10 px-3 py-2">
                                        <span class="text-base font-bold text-amber-700 dark:text-[#fbbc48]">Descuento</span>
                                        <span class="font-mono text-xl font-bold text-amber-700 dark:text-[#fbbc48]">-${{ $this->money($this->discountAmountValue()) }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- SPLIT DE PAGO: filas método + monto (hasta 3) --}}
                            <div>
                                <label class="mb-2 flex items-center gap-1.5 text-base font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">
                                    <x-heroicon-o-credit-card class="h-4 w-4 text-amber-600 dark:text-[#fbbc48]" />
                                    Métodos de pago
                                </label>

                                <div class="flex flex-col gap-2">
                                    @foreach ($this->payments as $index => $row)
                                        <div wire:key="pay-row-{{ $index }}" class="flex items-center gap-2 rounded-xl bg-white p-2 dark:bg-[#1c2026]">
                                            <select
                                                wire:model.live="payments.{{ $index }}.method"
                                                class="shrink-0 rounded-lg border-0 bg-slate-50 px-3 py-3.5 text-base font-bold text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-amber-500 dark:bg-[#0b0e15] dark:text-[#e0e2ec] dark:ring-[#414753]"
                                            >
                                                <option value="cash">💵 Efectivo</option>
                                                <option value="card">💳 Tarjeta</option>
                                                <option value="transfer">🔁 Transferencia</option>
                                            </select>

                                            <div class="relative min-w-0 flex-1">
                                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-mono text-xl font-black text-amber-600 dark:text-[#fbbc48]">$</span>
                                                <input
                                                    wire:model.live="payments.{{ $index }}.amount"
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0"
                                                    class="w-full rounded-lg border-0 bg-slate-50 py-3.5 pl-9 pr-3 text-right font-mono text-xl font-black text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-amber-500 dark:bg-[#0b0e15] dark:text-[#e0e2ec] dark:ring-[#414753]"
                                                >
                                            </div>

                                            @if (count($this->payments) > 1)
                                                <button
                                                    wire:click="removePaymentRow({{ $index }})"
                                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600 transition-colors hover:bg-red-100 dark:bg-[#93000a]/20 dark:text-[#ffb4ab] dark:hover:bg-[#93000a]/30"
                                                    title="Quitar método"
                                                >
                                                    <x-heroicon-o-x-mark class="h-5 w-5" />
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                @if (count($this->payments) < 3)
                                    <button
                                        wire:click="addPaymentRow"
                                        class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-amber-500/60 py-3 text-base font-bold text-amber-700 transition-colors hover:bg-amber-500/10 dark:border-[#fbbc48]/50 dark:text-[#fbbc48]"
                                    >
                                        <x-heroicon-o-plus class="h-5 w-5" />
                                        Agregar método
                                    </button>
                                @endif

                                {{-- Validación en vivo del split --}}
                                @if ($this->paymentsTotal > 0 && abs($this->paymentsTotal - $this->accountTotal()) > 0.01)
                                    <div class="mt-2 flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-base font-semibold text-red-700 dark:bg-[#93000a]/20 dark:text-[#ffb4ab]">
                                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                                        La suma de los métodos (${{ $this->money($this->paymentsTotal) }}) no coincide con el total (${{ $this->money($this->accountTotal()) }}).
                                    </div>
                                @endif
                            </div>

                            {{-- Efectivo: monto recibido + vuelto en vivo (solo si el split incluye efectivo) --}}
                            @if ($this->hasCashPayment())
                                <div class="rounded-xl bg-white p-4 dark:bg-[#1c2026]">
                                    <label class="mb-1.5 flex items-center gap-1.5 text-base font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">
                                        <x-heroicon-o-banknotes class="h-4 w-4 text-amber-600 dark:text-[#fbbc48]" />
                                        Monto recibido en efectivo
                                    </label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-mono text-2xl font-black text-amber-600 dark:text-[#fbbc48]">$</span>
                                        <input
                                            wire:model.live="montoRecibido"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0"
                                            class="w-full rounded-xl border-0 bg-slate-50 py-4 pl-10 pr-4 text-right font-mono text-3xl font-black text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-2 focus:ring-amber-500 dark:bg-[#0b0e15] dark:text-[#e0e2ec] dark:ring-[#414753]"
                                        >
                                    </div>

                                    @if ($this->cashInsufficient())
                                        <div class="mt-2 flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-base font-semibold text-red-700 dark:bg-[#93000a]/20 dark:text-[#ffb4ab]">
                                            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                                            Falta dinero: el recibido es menor al total en efectivo.
                                        </div>
                                    @endif

                                    <div class="mt-3 flex items-center justify-between rounded-lg bg-amber-500/10 px-3 py-2">
                                        <span class="text-base font-bold text-amber-700 dark:text-[#fbbc48]">Vuelto</span>
                                        <span class="font-mono text-2xl font-black text-amber-700 dark:text-[#fbbc48]">${{ $this->money($this->vuelto) }}</span>
                                    </div>
                                </div>
                            @endif

                            {{-- TOTAL --}}
                            <div class="rounded-xl bg-white p-4 dark:bg-[#1c2026]">
                                <div class="my-0.5 h-px bg-slate-200 dark:bg-[#414753]/40"></div>
                                <div class="flex items-end justify-between pt-1">
                                    <div>
                                        <span class="text-base font-bold uppercase tracking-wider text-slate-500 dark:text-[#c1c6d5]">TOTAL A COBRAR</span>
                                        <div class="text-sm text-slate-400 dark:text-[#c1c6d5]/70">{{ $this->paymentMethodLabel() }}</div>
                                    </div>
                                    <span class="font-mono text-5xl font-black tracking-tight text-amber-600 dark:text-[#fbbc48]">${{ $this->money($this->accountTotal()) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ══════════════ FOOTER: ACCIONES ══════════════ --}}
                @if ($this->account)
                    <div class="flex gap-3 border-t border-slate-100 px-6 py-4 dark:border-[#272a31]">
                        <button
                            wire:click="cancelar"
                            class="w-1/3 rounded-xl bg-slate-100 py-4 text-lg font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-[#272a31] dark:text-[#e0e2ec] dark:hover:bg-[#32353c]"
                            title="Cerrar sin cobrar"
                        >
                            Cancelar
                        </button>
                        <button
                            wire:click="cobrar"
                            wire:loading.attr="disabled"
                            @disabled(! $this->canCobrar())
                            class="flex w-2/3 items-center justify-center gap-2 rounded-xl bg-amber-500 py-5 text-xl font-bold text-white shadow-lg shadow-amber-500/20 transition-all hover:bg-amber-400 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none dark:bg-[#fbbc48] dark:text-[#422c00] dark:hover:bg-[#ffdeac]"
                        >
                            <x-heroicon-o-check-circle class="h-7 w-7" />
                            <span wire:loading.remove>COBRAR CUENTA</span>
                            <span wire:loading>PROCESANDO…</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>