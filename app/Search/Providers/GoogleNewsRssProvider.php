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
use SimpleXMLElement;
use Throwable;

final class GoogleNewsRssProvider implements SearchProvider
{
    private const ENDPOINT = 'https://news.google.com/rss/search';

    public function name(): string
    {
        return 'google_news_rss';
    }

    public function supports(SearchVertical $vertical): bool
    {
        return $vertical === SearchVertical::News;
    }

    public function search(SearchQuery $query): SearchResultSet
    {
        if (! $this->supports($query->vertical)) {
            throw SearchProviderException::unsupportedVertical($this->name(), $query->vertical);
        }

        $response = Http::timeout(15)
            ->retry(2, 200, throw: false)
            ->withOptions(['allow_redirects' => true])
            ->get(self::ENDPOINT, [
                'q' => $query->query,
                'hl' => "{$query->language}-{$query->country}",
                'gl' => $query->country,
                'ceid' => "{$query->country}:{$query->language}",
            ]);

        if ($response->failed()) {
            throw SearchProviderException::requestFailed($this->name(), $query->vertical, $response->status());
        }

        return new SearchResultSet(
            provider: $this->name(),
            vertical: $query->vertical,
            query: $query->query,
            fetchedAt: Carbon::now(),
            items: $this->mapItems($this->parseXml($response->body(), $query->vertical), $query->limit),
            meta: [],
        );
    }

    private function parseXml(string $body, SearchVertical $vertical): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false || ! isset($xml->channel)) {
            throw SearchProviderException::unexpectedPayload($this->name(), $vertical, 'body is not an RSS channel');
        }

        return $xml;
    }

    /**
     * @return array<int, SearchResultItem>
     */
    private function mapItems(SimpleXMLElement $xml, int $limit): array
    {
        $items = [];
        $position = 1;

        foreach ($xml->channel->item as $item) {
            if ($position > $limit) {
                break;
            }

            $url = trim((string) $item->link);

            if ($url === '') {
                continue;
            }

            $items[] = new SearchResultItem(
                position: $position,
                url: $url,
                title: $this->clean((string) $item->title),
                description: $this->clean((string) $item->description),
                source: $this->clean((string) $item->source),
                publishedAt: $this->parseDate((string) $item->pubDate),
                imageUrl: null,
                meta: ['source_url' => (string) ($item->source['url'] ?? '')],
            );

            $position++;
        }

        return $items;
    }

    /**
     * Google News descriptions are HTML fragments.
     */
    private function clean(string $value): ?string
    {
        $stripped = trim(strip_tags(html_entity_decode($value)));

        return $stripped === '' ? null : $stripped;
    }

    private function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
