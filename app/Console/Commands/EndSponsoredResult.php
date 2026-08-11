<?php

namespace App\Console\Commands;

use App\Models\SponsoredResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class EndSponsoredResult extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sponsored:end
                            {id : The sponsored result to retire}
                            {--at= : When it comes down; defaults to now}';

    /**
     * @var string
     */
    protected $description = 'Close a sponsored result window so it stops rendering';

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $promo = SponsoredResult::query()->find($id);

        if ($promo === null) {
            $this->components->error("No sponsored result with id [{$id}].");

            return self::FAILURE;
        }

        $at = $this->option('at');

        try {
            $endsAt = is_string($at) && trim($at) !== ''
                ? CarbonImmutable::parse(trim($at))
                : CarbonImmutable::now();
        } catch (Throwable) {
            $this->components->error('The --at value must be parseable, for example "2026-09-01 09:00".');

            return self::FAILURE;
        }

        $promo->update(['ends_at' => $endsAt]);

        $this->components->info(
            "Sponsored result #{$promo->id} ends at {$endsAt->toDateTimeString()} ({$promo->status()->value})."
        );

        return self::SUCCESS;
    }
}
