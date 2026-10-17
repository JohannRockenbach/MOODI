<?php

use App\Models\Table;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.reservar')] class extends Component
{
    /**
     * Fecha y hora de la reserva (formato datetime-local).
     */
    public string $fecha = '';

    /**
     * Cantidad de comensales.
     */
    public int $comensales = 2;

    /**
     * Mesa seleccionada por el cliente.
     */
    public ?int $mesaId = null;

    /**
     * Datos de la reserva confirmada (para mostrar el resumen al cliente).
     *
     * @var array{fecha: string, mesa: mixed, comensales: int}|null
     */
    public ?array $confirmacion = null;

    public function mount(): void
    {
        $user = Auth::user();

        // Solo el cliente web (o super_admin para pruebas) autogestiona su mesa.
        if (! $user || ! $user->hasAnyRole(['cliente', 'super_admin'])) {
            abort(403);
        }

        $this->fecha = now()->addHours(2)->startOfHour()->format('Y-m-d\TH:i');
    }

    /**
     * Mesas disponibles para la fecha/hora y comensales elegidos.
     *
     * Reutiliza ReservationService::availableTables (misma ventana ±2h y
     * filtros de negocio que el create). Devuelve colección vacía si la fecha
     * no es válida o no es futura.
     */
    #[Computed]
    public function mesasDisponibles(): \Illuminate\Support\Collection
    {
        if (blank($this->fecha) || $this->comensales < 1) {
            return collect();
        }

        try {
            $time = Carbon::parse($this->fecha);
        } catch (\Throwable $e) {
            return collect();
        }

        if (! $time->isFuture()) {
            return collect();
        }

        return app(ReservationService::class)
            ->availableTables($time, $this->comensales, 1);
    }

    /**
     * Al cambiar fecha o comensales, la mesa seleccionada deja de ser válida.
     */
    public function updatedFecha(): void
    {
        $this->reset('mesaId');
    }

    public function updatedComensales(): void
    {
        $this->reset('mesaId');
    }

    public function reservar(): void
    {
        $data = $this->validate([
            'fecha' => ['required', 'date', 'after:now'],
            'comensales' => ['required', 'integer', 'min:1', 'max:20'],
            'mesaId' => ['required', 'integer', 'exists:tables,id'],
        ], [
            'fecha.required' => 'Indicá la fecha y hora de la reserva.',
            'fecha.date' => 'La fecha y hora no es válida.',
            'fecha.after' => 'La reserva debe ser en el futuro.',
            'comensales.required' => 'Indicá la cantidad de comensales.',
            'comensales.integer' => 'La cantidad de comensales debe ser un número.',
            'comensales.min' => 'Debe haber al menos 1 comensal.',
            'comensales.max' => 'Máximo 20 comensales por reserva.',
            'mesaId.required' => 'Elegí una mesa para tu reserva.',
            'mesaId.exists' => 'La mesa seleccionada no es válida.',
        ]);

        try {
            $reservation = app(ReservationService::class)->create([
                'restaurant_id' => 1,
                'customer_id' => Auth::id(),
                'table_id' => (int) $data['mesaId'],
                'reservation_time' => $data['fecha'],
                'guest_count' => (int) $data['comensales'],
                'status' => 'pending',
            ]);
        } catch (ValidationException $e) {
            // El servicio es la autoridad: mapear sus claves (table_id,
            // guest_count, reservation_time) a los campos del formulario.
            $map = [
                'table_id' => 'mesaId',
                'guest_count' => 'comensales',
                'reservation_time' => 'fecha',
            ];

            foreach ($e->errors() as $field => $messages) {
                $target = $map[$field] ?? $field;
                foreach ($messages as $message) {
                    $this->addError($target, $message);
                }
            }

            return;
        } catch (\Throwable $e) {
            $this->addError('fecha', 'No pudimos registrar tu reserva. Intentá nuevamente.');

            return;
        }

        $mesa = Table::find($data['mesaId']);

        $this->confirmacion = [
            'fecha' => Carbon::parse($reservation->reservation_time)->format('d/m/Y H:i'),
            'mesa' => $mesa?->number,
            'comensales' => (int) $data['comensales'],
        ];

        $this->reset(['fecha', 'mesaId']);
        $this->comensales = 2;
        $this->fecha = now()->addHours(2)->startOfHour()->format('Y-m-d\TH:i');
    }
};

