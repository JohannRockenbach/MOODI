<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /**
     * API URL de Open-Meteo
     */
    private const API_URL = 'https://api.open-meteo.com/v1/forecast';

    /**
     * Coordenadas de Apóstoles, Misiones, Argentina
     */
    private const LATITUDE = -27.91;
    private const LONGITUDE = -55.76;

    /**
     * Códigos WMO de weather_code que indican lluvia (incluyendo tormentas)
     * @see https://open-meteo.com/en/docs
     */
    private const RAIN_WEATHER_CODES = [
        51, // Llovizna ligera
        53, // Llovizna moderada
        55, // Llovizna densa
        61, // Lluvia ligera
        63, // Lluvia moderada
        65, // Lluvia fuerte
        80, // Chubascos ligeros
        81, // Chubascos moderados
        82, // Chubascos violentos
        95, // Tormenta
        96, // Tormenta con granizo ligero
        99, // Tormenta con granizo fuerte
    ];

    /**
     * Obtiene los datos actuales del clima desde Open-Meteo
     *
     * @return array|null Array con los datos del clima o null si falla
     */
    public function getCurrentWeather(): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::API_URL, [
                'latitude' => self::LATITUDE,
                'longitude' => self::LONGITUDE,
                'current' => 'temperature_2m,is_day,precipitation,rain,weather_code',
                'timezone' => 'auto',
                'forecast_days' => 1,
            ]);

            if ($response->failed()) {
                Log::error('Open-Meteo API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'latitude' => self::LATITUDE,
                    'longitude' => self::LONGITUDE,
                ]);

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Error al obtener datos del clima', [
                'error' => $e->getMessage(),
                'latitude' => self::LATITUDE,
                'longitude' => self::LONGITUDE,
            ]);

            return null;
        }
    }

    /**
     * Determina si está lloviendo según los datos del clima
     *
     * @param array|null $data Datos retornados por getCurrentWeather()
     * @return bool True si está lloviendo, false en caso contrario
     */
    public function isRaining(?array $data): bool
    {
        // Si no hay datos, retornar false
        if ($data === null) {
            return false;
        }

        // Verificar si existe la sección 'current' en la respuesta
        if (!isset($data['current'])) {
            return false;
        }

        $current = $data['current'];

        // Verificar precipitación actual (mm)
        if (isset($current['precipitation']) && $current['precipitation'] > 0) {
            return true;
        }

        // Verificar lluvia actual (mm)
        if (isset($current['rain']) && $current['rain'] > 0) {
            return true;
        }

        // Verificar código WMO del clima
        if (isset($current['weather_code'])) {
            $weatherCode = (int) $current['weather_code'];
            if (in_array($weatherCode, self::RAIN_WEATHER_CODES, true)) {
                return true;
            }
        }

        return false;
    }

}
