<?php

namespace App\Models;

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
    use HasFactory;

    /**
     * @return BelongsTo<SearchSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SearchSnapshot::class, 'search_snapshot_id');
    }

    /**
     * The link target, or null when the stored URL is not safe to navigate to.
     *
     * Result URLs arrive from third-party search APIs and are never authored
     * here. Blade escaping protects the attribute boundary but not the scheme,
     * so a javascript:, data:, or vbscript: URL would still execute on click.
     * Callers render unlinked text when this returns null.
     */
    public function safeUrl(): ?string
    {
        return $this->safeHttpUrl($this->url);
    }

    /**
     * The thumbnail to render, or null when the stored value is not a plain
     * http(s) image reference.
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

    private function stripWww(string $host): string
    {
        return (string) preg_replace('/^www\./i', '', $host);
    }

    /**
     * Allow-list http and https. Anything else — including scheme-relative and
     * schemeless values, which resolve unpredictably — is rejected.
     */
    private function safeHttpUrl(string $url): ?string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return null;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return null;
        }

        return in_array(strtolower($scheme), ['http', 'https'], true) ? $trimmed : null;
    }
}
