<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Restaurant; // Importar Restaurante
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash; // <-- Importante para la contraseña
use Spatie\Permission\Models\Role; // <-- Importante para Roles
use function __; // Importar traductor

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users'; // Ícono de usuarios
    // Agrupar en Administración y colocar junto al Escritorio
    protected static ?string $navigationGroup = 'Administración';
    protected static ?int $navigationSort = 2;

    // --- Etiquetas en Español ---
    protected static ?string $modelLabel = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';
    protected static ?string $navigationLabel = 'Usuarios';
    // --- Fin Etiquetas ---

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Campo oculto: restaurant_id siempre = 1
                Forms\Components\Hidden::make('restaurant_id')
                    ->default(1),

                Forms\Components\Section::make('Información Personal')
                    ->schema([
                        // Nombre del empleado
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255),

                        // DNI
                        Forms\Components\TextInput::make('dni')
                            ->label('DNI')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('Ej: 12345678'),

                        // Email del empleado
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        // Teléfono
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->placeholder('Ej: +54 9 11 1234-5678'),

                        // Dirección
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        // Fecha de nacimiento
                        Forms\Components\DatePicker::make('birth_date')
                            ->label('Fecha de Nacimiento')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()->subYears(18)), // Mínimo 18 años
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Datos Laborales')
                    ->schema([
                        // Roles (solo empleados: excluye 'cliente')
                        Forms\Components\Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->options(fn() => \Spatie\Permission\Models\Role::where('name', '!=', 'cliente')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        // Turno laboral
                        Forms\Components\Select::make('work_shift')
                            ->label('Turno Laboral')
                            ->options([
                                'dia' => 'Día',
                                'noche' => 'Noche',
                                'mixto' => 'Mixto',
                            ])
                            ->required()
                            ->default('dia'),

                        // Tipo de contrato
                        Forms\Components\Select::make('contract_type')
                            ->label('Tipo de Contrato')
                            ->options([
                                'permanente' => 'Permanente',
                                'temporal' => 'Temporal',
                                'medio_tiempo' => 'Medio Tiempo',
                            ])
                            ->required()
                            ->default('permanente'),

                        // Estado del empleado
                        Forms\Components\Select::make('employment_status')
                            ->label('Estado')
                            ->options([
                                'activo' => 'Activo',
                                'inactivo' => 'Inactivo',
                            ])
                            ->required()
                            ->default('activo')
                            ->live(),

                        // Fecha de inicio
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Fecha de Inicio')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now()),

                        // Fecha de salida (solo visible si está inactivo)
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Fecha de Salida')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->visible(fn (Forms\Get $get): bool => $get('employment_status') === 'inactivo')
                            ->required(fn (Forms\Get $get): bool => $get('employment_status') === 'inactivo'),

                        // Observaciones
                        Forms\Components\Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->placeholder('Notas adicionales sobre el empleado'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Seguridad')
                    ->schema([
                        // Contraseña
                        Forms\Components\TextInput::make('password')
                            ->label(fn (string $operation): string => 
                                $operation === 'create' ? 'Contraseña' : 'Nueva Contraseña (dejar en blanco para mantener la actual)'
                            )
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                            ->minLength(8)
                            ->maxLength(255)
                            ->revealable()
                            ->helperText(fn (string $operation): ?string => 
                                $operation === 'edit' ? 'Solo completa este campo si deseas cambiar la contraseña' : null
                            ),
                        
                        // Confirmación de contraseña (solo al editar)
                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmar Nueva Contraseña')
                            ->password()
                            ->same('password')
                            ->dehydrated(false)
                            ->revealable()
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->requiredWith('password')
                            ->helperText('Repite la nueva contraseña para confirmar'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Nombre del empleado
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                // DNI
                Tables\Columns\TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                // Email del empleado
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                // Teléfono
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Roles asignados (solo empleados)
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->searchable(),

                // Turno laboral
                Tables\Columns\TextColumn::make('work_shift')
                    ->label('Turno')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'dia' => 'info',
                        'noche' => 'warning',
                        'mixto' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'dia' => 'Día',
                        'noche' => 'Noche',
                        'mixto' => 'Mixto',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // Estado del empleado
                Tables\Columns\TextColumn::make('employment_status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'activo' => 'success',
                        'inactivo' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                        default => $state,
                    }),

                // Fecha de inicio
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Fecha Inicio')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Fecha de creación
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employment_status')
                    ->label('Estado del Empleado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ])
                    ->placeholder('Todos'),
                
                Tables\Filters\SelectFilter::make('work_shift')
                    ->label('Turno')
                    ->options([
                        'dia' => 'Día',
                        'noche' => 'Noche',
                        'mixto' => 'Mixto',
                    ])
                    ->placeholder('Todos'),
                
                Tables\Filters\SelectFilter::make('contract_type')
                    ->label('Tipo de Contrato')
                    ->options([
                        'permanente' => 'Permanente',
                        'temporal' => 'Temporal',
                        'medio_tiempo' => 'Medio Tiempo',
                    ])
                    ->placeholder('Todos'),
                
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading(fn (User $record): string => 'Empleado: ' . $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make('Información Personal')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('name')
                                    ->label('Nombre Completo'),
                                \Filament\Infolists\Components\TextEntry::make('dni')
                                    ->label('DNI')
                                    ->placeholder('-'),
                                \Filament\Infolists\Components\TextEntry::make('email')
                                    ->label('Email')
                                    ->copyable()
                                    ->icon('heroicon-o-envelope'),
                                \Filament\Infolists\Components\TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->placeholder('-')
                                    ->icon('heroicon-o-phone'),
                                \Filament\Infolists\Components\TextEntry::make('address')
                                    ->label('Dirección')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                \Filament\Infolists\Components\TextEntry::make('birth_date')
                                    ->label('Fecha de Nacimiento')
                                    ->date('d/m/Y')
                                    ->placeholder('-'),
                            ])
                            ->columns(2),
                        \Filament\Infolists\Components\Section::make('Información Laboral')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('roles.name')
                                    ->label('Roles')
                                    ->badge()
                                    ->placeholder('Sin roles'),
                                \Filament\Infolists\Components\TextEntry::make('work_shift')
                                    ->label('Turno Laboral')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'dia' => 'info',
                                        'noche' => 'warning',
                                        'mixto' => 'success',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'dia' => 'Día',
                                        'noche' => 'Noche',
                                        'mixto' => 'Mixto',
                                        default => $state,
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('contract_type')
                                    ->label('Tipo de Contrato')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'permanente' => 'Permanente',
                                        'temporal' => 'Temporal',
                                        'medio_tiempo' => 'Medio Tiempo',
                                        default => $state,
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('employment_status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'activo' => 'success',
                                        'inactivo' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'activo' => 'Activo',
                                        'inactivo' => 'Inactivo',
                                        default => $state,
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('start_date')
                                    ->label('Fecha de Inicio')
                                    ->date('d/m/Y')
                                    ->placeholder('-'),
                                \Filament\Infolists\Components\TextEntry::make('end_date')
                                    ->label('Fecha de Salida')
                                    ->date('d/m/Y')
                                    ->placeholder('-')
                                    ->visible(fn (User $record): bool => $record->employment_status === 'inactivo'),
                            ])
                            ->columns(2),
                        \Filament\Infolists\Components\Section::make('Observaciones')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('observations')
                                    ->label('')
                                    ->placeholder('Sin observaciones')
                                    ->html()
                                    ->formatStateUsing(fn (?string $state): string => 
                                        $state ? nl2br(e($state)) : 'Sin observaciones'
                                    )
                                    ->columnSpanFull(),
                            ]),
                        \Filament\Infolists\Components\Section::make('Información del Sistema')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->label('Fecha de Creación')
                                    ->dateTime('d/m/Y H:i'),
                                \Filament\Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Última Actualización')
                                    ->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(2)
                            ->collapsed(),
                    ]),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // Filtrar solo usuarios del restaurante ID = 1
            ->where('restaurant_id', 1)
            // Optimización N+1: cargar roles
            ->with('roles')
            // Excluir usuarios con rol 'cliente'
            ->whereDoesntHave('roles', fn (Builder $query) => 
                $query->where('name', 'cliente')
            )
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}