<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <meta name="theme-color" content="#10402F" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0A1512" media="(prefers-color-scheme: dark)">

        {{-- Space Grotesk: display. Public Sans: UI. IBM Plex Mono: IDs, refs, money. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link
            href="https://fonts.bunny.net/css?family=space-grotesk:500,600,700|public-sans:400,500,600,700|ibm-plex-mono:400,500,600&display=swap"
            rel="stylesheet"
        />

        <!-- Dark Mode Script - Prevents Flash -->
        <script>
            (function() {
                const darkMode = localStorage.getItem('darkMode');
                if (darkMode === 'true' || (!darkMode && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
