<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CajaResource\Pages;
use App\Models\Caja;
use App\Models\Restaurant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Contracts\View\View;

class CajaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Caja::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Ventas y Finanzas';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Caja';
    protected static ?string $pluralModelLabel = 'Cajas';
    protected static ?string $navigationLabel = 'Cajas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Campo oculto: restaurant_id siempre = 1
                Forms\Components\Hidden::make('restaurant_id')
                    ->default(1),

                // Usuario que abre la caja (asignado automáticamente al usuario logueado)
                Forms\Components\Select::make('opening_user_id')
                    ->label('Usuario')
                    ->relationship('openingUser', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn () => Auth::id())
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),

                // Fecha de apertura (se establece automáticamente al crear)
                Forms\Components\DateTimePicker::make('opening_date')
                    ->label('Fecha Apertura')
                    ->required()
                    ->default(now())
                    ->readOnly(fn (string $operation): bool => $operation === 'edit'),

                // Saldo inicial (solo editable al crear)
                Forms\Components\TextInput::make('initial_balance')
                    ->label('Saldo Inicial')
                    ->numeric()
                    ->required()
                    ->prefix('$')
                    ->minValue(0)
                    ->maxValue(99999999.99)
                    ->step(0.01)
                    ->default(0)
                    ->readOnly(fn (string $operation): bool => $operation === 'edit'),

                // Balance actual (solo visible en edición de cajas abiertas)
                Forms\Components\Placeholder::make('live_balance')
                    ->label('Balance Actual')
                    ->content(function (?Model $record): View {
                        return view('filament.cajas.balance_live', [
                            'record' => $record,
                        ]);
                    })
                    ->visible(fn (string $operation, ?Model $record): bool => 
                        $operation === 'edit' && $record?->status === 'abierta'
                    ),

                // Estado (reactivo para mostrar/ocultar campos de cierre)
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'abierta' => 'Abierta',
                        'cerrada' => 'Cerrada',
                    ])
                    ->default('abierta')
                    ->required()
                    ->live()
                    ->disabled(fn (string $operation): bool => $operation === 'create'),

                // Fecha de cierre (solo visible cuando estado = cerrada)
                Forms\Components\DateTimePicker::make('closing_date')
                    ->label('Fecha Cierre')
                    ->nullable()
                    ->hidden(fn (Forms\Get $get): bool => $get('status') !== 'cerrada'),

                // Saldo final (solo visible cuando estado = cerrada)
                Forms\Components\TextInput::make('final_balance')
                    ->label('Saldo Final')
                    ->numeric()
                    ->nullable()
                    ->prefix('$')
                    ->minValue(0)
                    ->maxValue(99999999.99)
                    ->step(0.01)
                    ->hidden(fn (Forms\Get $get): bool => $get('status') !== 'cerrada')
                    ->disabled(fn (string $operation, ?Model $record): bool => 
                        $operation === 'edit' && $record?->status === 'cerrada'
                    ),

                // Usuario que cerró (solo visible cuando estado = cerrada)
                Forms\Components\Placeholder::make('closing_user_display')
                    ->label('Cerrada por')
                    ->content(function (?Model $record) {
                        if (! $record || ! $record->closingUser) return '-';
                        $user = $record->closingUser;
                        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->join(', ') : '';
                        return $user->name . ($roles ? ' (' . $roles . ')' : '');
                    })
                    ->visible(fn (string $operation, ?Model $record): bool => 
                        $operation === 'edit' && $record?->status === 'cerrada'
                    ),

                // Notas (campo único para apertura y cierre)
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->placeholder('Notas adicionales sobre la caja')
                    ->rows(4)
                    ->columnSpanFull()
                    ->disabled(fn (string $operation, ?Model $record): bool => 
                        $operation === 'edit' && $record?->status === 'cerrada'
                    ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                // Usuario que abrió la caja
                Tables\Columns\TextColumn::make('openingUser.name')
                    ->label('Abierta por')
                    ->searchable()
                    ->sortable(),
                
                // Fecha de apertura
                Tables\Columns\TextColumn::make('opening_date')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                // Saldo inicial
                Tables\Columns\TextColumn::make('initial_balance')
                    ->label('Saldo Inicial')
                    ->money('ARS')
                    ->sortable(),
                
                // Fecha de cierre
                Tables\Columns\TextColumn::make('closing_date')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Pendiente'),
                
                // Saldo final
                Tables\Columns\TextColumn::make('final_balance')
                    ->label('Saldo Final')
                    ->money('ARS')
                    ->sortable()
                    ->placeholder('-'),
                
                // Estado con badge
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'abierta' => 'success',
                        'cerrada' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'abierta' => 'Abiertas',
                        'cerrada' => 'Cerradas',
                    ])
                    ->placeholder('Todos los estados'),
                
                // Filtro de restaurant ya no es necesario (restaurante único)
                // Tables\Filters\SelectFilter::make('restaurant_id') - COMENTADO
                
                Tables\Filters\SelectFilter::make('opening_user_id')
                    ->label('Abierta por')
                    ->relationship('openingUser', 'name')
                    ->placeholder('Todos los usuarios')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('closing_user_id')
                    ->label('Cerrada por')
                    ->relationship('closingUser', 'name')
                    ->placeholder('Todos los usuarios')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('opening_date', 'desc')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading(fn (Caja $record): string => 'Detalles de la Caja #' . $record->id)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make('Información General')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('openingUser.name')
                                    ->label('Abierta por'),
                                \Filament\Infolists\Components\TextEntry::make('opening_date')
                                    ->label('Fecha de Apertura')
                                    ->dateTime('d/m/Y H:i'),
                                \Filament\Infolists\Components\TextEntry::make('initial_balance')
                                    ->label('Saldo Inicial')
                                    ->money('ARS'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'abierta' => 'success',
                                        'cerrada' => 'danger',
                                        default => 'gray',
                                    }),
                            ])
                            ->columns(2),
                        \Filament\Infolists\Components\Section::make('Balance Actual')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('balance_actual')
                                    ->label('Balance en Tiempo Real')
                                    ->state(function (Caja $record): string {
                                        $totalSales = $record->sales()->sum('total_amount');
                                        $balance = $record->initial_balance + $totalSales;
                                        return '$ ' . number_format($balance, 2, ',', '.');
                                    })
                                    ->badge()
                                    ->color('success'),
                                \Filament\Infolists\Components\TextEntry::make('num_ventas')
                                    ->label('Número de Ventas')
                                    ->state(fn (Caja $record): int => $record->sales()->count()),
                            ])
                            ->columns(2)
                            ->visible(fn (Caja $record): bool => $record->status === 'abierta'),
                        \Filament\Infolists\Components\Section::make('Información de Cierre')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('closingUser.name')
                                    ->label('Cerrada por')
                                    ->placeholder('-'),
                                \Filament\Infolists\Components\TextEntry::make('closing_date')
                                    ->label('Fecha de Cierre')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('Pendiente'),
                                \Filament\Infolists\Components\TextEntry::make('final_balance')
                                    ->label('Saldo Final')
                                    ->money('ARS')
                                    ->placeholder('-'),
                                \Filament\Infolists\Components\TextEntry::make('total_sales')
                                    ->label('Total de Ventas')
                                    ->money('ARS')
                                    ->placeholder('-'),
                            ])
                            ->columns(2)
                            ->visible(fn (Caja $record): bool => $record->status === 'cerrada'),
                        \Filament\Infolists\Components\Section::make('Notas')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('notes')
                                    ->label('')
                                    ->placeholder('Sin notas registradas')
                                    ->html()
                                    ->formatStateUsing(fn (?string $state): string => 
                                        $state ? nl2br(e($state)) : 'Sin notas registradas'
                                    )
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Caja $record): bool => $record->status === 'abierta'),
                Tables\Actions\Action::make('close')
                    ->label('Cerrar Caja')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->modalHeading('Cerrar Caja')
                    ->modalDescription(function (Caja $record): string {
                        $totalSales = $record->sales()->sum('total_amount');
                        $finalBalance = $record->initial_balance + $totalSales;
                        
                        $numSales = $record->sales()->count();
                        
                        return "**Resumen de cierre:**\n\n"
                            . "• Saldo inicial: $ " . number_format($record->initial_balance, 2, ',', '.') . "\n"
                            . "• Total de ventas: $ " . number_format($totalSales, 2, ',', '.') . " ({$numSales} ventas)\n"
                            . "• Balance final: $ " . number_format($finalBalance, 2, ',', '.') . "\n\n"
                            . "Agrega observaciones sobre el cierre:";
                    })
                    ->modalSubmitActionLabel('Cerrar Caja')
                    ->visible(fn (Caja $record): bool => $record->status === 'abierta')
                    ->form([
                        Forms\Components\Textarea::make('closing_notes')
                            ->label('Notas de cierre')
                            ->placeholder('Ej: Cierre normal, sin diferencias encontradas')
                            ->helperText('Estas notas se agregarán a las notas existentes de la caja')
                            ->maxLength(2000)
                            ->rows(4),
                    ])
                    ->action(function (Caja $record, array $data): void {
                        try {
                            DB::transaction(function () use ($record, $data) {
                                $totalSales = $record->sales()->sum('total_amount');
                                $final = $record->initial_balance + $totalSales;

                                // DB decimal(10,2) max absolute is 99,999,999.99
                                $max = 99999999.99;
                                if ($final > $max) {
                                    throw new \RuntimeException('El balance final excede el valor máximo permitido (' . number_format($max, 2) . ').');
                                }

                                // Construir notas con información de cierre
                                $closingInfo = '--- CIERRE DE CAJA ---' . "\n"
                                    . 'Fecha: ' . now()->format('d/m/Y H:i') . "\n"
                                    . 'Usuario: ' . Auth::user()->name . "\n";
                                
                                if (!empty($data['closing_notes'])) {
                                    $closingInfo .= 'Observaciones: ' . $data['closing_notes'];
                                }

                                $updatedNotes = $record->notes 
                                    ? $record->notes . "\n\n" . $closingInfo
                                    : $closingInfo;

                                $record->update([
                                    'closing_date' => now(),
                                    'closing_user_id' => Auth::id(),
                                    'status' => 'cerrada',
                                    'total_sales' => $totalSales,
                                    'final_balance' => $final,
                                    'notes' => $updatedNotes,
                                ]);
                            });

                            Notification::make()
                                ->title('Caja cerrada exitosamente')
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            // Show user-friendly message
                            Notification::make()
                                ->title('Error al cerrar la caja')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->disabled(fn (Caja $record) => ! Gate::allows('close', $record)),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // relations
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCajas::route('/'),
            'edit' => Pages\EditCaja::route('/{record}/edit'),
        ];
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
            'open',
            'close',
        ];
    }
    
    // Filtrar solo registros del restaurante ID = 1 y optimizar N+1
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('restaurant_id', 1)
            ->with('openingUser');
    }
}
