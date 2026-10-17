<?php

namespace App\Filament\Pages;

use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\ReservationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * GestionReservas — reservas estilo Stitch ("Restaurante El Fogón", mismo
 * clima que GestionPedidos) con identidad de calendario: fecha destacada,
 * filtro por fecha (Hoy/Próximas/Todas) y KPIs por estado.
 *
 * Confirmar/Cancelar NO reimplementan la máquina de estados de la mesa: el
 * ReservationObserver se dispara automáticamente al actualizar el status
 * (reserva/libera la mesa). La creación y edición delegan en el
 * ReservationResource (validación anti doble-reserva ±2h y capacidad).
 */
class GestionReservas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static string $view = 'filament.pages.gestion-reservas';

    /**
     * Gestión operativa a ancho completo, igual que TableMap/KDS.
     */
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected static ?string $title = 'Reservas';

    protected static ?string $navigationLabel = 'Reservas';

    protected static ?string $navigationGroup = 'Operaciones del Salón';

    protected static ?int $navigationSort = 3;

    /**
     * Estados posibles de una reserva (fuente única, coincide con la app).
     */
    public const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmada',
        'cancelled' => 'Cancelada',
    ];

    // Reservas enriquecidas para el grid.
    public array $reservations = [];

    // KPIs por estado + próximas hoy.
    public array $stats = [
        'pending' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'today' => 0,
    ];

    // Buscador y filtros del header.
    public string $search = '';

    public string $activeStatus = 'all';

    public string $activeDate = 'all';

    // ─── Modal "Nueva Reserva" (inline, mismo patrón que TableMap) ───
    public bool $modalNuevaReserva = false;

    public ?int $nuevaCustomerId = null;

    public ?int $nuevaTableId = null;

    public string $nuevaFecha = '';

    public int $nuevaPersonas = 2;

    // ─── Modal "Editar Reserva" (inline Stitch, sin página Filament) ───
    public ?int $reservaEditId = null;

    public ?int $editCustomerId = null;

    public ?int $editTableId = null;

    public string $editFecha = '';

    public int $editPersonas = 2;

    public string $editStatus = 'pending';

    /**
     * Acceso: super_admin y Mozo — el mismo conjunto que
     * ReservationPolicy::viewAny (Cocinero/Cajero no gestionan reservas).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['super_admin', 'Mozo']);
    }

    public function mount(): void
    {
        $this->loadReservations();
    }

    /**
     * Clientes disponibles para seleccionar en el modal (Users con nombre,
     * sistema actual: la relación customer de Reservation apunta a User).
     */
    #[Computed]
    public function clientes(): array
    {
        return User::query()
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * Mesas reservables: disponibles o ya reservadas (available/reserved).
     * IMPORTANTE: NO incluye occupied ni maintenance (Regla de negocio —
     * ReservationService::assertTableAvailable).
     */
    #[Computed]
    public function mesasReservables(): array
    {
        return Table::query()
            ->where('restaurant_id', 1)
            ->whereIn('status', [Table::STATUS_AVAILABLE, Table::STATUS_RESERVED])
            ->orderBy('number')
            ->get(['id', 'number', 'capacity', 'location'])
            ->map(fn (Table $t): array => [
                'id' => $t->id,
                'label' => 'Mesa #'.$t->number.' · '.$t->capacity.' pax'.($t->location ? ' · '.$t->location : ''),
            ])
            ->all();
    }

    /**
     * Crear una reserva desde el modal inline. Usa ReservationService (la
     * MISMA validación atómica que CreateReservation del Resource): mesa no
     * ocupada/mantenimiento, capacidad suficiente y anti doble-reserva ±2h.
     */
    public function crearReserva(): void
    {
        $this->validate([
            'nuevaCustomerId' => ['required'],
            'nuevaTableId' => ['required'],
            'nuevaFecha' => ['required', 'date', 'after:now'],
            'nuevaPersonas' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'nuevaCustomerId.required' => 'Elegí el cliente de la reserva.',
            'nuevaTableId.required' => 'Elegí una mesa para la reserva.',
            'nuevaFecha.required' => 'Indicá la fecha y hora de la reserva.',
            'nuevaFecha.after' => 'La reserva debe ser en el futuro.',
            'nuevaPersonas.min' => 'Debe haber al menos 1 persona.',
            'nuevaPersonas.max' => 'Máximo 20 personas por reserva.',
        ]);

        try {
            app(ReservationService::class)->create([
                'restaurant_id' => 1,
                'customer_id' => $this->nuevaCustomerId,
                'table_id' => $this->nuevaTableId,
                'reservation_time' => $this->nuevaFecha,
                'guest_count' => $this->nuevaPersonas,
                'status' => 'pending',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $message = collect($errors)->flatten()->first() ?? 'No se pudo crear la reserva.';

            Notification::make()
                ->danger()
                ->title('No se pudo crear la reserva')
                ->body($message)
                ->persistent()
                ->send();

            return;
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Error al crear la reserva')
                ->body('Ocurrió un error inesperado: '.$e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Reserva creada')
            ->body('La reserva quedó registrada en estado Pendiente.')
            ->send();

        $this->reset(['nuevaCustomerId', 'nuevaTableId', 'nuevaFecha']);
        $this->nuevaPersonas = 2;
        $this->modalNuevaReserva = false;

        $this->loadReservations();
    }

    /**
     * Cargar las reservas del restaurante (id 1) enriquecidas con cliente y
     * mesa, ordenadas por fecha (más próximas primero).
     */
    public function loadReservations(): void
    {
        $reservations = Reservation::with(['customer', 'table'])
            ->where('restaurant_id', 1)
            ->orderBy('reservation_time', 'asc')
            ->get();

        $this->reservations = $reservations
            ->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'customer_name' => $reservation->customer?->name ?? 'Cliente',
                'table_number' => $reservation->table?->number,
                'reservation_time' => $reservation->reservation_time,
                'guest_count' => (int) $reservation->guest_count,
                'status' => $reservation->status,
                'status_label' => self::STATUS_LABELS[$reservation->status] ?? ucfirst($reservation->status),
            ])
            ->values()
            ->all();

        $this->stats = [
            'pending' => $reservations->where('status', 'pending')->count(),
            'confirmed' => $reservations->where('status', 'confirmed')->count(),
            'cancelled' => $reservations->where('status', 'cancelled')->count(),
            // Próximas hoy: pendientes/confirmadas con fecha de hoy.
            'today' => $reservations
                ->whereIn('status', ['pending', 'confirmed'])
                ->where(fn (Reservation $reservation) => $reservation->reservation_time->isToday())
                ->count(),
        ];

        unset($this->visibleReservations);
    }

    /**
     * Reservas visibles según estado, fecha (Hoy/Próximas/Todas) y búsqueda
     * por cliente o mesa. El blade itera SOLO esta colección.
     */
    #[Computed]
    public function visibleReservations(): array
    {
        return collect($this->reservations)
            ->filter(fn (array $reservation): bool => $this->activeStatus === 'all' || $reservation['status'] === $this->activeStatus)
            ->filter(function (array $reservation): bool {
                return match ($this->activeDate) {
                    'today' => $reservation['reservation_time']->isToday(),
                    'upcoming' => $reservation['reservation_time']->isAfter(now()),
                    default => true,
                };
            })
            ->filter(fn (array $reservation): bool => $this->reservationMatchesSearch($reservation))
            ->values()
            ->all();
    }

    /**
     * ¿La reserva coincide con el buscador? Filtra por cliente y mesa.
     */
    public function reservationMatchesSearch(array $reservation): bool
    {
        if ($this->search === '') {
            return true;
        }

        $query = mb_strtolower(trim($this->search));

        $haystack = mb_strtolower(implode(' ', [
            (string) ($reservation['customer_name'] ?? ''),
            (string) ($reservation['table_number'] ?? ''),
        ]));

        return str_contains($haystack, $query);
    }

    /**
     * Confirmar reserva: pending → confirmed. El ReservationObserver se
     * dispara automáticamente y asegura la mesa (available → reserved).
     */
    public function confirmReservation(int $reservationId): void
    {
        $reservation = Reservation::find($reservationId);

        if (! $reservation || $reservation->status !== 'pending') {
            return;
        }

        $reservation->update(['status' => 'confirmed']);

        Notification::make()
            ->title('Reserva Confirmada')
            ->body('La reserva de '.($reservation->customer?->name ?? 'Cliente').' fue confirmada. La mesa se reservó automáticamente.')
            ->success()
            ->send();

        $this->loadReservations();
    }

    /**
     * Cancelar reserva: pending|confirmed → cancelled. El ReservationObserver
     * libera la mesa automáticamente (si no quedan otras reservas activas).
     */
    public function cancelReservation(int $reservationId): void
    {
        $reservation = Reservation::find($reservationId);

        if (! $reservation || ! in_array($reservation->status, ['pending', 'confirmed'], true)) {
            return;
        }

        $reservation->update(['status' => 'cancelled']);

        Notification::make()
            ->title('Reserva Cancelada')
            ->body('La reserva fue cancelada. La mesa se liberó automáticamente si no tenía otras reservas activas.')
            ->warning()
            ->send();

        $this->loadReservations();
    }

    /**
     * Editar reserva: abre el modal Stitch inline (mismo patrón que el de
     * creación). NO navega a la página de edición de Filament. Conserva la
     * validación atómica via ReservationService::update (anti doble-reserva
     * ±2h, mesa disponible, capacidad).
     */
    public function editReservation(int $reservationId): void
    {
        $reservation = Reservation::find($reservationId);

        if (! $reservation || $reservation->restaurant_id !== 1) {
            return;
        }

        $this->reservaEditId = $reservation->id;
        $this->editCustomerId = $reservation->customer_id;
        $this->editTableId = $reservation->table_id;
        $this->editFecha = $reservation->reservation_time->format('Y-m-d\TH:i');
        $this->editPersonas = $reservation->guest_count;
        $this->editStatus = $reservation->status;
    }

    /**
     * Guardar los cambios del modal Editar Reserva. Usa ReservationService
     * (la MISMA validación atómica que EditReservation del Resource): mesa no
     * ocupada/mantenimiento, capacidad suficiente y anti doble-reserva ±2h
     * excluyendo este registro.
     */
    public function guardarEdicionReserva(): void
    {
        $reservation = Reservation::find($this->reservaEditId);

        if (! $reservation || $reservation->restaurant_id !== 1) {
            return;
        }

        $this->validate([
            'editCustomerId' => ['required'],
            'editTableId' => ['required'],
            'editFecha' => ['required', 'date'],
            'editPersonas' => ['required', 'integer', 'min:1', 'max:20'],
            'editStatus' => ['required', 'in:pending,confirmed,cancelled'],
        ], [
            'editCustomerId.required' => 'Elegí el cliente de la reserva.',
            'editTableId.required' => 'Elegí una mesa para la reserva.',
            'editFecha.required' => 'Indicá la fecha y hora de la reserva.',
            'editPersonas.min' => 'Debe haber al menos 1 persona.',
            'editPersonas.max' => 'Máximo 20 personas por reserva.',
        ]);

        try {
            app(ReservationService::class)->update($reservation, [
                'restaurant_id' => 1,
                'customer_id' => $this->editCustomerId,
                'table_id' => $this->editTableId,
                'reservation_time' => $this->editFecha,
                'guest_count' => $this->editPersonas,
                'status' => $this->editStatus,
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $message = collect($errors)->flatten()->first() ?? 'No se pudo guardar la reserva.';

            Notification::make()
                ->danger()
                ->title('No se pudo guardar la reserva')
                ->body($message)
                ->persistent()
                ->send();

            return;
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Error al guardar la reserva')
                ->body('Ocurrió un error inesperado: '.$e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Reserva actualizada')
            ->body('Los cambios de la reserva se guardaron correctamente.')
            ->send();

        $this->reset(['reservaEditId', 'editCustomerId', 'editTableId', 'editFecha']);
        $this->editPersonas = 2;
        $this->editStatus = 'pending';

        $this->loadReservations();
    }

    /**
     * Nueva reserva: abre el modal inline (mismo patrón que TableMap) y crea
     * con ReservationService; conserva la validación anti doble-reserva ±2h
     * y de capacidad sin depender de la navegación del Resource.
     */
    public function createReservation(): void
    {
        $this->modalNuevaReserva = true;
    }
}