<?php

namespace App\Console\Commands;

use App\Models\SponsoredResult;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ListSponsoredResults extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sponsored:list';

    /**
     * @var string
     */
    protected $description = 'List every sponsored result with its current status';

    public function handle(): int
    {
        $promos = SponsoredResult::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($promos->isEmpty()) {
            $this->components->warn('No sponsored results yet. Add one with "sponsored:add".');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Pos', 'Status', 'Starts', 'Ends', 'Title', 'Host'],
            $promos->map(fn (SponsoredResult $promo): array => [
                $promo->id,
                $promo->position,
                ucfirst($promo->status()->value),
                $promo->starts_at?->toDateTimeString() ?? '—',
                $promo->ends_at?->toDateTimeString() ?? '—',
                Str::limit($promo->title, 40),
                $promo->displayHost(),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
