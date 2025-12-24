<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use function __; // <-- AÑADIDO: Importa la función de traducción

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Gestión del Menú';
    protected static ?int $navigationSort = 1;


    // --- AÑADIDO: Etiquetas para el menú y títulos ---
    protected static ?string $modelLabel = 'Categoría';
    protected static ?string $pluralModelLabel = 'Categorías';
    protected static ?string $navigationLabel = 'Categorías';
    // --- FIN DE LO AÑADIDO ---

    // Sobrescribir getEloquentQuery para hacer eager loading de products y children
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['products', 'children']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Categoría')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Categoría')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Hamburguesas, Bebidas, Papas Fritas')
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Descripción opcional de la categoría...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                
                Forms\Components\Section::make('Productos de esta Categoría')
                    ->schema([
                        Forms\Components\Select::make('products')
                            ->label('Asignar Productos')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Selecciona los productos que pertenecen a esta categoría. Para crear nuevos productos, ve a la sección Productos.')
                            ->placeholder('Selecciona uno o más productos...')
                            ->columnSpanFull(),
                    ])
                    ->description('Los productos que selecciones aquí se asociarán automáticamente a esta categoría.')
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Category $record): ?string => $record->parent ? 'Subcategoría de: ' . $record->parent->name : null),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Categoría padre')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->default('Principal')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Show count of products and a ViewColumn that mounts a Livewire panel
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Productos')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'warning')
                    ->icon('heroicon-o-cube'),

                Tables\Columns\ViewColumn::make('productos_panel')
                    ->label('')
                    ->view('filament.categories.products_panel')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('ver_productos')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading(fn (Category $record): string => 'Productos de: ' . $record->name)
                    ->modalContent(fn (Category $record): \Illuminate\Contracts\View\View => view('filament.categories.products-modal', [
                        'category' => $record,
                        'products' => $record->products,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->slideOver()
                    ->visible(fn (Category $record): bool => $record->products->count() > 0),
                
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Custom bulk delete action with reassignment option
                    Tables\Actions\BulkAction::make('eliminar_con_opciones')
                        ->label('Eliminar con opciones')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->modalHeading('Eliminar categorías seleccionadas')
                        ->form(fn (\Illuminate\Database\Eloquent\Collection $records) => [
                            Forms\Components\Select::make('reassign_to')
                                ->label('Reasignar productos a')
                                ->options(function () use ($records) {
                                    // Excluir las categorías que se están eliminando
                                    $categoriesToDelete = $records->pluck('id')->toArray();
                                    return Category::whereNotIn('id', $categoriesToDelete)->pluck('name', 'id');
                                })
                                ->nullable()
                                ->searchable()
                                ->helperText('Selecciona una categoría diferente para reasignar los productos'),
                            Forms\Components\Checkbox::make('delete_products')
                                ->label('Eliminar productos existentes')
                                ->helperText('Si marcas esto se eliminarán todos los productos asociados a estas categorías'),
                        ])
                        ->modalSubmitActionLabel('Confirmar eliminación')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            $reassign = $data['reassign_to'] ?? null;
                            $deleteProducts = $data['delete_products'] ?? false;
                            
                            // Validar que no se intente reasignar a una categoría que se está eliminando
                            if ($reassign) {
                                $categoryIdsToDelete = $records->pluck('id')->toArray();
                                if (in_array($reassign, $categoryIdsToDelete)) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Error de validación')
                                        ->body('No puedes reasignar productos a una categoría que estás eliminando. Selecciona otra categoría.')
                                        ->persistent()
                                        ->send();
                                    return;
                                }
                            }
                            
                            // VALIDACIÓN PREVIA: Verificar si hay productos y no hay acción seleccionada
                            if (!$reassign && !$deleteProducts) {
                                foreach ($records as $record) {
                                    /** @var \App\Models\Category $record */
                                    $productCount = \App\Models\Product::where('category_id', $record->id)->count();
                                    if ($productCount > 0) {
                                        \Filament\Notifications\Notification::make()
                                            ->danger()
                                            ->title('No se puede eliminar')
                                            ->body('La categoría "' . $record->name . '" tiene ' . $productCount . ' producto(s). Debes seleccionar una opción: reasignar productos a otra categoría o marcar el checkbox para eliminar los productos.')
                                            ->persistent()
                                            ->send();
                                        return;
                                    }
                                }
                            }
                            
                            \Illuminate\Support\Facades\DB::transaction(function () use ($records, $reassign, $deleteProducts) {
                                foreach ($records as $record) {
                                    /** @var \App\Models\Category $record */
                                    if ($reassign) {
                                        // Reasignar productos a otra categoría (incluye soft deleted)
                                        \App\Models\Product::withTrashed()
                                            ->where('category_id', $record->id)
                                            ->update(['category_id' => $reassign]);
                                    } elseif ($deleteProducts) {
                                        // Eliminar productos (FORCE DELETE - eliminación permanente)
                                        // Incluir productos con soft delete
                                        $products = \App\Models\Product::withTrashed()
                                            ->where('category_id', $record->id)
                                            ->get();
                                        
                                        foreach ($products as $product) {
                                            $product->forceDelete();
                                        }
                                    }

                                    $record->delete();
                                }
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Categorías eliminadas correctamente')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}