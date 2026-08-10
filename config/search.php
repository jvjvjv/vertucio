<?php

use App\Search\Providers\BraveSearchProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Monitored Query
    |--------------------------------------------------------------------------
    |
    | The term fetched by the "search:fetch" command when no argument is given.
    |
    */

    'query' => env('SEARCH_QUERY', 'vertucio'),

    'results_per_vertical' => 20,

    'locale' => [
        'language' => 'en',
        'country' => 'US',
    ],

    'providers' => [
        BraveSearchProvider::class,
    ],

];
