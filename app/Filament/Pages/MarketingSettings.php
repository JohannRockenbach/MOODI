<?php

namespace App\Filament\Pages;

use App\Models\Restaurant;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MarketingSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationLabel = 'Configuración de Marketing';
    protected static ?string $title = 'Configuración de Automatización';
    protected static ?string $navigationGroup = 'Administración';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static bool $shouldRegisterNavigation = true;

    protected static string $view = 'filament.pages.marketing-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $restaurant = Restaurant::find(1);
        $settings = $restaurant?->marketing_settings ?? [
            'temp_heat_threshold' => 28,
            'temp_cold_threshold' => 15,
            'rain_probability' => 50,
            'promo_messages' => [],
        ];
        
        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Clima y Entorno')
                    ->description('Parámetros para automatizaciones basadas en clima')
                    ->schema([
                        TextInput::make('temp_heat_threshold')
                            ->label('Umbral Calor (°C)')
                            ->numeric()
                            ->default(28)
                            ->required(),

                        TextInput::make('temp_cold_threshold')
                            ->label('Umbral Frío (°C)')
                            ->numeric()
                            ->default(15)
                            ->required(),

                        TextInput::make('rain_probability')
                            ->label('Mínimo Probabilidad Lluvia (%)')
                            ->numeric()
                            ->default(50)
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                    ])->columns(3),

                Section::make('Mensajes Creativos')
                    ->description('Define frases de marketing para tus promociones')
                    ->schema([
                        Repeater::make('promo_messages')
                            ->label('Frases de Marketing')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Título Gancero')
                                    ->placeholder('Ej: ¡Olvídate de cocinar!')
                                    ->required(),
                                Textarea::make('body')
                                    ->label('Cuerpo del Mensaje')
                                    ->rows(3)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Agregar frase')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Nueva frase')
                            ->collapsed()
                            ->default([]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar Cambios')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $restaurant = Restaurant::find(1);
        if (!$restaurant) {
            Notification::make()
                ->title('Restaurante no encontrado')
                ->body('No se pudo encontrar el Restaurante con ID 1.')
                ->danger()
                ->send();
            return;
        }

        $restaurant->marketing_settings = $data;
        $restaurant->save();

        Notification::make()
            ->title('✅ Configuración guardada')
            ->body('Los parámetros de automatización se actualizaron correctamente.')
            ->success()
            ->send();
    }
}
