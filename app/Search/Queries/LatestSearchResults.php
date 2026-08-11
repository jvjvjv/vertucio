<?php

namespace App\Search\Queries;

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Search\Enums\SearchVertical;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read model over the immutable snapshot history.
 *
 * Snapshots are append-only, so "latest" is the highest id per provider. A
 * vertical may be served by more than one provider (news comes from both
 * Brave and Google News RSS), so results are merged across them.
 */
final class LatestSearchResults
{
    /**
     * @return Collection<int, SearchResult>
     */
    public function for(SearchVertical $vertical, int $limit): Collection
    {
        $snapshotIds = $this->latestSnapshotIds($vertical);

        if ($snapshotIds === []) {
            return collect();
        }

        return SearchResult::query()
            ->whereIn('search_snapshot_id', $snapshotIds)
            ->with('snapshot')
            ->orderBy('position')
            ->orderBy('search_snapshot_id')
            ->limit($limit)
            ->get();
    }

    /**
     * Every section on the search page paginates independently, so callers
     * pass their own $pageName — see Livewire's named-paginator support.
     *
     * @return LengthAwarePaginator<int, SearchResult>
     */
    public function paginate(SearchVertical $vertical, int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        $snapshotIds = $this->latestSnapshotIds($vertical);

        if ($snapshotIds === []) {
            return new LengthAwarePaginator([], 0, $perPage, null, ['pageName' => $pageName]);
        }

        return SearchResult::query()
            ->whereIn('search_snapshot_id', $snapshotIds)
            ->with('snapshot')
            ->orderBy('position')
            ->orderBy('search_snapshot_id')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * Whether a fetch has ever landed for this vertical. Distinct from having
     * zero results: "not collected" and "collected, found nothing" are
     * different facts and the UI states for them differ.
     */
    public function wasCollected(SearchVertical $vertical): bool
    {
        return $this->latestSnapshotIds($vertical) !== [];
    }

    public function lastCollectedAt(SearchVertical $vertical): ?Carbon
    {
        $fetchedAt = SearchSnapshot::query()
            ->where('vertical', $vertical)
            ->max('fetched_at');

        return $fetchedAt === null ? null : Carbon::parse($fetchedAt);
    }

    /**
     * The newest snapshot id for each provider serving this vertical.
     *
     * @return array<int, int>
     */
    private function latestSnapshotIds(SearchVertical $vertical): array
    {
        /** @var array<int, int> $ids */
        $ids = SearchSnapshot::query()
            ->where('vertical', $vertical)
            ->groupBy('provider')
            ->pluck(SearchSnapshot::raw('MAX(id)'))
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }
}
