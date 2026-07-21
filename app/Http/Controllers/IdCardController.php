<?php

namespace App\Http\Controllers;

use App\Models\ServicePrice;
use App\Models\IdCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * ID Card application — user side.
 *
 * Port of the nimcweb Next.js feature (app/(protectedpages)/idcard): a user
 * submits an ID-card application (full name / email / agent id + passport
 * photo) and is charged the configured `idcardfee` from their wallet. The
 * form and the user's own request list live on a single page, mirroring the
 * source page.tsx. Requests are then processed by an admin.
 */
class IdCardController extends Controller
{
    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    private function price(): ?float
    {
        return ServicePrice::priceForUser('bvn.idcard', Auth::user());
    }

    /**
     * Show the application form + the current user's requests.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = IdCard::where('userId', $user->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('agentId', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $requests = $query
            ->orderBy('createdAt', 'desc')
            ->paginate(10)
            ->through(fn (IdCard $r) => [
                'id' => $r->id,
                'fullname' => $r->fullname,
                'email' => $r->email,
                'agentId' => $r->agentId,
                'status' => $r->status,
                'comment' => $r->comment,
                'old_balance' => $r->oldBalance,
                'new_balance' => $r->newBalance,
                'amount_charged' => $r->amountCharged,
                'created_at' => $r->createdAt,
                'image_url' => route('idcard.image', $r->id),
            ])
            ->withQueryString();

        return Inertia::render('IdCard/Index', [
            'wallet' => $this->walletPayload($user),
            'price' => $this->price(),
            'requests' => $requests,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Submit a new ID card application.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fullname' => 'required|string|max:27',
            'email' => 'required|email',
            'agentId' => 'required|string',
            // 500KB max, matching the source client/server validation.
            'passportImage' => 'required|file|mimes:jpeg,jpg,png,webp|max:500',
        ]);

        $price = $this->price();
        if ($price === null) {
            return back()->withErrors(['message' => 'ID card service fee not configured. Please contact support.']);
        }

        $user = Auth::user();
        $oldBalance = (float) $user->balance;

        if ($oldBalance < $price) {
            return back()->withErrors(['message' => 'Insufficient balance. Please fund your wallet.']);
        }

        $binary = file_get_contents($request->file('passportImage')->getRealPath());

        // Postgres `bytea` columns must be bound as a LOB, otherwise the raw
        // bytes are rejected as an invalid UTF-8 text parameter. Laravel binds
        // resource values as PDO::PARAM_LOB, so pass a stream rather than a string.
        $imageStream = fopen('php://temp', 'r+');
        fwrite($imageStream, $binary);
        rewind($imageStream);

        // Charge the wallet first; refund if persistence fails.
        if (! $user->debit($price, false, ['fundingtype' => 'idcard'])) {
            return back()->withErrors(['message' => 'Insufficient balance. Please fund your wallet.']);
        }

        $newBalance = (float) $user->fresh()->balance;

        try {
            IdCard::create([
                'fullname' => $validated['fullname'],
                'email' => $validated['email'],
                'agentId' => $validated['agentId'],
                'passportImage' => $imageStream,
                'oldBalance' => (string) $oldBalance,
                'newBalance' => (string) $newBalance,
                'amountCharged' => (string) $price,
                'status' => 'pending',
                'userId' => $user->id,
            ]);
        } catch (\Throwable $e) {
            // Refund on failure.
            $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error('ID card request error: '.$e->getMessage());

            return back()->withErrors(['message' => 'Failed to process request. You have not been charged.']);
        } finally {
            if (is_resource($imageStream)) {
                fclose($imageStream);
            }
        }

        return redirect()->route('idcard.index')
            ->with('success', 'Your ID card application has been submitted successfully.');
    }

    /**
     * Serve the stored passport image for a request (own request only).
     */
    public function image(IdCard $idCard)
    {
        if ($idCard->userId !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403);
        }

        $binary = $idCard->passportImage;
        if (empty($binary)) {
            abort(404);
        }

        // Pg bytea may surface as a stream resource.
        if (is_resource($binary)) {
            $binary = stream_get_contents($binary);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) ?: 'image/jpeg';

        return response($binary, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="passport.jpg"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
