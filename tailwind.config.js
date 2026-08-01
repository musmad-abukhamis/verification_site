import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * "Ledger" design system.
 *
 * The product is a workbench for Nigerian agents running NIN/BVN lookups and
 * selling data. Two things matter on every screen: money and status.
 *
 * Repalletted 2026-08-01 onto the cool-grey / saturated-status scheme the user
 * dashboard was built in — see the "Quick Dots + Watermark Spine" block in
 * app.css. The structure of the system is unchanged (brand for chrome and
 * actions, a status set that carries meaning); only the hues moved: sage → the
 * dashboard's greys, pine → its blue, brass → its amber. The dashboard's own
 * `--d-*` variables and these tokens are now the same values, so the two
 * stopped being two design languages.
 *
 * Legacy Tailwind palette names (indigo/gray/blue/…) are deliberately remapped
 * onto these scales. ~80 pages were written against the stock palette; aliasing
 * here brings every one of them onto the system without touching each file.
 */

/**
 * Brand. The blue the dashboard's primary "Verify" dot is built from
 * (#3b82f6 → #1d4ed8), continued into a full ramp. Chrome and actions only.
 */
const blue = {
    50: '#EFF4FF',
    100: '#D1E0FF',
    200: '#B2CCFF',
    300: '#84ADFF', // the dashboard's dark-mode info tag, exactly
    400: '#528BFF',
    500: '#2970FF',
    600: '#155EEF', // brand anchor — the dashboard's info hue
    700: '#004EEB',
    800: '#0040C1',
    900: '#00359E',
    950: '#002266',
};

/**
 * Accent. Money and anything awaiting a human. Formerly brass, kept warm but
 * repitched to the dashboard's amber so the wallet strip, the "needs review"
 * chip and the pending tag are all one colour instead of three.
 *
 * Note this is the same ramp as `warning` — the old rule that no accent may
 * share a hue with a status no longer holds here, because the dashboard
 * itself spends amber on both. Same for `brand`/`info`, which are both blue.
 */
const amber = {
    50: '#FFFAEB',
    100: '#FEF0C7',
    200: '#FEDF89',
    300: '#FEC84B',
    400: '#FDB022', // dark-mode pending tag
    500: '#F79009', // accent anchor
    600: '#DC6803',
    700: '#B54708', // light-mode pending tag ink
    800: '#93370D',
    900: '#7A2E0E',
    950: '#4E1D09',
};

/**
 * Neutrals are cool grey — the ramp the user dashboard was built on, now
 * carrying the whole app. Every step here is a value the dashboard already
 * names, which is what makes the two read as one product:
 *
 *   100 = --d-chip      300 = --d-line     500 = --d-muted    600 = --d-chip-ink
 *   800 = --d-edge/chip (dark)   900 = --d-card (dark)   950 = the dark canvas
 */
const neutral = {
    50: '#F9FAFB',  // table heads, inset wells, the canvas
    100: '#F2F4F7', // chip / ghost-hover tint
    200: '#EAECF0', // hairline
    300: '#D0D5DD', // input borders, divider rules
    400: '#98A2B3', // muted ink, dark
    500: '#667085', // muted ink
    600: '#475467',
    700: '#344054', // body ink; hairline, dark
    800: '#1D2939', // card edge, dark
    900: '#101828', // headings; card, dark
    950: '#0C111D', // canvas, dark
};

/**
 * The card surface. Genuinely white now — the dashboard's panels are #ffffff
 * and the depth comes from the shadow, not from a tint.
 */
const paper = '#ffffff';

/**
 * The page canvas: grey-50, one step under the card. Kept as its own token
 * rather than folded into the `ink` ramp so the intent stays readable at the
 * call site, and so the dark canvas (#0c111d) can sit below the dark card.
 */
const canvas = '#F9FAFB';

/* ---- Status. Reserved: never used for brand, decoration or chrome. ---- */

const success = {
    50: '#ECFDF3',
    100: '#D1FADF',
    200: '#A6F4C5',
    300: '#6CE9A6',
    400: '#32D583', // dark-mode success tag
    500: '#12B76A',
    600: '#039855', // the dashboard's success hue
    700: '#027A48',
    800: '#05603A',
    900: '#054F31',
    950: '#053321',
};

const danger = {
    50: '#FEF3F2',
    100: '#FEE4E2',
    200: '#FECDCA',
    300: '#FDA29B', // dark-mode danger tag
    400: '#F97066',
    500: '#F04438',
    600: '#D92D20', // the dashboard's danger hue
    700: '#B42318',
    800: '#912018',
    900: '#7A271A',
    950: '#55160C',
};

/** Refunds are genuinely their own state, so they get their own hue. */
const refund = {
    50: '#F4F3FF',
    100: '#EBE9FE',
    200: '#D9D6FE',
    300: '#BDB4FE', // dark-mode refund tag
    400: '#9B8AFB',
    500: '#7A5AF8', // the dashboard's refund hue
    600: '#6938EF',
    700: '#5925DC',
    800: '#4A1FB8',
    900: '#3E1C96',
    950: '#27115F',
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
                white: paper,

                /* Semantic names — use these in anything newly written. */
                canvas,
                brand: blue,
                brass: amber,   /* kept as a name: ~40 files say `brass-500` */
                ink: neutral,
                success,
                warning: amber,
                danger,
                info: blue,
                refund,

                /* Legacy aliases so existing pages inherit the system. */
                indigo: blue,
                gray: neutral,
                slate: neutral,
                zinc: neutral,
                stone: neutral,
                green: success,
                emerald: success,
                teal: success,
                red: danger,
                rose: danger,
                amber,
                yellow: amber,
                orange: amber,
                blue,
                sky: blue,
                cyan: blue,
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
                /* Tinted with ink-900 (#101828) rather than pure black — the
                   same shadow the dashboard's panels cast. On white cards the
                   shadow is now the only thing separating card from canvas in
                   light mode, so `card` matches --d-shadow exactly. */
                card: '0 1px 3px rgb(16 24 40 / 0.08)',
                lift: '0 4px 6px -1px rgb(16 24 40 / 0.07), 0 8px 18px -4px rgb(16 24 40 / 0.10)',
                pop: '0 10px 15px -3px rgb(16 24 40 / 0.10), 0 24px 40px -12px rgb(16 24 40 / 0.18)',
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
