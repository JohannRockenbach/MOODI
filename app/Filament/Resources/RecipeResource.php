<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecipeResource\Pages;
use App\Models\Recipe;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use function __; // Importar traductor

class RecipeResource extends Resource
{
    protected static ?string $model = Recipe::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Gestión del Menú';
    protected static ?int $navigationSort = 3;

    // --- Etiquetas en Español ---
    protected static ?string $modelLabel = 'Receta';
    protected static ?string $pluralModelLabel = 'Recetas';
    protected static ?string $navigationLabel = 'Recetas';
    // --- Fin Etiquetas ---

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre de la Receta')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Ya existe una receta con este nombre.',
                    ])
                    ->columnSpanFull()
                    ->placeholder('Ej: Pizza Margarita, Hamburguesa Clásica'),

                Forms\Components\Textarea::make('instructions')
                    ->label('Instrucciones')
                    ->required()
                    ->columnSpanFull()
                    ->rows(4)
                    ->placeholder('Describe el proceso de preparación paso a paso...')
                    ->helperText('Las instrucciones son obligatorias para crear la receta'),

                // REPEATER MÁS IMPORTANTE: Relación con Ingredientes
                Forms\Components\Repeater::make('ingredientsData')
                    ->label('Ingredientes')
                    ->schema([
                        Forms\Components\Select::make('ingredient_id')
                            ->label('Ingrediente')
                            ->options(\App\Models\Ingredient::all()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('required_amount')
                            ->label('Cantidad Requerida')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0.001)
                            ->step(0.01)
                            ->helperText('Cantidad necesaria de este ingrediente')
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->addActionLabel('Agregar Ingrediente')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => 
                        \App\Models\Ingredient::find($state['ingredient_id'])?->name ?? 'Ingrediente'
                    )
                    ->saveRelationshipsUsing(function ($component, $state, $record) {
                        $record->ingredients()->sync(
                            collect($state)->mapWithKeys(fn ($item) => [
                                $item['ingredient_id'] => ['required_amount' => $item['required_amount']]
                            ])
                        );
                    })
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record && $record->exists) {
                            $component->state(
                                $record->ingredients->map(fn ($ingredient) => [
                                    'ingredient_id' => $ingredient->id,
                                    'required_amount' => $ingredient->pivot->required_amount,
                                ])->toArray()
                            );
                        }
                    })
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre de la Receta')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // COLUMNA IMPORTANTE: Conteo de ingredientes
                Tables\Columns\TextColumn::make('ingredients_count')
                    ->label('Ingredientes')
                    ->counts('ingredients')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (string $state): string => 
                        $state . ' ' . ($state == 1 ? 'ingrediente' : 'ingredientes')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('instructions')
                    ->label('Instrucciones')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('primary'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Relación de ingredientes ya manejada en el Repeater del formulario
        ];
    }

    // MÉTODO IMPORTANTE: Prevenir N+1 con withCount
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount('ingredients'); // Optimización para evitar queries N+1
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecipes::route('/'),
            'create' => Pages\CreateRecipe::route('/create'),
            'edit' => Pages\EditRecipe::route('/{record}/edit'),
        ];
    }
}