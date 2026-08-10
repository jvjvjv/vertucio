<?php

namespace App\Models;

use Database\Factories\SearchResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
