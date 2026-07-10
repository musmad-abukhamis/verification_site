import { computed, unref } from 'vue';

/**
 * Client-side network detection for a phone number, fed entirely by the
 * server-provided prefix map (no hard-coded prefixes here). Detection is a
 * *hint only* — the user's chosen network always wins, and the ported toggle
 * suppresses every hint.
 *
 * @param {import('vue').Ref<string>|string} phone        raw phone input
 * @param {import('vue').Ref<Object>|Object} prefixMap    { network: [prefix, ...] }
 * @param {import('vue').Ref<string>|string} selected     selected network value
 * @param {import('vue').Ref<boolean>|boolean} ported     ported-number toggle
 */
export function usePhoneNetworkHint(phone, prefixMap, selected, ported) {
    const digits = computed(() => String(unref(phone) ?? '').replace(/\D+/g, ''));

    const label = (value) => (value ? String(value).toUpperCase() : '');

    // Longest-prefix match across every network in the map.
    const detected = computed(() => {
        const number = digits.value;
        if (number.length < 4) {
            return null;
        }

        const map = unref(prefixMap) ?? {};
        let best = null;
        let bestLen = 0;

        for (const [network, prefixes] of Object.entries(map)) {
            for (const prefix of prefixes ?? []) {
                if (number.startsWith(prefix) && prefix.length > bestLen) {
                    best = network;
                    bestLen = prefix.length;
                }
            }
        }

        return best;
    });

    // When ported is on, no hints of any kind are shown.
    const active = computed(() => !unref(ported) && detected.value !== null);

    const suggestion = computed(() =>
        active.value ? `Looks like an ${label(detected.value)} number` : ''
    );

    const mismatch = computed(() => {
        if (!active.value || !unref(selected)) {
            return false;
        }
        return detected.value !== unref(selected);
    });

    const mismatchNote = computed(() =>
        mismatch.value ? `This number looks like ${label(detected.value)} — you can still proceed` : ''
    );

    return { detected, suggestion, mismatch, mismatchNote };
}
