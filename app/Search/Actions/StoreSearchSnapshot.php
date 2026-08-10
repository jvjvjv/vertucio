<?php

namespace App\Search\Actions;

use App\Models\SearchSnapshot;
use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use Illuminate\Support\Facades\DB;

final class StoreSearchSnapshot
{
    /**
     * Persist one provider/vertical fetch as an immutable snapshot.
     */
    public function handle(SearchResultSet $resultSet): SearchSnapshot
    {
        return DB::transaction(function () use ($resultSet): SearchSnapshot {
            $snapshot = SearchSnapshot::create([
                'provider' => $resultSet->provider,
                'vertical' => $resultSet->vertical,
                'query' => $resultSet->query,
                'fetched_at' => $resultSet->fetchedAt,
                'result_count' => $resultSet->count(),
                'meta' => $resultSet->meta,
            ]);

            if ($resultSet->items !== []) {
                $snapshot->results()->createMany(array_map(
                    static fn (SearchResultItem $item): array => [
                        'position' => $item->position,
                        'url' => $item->url,
                        'url_hash' => hash('sha256', $item->url),
                        'title' => $item->title,
                        'description' => $item->description,
                        'source' => $item->source,
                        'published_at' => $item->publishedAt,
                        'image_url' => $item->imageUrl,
                        'meta' => $item->meta,
                    ],
                    $resultSet->items,
                ));
            }

            return $snapshot;
        });
    }
}
