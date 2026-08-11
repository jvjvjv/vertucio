<?php

namespace App\Providers;

use App\Search\Contracts\SearchProvider;
use App\Search\SearchManager;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SearchManager::class, static function (Application $app): SearchManager {
            $providers = [];

            foreach ($app->make(Repository::class)->array('search.providers') as $class) {
                if (! is_string($class)) {
                    throw new InvalidArgumentException('Each search.providers entry must be a class name.');
                }

                $provider = $app->make($class);

                if (! $provider instanceof SearchProvider) {
                    throw new InvalidArgumentException("[{$class}] must implement ".SearchProvider::class.'.');
                }

                $providers[] = $provider;
            }

            return new SearchManager($providers);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
