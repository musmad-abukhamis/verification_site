<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesAsyncNinJobs;
use App\Http\Controllers\Controller;
use App\Models\Ipe;
use App\Services\Nin\AsyncJobService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin view of IPE clearances — jobs that take 30 minutes to 3 hours.
 *
 * The user-facing side has existed since the port; this is the counterpart the
 * NIN validations already had, and it is where a stalled clearance gets settled:
 * force the status check, set the outcome by hand, refund. See
 * ManagesAsyncNinJobs.
 */
class NinIpeController extends Controller
{
    use ManagesAsyncNinJobs;

    protected function jobService(): string
    {
        return 'nin.ipe';
    }

    /**
     * @return array<string, mixed>
     */
    protected function jobInput(Model $record): array
    {
        return ['tracking_id' => $record->trkid];
    }

    /** Overwrite the clearance's status and result by hand. */
    public function updateStatus(Request $request, Ipe $clearance)
    {
        return $this->settleUpdate($request, $clearance);
    }

    /** Poll the provider on the user's behalf. */
    public function recheck(Ipe $clearance, AsyncJobService $jobs)
    {
        return $this->settleRecheck($clearance, $jobs);
    }

    /** Return money for a clearance that failed or stalled. */
    public function refund(Request $request, Ipe $clearance)
    {
        return $this->settleRefund($request, $clearance);
    }

    public function index(Request $request)
    {
        $query = Ipe::query()
            ->with(['user', 'provider'])
            ->latest('createdAt');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Clearances are meant to settle within three hours, so anything older
        // than that and still open is the queue an admin actually works.
        if ($request->boolean('stalled')) {
            $query->open()->where('createdAt', '<', now()->subHours(3));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('trkid', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $clearances = $query->paginate(20)->through(fn (Ipe $ipe) => [
            'id' => $ipe->id,
            'user' => $ipe->user ? [
                'id' => $ipe->user->id,
                'name' => $ipe->user->name,
                'email' => $ipe->user->email,
            ] : null,
            'tracking_id' => $ipe->trkid,
            'status' => $ipe->status,
            'comment' => $ipe->comment,
            'fee' => $ipe->chargedAmount(),
            'created_at' => $ipe->createdAt?->format('Y-m-d H:i'),
            'updated_at' => $ipe->updatedAt?->format('Y-m-d H:i'),
        ] + $this->jobAdminPayload($ipe));

        return Inertia::render('Admin/NinIpe/Index', [
            'clearances' => $clearances,
            'filters' => $request->only(['status', 'search', 'stalled']),
            'statuses' => Ipe::EDITABLE_STATUSES,
        ]);
    }

    public function show(Ipe $clearance)
    {
        $clearance->load(['user', 'provider']);

        return Inertia::render('Admin/NinIpe/Show', [
            'statuses' => Ipe::EDITABLE_STATUSES,
            'clearance' => $this->jobAdminPayload($clearance) + [
                'id' => $clearance->id,
                'user' => $clearance->user ? [
                    'id' => $clearance->user->id,
                    'name' => $clearance->user->name,
                    'email' => $clearance->user->email,
                ] : null,
                'tracking_id' => $clearance->trkid,
                'status' => $clearance->status,
                'comment' => $clearance->comment,
                'result' => $clearance->result,
                'fee' => $clearance->chargedAmount(),
                'old_balance' => (float) $clearance->oldBal,
                'new_balance' => (float) $clearance->newBal,
                'created_at' => $clearance->createdAt?->format('Y-m-d H:i:s'),
                'updated_at' => $clearance->updatedAt?->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
