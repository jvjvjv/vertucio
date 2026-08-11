<?php

namespace App\Console\Commands;

use App\Models\SponsoredResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\text;

final class AddSponsoredResult extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sponsored:add
                            {--url= : Where the promo links to; must be http or https}
                            {--title= : The headline shown in the sponsored slot}
                            {--description= : Optional supporting copy}
                            {--image-url= : Optional http(s) image shown with the promo}
                            {--position=1 : Sort order within the sponsored block}
                            {--starts-at= : When it goes live; blank means immediately}
                            {--ends-at= : When it comes down; blank means never}';

    /**
     * @var string
     */
    protected $description = 'Create a sponsored result for the SERP page';

    public function handle(): int
    {
        $url = $this->valueFor('url', 'Link URL');
        $title = $this->valueFor('title', 'Headline');

        if ($url === null || $title === null) {
            $this->components->error('Both --url and --title are required.');

            return self::FAILURE;
        }

        if (! $this->isHttpUrl($url)) {
            $this->components->error('The URL must start with http:// or https://.');

            return self::FAILURE;
        }

        $imageUrl = $this->valueFor('image-url', 'Image URL (optional)');

        if ($imageUrl !== null && ! $this->isHttpUrl($imageUrl)) {
            $this->components->error('The image URL must start with http:// or https://.');

            return self::FAILURE;
        }

        try {
            $startsAt = $this->parseMoment($this->valueFor('starts-at', 'Starts at (optional)'));
            $endsAt = $this->parseMoment($this->valueFor('ends-at', 'Ends at (optional)'));
        } catch (Throwable) {
            $this->components->error('Dates must be parseable, for example "2026-09-01 09:00".');

            return self::FAILURE;
        }

        if ($startsAt !== null && $endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            $this->components->error('The end date must fall after the start date.');

            return self::FAILURE;
        }

        $promo = SponsoredResult::create([
            'position' => (int) $this->option('position'),
            'url' => $url,
            'title' => $title,
            'description' => $this->valueFor('description', 'Description (optional)'),
            'image_url' => $imageUrl,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->components->info(
            "Sponsored result #{$promo->id} created ({$promo->status()->value})."
        );

        return self::SUCCESS;
    }

    /**
     * The option, falling back to a prompt when running interactively.
     *
     * Empty strings collapse to null so an unanswered optional prompt and an
     * omitted option mean the same thing.
     */
    private function valueFor(string $option, string $label): ?string
    {
        $value = $this->option($option);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $answer = trim(text($label));

        return $answer === '' ? null : $answer;
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    private function parseMoment(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value);
    }
}
