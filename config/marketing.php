<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración de Marketing Automático
    |--------------------------------------------------------------------------
    |
    | Configuraciones para las campañas automáticas basadas en clima y stock
    |
    */

    'weather' => [
        /*
        |--------------------------------------------------------------------------
        | Umbrales de Temperatura para Promociones
        |--------------------------------------------------------------------------
        |
        | Define las temperaturas que activan diferentes tipos de campañas.
        | Puedes ajustar estos valores según tu región y productos.
        |
        */
        'thresholds' => [
            'extreme_heat' => [
                'min_temp' => 32,  // °C - Temperatura mínima para considerar calor extremo
                'discount' => 20,   // % de descuento
                'coupon_prefix' => 'CALOR',
                'products' => ['Pinta', 'Cerveza', 'Chopp', 'Helado', 'Postre Frío'],
                'min_stock' => 15,
            ],
            
            'cold' => [
                'max_temp' => 15,   // °C - Temperatura máxima para considerar frío
                'discount' => 20,   // % de descuento
                'coupon_prefix' => 'FRIO',
                'products' => ['Guiso', 'Sopa', 'Caldo', 'Doble', 'Double', 'Cuarto'],
                'min_stock' => 10,
            ],
            
            'rainy' => [
                'discount' => 15,   // % de descuento
                'coupon_prefix' => 'LLUVIA',
                'products' => ['Combo', 'Menu', 'Hamburguesa', 'Empanada', 'Pizza'],
                'min_stock' => 12,
            ],
            
            'hot' => [
                'min_temp' => 25,   // °C - Temperatura para considerar calor moderado
                'max_temp' => 32,   // °C - Límite antes del calor extremo
                'discount' => 15,   // % de descuento
                'coupon_prefix' => 'VERANO',
                'products' => ['Bebidas', 'Coca', 'Sprite', 'Fanta'],
                'min_stock' => 8,
            ],
            
            'pleasant' => [
                'min_temp' => 18,   // °C - Temperatura mínima para clima agradable
                'max_temp' => 25,   // °C - Temperatura máxima para clima agradable
                'discount' => 10,   // % de descuento
                'coupon_prefix' => 'DIA',
                'products' => ['Burger Clásica', 'Cheeseburger', 'Pinta IPA', 'Coca-Cola 350ml'],
                'min_stock' => 5,
            ],
        ],
    ],

    'stock' => [
        /*
        |--------------------------------------------------------------------------
        | Configuración de Alertas de Stock/Vencimiento
        |--------------------------------------------------------------------------
        */
        'expiry_warning_days' => 3,     // Días antes del vencimiento para alertar
        'critical_stock_multiplier' => 0.2, // 20% del stock mínimo
        'default_discount' => 30,       // % de descuento por defecto
        'default_coupon' => 'NOPIERDO',
    ],
];
