<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SearchResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $search_snapshot_id
 * @property int $position
 * @property string $url
 * @property string $url_hash
 * @property string|null $title
 * @property string|null $description
 * @property string|null $source
 * @property CarbonImmutable|null $published_at
 * @property string|null $image_url
 * @property array<string, mixed>|null $meta
 * @property-read SearchSnapshot $snapshot
 */
class SearchResult extends Model
{
    /** @use HasFactory<SearchResultFactory> */
    use HasFactory;

    /**
     * Written only by StoreSearchSnapshot, never from request input.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @return BelongsTo<SearchSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SearchSnapshot::class, 'search_snapshot_id');
    }

    /**
     * The publisher host to show above a result.
     *
     * Google News RSS hands back an opaque news.google.com redirect as the
     * link, so the real publisher only survives in meta.source_url. Brave
     * already resolves a hostname into source. Falling back to the link's own
     * host keeps this total.
     */
    public function displayHost(): string
    {
        $sourceUrl = is_array($this->meta) ? ($this->meta['source_url'] ?? null) : null;

        if (is_string($sourceUrl) && $sourceUrl !== '') {
            $host = parse_url($sourceUrl, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                return $this->stripWww($host);
            }
        }

        if (is_string($this->source) && $this->source !== '') {
            return $this->stripWww($this->source);
        }

        $host = parse_url($this->url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $this->stripWww($host) : $this->url;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    private function stripWww(string $host): string
    {
        return (string) preg_replace('/^www\./i', '', $host);
    }
}
