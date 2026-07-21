<?php

namespace App\Http\Controllers;

use App\Models\BvnModification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * BVN Modification — user side.
 *
 * Port of the nimcweb Next.js feature (app/(protectedpages)/bvn-modification):
 * a user submits a BVN modification request (name / DOB / phone, in various
 * combinations), uploads a NIN slip and is charged the configured service fee
 * from their wallet balance. Requests are then processed by an admin.
 */
class BvnModificationController extends Controller
{
    use Concerns\BvnModificationPricing;

    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    /**
     * Show the submission form (with current prices + wallet balance).
     */
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('BvnModification/Index', [
            'wallet' => $this->walletPayload($user),
            'prices' => $this->pricePayload(),
        ]);
    }

    /**
     * List the current user's submitted requests.
     */
    public function requests(Request $request)
    {
        $user = Auth::user();

        $query = BvnModification::where('userId', $user->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('bvn', 'like', "%{$search}%")
                    ->orWhere('nin', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($serviceType = $request->input('serviceType')) && $serviceType !== 'all') {
            $query->where('serviceType', $serviceType);
        }

        if ($dateFrom = $request->input('dateFrom')) {
            $query->whereDate('createdAt', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('dateTo')) {
            $query->whereDate('createdAt', '<=', $dateTo);
        }

        $requests = $query
            ->orderBy('createdAt', 'desc')
            ->paginate(10)
            ->through(fn (BvnModification $r) => [
                'id' => $r->id,
                'bvn' => $r->bvn,
                'nin' => $r->nin,
                'serviceType' => $r->serviceType,
                'service_label' => $this->serviceLabel($r->serviceType),
                'status' => $r->status,
                'comment' => $r->comment,
                'old_balance' => $r->oldBal,
                'new_balance' => $r->newBal,
                'amount_charged' => $r->amountCharged,
                'created_at' => $r->createdAt,
            ])
            ->withQueryString();

        return Inertia::render('BvnModification/Requests', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status', 'serviceType', 'dateFrom', 'dateTo']),
        ]);
    }

    /**
     * Submit a new BVN modification request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'serviceType' => 'required|string|in:'.implode(',', $this->serviceTypes()),
            'bvn' => 'required|string',
            'nin' => 'required|string',
            'ninSlip' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'oldFirstName' => 'nullable|string',
            'oldMiddleName' => 'nullable|string',
            'oldLastName' => 'nullable|string',
            'oldDob' => 'nullable|date',
            'oldPhoneNumber' => 'nullable|string',
            'newFirstName' => 'nullable|string',
            'newMiddleName' => 'nullable|string',
            'newLastName' => 'nullable|string',
            'newDob' => 'nullable|date',
            'newPhoneNumber' => 'nullable|string',
        ]);

        $serviceType = $validated['serviceType'];

        // Conditional validation by service type (mirrors the form's validateForm()).
        if ($this->needsName($serviceType) && (empty($validated['oldFirstName']) || empty($validated['oldLastName']) || empty($validated['newFirstName']) || empty($validated['newLastName']))) {
            return back()->withErrors(['message' => 'First and last name fields are required for this service.']);
        }
        if ($this->needsDob($serviceType) && (empty($validated['oldDob']) || empty($validated['newDob']))) {
            return back()->withErrors(['message' => 'Both old and new date of birth fields are required.']);
        }
        if ($this->needsPhone($serviceType) && (empty($validated['oldPhoneNumber']) || empty($validated['newPhoneNumber']))) {
            return back()->withErrors(['message' => 'Both old and new phone numbers are required.']);
        }

        $price = $this->servicePrice($serviceType);
        if ($price === null) {
            return back()->withErrors(['message' => 'Price not set for the selected service. Please contact support.']);
        }

        $user = Auth::user();
        $oldBalance = (float) $user->balance;

        if ($oldBalance < $price) {
            return back()->withErrors(['message' => 'Insufficient balance. Please fund your wallet.']);
        }

        $file = $request->file('ninSlip');
        $binary = file_get_contents($file->getRealPath());
        $fileName = 'nin-slip-'.$validated['bvn'].'.'.$file->getClientOriginalExtension();

        // Postgres `bytea` columns must be bound as a LOB, otherwise the raw
        // bytes are rejected as an invalid UTF-8 text parameter. Laravel binds
        // resource values as PDO::PARAM_LOB, so pass a stream rather than a string.
        $slipStream = fopen('php://temp', 'r+');
        fwrite($slipStream, $binary);
        rewind($slipStream);

        // Charge the wallet first; refund if persistence fails.
        if (! $user->debit($price, false, ['fundingtype' => 'bvn_modification'])) {
            return back()->withErrors(['message' => 'Insufficient balance. Please fund your wallet.']);
        }

        $newBalance = (float) $user->fresh()->balance;

        try {
            $data = [
                'serviceType' => $serviceType,
                'bvn' => $validated['bvn'],
                'nin' => $validated['nin'],
                'ninSlipUrl' => $fileName,
                'ninSlipImage' => $slipStream,
                'oldBal' => (string) $oldBalance,
                'newBal' => (string) $newBalance,
                'amountCharged' => (string) $price,
                'status' => 'pending',
                'userId' => $user->id,
            ];

            if ($this->needsName($serviceType)) {
                $data['oldFirstName'] = $validated['oldFirstName'] ?? null;
                $data['oldMiddleName'] = $validated['oldMiddleName'] ?? null;
                $data['oldLastName'] = $validated['oldLastName'] ?? null;
                $data['newFirstName'] = $validated['newFirstName'] ?? null;
                $data['newMiddleName'] = $validated['newMiddleName'] ?? null;
                $data['newLastName'] = $validated['newLastName'] ?? null;
            }

            if ($this->needsDob($serviceType)) {
                $data['oldDob'] = $validated['oldDob'] ?? null;
                $data['newDob'] = $validated['newDob'] ?? null;
            }

            if ($this->needsPhone($serviceType)) {
                $data['oldPhoneNumber'] = $validated['oldPhoneNumber'] ?? null;
                $data['newPhoneNumber'] = $validated['newPhoneNumber'] ?? null;
            }

            BvnModification::create($data);
        } catch (\Throwable $e) {
            // Refund on failure.
            $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error('BVN modification error: '.$e->getMessage());

            return back()->withErrors(['message' => 'Failed to process request. You have not been charged.']);
        } finally {
            if (is_resource($slipStream)) {
                fclose($slipStream);
            }
        }

        return redirect()->route('bvn-modification.requests')
            ->with('success', 'Your BVN modification request has been submitted successfully.');
    }

    /**
     * Show a single request's details (own request only).
     */
    public function show(BvnModification $modification)
    {
        if ($modification->userId !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403);
        }

        return Inertia::render('BvnModification/Show', [
            'request' => $this->detailPayload($modification),
        ]);
    }

    /**
     * Serve the stored NIN slip image/PDF for a request.
     */
    public function slip(BvnModification $modification)
    {
        if ($modification->userId !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403);
        }

        $binary = $modification->ninSlipImage;
        if (empty($binary)) {
            abort(404);
        }

        // Pg bytea may surface as a stream resource.
        if (is_resource($binary)) {
            $binary = stream_get_contents($binary);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) ?: 'application/octet-stream';
        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            default => 'bin',
        };

        return response($binary, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="nin-slip-'.Str::limit($modification->id, 8, '').'.'.$ext.'"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
