<?php

namespace App\Models;

use App\Concerns\RendersResultLinks;
use Carbon\CarbonImmutable;
use Database\Factories\SearchResultFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single result row belonging to a snapshot.
 *
 * Unguarded: written only by StoreSearchSnapshot, never from request input.
 *
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
#[Guarded([])]
class SearchResult extends Model
{
    /** @use HasFactory<SearchResultFactory> */
    use HasFactory, RendersResultLinks;

    /**
     * @return BelongsTo<SearchSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SearchSnapshot::class, 'search_snapshot_id');
    }

    /**
     * Falls back to the Brave thumbnail carried in meta when the row has no
     * image URL of its own.
     */
    public function safeImageUrl(): ?string
    {
        $thumbnail = $this->image_url;

        if (! is_string($thumbnail) || $thumbnail === '') {
            $thumbnail = is_array($this->meta) ? ($this->meta['thumbnail'] ?? null) : null;
        }

        return is_string($thumbnail) ? $this->safeHttpUrl($thumbnail) : null;
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
}
