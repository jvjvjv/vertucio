<?php

namespace App\Console\Commands;

use App\Search\Actions\StoreSearchSnapshot;
use App\Search\SearchManager;
use Illuminate\Console\Command;
use Throwable;

final class FetchSearchResults extends Command
{
    /**
     * @var string
     */
    protected $signature = 'search:fetch
                            {query? : The term to search for; defaults to the configured query}';

    /**
     * @var string
     */
    protected $description = 'Fetch search results from every configured provider and store them as snapshots';

    public function handle(SearchManager $manager, StoreSearchSnapshot $store): int
    {
        $query = $this->argument('query') ?? (string) config('search.query');
        $failed = false;

        foreach ($manager->pairs() as $pair) {
            $provider = $pair['provider'];
            $vertical = $pair['vertical'];
            $label = "{$provider->name()}/{$vertical->value}";

            try {
                $snapshot = $store->handle($manager->fetch($provider, $vertical, $query));

                $this->components->info("{$label}: stored {$snapshot->result_count} results.");
            } catch (Throwable $exception) {
                $failed = true;

                report($exception);

                $this->components->error("{$label}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
