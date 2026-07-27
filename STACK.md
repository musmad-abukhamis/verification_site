# Tech Stack

A Laravel + Inertia + Vue monolith serving a NIN/BVN verification and VTU/wallet platform.

## Backend

| Layer | Technology | Version |
|-------|-----------|---------|
| Language | PHP | ^8.2 |
| Framework | Laravel | ^12.0 |
| SPA bridge | Inertia.js (Laravel adapter) | ^2.0 |
| API auth | Laravel Sanctum | ^4.0 |
| Route → JS | Ziggy (`tightenco/ziggy`) | ^2.0 |
| REPL | Laravel Tinker | ^2.10 |

## Frontend

| Layer | Technology | Version |
|-------|-----------|---------|
| UI framework | Vue 3 | ^3.4 |
| SPA bridge | Inertia.js (Vue 3 adapter) | ^2.0 |
| Build tool | Vite | ^6.0 |
| CSS | Tailwind CSS | ^3.2 (+ `@tailwindcss/forms`) |
| HTTP client | Axios | ^1.13 |
| Icons | Heroicons (`@heroicons/vue`) | ^2.2 |
| Alerts/modals | SweetAlert2 | ^11 |
| PDF generation | pdf-lib | ^1.17 |
| QR codes | qrcode.vue / qrcode-generator | ^3.8 / ^2.0 |
| SSR | `@vue/server-renderer` (Inertia SSR build) | ^3.4 |

## Data & infrastructure

| Concern | Choice |
|---------|--------|
| Database | PostgreSQL (`DB_CONNECTION=pgsql`, DB `abcweb`) |
| Sessions | database driver |
| Queue | database driver |
| Cache | database store |
| Schema | Eloquent migrations; cuid-keyed port of an external Prisma schema |

## Tooling

- **Auth scaffolding:** Laravel Breeze (Vue + Inertia stack)
- **Code style:** Laravel Pint
- **Testing:** PHPUnit ^11, Mockery, FakerPHP
- **Dev runtime:** `composer dev` runs `php artisan serve`, `queue:listen`, `pail` (logs), and `npm run dev` (Vite) concurrently
- **Local containers:** Laravel Sail
- **Error reporting:** Nuno Maduro Collision

## Architecture

- Server-rendered SPA: Laravel controllers return **Inertia** responses that mount **Vue 3** page components under `resources/js/Pages/**`.
- No REST/JSON API layer for the frontend — page props are passed directly through Inertia; Ziggy exposes named routes to JS.
- Frontend assets bundled by **Vite** (`laravel-vite-plugin`), with a separate SSR build (`vite build --ssr`).
