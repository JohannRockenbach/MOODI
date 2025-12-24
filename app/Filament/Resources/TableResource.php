<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TableResource\Pages;
use App\Models\Restaurant; // Importar Restaurante
use App\Models\User; // Importar Usuario
use App\Models\Table as TableModel; // Renombrar Table para evitar conflicto
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use function __;

class TableResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = TableModel::class; // Usar el alias
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationGroup = 'Operaciones del Salón';
    protected static ?int $navigationSort = 1;

    // --- Etiquetas en Español ---
    protected static ?string $modelLabel = 'Mesa';
    protected static ?string $pluralModelLabel = 'Mesas';
    protected static ?string $navigationLabel = 'Mesas';
    // --- Fin Etiquetas ---

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Restaurant ID oculto (sistema de restaurante único)
                Forms\Components\Hidden::make('restaurant_id')
                    ->default(1),

                // Número de Mesa
                Forms\Components\TextInput::make('number')
                    ->label('Número de Mesa')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxLength(10)
                    ->unique(
                        table: TableModel::class,
                        column: 'number',
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule, callable $get) {
                            // Validar unicidad dentro del mismo restaurante (siempre 1)
                            return $rule->where('restaurant_id', 1);
                        }
                    )
                    ->helperText('Número único identificador de la mesa'),

                // Capacidad
                Forms\Components\TextInput::make('capacity')
                    ->label('Capacidad')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20)
                    ->default(4)
                    ->suffix('personas')
                    ->helperText('Cantidad máxima de comensales'),

                // Ubicación
                Forms\Components\TextInput::make('location')
                    ->label('Ubicación')
                    ->maxLength(100)
                    ->placeholder('Ej: Salón, Terraza, Ventana, Interior')
                    ->helperText('Ubicación física de la mesa (opcional)'),

                // Estado de la Mesa
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->required()
                    ->default('available')
                    ->options([
                        'available' => 'Disponible',
                        'occupied' => 'Ocupada',
                        'reserved' => 'Reservada',
                        'maintenance' => 'Mantenimiento',
                    ])
                    ->native(false)
                    ->helperText('Estado actual de la mesa'),

                // Mozo Asignado (usa waiter_id en BD, user en relación)
                Forms\Components\Select::make('waiter_id')
                    ->label('Mozo Asignado')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Usuario responsable de atender esta mesa')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Número de Mesa
                Tables\Columns\TextColumn::make('number')
                    ->label('Nº')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->size('lg'),

                // Mozo Asignado (con optimización N+1)
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Mozo Asignado')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Sin asignar')
                    ->icon('heroicon-o-user')
                    ->iconColor('primary'),

                // Capacidad
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacidad')
                    ->sortable()
                    ->suffix(' pers.')
                    ->alignCenter(),

                // Ubicación
                Tables\Columns\TextColumn::make('location')
                    ->label('Ubicación')
                    ->searchable()
                    ->placeholder('No especificada')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->location),

                // Estado con Badge y Colores
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'occupied' => 'danger',
                        'reserved' => 'warning',
                        'maintenance' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => 'Disponible',
                        'occupied' => 'Ocupada',
                        'reserved' => 'Reservada',
                        'maintenance' => 'Mantenimiento',
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
                        'available' => 'Disponible',
                        'occupied' => 'Ocupada',
                        'reserved' => 'Reservada',
                        'maintenance' => 'Mantenimiento',
                    ]),
                
                // Filtro por Mozo
                Tables\Filters\SelectFilter::make('waiter_id')
                    ->label('Mozo')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, $record) {
                        // Verificar si la mesa tiene pedidos asociados
                        if ($record->orders()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se puede eliminar')
                                ->body('Esta mesa tiene pedidos asociados. No puede ser eliminada por integridad de datos.')
                                ->danger()
                                ->send();
                            
                            // Cancelar la acción
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, $records) {
                            // Verificar si alguna mesa tiene pedidos
                            $hasOrders = $records->filter(fn($record) => $record->orders()->exists())->count() > 0;
                            
                            if ($hasOrders) {
                                \Filament\Notifications\Notification::make()
                                    ->title('No se puede eliminar')
                                    ->body('Una o más mesas tienen pedidos asociados. No pueden ser eliminadas.')
                                    ->danger()
                                    ->send();
                                
                                $action->cancel();
                            }
                        }),
                ]),
            ])
            ->defaultSort('number', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTables::route('/'),
            'create' => Pages\CreateTable::route('/create'),
            'edit' => Pages\EditTable::route('/{record}/edit'),
        ];
    }

    // Filtrar por restaurante único + Optimización N+1
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('restaurant_id', 1) // Sistema de restaurante único
            ->with('user'); // Optimización N+1 para columna del mozo
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
            'assign_table',
            'select_table',
        ];
    }
}