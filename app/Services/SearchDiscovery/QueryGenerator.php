<?php

namespace App\Services\SearchDiscovery;

class QueryGenerator
{
    /**
     * @param  array<int, string>  $phrases
     * @return array<int, string>
     */
    public function generate(string $city, string $niche, array $phrases): array
    {
        $city = trim($city);
        $niche = trim($niche);

        $queries = [];

        foreach ($phrases as $phrase) {
            $phrase = trim($phrase);

            if ($phrase === '' || $city === '' || $niche === '') {
                continue;
            }

            $queries[] = sprintf('site:instagram.com "%s" "%s" "%s"', $phrase, $niche, $city);
        }

        return array_values(array_unique($queries));
    }
}
