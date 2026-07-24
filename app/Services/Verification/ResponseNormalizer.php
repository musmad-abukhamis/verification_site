<?php

namespace App\Services\Verification;

/**
 * Flattens any provider's reply into one canonical identity record.
 *
 * Providers disagree about everything: the person may sit at the top level, or
 * under `data`, `user_data`, `response` or `result`; their surname may be
 * `surname`, `last_name`, `lastName` or `familyName`; their date of birth may be
 * `birthdate`, `dob`, `birth_date` or `birthday`, formatted `1992-08-14` or
 * `22-05-1998`. Nothing downstream should have to care.
 *
 * Three passes:
 *   1. unwrap  — find the object that actually holds the person's fields;
 *   2. match   — map every key onto a canonical name via the alias table,
 *                comparing keys case- and separator-insensitively so
 *                `first_name`, `firstName` and `FirstName` are one thing;
 *   3. clean   — normalize the values themselves (dates to Y-m-d, gender to
 *                MALE/FEMALE, photos stripped of their data-URI prefix, phone
 *                numbers back to the local 0-prefixed form).
 *
 * An endpoint can supply a `response_map` of canonical name => dotted path for
 * the rare field the alias table cannot guess; those overrides win.
 */
class ResponseNormalizer
{
    /**
     * Wrapper keys to look inside, in priority order, when the person's fields
     * are not at the top level. Listed in normalized form (lowercased, no
     * separators) and matched case- and separator-insensitively, so `user_data`
     * covers `userData`/`USER_DATA` and `apiresponse` covers `api_response`.
     */
    private const WRAPPERS = [
        'data', 'userdata', 'response', 'result', 'results',
        'details', 'detail', 'record', 'payload', 'person', 'customer',
        'verification', 'bvndata', 'nindata', 'entity', 'apiresponse',
    ];

    /**
     * canonical name => the spellings providers use for it.
     *
     * Compared after stripping underscores/hyphens/spaces and lowercasing, so
     * only genuinely different words need listing here — `first_name` covers
     * `firstName`, `FIRSTNAME` and `First Name` too.
     */
    private const ALIASES = [
        'first_name' => ['firstname', 'givenname', 'forename', 'fname'],
        'middle_name' => ['middlename', 'othername', 'mname'],
        'last_name' => ['surname', 'lastname', 'familyname', 'lname'],
        'full_name' => ['fullname', 'name', 'customername', 'accountname'],
        // Kept separate from full_name: the name printed on a BVN card is
        // usually abbreviated ("JOHN A DOE"), so using it as the person's name
        // would silently truncate the middle name on every slip.
        'name_on_card' => ['nameoncard'],
        'other_names' => ['othernames'],
        'gender' => ['gender', 'sex'],
        'date_of_birth' => ['birthdate', 'dob', 'dateofbirth', 'birthday', 'datebirth'],
        'nin' => ['nin', 'ninnumber', 'nationalidentificationnumber', 'newnin'],
        'vnin' => ['vnin', 'virtualnin'],
        'bvn' => ['bvn', 'bvnnumber', 'bankverificationnumber'],
        'phone' => ['telephoneno', 'phonenumber', 'phone', 'msisdn', 'mobile', 'mobilenumber', 'telephone', 'phoneno'],
        'phone2' => ['phonenumber2', 'telephoneno2', 'altphone', 'alternatephonenumber', 'phone2'],
        'email' => ['email', 'emailaddress'],
        'photo' => ['photo', 'photopath', 'image', 'picture', 'photobase64', 'base64image', 'faceimage'],
        'signature' => ['signature', 'signaturepath', 'sign'],
        'title' => ['title'],
        'marital_status' => ['maritalstatus'],
        'birth_state' => ['birthstate', 'stateofbirth'],
        'birth_lga' => ['birthlga', 'lgaofbirth'],
        'birth_country' => ['birthcountry', 'countryofbirth'],
        'state_of_origin' => ['selforiginstate', 'stateoforigin', 'originstate'],
        'lga_of_origin' => ['selforiginlga', 'lgaoforigin', 'originlga'],
        'place_of_origin' => ['selforiginplace', 'placeoforigin'],
        'residence_address' => ['residenceaddress', 'address', 'residentialaddress', 'addressline', 'addressline1'],
        'residence_state' => ['residencestate', 'state', 'stateofresidence'],
        'residence_lga' => ['residencelga', 'lga', 'lgaofresidence'],
        'residence_town' => ['residencetown', 'town', 'city'],
        'residence_status' => ['residencestatus'],
        'nationality' => ['nationality', 'country'],
        // BVN-only enrolment details. The BVN slip renders all three, so they
        // must survive normalization or switching provider silently blanks them.
        'registration_date' => ['registrationdate', 'dateofregistration', 'enrollmentdate'],
        'enrollment_bank' => ['enrollmentbank', 'registrationbank'],
        'enrollment_bank_branch' => ['enrollmentbranch', 'enrollmentbankbranch', 'registrationbranch'],
        'level_of_account' => ['levelofaccount', 'accountlevel'],
        'religion' => ['religion'],
        'profession' => ['profession', 'occupation'],
        'employment_status' => ['employmentstatus'],
        'education_level' => ['educationallevel', 'educationlevel', 'levelofeducation'],
        'spoken_language' => ['spokenlanguage', 'ospokenlang', 'language'],
        // NIMC's own exports misspell this as "heigth"; both are accepted.
        'height' => ['height', 'heigth'],
        'tracking_id' => ['trackingid', 'trkid', 'newtrackingid'],
        'central_id' => ['centralid'],
        'reference' => ['reference', 'transactionref', 'trxref', 'verificationreference', 'requestid', 'transactionid'],
        'status' => ['status'],
        'nok_first_name' => ['nokfirstname'],
        'nok_middle_name' => ['nokmiddlename'],
        'nok_last_name' => ['noksurname', 'noklastname'],
        'nok_address' => ['nokaddress1', 'nokaddress', 'nokaddressline'],
        'nok_state' => ['nokstate'],
        'nok_lga' => ['noklga'],
        'nok_town' => ['noktown'],
    ];

