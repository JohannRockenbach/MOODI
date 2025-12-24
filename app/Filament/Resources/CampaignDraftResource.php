<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignDraftResource\Pages;
use App\Filament\Resources\CampaignDraftResource\RelationManagers;
use App\Models\CampaignDraft;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CampaignDraftResource extends Resource
{
    protected static ?string $model = CampaignDraft::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    
    protected static ?string $navigationLabel = 'Borradores';
    
    protected static ?string $modelLabel = 'Borrador';
    
    protected static ?string $pluralModelLabel = 'Borradores de Campañas';
    
    protected static ?string $navigationGroup = 'Marketing';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('subject')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name'),
                Forms\Components\TextInput::make('discount_type')
                    ->required()
                    ->maxLength(255)
                    ->default('percentage'),
                Forms\Components\TextInput::make('discount_value')
                    ->numeric(),
                Forms\Components\TextInput::make('coupon_code')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('valid_until'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Descuento')
                    ->formatStateUsing(fn ($state, $record) => 
                        $record->discount_type === 'percentage' ? "{$state}%" : "\${$state}"
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('coupon_code')
                    ->label('Cupón')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('load')
                    ->label('Cargar')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->url(fn (CampaignDraft $record) => 
                        \App\Filament\Pages\SendCampaign::getUrl([
                            'subject' => $record->subject,
                            'body' => $record->body,
                            'product_id' => $record->product_id,
                            'discount_type' => $record->discount_type,
                            'discount_value' => $record->discount_value,
                            'coupon_code' => $record->coupon_code,
                            'valid_until' => $record->valid_until?->format('Y-m-d'),
                        ])
                    ),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListCampaignDrafts::route('/'),
            'create' => Pages\CreateCampaignDraft::route('/create'),
            'edit' => Pages\EditCampaignDraft::route('/{record}/edit'),
        ];
    }
}
