<?php

namespace App\Services\LeadDiscovery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    /**
     * @return array{
     *     results: array<int, array{name: string, place_id: string, formatted_address: ?string, rating: float|null}>,
     *     status: string,
     *     error_message: string|null
     * }
     */
    public function textSearch(string $query, string $location, string $depthMode = 'standard'): array
    {
        $apiKey = (string) config('leads.google_places_api_key');

        if ($apiKey === '') {
            Log::warning('Google Places API key is not configured.');

            return [
                'results' => [],
                'status' => 'MISSING_API_KEY',
                'error_message' => 'GOOGLE_PLACES_API_KEY is not configured.',
            ];
        }

        $endpoint = rtrim((string) config('leads.google_places_endpoint'), '/');
        $regionCode = (string) config('leads.google_places_region_code', 'GB');
        $depthSettings = $this->resolveDepthMode($depthMode);
        $basePayload = [
            'textQuery' => trim($query.' in '.$location),
            'regionCode' => $regionCode,
        ];
        $allResults = [];
        $nextPageToken = null;
        $pagesFetched = 0;

        do {
            $payload = $basePayload;

            if (is_string($nextPageToken) && $nextPageToken !== '') {
                // Google may require a short delay before the page token becomes active.
                usleep(2_000_000);
                $payload['pageToken'] = $nextPageToken;
            }

            try {
                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.rating,nextPageToken',
                ])->timeout(20)
                    ->retry(2, 500)
                    ->post($endpoint.'/places:searchText', $payload);
            } catch (\Throwable $exception) {
                Log::warning('Google Places text search failed.', [
                    'query' => $query,
                    'location' => $location,
                    'depth_mode' => $depthMode,
                    'pages_fetched' => $pagesFetched,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'results' => [],
                    'status' => 'REQUEST_EXCEPTION',
                    'error_message' => $exception->getMessage(),
                ];
            }

            if (! $response->ok()) {
                $errorMessage = $response->json('error.message');

                Log::warning('Google Places text search returned non-OK response.', [
                    'query' => $query,
                    'location' => $location,
                    'depth_mode' => $depthMode,
                    'pages_fetched' => $pagesFetched,
                    'status_code' => $response->status(),
                    'body' => $response->body(),
                    'error_message' => $errorMessage,
                ]);

                return [
                    'results' => [],
                    'status' => 'HTTP_'.$response->status(),
                    'error_message' => is_string($errorMessage) ? $errorMessage : 'Google Places HTTP request failed.',
                ];
            }

            $results = $response->json('places', []);

            if (! is_array($results)) {
                return [
                    'results' => [],
                    'status' => 'INVALID_RESPONSE',
                    'error_message' => 'Google Places response did not include a valid places array.',
                ];
            }

            $normalized = [];

            foreach ($results as $result) {
                if (! is_array($result) || empty($result['id'])) {
                    continue;
                }

                $nameField = $result['displayName'] ?? null;
                $name = null;

                if (is_array($nameField) && isset($nameField['text']) && is_string($nameField['text'])) {
                    $name = $nameField['text'];
                } elseif (is_string($nameField) && $nameField !== '') {
                    $name = $nameField;
                }

                if (! $name) {
                    continue;
                }

                $normalized[] = [
                    'name' => $name,
                    'place_id' => (string) $result['id'],
                    'formatted_address' => isset($result['formattedAddress']) ? (string) $result['formattedAddress'] : null,
                    'rating' => isset($result['rating']) ? (float) $result['rating'] : null,
                ];
            }

            $allResults = array_merge($allResults, $normalized);
            $pagesFetched++;
            $nextPageToken = $response->json('nextPageToken');
        } while (
            is_string($nextPageToken)
            && $nextPageToken !== ''
            && $pagesFetched < $depthSettings['max_pages']
            && count($allResults) < $depthSettings['max_results']
        );

        if (count($allResults) > $depthSettings['max_results']) {
            $allResults = array_slice($allResults, 0, $depthSettings['max_results']);
        }

        return [
            'results' => $allResults,
            'status' => $allResults === [] ? 'ZERO_RESULTS' : 'OK',
            'error_message' => null,
        ];
    }

    /**
     * @return array{max_pages: int, max_results: int}
     */
    protected function resolveDepthMode(string $depthMode): array
    {
        $modes = config('leads.scan_depth_modes', []);
        $defaultMode = (string) config('leads.scan_depth_default', 'standard');
        $mode = array_key_exists($depthMode, $modes) ? $depthMode : $defaultMode;
        $selected = $modes[$mode] ?? ['max_pages' => 3, 'max_results' => 60];

        return [
            'max_pages' => max((int) ($selected['max_pages'] ?? 1), 1),
            'max_results' => max((int) ($selected['max_results'] ?? 20), 20),
        ];
    }
}
