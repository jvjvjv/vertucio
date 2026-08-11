<?php

namespace App\Search\Providers;

use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use App\Search\Exceptions\SearchProviderException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

final class BraveSearchProvider implements SearchProvider
{
    /**
     * @var array<string, string>
     */
    private const ENDPOINTS = [
        'web' => 'https://api.search.brave.com/res/v1/web/search',
        'news' => 'https://api.search.brave.com/res/v1/news/search',
        'images' => 'https://api.search.brave.com/res/v1/images/search',
    ];

    /**
     * Where the result array lives in each vertical's payload. Web nests its
     * results; news and images return them at the top level.
     *
     * @var array<string, string>
     */
    private const RESULTS_PATH = [
        'web' => 'web.results',
        'news' => 'results',
        'images' => 'results',
    ];

    public function name(): string
    {
        return 'brave';
    }

    public function supports(SearchVertical $vertical): bool
    {
        return true;
    }

    public function search(SearchQuery $query): SearchResultSet
    {
        $response = Http::withHeaders([
            'X-Subscription-Token' => (string) config('services.brave.key'),
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->retry(2, 200, throw: false)
            ->get(self::ENDPOINTS[$query->vertical->value], [
                'q' => $query->query,
                'count' => $query->limit,
                'country' => $query->country,
                'search_lang' => $query->language,
            ]);

        if ($response->failed()) {
            throw SearchProviderException::requestFailed($this->name(), $query->vertical, $response->status());
        }

        $path = self::RESULTS_PATH[$query->vertical->value];
        $results = data_get($response->json(), $path);

        if (! is_array($results)) {
            throw SearchProviderException::unexpectedPayload(
                $this->name(),
                $query->vertical,
                "missing \"{$path}\" array"
            );
        }

        return new SearchResultSet(
            provider: $this->name(),
            vertical: $query->vertical,
            query: $query->query,
            fetchedAt: Carbon::now(),
            items: $this->excludeTerms($this->mapItems($results, $query->vertical), $query->query),
            meta: ['altered_query' => data_get($response->json(), 'query.altered')],
        );
    }

    /**
     * Brave's Web and News endpoints honor a "-term" exclusion in the query
     * themselves, but Images does not — it returns unfiltered results
     * regardless. Enforcing exclusions here, after the fact, makes the
     * guarantee hold for every vertical no matter what Brave's own query
     * parsing does with it.
     *
     * @param  array<int, SearchResultItem>  $items
     * @return array<int, SearchResultItem>
     */
    private function excludeTerms(array $items, string $query): array
    {
        $excluded = $this->excludedTerms($query);

        if ($excluded === []) {
            return array_values($items);
        }

        return array_values(array_filter(
            $items,
            fn (SearchResultItem $item): bool => ! $this->matchesExcludedTerm($item, $excluded)
        ));
    }

    /**
     * @param  array<int, string>  $excluded
     */
    private function matchesExcludedTerm(SearchResultItem $item, array $excluded): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $item->title, $item->description, $item->url, $item->source,
        ])));

        foreach ($excluded as $term) {
            if (str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function excludedTerms(string $query): array
    {
        preg_match_all('/-(\S+)/', $query, $matches);

        return array_map(mb_strtolower(...), $matches[1]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, SearchResultItem>
     */
    private function mapItems(array $results, SearchVertical $vertical): array
    {
        $items = [];
        $position = 1;

        foreach (array_values($results) as $result) {
            $url = data_get($result, 'url');

            if (! is_string($url) || $url === '') {
                continue;
            }

            $items[] = new SearchResultItem(
                position: $position,
                url: $url,
                title: $this->clean(data_get($result, 'title')),
                description: $this->clean(data_get($result, 'description')),
                source: $this->clean(data_get($result, 'meta_url.hostname') ?? data_get($result, 'source')),
                publishedAt: $this->parseDate(data_get($result, 'page_age') ?? data_get($result, 'age')),
                imageUrl: $vertical === SearchVertical::Images
                    ? $this->clean(data_get($result, 'properties.url') ?? data_get($result, 'thumbnail.src'))
                    : null,
                meta: ['thumbnail' => data_get($result, 'thumbnail.src')],
            );

            $position++;
        }

        return $items;
    }

    /**
     * Brave wraps query term matches in <strong> tags.
     */
    private function clean(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return html_entity_decode(strip_tags($value));
    }

    /**
     * Brave returns ISO-8601 in "page_age" but human phrasing ("November 8, 2022",
     * "about 3 hours ago") in "age". Only a strict ISO parse is trustworthy.
     */
    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
