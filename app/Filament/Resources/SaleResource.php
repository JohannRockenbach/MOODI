<?php

namespace App\Filament\Resources;

use App\Actions\Sales\AnnulSaleAction;
use App\Actions\Sales\RestoreSaleAnnulmentAction;
use App\Filament\Resources\SaleResource\Pages;
use App\Models\Caja;
use App\Models\Order;
use App\Models\Sale;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaleResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Sale::class;

    // Use a heroicon that exists in the project's icon set to avoid Blade UI Icons errors
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Ventas y Finanzas';

    protected static ?int $navigationSort = 2;

    // Etiquetas en español
    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    protected static ?string $navigationLabel = 'Ventas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Ocultar y fijar restaurant_id = 1 (Sistema de restaurante único)
            Forms\Components\Hidden::make('restaurant_id')
                ->default(1),

            Forms\Components\Section::make('Información de la Venta')
                ->schema([
                    // Pedido (solo pedidos del restaurante 1)
                    Forms\Components\Select::make('order_id')
                        ->label('Pedido Nº')
                        ->relationship('order', 'id')
                        ->options(fn () => Order::where('restaurant_id', 1)->pluck('id', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('Selecciona el pedido asociado a esta venta')
                        ->columnSpan(1),

                    // Caja (solo cajas del restaurante 1)
                    Forms\Components\Select::make('caja_id')
                        ->label('Caja Nº')
                        ->relationship('caja', 'id')
                        ->options(fn () => Caja::where('restaurant_id', 1)
                            ->where('status', 'abierta')
                            ->pluck('id', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('Caja donde se registra la venta (solo cajas abiertas)')
                        ->columnSpan(1),

                    // Cajero
                    Forms\Components\Select::make('cashier_id')
                        ->label('Cajero')
                        ->relationship('cashier', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Usuario que procesa la venta')
                        ->columnSpan(1),

                    // Método de Pago
                    Forms\Components\Select::make('payment_method')
                        ->label('Método de Pago')
                        ->options([
                            'cash' => 'Efectivo',
                            'card' => 'Tarjeta',
                            'transfer' => 'Transferencia',
                        ])
                        ->required()
                        ->native(false)
                        ->helperText('Forma de pago de la venta')
                        ->columnSpan(1),

                    // Estado (solo opciones para creación manual, sin 'failed')
                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options([
                            'paid' => 'Pagado',
                            'pending' => 'Pendiente',
                        ])
                        ->default('paid')
                        ->required()
                        ->native(false)
                        ->helperText('Estado de la venta (Fallido no es una opción manual)')
                        ->columnSpan(1),

                    // Monto Total (readOnly - se puede calcular desde el pedido)
                    Forms\Components\TextInput::make('total_amount')
                        ->label('Monto Total')
                        ->numeric()
                        ->required()
                        ->readOnly()
                        ->prefix('$')
                        ->helperText('Total de la venta (calculado desde el pedido)')
                        ->columnSpan(1),
                ])
                ->columns(2)
                ->collapsible(),

            // Descuentos (relación muchos a muchos)
            Forms\Components\Section::make('Descuentos Aplicados')
                ->schema([
                    Forms\Components\Select::make('discounts')
                        ->label('Descuentos')
                        ->relationship('discounts', 'code')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('Descuentos aplicados a esta venta')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->formatStateUsing(function ($state): string {
                        if (! $state) {
                            return '-';
                        }

                        $date = $state instanceof Carbon ? $state : Carbon::parse($state);
                        Carbon::setLocale('es');

                        return ucfirst($date->translatedFormat('d/m/Y · H:i'));
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('id')
                    ->label('Ticket')
                    ->formatStateUsing(fn ($state): string => '#'.$state)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('origin_cashier')
                    ->label('Origen/Cajero')
                    ->state(function (Sale $record): string {
                        $type = $record->order?->type;

                        if ($type === 'delivery' || $type === 'para_llevar') {
                            return '🌐 Pedido web';
                        }

                        if ($record->order?->waiter?->name) {
                            return $record->order->waiter->name;
                        }

                        if ($record->cashier?->name) {
                            return $record->cashier->name;
                        }

                        return '🤖 Auto-gestionado';
                    })
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('cashier', fn ($cashier) => $cashier->where('name', 'like', "%{$search}%"));
                    }),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Pago')
                    ->sortable()
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'cash' => 'heroicon-m-banknotes',
                        'card' => 'heroicon-m-credit-card',
                        'transfer' => 'heroicon-m-building-library',
                        default => 'heroicon-m-wallet',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'card' => 'info',
                        'transfer' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'transfer' => 'Transferencia',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'paid' => 'heroicon-m-check-circle',
                        'pending' => 'heroicon-m-clock',
                        'annulled' => 'heroicon-m-x-circle',
                        'failed' => 'heroicon-m-exclamation-triangle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'annulled' => 'danger',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Pagado',
                        'pending' => 'Pendiente',
                        'annulled' => 'Anulado',
                        'failed' => 'Fallido',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->sortable()
                    ->money('ars')
                    ->weight('bold')
                    ->alignEnd()
                    ->color('success'),

                Tables\Columns\TextColumn::make('annulled_at')
                    ->label('Anulada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('annulledByUser.name')
                    ->label('Anulada por')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtro por Estado
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'paid' => 'Pagado',
                        'pending' => 'Pendiente',
                        'annulled' => 'Anulada',
                        'failed' => 'Fallido',
                    ]),

                Tables\Filters\TernaryFilter::make('annulled')
                    ->label('Anuladas')
                    ->placeholder('Todas')
                    ->trueLabel('Solo anuladas')
                    ->falseLabel('Solo no anuladas')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('annulled_at'),
                        false: fn ($query) => $query->whereNull('annulled_at'),
                        blank: fn ($query) => $query,
                    ),

                // Filtro por Método de Pago
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options([
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'transfer' => 'Transferencia',
                    ]),

                // Filtro por Caja
                Tables\Filters\SelectFilter::make('caja_id')
                    ->label('Caja')
                    ->relationship('caja', 'id'),

                // Filtro por Fecha
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('annul')
                    ->label('Anular venta')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Anular venta')
                    ->extraAttributes(['aria-label' => 'Anular venta'])
                    ->visible(fn (Sale $record): bool => ! $record->isAnnulled() && Gate::allows('annul', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Anular venta')
                    ->modalDescription('Esta acción no elimina el registro. La venta quedará trazada como anulada y no sumará en caja.')
                    ->modalSubmitActionLabel('Confirmar anulación')
                    ->form([
                        Forms\Components\Textarea::make('annulled_reason')
                            ->label('Motivo de anulación')
                            ->required()
                            ->minLength(5)
                            ->maxLength(2000)
                            ->rows(4),
                    ])
                    ->action(function (Sale $record, array $data): void {
                        $user = Auth::user();

                        if (! $user || ! Gate::forUser($user)->allows('annul', $record)) {
                            throw ValidationException::withMessages([
                                'annulled_reason' => 'No tenés permisos para anular ventas.',
                            ]);
                        }

                        try {
                            app(AnnulSaleAction::class)($record, $user, $data['annulled_reason']);

                            Notification::make()
                                ->title('Venta anulada')
                                ->success()
                                ->send();
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->title('No se pudo anular')
                                ->body($e->getMessage())
                                ->warning()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('restore_annulment')
                    ->label('Restaurar venta')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Restaurar venta')
                    ->extraAttributes(['aria-label' => 'Restaurar venta'])
                    ->visible(fn (Sale $record): bool => $record->isAnnulled() && Gate::allows('restoreAnnulled', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Restaurar venta anulada')
                    ->modalDescription('¿Confirmás restaurar esta venta? Se quitará la anulación y la venta volverá al estado Pagado.')
                    ->modalSubmitActionLabel('Confirmar restauración')
                    ->action(function (Sale $record): void {
                        $user = Auth::user();

                        if (! $user || ! Gate::forUser($user)->allows('restoreAnnulled', $record)) {
                            Notification::make()
                                ->title('No autorizado')
                                ->body('No tenés permisos para restaurar ventas anuladas.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            app(RestoreSaleAnnulmentAction::class)($record, $user);

                            Notification::make()
                                ->title('Venta restaurada')
                                ->success()
                                ->send();
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->title('No se pudo restaurar')
                                ->body($e->getMessage())
                                ->warning()
                                ->send();
                        } catch (\Throwable) {
                            Notification::make()
                                ->title('Error al restaurar')
                                ->body('Ocurrió un error inesperado al restaurar la venta.')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('ver_detalle')
                    ->label('Ver detalle de venta')
                    ->icon('heroicon-m-eye')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Ver detalle de venta')
                    ->extraAttributes(['aria-label' => 'Ver detalle de venta'])
                    ->visible(fn (Sale $record): bool => auth()->user()->can('view', $record))
                    ->modalHeading(fn (Sale $record): string => 'Detalle de venta #'.$record->id)
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalWidth('5xl')
                    ->modalContent(fn ($record): View => view('filament.sales.view-modal', ['sale' => $record->loadMissing(['order.products', 'cashier', 'order.customer'])])),
                Tables\Actions\EditAction::make()
                    ->label('Editar venta')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Editar venta')
                    ->extraAttributes(['aria-label' => 'Editar venta'])
                    ->visible(fn (Sale $record): bool => ! $record->isAnnulled() && Gate::allows('update', $record)),
            ])
            ->recordAction(null)
            ->recordUrl(null)
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }

    // Filtrar solo registros del restaurante ID = 1 + Optimización N+1
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'order:id,type,waiter_id',
                'order.waiter:id,name',
                'cashier:id,name',
                'annulledByUser:id,name',
            ]);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $isGlobalAdmin = $user && $user->hasRole('super_admin');

        $restaurantId = $user?->restaurant_id;

        if (! $isGlobalAdmin && $restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        }

        return $query;
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'annul',
            'restore_annulled',
        ];
    }
}
