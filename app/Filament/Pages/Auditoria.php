<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Auditoria — supervisión de acciones críticas (TAREA 2.1 / OBJ-09).
 *
 * SOLO super_admin. Tabla de solo lectura: fecha/hora, usuario, acción
 * (label en español), sujeto (tipo + id) y contexto. Filtros por acción,
 * usuario, texto y rango de fecha. Ordenado por created_at desc.
 */
class Auditoria extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string $view = 'filament.pages.auditoria';

    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected static ?string $title = 'Auditoría';

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    /**
     * Mapa action → texto amigable en español (columna + filtro).
     */
    public const ACTION_LABELS = [
        'sale.annulled' => 'Venta anulada',
        'sale.restored' => 'Venta restaurada',
        'sale.created' => 'Venta creada',
        'caja.closed' => 'Caja cerrada',
        'product.price_changed' => 'Precio de producto modificado',
        'user.created' => 'Usuario creado',
        'user.deleted' => 'Usuario eliminado',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? $action;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ActivityLog::query()->with('user'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('—')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn (?string $state, ActivityLog $record): string => $state ?? '—'),

                TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (string $state): string => self::actionLabel($state))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('subject')
                    ->label('Sujeto')
                    ->placeholder('—')
                    ->state(function (ActivityLog $record): string {
                        if (! $record->subject_type || ! $record->subject_id) {
                            return '—';
                        }

                        $short = class_basename($record->subject_type);

                        return $short.' #'.$record->subject_id;
                    })
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->where('subject_type', 'ilike', "%{$search}%")
                            ->orWhere('subject_id', 'ilike', "%{$search}%");
                    }),

                TextColumn::make('context')
                    ->label('Contexto')
                    ->placeholder('—')
                    ->state(function (ActivityLog $record): string {
                        $context = $record->context;

                        if (empty($context)) {
                            return '—';
                        }

                        return collect($context)
                            ->map(fn ($value, string $key): string => $key.': '.$this->contextValue($value))
                            ->implode(' · ');
                    })
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Acción')
                    ->options(self::ACTION_LABELS),

                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde'),
                        DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            // Solo lectura: sin acciones de fila, sin bulk, sin crear/editar.
            ->actions([])
            ->bulkActions([])
            ->paginated([10, 25, 50]);
    }

    private function contextValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        return (string) $value;
    }
}