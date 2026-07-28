import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * "Ledger" design system.
 *
 * The product is a workbench for Nigerian agents running NIN/BVN lookups and
 * selling data. Two things matter on every screen: money and status. So the
 * palette reserves brand colour (pine) for chrome and actions, and keeps a
 * separate, never-reused set of status hues, so "success" can never be
 * mistaken for "this is just our brand green".
 *
 * Legacy Tailwind palette names (indigo/gray/blue/…) are deliberately remapped
 * onto these scales. ~80 pages were written against the stock palette; aliasing
 * here brings every one of them onto the system without touching each file.
 */

/** Brand. Deep, ink-like green — authoritative rather than flag-patriotic. */
const pine = {
    50: '#F0F6F3',
    100: '#DCEBE4',
    200: '#BBD8CA',
    300: '#8FBEA9',
    400: '#5E9D85',
    500: '#3C7F67',
    600: '#2A6551',
    700: '#205141',
    800: '#10402F', // brand anchor
    900: '#0C3025',
    950: '#051A13',
};

/** Signature accent. Borrowed from the foil and seals of printed ID documents. */
const brass = {
    50: '#FBF7EF',
    100: '#F5ECD8',
    200: '#EBD8AF',
    300: '#DDBE7E',
    400: '#CDA155',
    500: '#B8863B', // accent anchor
    600: '#9C6C2E',
    700: '#7D5326',
    800: '#654325',
    900: '#553921',
    950: '#311E10',
};

/**
 * Neutrals are sage — a real green cast, not a hint of one. Nothing in the
 * app sits on stark white: the canvas is tinted paper and every card is a
 * lighter tint of the same family, so surfaces read as one material.
 */
const neutral = {
    50: '#E9F0EB',  // table heads, inset wells — a shade under the card
    100: '#D9E5DC', // canvas (the page itself)
    200: '#C6D6CB', // hairline
    300: '#A9BFB1', // input borders
    400: '#7F9887',
    500: '#5E7568',
    600: '#47594E',
    700: '#35443B',
    800: '#25382F', // hairline, dark
    900: '#152420', // card, dark
    950: '#0A1713', // canvas, dark
};

/**
 * Even "white" is tinted. Remapping it here means every `bg-white` already
 * written across ~80 pages becomes the card surface without touching a
 * single one of them — the same aliasing trick the legacy palette uses.
 */
const paper = '#F2F7F3';

/* ---- Status. Reserved: never used for brand, decoration or chrome. ---- */

const success = {
    50: '#E7F7EF',
    100: '#C8EDDA',
    200: '#95DCBA',
    300: '#5CC495',
    400: '#2CAE76',
    500: '#12A150',
    600: '#0E8544',
    700: '#0B6B39',
    800: '#0A542E',
    900: '#083F24',
    950: '#042314',
};

const warning = {
    50: '#FDF6E7',
    100: '#FAEBC7',
    200: '#F4D68C',
    300: '#E7BB53',
    400: '#D4A22C',
    500: '#C08A16',
    600: '#A87513',
    700: '#8A6110',
    800: '#6E4D0F',
    900: '#553B0D',
    950: '#2E2006',
};

const danger = {
    50: '#FCEFEE',
    100: '#F9DCD9',
    200: '#F2B8B2',
    300: '#E68C83',
    400: '#D66357',
    500: '#C7453A',
    600: '#B93A2F',
    700: '#9A3027',
    800: '#7C2721',
    900: '#61201C',
    950: '#360F0D',
};

const info = {
    50: '#ECF3FB',
    100: '#D5E6F6',
    200: '#AECDED',
    300: '#7FAEE0',
    400: '#528DD0',
    500: '#2E76C4',
    600: '#245FA3',
    700: '#1D4C83',
    800: '#183D69',
    900: '#143152',
    950: '#0A1B2E',
};

/** Refunds are genuinely their own state, so they get their own hue. */
const refund = {
    50: '#F1EEFB',
    100: '#E2DBF7',
    200: '#C7BAEF',
    300: '#A895E4',
    400: '#8F76DC',
    500: '#7B5FD4',
    600: '#6B4FC4',
    700: '#57409E',
    800: '#46347F',
    900: '#382A65',
    950: '#1F1739',
};

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                /* No stark white anywhere in the UI. */
                white: paper,

                /* Semantic names — use these in anything newly written. */
                brand: pine,
                brass,
                ink: neutral,
                success,
                warning,
                danger,
                info,
                refund,

                /* Legacy aliases so existing pages inherit the system. */
                indigo: pine,
                gray: neutral,
                slate: neutral,
                zinc: neutral,
                stone: neutral,
                green: success,
                emerald: success,
                teal: success,
                red: danger,
                rose: danger,
                amber: warning,
                yellow: warning,
                orange: brass,
                blue: info,
                sky: info,
                cyan: info,
                purple: refund,
                violet: refund,
                fuchsia: refund,
                pink: refund,
            },

            fontFamily: {
                /* Display: technical, slightly odd letterforms — reads as
                   infrastructure, not startup. Used large and sparingly. */
                display: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
                /* Body: civic workhorse, built for forms and dense UI. */
                sans: ['"Public Sans"', ...defaultTheme.fontFamily.sans],
                /* Data: every NIN, BVN, reference and amount. */
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },

            fontSize: {
                /* Eyebrow/label size used throughout the chrome. */
                '2xs': ['0.6875rem', { lineHeight: '1rem', letterSpacing: '0.08em' }],
            },

            borderRadius: {
                card: '0.875rem',
            },

            boxShadow: {
                /* Shadows are tinted with ink rather than pure black so they
                   read as depth on the green-cast paper, not as grey haze. */
                card: '0 1px 2px 0 rgb(10 21 18 / 0.04), 0 1px 3px 0 rgb(10 21 18 / 0.06)',
                lift: '0 4px 6px -1px rgb(10 21 18 / 0.07), 0 10px 20px -6px rgb(10 21 18 / 0.10)',
                pop: '0 10px 15px -3px rgb(10 21 18 / 0.10), 0 24px 40px -12px rgb(10 21 18 / 0.18)',
            },

            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                sheen: {
                    '0%': { backgroundPosition: '200% 0' },
                    '100%': { backgroundPosition: '-200% 0' },
                },
            },

            animation: {
                'fade-up': 'fade-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) both',
                sheen: 'sheen 1.6s linear infinite',
            },
        },
    },

    plugins: [forms],
};
