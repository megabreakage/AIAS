<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Central\Country;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.google_maps.key', '');
    }

    /**
     * Geocode an office location string.
     * Returns null values if geocoding fails, API key not set, or location not found.
     *
     * @return array{latitude: float|null, longitude: float|null, country_id: int|null}
     */
    public function geocode(string $officeLocation): array
    {
        if (empty($this->apiKey)) {
            return ['latitude' => null, 'longitude' => null, 'country_id' => null];
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $officeLocation,
                'key' => $this->apiKey,
            ]);

            if (! $response->ok()) {
                return ['latitude' => null, 'longitude' => null, 'country_id' => null];
            }

            $result = $response->json();

            if (($result['status'] ?? '') !== 'OK' || empty($result['results'])) {
                return ['latitude' => null, 'longitude' => null, 'country_id' => null];
            }

            $location = $result['results'][0]['geometry']['location'];
            $latitude = (float) $location['lat'];
            $longitude = (float) $location['lng'];
            $countryId = $this->resolveCountryId($result['results'][0]['address_components'] ?? []);

            return ['latitude' => $latitude, 'longitude' => $longitude, 'country_id' => $countryId];
        } catch (\Throwable $e) {
            Log::warning('Geocoding failed', [
                'location' => $officeLocation,
                'error' => $e->getMessage(),
            ]);

            return ['latitude' => null, 'longitude' => null, 'country_id' => null];
        }
    }

    /**
     * Extract country ID from address components.
     *
     * @param  array<int, array<string, mixed>>  $addressComponents
     */
    private function resolveCountryId(array $addressComponents): ?int
    {
        $shortCode = null;

        foreach ($addressComponents as $component) {
            if (in_array('country', (array) ($component['types'] ?? []), true)) {
                $shortCode = $component['short_name'] ?? null;
                break;
            }
        }

        if (! $shortCode) {
            return null;
        }

        /** @var Country|null $country */
        $country = Country::on('central')->where('short_code', $shortCode)->first();

        return $country?->id;
    }
}
