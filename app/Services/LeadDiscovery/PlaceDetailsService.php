<?php

namespace App\Services\LeadDiscovery;

use App\Models\BusinessLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlaceDetailsService
{
    public function enrichBusinessLead(BusinessLead $lead): BusinessLead
    {
        $apiKey = (string) config('leads.google_places_api_key');

        if ($apiKey === '') {
            Log::warning('Google Places API key is not configured.');

            return $lead;
        }

        $endpoint = rtrim((string) config('leads.google_places_endpoint'), '/');
        $regionCode = (string) config('leads.google_places_region_code', 'GB');
        $fieldMask = 'websiteUri,nationalPhoneNumber,internationalPhoneNumber,rating,userRatingCount';

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => $fieldMask,
            ])->timeout(20)
                ->retry(2, 500)
                ->get($endpoint.'/places/'.rawurlencode((string) $lead->place_id), [
                    'regionCode' => $regionCode,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Google Place details lookup failed.', [
                'place_id' => $lead->place_id,
                'error' => $exception->getMessage(),
            ]);

            return $lead;
        }

        if (! $response->ok()) {
            $errorMessage = $response->json('error.message');

            Log::warning('Google Place details returned non-OK response.', [
                'place_id' => $lead->place_id,
                'status_code' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            return $lead;
        }

        $result = $response->json();

        if (! is_array($result)) {
            return $lead;
        }

        $phone = isset($result['nationalPhoneNumber']) ? (string) $result['nationalPhoneNumber'] : null;
        $internationalPhone = isset($result['internationalPhoneNumber']) ? (string) $result['internationalPhoneNumber'] : null;
        $website = isset($result['websiteUri']) ? (string) $result['websiteUri'] : null;
        $rating = isset($result['rating']) ? (float) $result['rating'] : null;
        $reviewCount = isset($result['userRatingCount']) ? (int) $result['userRatingCount'] : null;

        $lead->update([
            'website' => $website ?: $lead->website,
            'phone' => $phone ?: $internationalPhone ?: $lead->phone,
            'mobile_phone' => $this->extractMobileNumber($phone, $internationalPhone, $lead->mobile_phone),
            'rating' => $rating ?? $lead->rating,
            'review_count' => $reviewCount ?? $lead->review_count,
        ]);

        return $lead->fresh() ?? $lead;
    }

    protected function extractMobileNumber(?string $phone, ?string $internationalPhone, ?string $fallback): ?string
    {
        $candidates = [$phone, $internationalPhone];

        foreach ($candidates as $candidate) {
            if (! $candidate) {
                continue;
            }

            $normalized = preg_replace('/\s+/', '', $candidate) ?? '';

            if (str_starts_with($normalized, '07') || str_starts_with($normalized, '+447')) {
                return $candidate;
            }
        }

        return $fallback;
    }

}
