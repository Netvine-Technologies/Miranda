<?php

namespace App\Support;

use Illuminate\Support\Str;

class MarketTimezoneResolver
{
    public function resolve(?string $location): ?string
    {
        $rawLocation = trim((string) $location);

        if ($rawLocation === '') {
            return null;
        }

        $normalizedLocation = $this->normalize($rawLocation);
        $marketTimezone = $this->resolveConfiguredMarket($normalizedLocation);

        if ($marketTimezone !== null) {
            return $marketTimezone;
        }

        $parts = collect(preg_split('/\s*,\s*/', $rawLocation) ?: [])
            ->map(fn (string $part): string => $this->normalize($part))
            ->filter()
            ->values();
        $city = (string) $parts->first();
        $region = (string) $parts->get(1, '');
        $country = (string) $parts->last();

        $regionTimezone = $this->timezoneFromMap(
            'lead-markets.region_timezones',
            trim($region.' '.$country),
        ) ?? $this->timezoneFromMap('lead-markets.region_timezones', $region);

        if ($regionTimezone !== null) {
            return $regionTimezone;
        }

        $cityTimezone = $this->timezoneFromMap('lead-markets.city_timezones', $city);

        if ($cityTimezone !== null) {
            return $cityTimezone;
        }

        return $this->timezoneFromMap('lead-markets.country_timezones', $country);
    }

    protected function resolveConfiguredMarket(string $location): ?string
    {
        foreach ((array) config('lead-markets.markets', []) as $market) {
            if (! is_array($market)) {
                continue;
            }

            $knownLocation = $this->normalize((string) ($market['location'] ?? ''));
            $city = $this->normalize((string) ($market['name'] ?? ''));
            $aliases = collect((array) ($market['aliases'] ?? []))
                ->map(fn ($alias): string => $this->normalize((string) $alias));

            $matches = $location === $knownLocation
                || $aliases->contains($location)
                || ($city !== '' && $location === $city);

            if ($matches && filled($market['timezone'] ?? null)) {
                return (string) $market['timezone'];
            }
        }

        return null;
    }

    protected function timezoneFromMap(string $configKey, string $lookup): ?string
    {
        if ($lookup === '') {
            return null;
        }

        foreach ((array) config($configKey, []) as $key => $timezone) {
            if ($this->normalize((string) $key) === $lookup && filled($timezone)) {
                return (string) $timezone;
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
