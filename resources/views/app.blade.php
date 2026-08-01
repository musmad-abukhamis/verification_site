<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <meta name="theme-color" content="#F9FAFB" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0C111D" media="(prefers-color-scheme: dark)">

        {{-- Space Grotesk: display. Public Sans: UI. IBM Plex Mono: IDs, refs, money.
             Sora + Inter: the user dashboard only, which runs its own type pairing. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link
            href="https://fonts.bunny.net/css?family=space-grotesk:500,600,700|public-sans:400,500,600,700|ibm-plex-mono:400,500,600|sora:600,700|inter:400,500,600,700&display=swap"
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
    {{-- The dashboard paints its own canvas (same grey in light, a cooler
         near-black in dark). Setting it here as well as on mount means a
         full page load doesn't flash the default canvas first. --}}
    <body class="font-sans antialiased {{ $page['component'] === 'Dashboard' ? 'dash-canvas' : '' }}">
        @inertia
    </body>
</html>