    /**
     * Fields that are already the parent's/next-of-kin's details in NIMC
     * exports and must never be mistaken for the subject's own.
     */
    private const IGNORED = ['pmiddlename', 'psurname', 'pfirstname'];

    /**
     * @param  array<string, mixed>  $raw  the decoded provider response
     * @param  array<string, string>  $overrides  canonical name => dotted path
     * @param  array<string, mixed>  $seed  values the caller already knows (the
     *                                      submitted NIN etc.), used only where
     *                                      the provider returned nothing
     * @return array<string, mixed>
     */
    public function normalize(array $raw, array $overrides = [], array $seed = []): array
    {
        $subject = $this->unwrap($raw);
        $flat = $this->flatten($subject);

        // The envelope often carries the reference/status while the person sits
        // in `data`; merge it in underneath so it never shadows real fields.
        $flat += $this->flatten($raw, depth: 1);

        $normalized = [];

        foreach (self::ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($flat[$alias]) && $flat[$alias] !== '') {
                    $normalized[$canonical] = $flat[$alias];
                    break;
                }
            }
        }

        // Explicit per-endpoint overrides beat anything the alias table guessed.
        foreach ($overrides as $canonical => $path) {
            $value = $this->valueAt($raw, (string) $path);
            if ($value !== null && $value !== '') {
                $normalized[$canonical] = $value;
            }
        }

        $normalized = $this->clean($normalized);

        // Fill only what the provider left blank — a provider that echoes the
        // NIN back is more authoritative than what we typed in.
        foreach ($seed as $key => $value) {
            if (($normalized[$key] ?? null) === null || $normalized[$key] === '') {
                $normalized[$key] = $value;
            }
        }

        if (empty($normalized['full_name'])) {
            $normalized['full_name'] = $this->composeFullName($normalized);
        }

        return array_filter($normalized, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Find the object holding the person's fields.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function unwrap(array $payload): array
    {
        $current = $payload;

        // Bounded, because a hostile or odd reply could nest `data` forever.
        // Deeper than the two-level `api_response`→`data`→`data` envelope some
        // providers use.
        for ($i = 0; $i < 5; $i++) {
            $next = null;

            foreach (self::WRAPPERS as $wrapper) {
                $candidate = $this->childByNormalizedKey($current, $wrapper);

                // A list response (`results: [...]`) is answered by its first row.
                if (is_array($candidate) && array_is_list($candidate)) {
                    $candidate = $candidate[0] ?? null;
                }

                if (is_array($candidate) && $candidate !== []) {
                    $next = $candidate;
                    break;
                }
            }

            if ($next === null) {
                return $current;
            }

            $current = $next;
        }

        return $current;
    }

    /**
     * Fetch a child whose key matches the wrapper after normalization, so
     * `api_response`, `apiResponse` and `API_RESPONSE` are all the same wrapper.
     *
     * @param  array<string, mixed>  $node
     */
    protected function childByNormalizedKey(array $node, string $normalizedWrapper): mixed
    {
        foreach ($node as $key => $value) {
            if ($this->normalizeKey((string) $key) === $normalizedWrapper) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Collapse a nested array into leaf-key => scalar, keys normalized for
     * alias matching. Shallower keys win, so a top-level `nin` beats one buried
     * in a sub-object.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, scalar>
     */
    protected function flatten(array $payload, int $depth = 3): array
    {
        $flat = [];

        $walk = function (array $node, int $level) use (&$walk, &$flat, $depth) {
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    if ($level < $depth) {
                        $walk($value, $level + 1);
                    }

                    continue;
                }

                if (! is_scalar($value)) {
                    continue;
                }

                $normalizedKey = $this->normalizeKey((string) $key);

                if ($normalizedKey === '' || in_array($normalizedKey, self::IGNORED, true)) {
                    continue;
                }

                // First writer wins: the outer level was walked first.
                if (! array_key_exists($normalizedKey, $flat)) {
                    $flat[$normalizedKey] = $value;
                }
            }
        };

        $walk($payload, 1);

        return $flat;
    }

    /** Lowercase and strip separators so key spellings collapse to one form. */
    protected function normalizeKey(string $key): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($key)) ?? '';
    }

    /**
     * Normalize the values themselves.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function clean(array $fields): array
    {
        foreach (['date_of_birth'] as $key) {
            if (! empty($fields[$key])) {
                $fields[$key] = $this->toIsoDate((string) $fields[$key]);
            }
        }

        if (! empty($fields['gender'])) {
            $fields['gender'] = $this->toGender((string) $fields['gender']);
        }

        foreach (['photo', 'signature'] as $key) {
            if (! empty($fields[$key])) {
                $fields[$key] = $this->stripDataUri((string) $fields[$key]);
            }
        }

        foreach (['phone', 'phone2'] as $key) {
            if (! empty($fields[$key])) {
                $fields[$key] = $this->toLocalPhone((string) $fields[$key]);
            }
        }

        foreach (['nin', 'bvn', 'vnin'] as $key) {
            if (! empty($fields[$key])) {
                $fields[$key] = preg_replace('/\D+/', '', (string) $fields[$key]);
            }
        }

        foreach (['first_name', 'middle_name', 'last_name', 'full_name', 'other_names'] as $key) {
            if (! empty($fields[$key])) {
                $fields[$key] = trim(preg_replace('/\s+/', ' ', (string) $fields[$key]) ?? '');
            }
        }

        return $fields;
    }

    /**
     * Any common Nigerian provider date format to Y-m-d.
     *
     * `22-05-1998` is unambiguously d-m-Y here; PHP would read it as d-m-Y too,
     * but `05/22/1998`-style slashes it reads as m/d/Y, so slashes are converted
     * to hyphens first. NIMC and every provider in this market write day-first.
     */
    protected function toIsoDate(string $value): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^0{2,4}[-\/]/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        $candidate = str_replace('/', '-', $value);
        $parsed = date_create($candidate);

        return $parsed !== false ? $parsed->format('Y-m-d') : $value;
    }

    /** `M`, `m`, `male`, `MALE`, `1` → MALE. Anything unrecognised is kept. */
    protected function toGender(string $value): string
    {
        return match (strtolower(trim($value))) {
            'm', 'male', '1' => 'MALE',
            'f', 'female', '2' => 'FEMALE',
            default => strtoupper(trim($value)),
        };
    }

    /** Drop a `data:image/jpeg;base64,` prefix so consumers get pure base64. */
    protected function stripDataUri(string $value): string
    {
        if (str_starts_with($value, 'data:')) {
            $comma = strpos($value, ',');

            return $comma === false ? $value : substr($value, $comma + 1);
        }

        return $value;
    }

    /** +2348012345678 / 2348012345678 → 08012345678. */
    protected function toLocalPhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? $value;

        if (strlen($digits) === 13 && str_starts_with($digits, '234')) {
            return '0'.substr($digits, 3);
        }

        if (strlen($digits) === 10 && ! str_starts_with($digits, '0')) {
            return '0'.$digits;
        }

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function composeFullName(array $fields): ?string
    {
        $name = trim(implode(' ', array_filter([
            $fields['first_name'] ?? null,
            $fields['middle_name'] ?? null,
            $fields['last_name'] ?? null,
        ])));

        return $name !== '' ? $name : null;
    }

    /**
     * Read a dotted path out of the raw response, for `response_map` overrides.
     */
    protected function valueAt(array $payload, string $path): mixed
    {
        $cursor = $payload;

        foreach (explode('.', $path) as $segment) {
            if (is_array($cursor) && array_key_exists($segment, $cursor)) {
                $cursor = $cursor[$segment];

                continue;
            }

            return null;
        }

        return is_scalar($cursor) ? $cursor : null;
    }
}
