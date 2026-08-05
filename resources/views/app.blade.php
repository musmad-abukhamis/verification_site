<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <meta name="theme-color" content="#F9FAFB" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0C111D" media="(prefers-color-scheme: dark)">

        {{-- PWA. The manifest carries the icons, name and colours; these tags
             cover iOS, which reads none of it. Note there is deliberately no
             `viewport-fit=cover` above: the safe-area padding in the install
             toast degrades to zero without it, whereas turning it on would
             push every existing page under the notch. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <meta name="application-name" content="ABC Services">
        <meta name="apple-mobile-web-app-title" content="ABC Services">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">

        {{-- `beforeinstallprompt` regularly fires before the Vue app has
             mounted, and it is only offered once per page load. Stash it here
             so PwaInstallToast can still find it whenever it wakes up. --}}
        <script>
            window.__pwaInstallEvent = null;
            window.addEventListener('beforeinstallprompt', function (event) {
                event.preventDefault();
                window.__pwaInstallEvent = event;
                window.dispatchEvent(new Event('pwa:installable'));
            });
        </script>

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
