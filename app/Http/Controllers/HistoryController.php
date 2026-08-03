<?php

namespace App\Http\Controllers;

use App\Models\BvnModification;
use App\Models\BvnRetrieval;
use App\Models\BvnSdkForm;
use App\Models\FailedVerificationCharge;
use App\Models\Ipe;
use App\Models\NinDetail;
use App\Models\Validation;
use App\Models\WalletHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * One place to read your own history, split by what the record actually is.
 *
 * Before this, a user's past was scattered across the service pages that
 * produced it, and two of those pages showed each other's rows: NIN
 * Verification and NIN Validation share the `validation` table, and nothing
 * distinguished them. They are separate services — a lookup that answers in
 * seconds versus a job filed with NIMC that takes days — with separate prices,
 * so they get separate tabs and the queries scope by `validation.service`
 * (see the Validation model's `verifications()` / `validations()` scopes).
 *
 * Each screen renders one tab per request: the tab is a URL parameter, so a
 * history view is linkable, and only the active tab's rows are queried.
 */
class HistoryController extends Controller
{
    /** Rows per page across every tab. */
    private const PER_PAGE = 15;

    /* ======================================================================
     | NIN
     | ====================================================================== */

    /**
     * NIN history — Verifications, Validations and IPE clearances.
     */
    public function nin(Request $request)
    {
        $userId = Auth::id();

        $tabs = [
            ['key' => 'verify', 'label' => 'Verifications', 'count' => Validation::where('userId', $userId)->verifications()->count()],
            ['key' => 'validation', 'label' => 'Validations', 'count' => Validation::where('userId', $userId)->validations()->count()],
            ['key' => 'ipe', 'label' => 'IPE Clearance', 'count' => Ipe::where('userId', $userId)->count()],
        ];

        $tab = $this->resolveTab($request, $tabs);

        [$records, $stats] = match ($tab) {
            'validation' => $this->ninValidationTab($request, $userId),
            'ipe' => $this->ipeTab($request, $userId),
            default => $this->ninVerifyTab($request, $userId),
        };

        return Inertia::render('History/Nin', [
            'tab' => $tab,
            'tabs' => $tabs,
            'records' => $records,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Instant NIN lookups. Charged per attempt, and a provider-confirmed
     * failure can carry its own smaller fee — which is why every row reports
     * whether it was billed, not just whether it succeeded.
     */
    private function ninVerifyTab(Request $request, string $userId): array
    {
        $base = Validation::where('userId', $userId)->verifications();

        $records = $this->applyFilters(clone $base, $request, ['nin', 'comment'])
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // One lookup for the page: the failed-verification charge (or the
        // recorded decision not to charge) is keyed by the row it belongs to.
        $charges = FailedVerificationCharge::whereIn(
            'record_id',
            collect($records->items())->pluck('id')->map(fn ($id) => (string) $id),
        )->get()->keyBy('record_id');

        $records->through(fn (Validation $r) => [
            'id' => $r->id,
            'reference' => (string) $r->id,
            'identity' => $r->nin,
            'method' => $this->methodLabel($r->service),
            'status' => $r->status,
            'comment' => $r->comment,
            'old_balance' => (float) $r->oldBal,
            'new_balance' => (float) $r->newBal,
            // A lookup's fee is the balance movement: these rows predate the
            // `price` column and refunds mean the charge is not the list price.
            'amount' => round((float) $r->oldBal - (float) $r->newBal, 2),
            'date' => $r->createdAt?->format('M d, Y H:i'),
        ] + (($charges->get((string) $r->id))?->toHistoryPayload() ?? FailedVerificationCharge::emptyHistoryPayload()));

        $spent = (float) (clone $base)->sum(DB::raw('"oldBal" - "newBal"'));

        return [$records, [
            ['label' => 'Verifications', 'value' => (clone $base)->count()],
            ['label' => 'Successful', 'value' => (clone $base)->where('status', 'completed')->count(), 'tone' => 'success'],
            ['label' => 'Failed', 'value' => (clone $base)->where('status', 'failed')->count(), 'tone' => 'danger'],
            ['label' => 'Total spent', 'value' => $spent, 'money' => true, 'tone' => 'brass'],
        ]];
    }

    /**
     * Filed NIN validations — the multi-day NIMC job. Not a lookup, and never
     * listed with one.
     */
    private function ninValidationTab(Request $request, string $userId): array
    {
        $base = Validation::where('userId', $userId)->validations();

        $records = $this->applyFilters(clone $base, $request, ['nin', 'comment', 'providerRef'])
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Validation $r) => $this->asyncJobRow($r, $r->nin, 'NIN'));

        return [$records, $this->asyncJobStats($base)];
    }

    /**
     * IPE clearances, keyed on the 15-character tracking ID.
     */
    private function ipeTab(Request $request, string $userId): array
    {
        $base = Ipe::where('userId', $userId);

        $records = $this->applyFilters(clone $base, $request, ['trkid', 'comment', 'providerRef'])
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Ipe $r) => $this->asyncJobRow($r, $r->trkid, 'Tracking ID'));

        return [$records, $this->asyncJobStats($base)];
    }

    /* ======================================================================
     | BVN
     | ====================================================================== */

    /**
     * BVN history — Verifications, Modifications, Onboarding and Retrievals.
     */
    public function bvn(Request $request)
    {
        $userId = Auth::id();

        $tabs = [
            ['key' => 'verify', 'label' => 'Verifications', 'count' => NinDetail::where('userId', $userId)->where('idtype', 'bvn')->count()],
            ['key' => 'modification', 'label' => 'Modifications', 'count' => BvnModification::where('userId', $userId)->count()],
            ['key' => 'onboarding', 'label' => 'Onboarding', 'count' => BvnSdkForm::where('userId', $userId)->count()],
            ['key' => 'retrieval', 'label' => 'Retrievals', 'count' => BvnRetrieval::where('userId', $userId)->count()],
        ];

        $tab = $this->resolveTab($request, $tabs);

        [$records, $stats] = match ($tab) {
            'modification' => $this->bvnModificationTab($request, $userId),
            'onboarding' => $this->bvnOnboardingTab($request, $userId),
            'retrieval' => $this->bvnRetrievalTab($request, $userId),
            default => $this->bvnVerifyTab($request, $userId),
        };

        return Inertia::render('History/Bvn', [
            'tab' => $tab,
            'tabs' => $tabs,
            'records' => $records,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * BVN lookups, logged to NINDetails with idtype = "bvn".
     */
    private function bvnVerifyTab(Request $request, string $userId): array
    {
        $base = NinDetail::where('userId', $userId)->where('idtype', 'bvn');

        $records = $this->applyFilters(clone $base, $request, ['idvalue', 'surname', 'othernames'], 'createdAt')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // BVN searches record the charge against the same reference they use
        // as the record id, so the "was I billed for this failure" answer
        // resolves in one query for the page.
        $charges = FailedVerificationCharge::whereIn(
            'record_id',
            collect($records->items())->pluck('id')->map(fn ($id) => (string) $id),
        )->get()->keyBy('record_id');

        $records->through(fn (NinDetail $r) => [
            'id' => $r->id,
            'reference' => (string) $r->id,
            'identity' => $r->idvalue,
            'name' => trim(($r->surname ?? '').' '.($r->othernames ?? '')) ?: '—',
            'slip_type' => $r->sliptype,
            'status' => $r->status,
            'amount' => (float) ($r->price ?? 0),
            'old_balance' => (float) $r->oldBal,
            'new_balance' => (float) $r->newBal,
            'date' => $r->createdAt?->format('M d, Y H:i'),
        ] + (($charges->get((string) $r->id))?->toHistoryPayload() ?? FailedVerificationCharge::emptyHistoryPayload()));

        return [$records, [
            ['label' => 'Verifications', 'value' => (clone $base)->count()],
            ['label' => 'Successful', 'value' => (clone $base)->where('status', 'success')->count(), 'tone' => 'success'],
            ['label' => 'Failed', 'value' => (clone $base)->where('status', 'fail')->count(), 'tone' => 'danger'],
            ['label' => 'Total spent', 'value' => (float) (clone $base)->sum('price'), 'money' => true, 'tone' => 'brass'],
        ]];
    }

    /**
     * BVN modification requests, fulfilled by an admin.
     */
    private function bvnModificationTab(Request $request, string $userId): array
    {
        $base = BvnModification::where('userId', $userId);

        $records = $this->applyFilters(clone $base, $request, ['bvn', 'nin', 'comment'], 'createdAt')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (BvnModification $r) => [
                'id' => $r->id,
                'reference' => (string) $r->id,
                'identity' => $r->bvn,
                'nin' => $r->nin,
                'service_label' => $this->modificationLabel($r->serviceType),
                'status' => $r->status,
                'comment' => $r->comment,
                'amount' => (float) $r->amountCharged,
                'old_balance' => (float) $r->oldBal,
                'new_balance' => (float) $r->newBal,
                'date' => $r->createdAt?->format('M d, Y H:i'),
            ]);

        return [$records, [
            ['label' => 'Requests', 'value' => (clone $base)->count()],
            ['label' => 'Completed', 'value' => (clone $base)->where('status', 'completed')->count(), 'tone' => 'success'],
            ['label' => 'Pending', 'value' => (clone $base)->where('status', 'pending')->count(), 'tone' => 'warning'],
            // amountCharged is a Prisma string column; sum it as a number.
            ['label' => 'Total spent', 'value' => $this->sumAsNumber($base, 'amountCharged'), 'money' => true, 'tone' => 'brass'],
        ]];
    }

    /**
     * BVN SDK onboarding registrations.
     */
    private function bvnOnboardingTab(Request $request, string $userId): array
    {
        $base = BvnSdkForm::where('userId', $userId);

        $records = $this->applyFilters(clone $base, $request, ['firstName', 'lastName', 'email', 'phoneNumber'], 'createdAt')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (BvnSdkForm $r) => [
                'id' => $r->id,
                'reference' => (string) $r->id,
                'name' => trim($r->firstName.' '.$r->lastName),
                'email' => $r->email,
                'phone' => $r->phoneNumber,
                'location' => trim(($r->lga ?? '').', '.($r->stateOfResidence ?? ''), ', '),
                'status' => $r->status ?: 'pending',
                'comment' => $r->comment,
                'old_balance' => (float) $r->oldBal,
                'new_balance' => (float) $r->newBal,
                'amount' => round((float) $r->oldBal - (float) $r->newBal, 2),
                'date' => $r->createdAt?->format('M d, Y H:i'),
            ]);

        return [$records, [
            ['label' => 'Registrations', 'value' => (clone $base)->count()],
            ['label' => 'Completed', 'value' => (clone $base)->where('status', 'completed')->count(), 'tone' => 'success'],
            // `status` is nullable on this table and a fresh registration
            // leaves it unset, so "not yet completed" is the honest count.
            ['label' => 'Awaiting', 'value' => (clone $base)->where(function (Builder $q) {
                $q->whereNull('status')->orWhere('status', '!=', 'completed');
            })->count(), 'tone' => 'warning'],
        ]];
    }

    /**
     * BVN retrieval requests (Ticket / BMS ID).
     */
    private function bvnRetrievalTab(Request $request, string $userId): array
    {
        $base = BvnRetrieval::where('userId', $userId);

        $records = $this->applyFilters(clone $base, $request, ['ticketId1', 'ticketId2', 'batchId', 'nin', 'bvn'], 'createdAt')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (BvnRetrieval $r) => [
                'id' => $r->id,
                'reference' => (string) $r->id,
                'name' => trim(($r->firstname ?? '').' '.($r->surname ?? '')) ?: '—',
                'ticket' => trim($r->ticketId1 ?: $r->ticketId2 ?: $r->batchId) ?: '—',
                'retrieval_type' => $r->retrievalType,
                'bvn' => trim((string) $r->bvn) ?: null,
                'status' => $r->status ?: 'pending',
                'comment' => $r->comment,
                'old_balance' => (float) $r->oldBal,
                'new_balance' => (float) $r->newBal,
                'amount' => round((float) $r->oldBal - (float) $r->newBal, 2),
                'date' => $r->createdAt?->format('M d, Y H:i'),
            ]);

        return [$records, [
            ['label' => 'Requests', 'value' => (clone $base)->count()],
            ['label' => 'Completed', 'value' => (clone $base)->where('status', 'completed')->count(), 'tone' => 'success'],
            // Nullable status column: anything not completed is still open.
            ['label' => 'Awaiting', 'value' => (clone $base)->where(function (Builder $q) {
                $q->whereNull('status')->orWhere('status', '!=', 'completed');
            })->count(), 'tone' => 'warning'],
        ]];
    }

    /* ======================================================================
     | Wallet
     | ====================================================================== */

    /**
     * Wallet history — money in, and money given back.
     *
     * Refunds are deliberately their own tab rather than a filter: a user
     * chasing a failed purchase is asking one specific question, and the
     * answer lives in two ledgers (see refundsTab).
     */
    public function wallet(Request $request)
    {
        $userId = Auth::id();

        $tabs = [
            ['key' => 'funding', 'label' => 'Wallet Funding', 'count' => $this->fundingQuery($userId)->count()],
            ['key' => 'refunds', 'label' => 'Refunds', 'count' => $this->refundCount($userId)],
        ];

        $tab = $this->resolveTab($request, $tabs);

        [$records, $stats] = $tab === 'refunds'
            ? $this->refundsTab($request, $userId)
            : $this->fundingTab($request, $userId);

        return Inertia::render('History/Wallet', [
            'tab' => $tab,
            'tabs' => $tabs,
            'records' => $records,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
            'balance' => (float) Auth::user()->balance,
        ]);
    }

    /**
     * Money paid in: virtual-account transfers, and admin credits. A refund is
     * a credit too, but it is not funding — it is your own money coming back.
     */
    private function fundingQuery(string $userId): Builder
    {
        return WalletHistory::where('userId', $userId)
            ->where('type', 'credit')
            ->where('fundingtype', '!=', 'refund');
    }

    private function fundingTab(Request $request, string $userId): array
    {
        $base = $this->fundingQuery($userId);

        $records = $this->applyFilters(clone $base, $request, ['id', 'fundingtype'], 'createdAt')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (WalletHistory $w) => [
                'id' => $w->id,
                'reference' => $w->id,
                'source' => $this->fundingLabel($w->fundingtype),
                'status' => $w->status,
                'amount' => (float) $w->amount,
                'old_balance' => (float) $w->oldbal,
                'new_balance' => (float) $w->newbal,
                'date' => $w->createdAt?->format('M d, Y H:i'),
            ]);

        return [$records, [
            ['label' => 'Times funded', 'value' => (clone $base)->count()],
            ['label' => 'Total funded', 'value' => (float) (clone $base)->sum('amount'), 'money' => true, 'tone' => 'success'],
            ['label' => 'Last funding', 'value' => (clone $base)->max('createdAt')
                ? Carbon::parse((clone $base)->max('createdAt'))->format('M d, Y')
                : '—'],
        ]];
    }

    /**
     * Refunds live in two ledgers and both count as the user's money coming
     * back, so the tab reads from both:
     *
     *   wallethistory  — every NIN/BVN/ID-card service reversal
     *   wallet_entries — the data module's own ledger, which is the only place
     *                    an auto-refunded data purchase is recorded
     *
     * Unioned in SQL rather than merged in PHP so the page stays paginated
     * over the whole set instead of over a truncated slice of each source.
     */
    private function refundsUnion(string $userId): \Illuminate\Database\Query\Builder
    {
        $fromWallet = DB::table('wallethistory')
            ->where('userId', $userId)
            ->where(function ($q) {
                $q->where('fundingtype', 'refund')->orWhere('status', 'refund');
            })
            ->select([
                'id',
                DB::raw("'service' as source"),
                'amount',
                'oldbal as old_balance',
                'newbal as new_balance',
                DB::raw('null as related_id'),
                DB::raw('"createdAt" as created_at'),
            ]);

        $fromEntries = DB::table('wallet_entries')
            ->where('user_id', $userId)
            ->where('direction', 'credit')
            ->where('reason', 'refund')
            ->select([
                'id',
                DB::raw("'data' as source"),
                'amount',
                // The data ledger records only the resulting balance; the
                // opening balance is implied by the amount.
                DB::raw('balance_after - amount as old_balance'),
                'balance_after as new_balance',
                'data_transaction_id as related_id',
                'created_at',
            ]);

        return DB::query()->fromSub($fromWallet->unionAll($fromEntries), 'refunds');
    }

    private function refundCount(string $userId): int
    {
        return $this->refundsUnion($userId)->count();
    }

    private function refundsTab(Request $request, string $userId): array
    {
        $query = $this->refundsUnion($userId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")->orWhere('related_id', 'like', "%{$search}%");
            });
        }

        $records = $query
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn ($r) => [
                'id' => $r->id,
                'reference' => $r->id,
                'source' => $r->source === 'data' ? 'Data purchase' : 'Service refund',
                'related_id' => $r->related_id,
                'status' => 'refund',
                'amount' => (float) $r->amount,
                'old_balance' => (float) $r->old_balance,
                'new_balance' => (float) $r->new_balance,
                'date' => $r->created_at
                    ? Carbon::parse($r->created_at)->format('M d, Y H:i')
                    : null,
            ]);

        $totals = $this->refundsUnion($userId)
            ->selectRaw('count(*) as entries, coalesce(sum(amount), 0) as total')
            ->first();

        return [$records, [
            ['label' => 'Refunds', 'value' => (int) ($totals->entries ?? 0)],
            ['label' => 'Total refunded', 'value' => (float) ($totals->total ?? 0), 'money' => true, 'tone' => 'success'],
        ]];
    }

    /* ======================================================================
     | Helpers
     | ====================================================================== */

    /**
     * The requested tab, or the first one when the parameter is missing or
     * points at something this page does not have.
     *
     * @param  array<int, array{key: string}>  $tabs
     */
    private function resolveTab(Request $request, array $tabs): string
    {
        $keys = array_column($tabs, 'key');
        $requested = (string) $request->input('tab');

        return in_array($requested, $keys, true) ? $requested : $keys[0];
    }

    /**
     * Search across the given columns plus an exact status match. Every tab
     * takes the same two parameters so switching tabs keeps the query string
     * meaningful.
     *
     * @param  array<int, string>  $searchable
     */
    private function applyFilters(Builder $query, Request $request, array $searchable, string $dateColumn = 'createdAt'): Builder
    {
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($searchable, $search) {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if (($status = $request->input('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($from = $request->input('from')) {
            $query->whereDate($dateColumn, '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate($dateColumn, '<=', $to);
        }

        return $query;
    }

    /**
     * Validation and IPE are the same shape of thing — a job filed with a
     * provider that finishes later — so they render through one row builder.
     */
    private function asyncJobRow($record, ?string $identity, string $identityLabel): array
    {
        return [
            'id' => $record->id,
            'reference' => (string) $record->id,
            'identity' => $identity,
            'identity_label' => $identityLabel,
            'status' => $record->status,
            'result' => $record->result,
            'comment' => $record->comment,
            'amount' => (float) ($record->price ?? 0),
            'refunded' => $record->refundedAt !== null,
            'refund_amount' => (float) ($record->refundAmount ?? 0),
            'provider_reference' => $record->providerRef,
            'old_balance' => (float) $record->oldBal,
            'new_balance' => (float) $record->newBal,
            'date' => $record->createdAt?->format('M d, Y H:i'),
            'updated' => $record->updatedAt?->format('M d, Y H:i'),
        ];
    }

    /**
     * @param  Builder  $base  an unfiltered, user-scoped query
     */
    private function asyncJobStats(Builder $base): array
    {
        return [
            ['label' => 'Submitted', 'value' => (clone $base)->count()],
            ['label' => 'In progress', 'value' => (clone $base)->where('status', 'processing')->count(), 'tone' => 'info'],
            ['label' => 'Completed', 'value' => (clone $base)->where('status', 'completed')->count(), 'tone' => 'success'],
            ['label' => 'Refunded', 'value' => (float) (clone $base)->sum('refundAmount'), 'money' => true, 'tone' => 'warning'],
        ];
    }

    /**
     * Which lookup produced a verification row.
     */
    private function methodLabel(?string $service): string
    {
        return match ($service) {
            'nin.phone' => 'By phone',
            'nin.demographic' => 'By demographics',
            'nin.verify' => 'By NIN',
            // Pre-dates the service column and could not be classified.
            default => 'Lookup',
        };
    }

    private function modificationLabel(?string $serviceType): string
    {
        return match ($serviceType) {
            'modify-name' => 'Name Modification',
            'modify-dob' => 'DOB Modification',
            'modify-name-dob' => 'Name & DOB Modification',
            'modify-phone' => 'Phone Number Modification',
            'modify-name-dob-phone' => 'Name, DOB & Phone Modification',
            'modify-email' => 'Email Modification',
            'modify-name-phone' => 'Name & Phone Modification',
            default => $serviceType ?: '—',
        };
    }

    /**
     * `fundingtype` is a storage tag, not a sentence.
     */
    private function fundingLabel(?string $fundingType): string
    {
        return match ($fundingType) {
            'automatic-funding' => 'Bank transfer',
            'manual-funding' => 'Manual funding',
            'admin-credit' => 'Admin credit',
            'wallet-funding' => 'Wallet funding',
            default => ucfirst(str_replace(['-', '_'], ' ', (string) $fundingType)) ?: 'Funding',
        };
    }

    /**
     * Three BVN tables store money in string columns (a Prisma port artefact),
     * so they cannot be summed in SQL portably.
     */
    private function sumAsNumber(Builder $base, string $column): float
    {
        return round((clone $base)->pluck($column)->sum(fn ($v) => (float) $v), 2);
    }
}
