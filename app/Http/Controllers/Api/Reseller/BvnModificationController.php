<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Concerns\BvnModificationPricing;
use App\Http\Controllers\Controller;
use App\Models\BvnModification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * BVN modification requests for API resellers.
 *
 * Files a correction against a BVN record — name, date of birth, phone, or a
 * combination — priced per combination from the same `bvn.mod.*` service keys
 * as the web form, so an integrator is billed their own rate.
 *
 * This is a *submission fulfilled by our staff*, not a lookup and not a
 * provider job: nothing is verified at the point of submission, and the
 * outcome is a status an admin sets after working the request. Integrators
 * poll it the same way they poll IPE, but the answer takes days.
 */
class BvnModificationController extends Controller
{
    use BvnModificationPricing, Concerns\FulfilledService;

    /**
     * The most a NIN slip may weigh, matching the web form's 5MB limit. Applied
     * to the decoded bytes, so a caller cannot slip past it with base64's ~33%
     * inflation working in their favour.
     */
    private const MAX_SLIP_BYTES = 5 * 1024 * 1024;

    private const SLIP_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    /**
     * Submit a modification request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_type' => ['required', 'string', Rule::in($this->serviceTypes())],
            'bvn' => ['required', 'string', 'regex:/^\d{11}$/'],
            'nin' => ['required', 'string', 'regex:/^\d{11}$/'],
            // A file for multipart callers, a base64 string for JSON ones. Most
            // integrations post JSON from a server, where attaching a file is
            // the awkward path.
            'nin_slip' => ['required'],
            'old_first_name' => ['nullable', 'string', 'max:100'],
            'old_middle_name' => ['nullable', 'string', 'max:100'],
            'old_last_name' => ['nullable', 'string', 'max:100'],
            'old_dob' => ['nullable', 'date_format:Y-m-d'],
            'old_phone' => ['nullable', 'string', 'max:20'],
            'new_first_name' => ['nullable', 'string', 'max:100'],
            'new_middle_name' => ['nullable', 'string', 'max:100'],
            'new_last_name' => ['nullable', 'string', 'max:100'],
            'new_dob' => ['nullable', 'date_format:Y-m-d'],
            'new_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $serviceType = $validated['service_type'];

        if ($missing = $this->missingFieldsFor($serviceType, $validated)) {
            return $this->error(
                'missing_fields',
                'This service type requires: '.implode(', ', $missing).'.',
                422,
                ['fields' => $missing],
            );
        }

        $slip = $this->decodeSlip($request);

        if (is_string($slip)) {
            return $this->error('invalid_slip', $slip, 422);
        }

        $user = $request->user();
        $oldBalance = (float) $user->balance;

        [$price, $refusal] = $this->charge($user, $this->priceColumn($serviceType), 'bvn_modification');

        if ($refusal) {
            return $refusal;
        }

        // Postgres `bytea` must be bound as a LOB or the raw bytes are rejected
        // as an invalid UTF-8 text parameter. Laravel binds resources as
        // PDO::PARAM_LOB, so the slip goes in as a stream, not a string.
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $slip['bytes']);
        rewind($stream);

        try {
            $record = BvnModification::create($this->attributes($serviceType, $validated) + [
                'serviceType' => $serviceType,
                'bvn' => $validated['bvn'],
                'nin' => $validated['nin'],
                'ninSlipUrl' => 'nin-slip-'.$validated['bvn'].'.'.$slip['extension'],
                'ninSlipImage' => $stream,
                'oldBal' => (string) $oldBalance,
                'newBal' => (string) $user->balance,
                'amountCharged' => (string) $price,
                'status' => 'pending',
                'userId' => $user->id,
            ]);
        } catch (\Throwable $e) {
            $this->refund($user, $price);
            report($e);

            return $this->error('submission_failed', 'The request could not be filed. You were not charged.', 502);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $this->accepted($record, $price, $this->present($record));
    }

    /**
     * The caller's own requests, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BvnModification::where('userId', $request->user()->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return $this->listing($query, fn (BvnModification $r) => $this->present($r), (int) $request->input('limit', 50));
    }

    /**
     * One request, by the id we returned or by the BVN it was filed against.
     */
    public function show(Request $request, string $submission): JsonResponse
    {
        $query = BvnModification::where('userId', $request->user()->id);

        // Ids are cuids, so an 11-digit string is unambiguously a BVN. The same
        // BVN can be modified more than once; the latest request is the one
        // someone polling for an outcome means.
        $record = preg_match('/^\d{11}$/', $submission)
            ? $query->where('bvn', $submission)->orderByDesc('createdAt')->first()
            : $query->find($submission);

        if (! $record) {
            return $this->error('not_found', 'No modification request found for that id.', 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->present($record),
        ]);
    }

