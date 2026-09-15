<?php

namespace App\Support;

use Illuminate\Support\Str;

class LeadLocationResolver
{
    public function __construct(protected MarketTimezoneResolver $timezoneResolver) {}

    /** @return array{country: string|null, region: string|null, timezone: string|null} */
    public function resolve(?string $location): array
    {
        $location = trim((string) $location);

        if ($location === '') {
            return ['country' => null, 'region' => null, 'timezone' => null];
        }

        $country = $this->country($location);
        $parts = collect(preg_split('/\s*,\s*/', $location) ?: [])
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();
        $region = trim((string) $parts->first());

        if ($country !== null && $this->normalize($region) === $this->normalize($country)) {
            $region = '';
        }

        return [
            'country' => $country,
            'region' => $region !== '' ? $region : null,
            'timezone' => $this->timezoneResolver->resolve($location),
        ];
    }

    public function country(?string $location): ?string
    {
        $parts = collect(preg_split('/\s*,\s*/', trim((string) $location)) ?: [])
            ->map(fn (string $part): string => $this->normalize($part))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return null;
        }

        foreach ((array) config('lead-markets.country_aliases', []) as $country => $aliases) {
            $known = collect([(string) $country, ...(array) $aliases])
                ->map(fn (string $alias): string => $this->normalize($alias));

            if ($parts->contains(fn (string $part): bool => $known->contains($part))) {
                return (string) $country;
            }
        }

        $region = (string) $parts->get(1, '');

        foreach ((array) config('lead-markets.country_regions', []) as $country => $regions) {
            $knownRegions = collect((array) $regions)
                ->map(fn (string $value): string => $this->normalize($value));

            if ($region !== '' && $knownRegions->contains($region)) {
                return (string) $country;
            }
        }

        $normalizedLocation = $this->normalize((string) $location);
        $city = (string) $parts->first();

        foreach ((array) config('lead-markets.markets', []) as $market) {
            $knownLocations = collect([
                $market['location'] ?? null,
                $market['name'] ?? null,
                ...(array) ($market['aliases'] ?? []),
            ])
                ->map(fn ($value): string => $this->normalize((string) $value))
                ->filter();

            if (($knownLocations->contains($normalizedLocation) || $knownLocations->contains($city))
                && filled($market['country'] ?? null)) {
                return (string) $market['country'];
            }
        }

        return null;
    }

    protected function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish();
    }
}