?>

<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-10 md:py-16">
    <div class="mb-8 text-center">
        <p class="inline-flex items-center px-3 py-1.5 rounded-full bg-orange-100 text-orange-700 text-xs font-bold uppercase tracking-[0.2em]">
            Reserva de mesa
        </p>
        <h1 class="mt-4 text-3xl sm:text-4xl font-black text-gray-900">Reservá tu mesa</h1>
        <p class="mt-2 text-sm text-gray-500">Elegí fecha, comensales y la mesa que prefieras.</p>
    </div>

    @if ($confirmacion)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 mb-8">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-lg font-black text-emerald-800">Reserva registrada</h2>
                    <p class="text-sm text-emerald-700">
                        Mesa #{{ $confirmacion['mesa'] }} · {{ $confirmacion['fecha'] }} · {{ $confirmacion['comensales'] }} comensales
                    </p>
                </div>
            </div>
            <div class="mt-4">
                <button
                    type="button"
                    wire:click="$set('confirmacion', null)"
                    class="text-sm font-bold text-emerald-700 hover:text-emerald-900 underline underline-offset-2"
                >
                    Hacer otra reserva
                </button>
            </div>
        </div>
    @endif

    <form wire:submit="reservar" class="space-y-6">
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6 space-y-5">
            {{-- Fecha y hora --}}
            <div>
                <label for="fecha" class="block text-sm font-bold text-gray-800">Fecha y hora</label>
                <input
                    id="fecha"
                    type="datetime-local"
                    wire:model.live="fecha"
                    class="mt-1 w-full h-12 rounded-xl border border-gray-200 px-4 text-sm focus:border-orange-300 focus:ring-4 focus:ring-orange-100 outline-none transition"
                >
                @error('fecha')
                    <p class="mt-1.5 text-sm font-semibold text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Comensales --}}
            <div>
                <label for="comensales" class="block text-sm font-bold text-gray-800">Comensales</label>
                <select
                    id="comensales"
                    wire:model.live="comensales"
                    class="mt-1 w-full h-12 rounded-xl border border-gray-200 px-4 text-sm bg-white focus:border-orange-300 focus:ring-4 focus:ring-orange-100 outline-none transition"
                >
                    @for ($i = 1; $i <= 20; $i++)
                        <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'comensal' : 'comensales' }}</option>
                    @endfor
                </select>
                @error('comensales')
                    <p class="mt-1.5 text-sm font-semibold text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Mesas disponibles --}}
        <div>
            <h2 class="text-sm font-bold text-gray-800 mb-3">
                Mesas disponibles
                @if ($this->mesasDisponibles->isNotEmpty())
                    <span class="ml-1 text-gray-400 font-semibold">({{ $this->mesasDisponibles->count() }})</span>
                @endif
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @forelse ($this->mesasDisponibles as $mesa)
                    <button
                        type="button"
                        wire:click="$set('mesaId', {{ $mesa->id }})"
                        class="rounded-2xl border p-4 text-left transition
                            {{ $mesaId === $mesa->id ? 'border-orange-500 bg-orange-50 ring-2 ring-orange-200' : 'border-gray-200 bg-white hover:border-orange-300' }}"
                    >
                        <p class="font-black text-gray-900">Mesa #{{ $mesa->number }}</p>
                        <p class="text-sm text-gray-500 mt-1">{{ $mesa->capacity }} pax</p>
                        @if ($mesa->location)
                            <p class="text-xs text-gray-400 mt-0.5">{{ $mesa->location }}</p>
                        @endif
                    </button>
                @empty
                    <div class="col-span-full rounded-2xl border border-gray-200 bg-gray-50 p-6 text-center text-sm text-gray-500">
                        No hay mesas disponibles para esa fecha, hora y cantidad de comensales. Probá otro horario.
                    </div>
                @endforelse
            </div>

            @error('mesaId')
                <p class="mt-1.5 text-sm font-semibold text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full inline-flex items-center justify-center rounded-full bg-orange-500 px-6 py-3.5 text-sm font-black text-white hover:bg-orange-600 transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orange-200"
        >
            Confirmar reserva
        </button>
    </form>
</div>
