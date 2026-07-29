/**
 * Date-only parsing that does not drift by a day.
 *
 * `new Date('1990-05-14')` is defined to parse as **UTC midnight**, but
 * `toLocaleDateString()` renders in the *runtime's* timezone. Anywhere behind
 * UTC that prints as 13 May 1990 — a person's date of birth silently one day
 * early on their slip. Date-only values (a DOB, an enrolment date) carry no
 * timezone at all, so they must be anchored to local midnight instead.
 *
 * Providers hand us either `1992-08-14` or `22-05-1998`, verbatim, so both
 * orders are accepted. Anything with a time component is a real timestamp and
 * is left to the normal `Date` parser.
 */

const DATE_ONLY = /^\s*(\d{1,4})[-/.](\d{1,2})[-/.](\d{1,4})\s*$/;

/**
 * Parse a value into a Date anchored at **local** midnight when it is
 * date-only. Returns null when the value cannot be understood, so callers can
 * fall back to rendering the raw string rather than showing a wrong date.
 *
 * @param {string|Date|null|undefined} value
 * @returns {Date|null}
 */
export function parseLocalDate(value) {
    if (!value) return null;
    if (value instanceof Date) return isNaN(value.getTime()) ? null : value;

    const raw = String(value).trim();
    if (!raw) return null;

    const parts = raw.match(DATE_ONLY);
    if (parts) {
        const [, a, month, b] = parts;
        let year, day;

        if (a.length === 4) {
            // YYYY-MM-DD
            year = a;
            day = b;
        } else if (b.length === 4) {
            // DD-MM-YYYY
            day = a;
            year = b;
        } else {
            // No unambiguous 4-digit year — don't guess.
            return null;
        }

        const y = Number(year);
        const m = Number(month);
        const d = Number(day);
        if (m < 1 || m > 12 || d < 1 || d > 31) return null;

        const parsed = new Date(y, m - 1, d);
        // Rejects impossible dates that JS would roll over (e.g. 31-02-1990).
        if (parsed.getFullYear() !== y || parsed.getMonth() !== m - 1 || parsed.getDate() !== d) {
            return null;
        }
        return parsed;
    }

    // Timestamps carry their own offset (or are already local) — trust them.
    const parsed = new Date(raw);
    return isNaN(parsed.getTime()) ? null : parsed;
}

/**
 * Format a date-only value for display. Unparseable input is returned
 * unchanged so a raw provider value is never replaced by a wrong one.
 *
 * @param {string|Date|null|undefined} value
 * @param {object} [options] Intl.DateTimeFormat options.
 * @param {string} [locale]
 * @param {string} [fallback] Shown when the value is empty.
 */
export function formatDateOnly(
    value,
    options = { day: 'numeric', month: 'short', year: 'numeric' },
    locale = 'en-NG',
    fallback = '-',
) {
    if (!value) return fallback;
    const parsed = parseLocalDate(value);
    return parsed ? parsed.toLocaleDateString(locale, options) : String(value);
}
