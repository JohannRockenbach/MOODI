<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table as TableModel;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use function __;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    // Poner reservas dentro de Operaciones del Salón y etiquetas en español
    protected static ?string $navigationGroup = 'Operaciones del Salón';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Reserva';
    protected static ?string $pluralModelLabel = 'Reservas';
    protected static ?string $navigationLabel = 'Reservas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Restaurant ID oculto (sistema de restaurante único)
            Forms\Components\Hidden::make('restaurant_id')
                ->default(1),

            // Cliente (usa customer_id en BD, customer en relación)
            Forms\Components\Select::make('customer_id')
                ->label('Cliente')
                ->relationship('customer', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText('Selecciona el cliente que realizó la reserva')
                ->columnSpanFull(),

            // Mesa (solo mesas del restaurante 1)
            Forms\Components\Select::make('table_id')
                ->label('Mesa')
                ->relationship('table', 'number')
                ->options(fn() => TableModel::where('restaurant_id', 1)->pluck('number', 'id'))
                ->searchable()
                ->required()
                ->helperText('Mesa asignada para la reserva'),

            // Fecha y Hora de Reserva
            Forms\Components\DateTimePicker::make('reservation_time')
                ->label('Fecha y Hora')
                ->required()
                ->seconds(false)
                ->native(false)
                ->minDate(now())
                ->helperText('Fecha y hora de la reserva')
                ->default(now()->addHours(2))
                ->rules([
                    function ($record, $get) {
                        return function ($attribute, $value, \Closure $fail) use ($record, $get) {
                            $tableId = $get('table_id');
                            
                            if (!$tableId || !$value) {
                                return; // No validar si falta la mesa o la fecha
                            }
                            
                            $reservationTime = \Carbon\Carbon::parse($value);
                            
                            // Buscar reservas activas en la misma mesa que se superpongan
                            // Consideramos un margen de 2 horas por reserva
                            $conflict = Reservation::where('table_id', $tableId)
                                ->whereIn('status', ['pending', 'confirmed'])
                                ->where(function ($query) use ($reservationTime) {
                                    // Reserva que empieza o termina dentro de nuestro rango (±2 horas)
                                    $query->whereBetween('reservation_time', [
                                        $reservationTime->copy()->subHours(2),
                                        $reservationTime->copy()->addHours(2)
                                    ]);
                                })
                                ->when($record, fn($query) => $query->where('id', '!=', $record->id)) // Excluir el registro actual al editar
                                ->exists();
                            
                            if ($conflict) {
                                $fail('Esta mesa ya tiene una reserva activa en ese horario (±2 horas). Por favor, selecciona otro horario o mesa.');
                            }
                        };
                    }
                ]),

            // Número de Personas (usa guest_count en BD)
            Forms\Components\TextInput::make('guest_count')
                ->label('Nº Personas')
                ->numeric()
                ->required()
                ->minValue(1)
                ->maxValue(20)
                ->default(2)
                ->suffix('personas')
                ->helperText('Cantidad de comensales'),

            // Estado de la Reserva
            Forms\Components\Select::make('status')
                ->label('Estado')
                ->required()
                ->default('pending')
                ->options([
                    'pending' => 'Pendiente',
                    'confirmed' => 'Confirmada',
                    'cancelled' => 'Cancelada',
                ])
                ->native(false)
                ->helperText('Estado actual de la reserva'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Cliente (con optimización N+1)
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user')
                    ->iconColor('primary'),

                // Mesa (con optimización N+1)
                Tables\Columns\TextColumn::make('table.number')
                    ->label('Mesa')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->prefix('Mesa '),

                // Fecha y Hora
                Tables\Columns\TextColumn::make('reservation_time')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->reservation_time->diffForHumans()),

                // Número de Personas (usa guest_count en BD)
                Tables\Columns\TextColumn::make('guest_count')
                    ->label('Personas')
                    ->sortable()
                    ->suffix(' pers.')
                    ->alignCenter(),

                // Estado con Badge y Colores
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                // Columna oculta (restaurante único)
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurante')
                    ->hidden(),
            ])
            ->filters([
                // Filtro por Estado
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                    ]),
                
                // Filtro por Mesa
                Tables\Filters\SelectFilter::make('table_id')
                    ->label('Mesa')
                    ->relationship('table', 'number')
                    ->searchable()
                    ->preload(),

                // Filtro por Fecha
                Tables\Filters\Filter::make('reservation_time')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($query, $date) => $query->whereDate('reservation_time', '>=', $date))
                            ->when($data['until'], fn ($query, $date) => $query->whereDate('reservation_time', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('primary'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                
                // Acción rápida para confirmar reserva
                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(fn ($record) => $record->update(['status' => 'confirmed'])),
                
                // Acción rápida para cancelar reserva
                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status !== 'cancelled')
                    ->action(fn ($record) => $record->update(['status' => 'cancelled'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('reservation_time', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
    
    // Filtrar por restaurante único + Optimización N+1
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('restaurant_id', 1) // Sistema de restaurante único
            ->with(['customer', 'table']); // Optimización N+1 para columnas de cliente y mesa
    }
}