    /**
     * Which of the old/new pairs this service type cannot do without.
     *
     * The web form enforces the same rule client-side; an API caller has no
     * form to stop them, so a name change submitted with no names would
     * otherwise be charged for and then rejected by hand days later.
     *
     * @return array<int, string>
     */
    private function missingFieldsFor(string $serviceType, array $input): array
    {
        $required = [];

        if ($this->needsName($serviceType)) {
            $required = ['old_first_name', 'old_last_name', 'new_first_name', 'new_last_name'];
        }

        if ($this->needsDob($serviceType)) {
            $required = [...$required, 'old_dob', 'new_dob'];
        }

        if ($this->needsPhone($serviceType)) {
            $required = [...$required, 'old_phone', 'new_phone'];
        }

        return array_values(array_filter($required, fn (string $field) => empty($input[$field])));
    }

    /**
     * Map the request's snake_case fields onto the table's camelCase columns,
     * keeping only the ones this service type actually changes — a phone
     * modification carrying stray name fields must not record a name change.
     */
    private function attributes(string $serviceType, array $input): array
    {
        $columns = [];

        if ($this->needsName($serviceType)) {
            $columns += [
                'oldFirstName' => 'old_first_name',
                'oldMiddleName' => 'old_middle_name',
                'oldLastName' => 'old_last_name',
                'newFirstName' => 'new_first_name',
                'newMiddleName' => 'new_middle_name',
                'newLastName' => 'new_last_name',
            ];
        }

        if ($this->needsDob($serviceType)) {
            $columns += ['oldDob' => 'old_dob', 'newDob' => 'new_dob'];
        }

        if ($this->needsPhone($serviceType)) {
            $columns += ['oldPhoneNumber' => 'old_phone', 'newPhoneNumber' => 'new_phone'];
        }

        return array_map(fn (string $field) => $input[$field] ?? null, $columns);
    }

    /**
     * Read the NIN slip out of the request, whichever way it was sent.
     *
     * Returns the decoded bytes and the extension to store, or a string
     * describing what is wrong with it. The type is sniffed from the content
     * rather than trusted from a filename or a data: prefix, since the caller
     * controls both.
     *
     * @return array{bytes: string, extension: string}|string
     */
    private function decodeSlip(Request $request): array|string
    {
        if ($request->hasFile('nin_slip')) {
            $file = $request->file('nin_slip');

            if (! $file->isValid()) {
                return 'The uploaded NIN slip could not be read.';
            }

            $bytes = file_get_contents($file->getRealPath());
        } else {
            $encoded = $request->input('nin_slip');

            if (! is_string($encoded) || $encoded === '') {
                return 'nin_slip must be a base64-encoded JPEG, PNG or PDF, or an uploaded file.';
            }

            // Accept a data: URI as well as bare base64 — browsers and most
            // file readers hand back the prefixed form.
            $encoded = preg_replace('#^data:[^;]+;base64,#', '', trim($encoded));
            $bytes = base64_decode($encoded, true);

            if ($bytes === false || $bytes === '') {
                return 'nin_slip is not valid base64.';
            }
        }

        if (strlen($bytes) > self::MAX_SLIP_BYTES) {
            return 'The NIN slip must be 5MB or smaller.';
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        if (! in_array($mime, self::SLIP_MIMES, true)) {
            return 'The NIN slip must be a JPEG, PNG or PDF.';
        }

        return [
            'bytes' => $bytes,
            'extension' => match ($mime) {
                'application/pdf' => 'pdf',
                'image/png' => 'png',
                default => 'jpg',
            },
        ];
    }

    /**
     * The core fields are always present; the old/new pairs appear only for the
     * service type that changes them, so a phone modification does not come
     * back padded with empty name fields.
     *
     * @return array<string, mixed>
     */
    private function present(BvnModification $r): array
    {
        $changes = [];

        if ($this->needsName($r->serviceType)) {
            $changes += [
                'old_first_name' => $r->oldFirstName,
                'old_middle_name' => $r->oldMiddleName,
                'old_last_name' => $r->oldLastName,
                'new_first_name' => $r->newFirstName,
                'new_middle_name' => $r->newMiddleName,
                'new_last_name' => $r->newLastName,
            ];
        }

        if ($this->needsDob($r->serviceType)) {
            $changes += [
                'old_dob' => $r->oldDob?->toDateString(),
                'new_dob' => $r->newDob?->toDateString(),
            ];
        }

        if ($this->needsPhone($r->serviceType)) {
            $changes += [
                'old_phone' => $r->oldPhoneNumber,
                'new_phone' => $r->newPhoneNumber,
            ];
        }

        return [
            'id' => $r->id,
            'bvn' => $r->bvn,
            'nin' => $r->nin,
            'service_type' => $r->serviceType,
            'service_label' => $this->serviceLabel($r->serviceType),
            'status' => $r->status,
            'comment' => $r->comment,
            'amount_charged' => (float) $r->amountCharged,
            ...$changes,
            'submitted_at' => $r->createdAt?->toIso8601String(),
            'updated_at' => $r->updatedAt?->toIso8601String(),
        ];
    }
}
