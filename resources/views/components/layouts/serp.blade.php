<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head')
        @if (isset($title))
            <title>{{ $title }}</title>
        @endif
    </head>

    <body class="min-h-screen bg-(--serp-surface) text-(--serp-text) antialiased">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
