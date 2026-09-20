<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BvnModification;
use App\Models\BvnRetrieval;
use App\Models\BvnSdkForm;
use App\Models\FailedVerificationCharge;
use App\Models\Ipe;
use App\Models\NinDetail;
use App\Models\User;
use App\Models\Validation;
use App\Models\WalletHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * One place to read everybody's history, split by what the record actually is.
 *
 * The admin panel used to answer "what happened" from five unrelated screens in
 * four different sidebar groups — a Transactions list, a Wallet ▸ Transactions
 * list, a Verification Logs list, a BVN ▸ Searches list and a Reports ▸ NIN/BVN
 * Transactions list — each with its own filter bar and its own idea of what a
 * row looks like. Worse, Verification Logs read the whole `validation` table
 * without scoping by `service`, so it listed NIN lookups and filed NIN
 * validations together: two services with different prices and turnarounds,
 * indistinguishable in the list an admin uses to answer a billing question.
 *
 * This is the admin twin of the user-side {@see \App\Http\Controllers\HistoryController}:
 * the same three screens, the same tabs, the same layout — but unscoped, so a
 * row carries the user it belongs to and the search box matches on them.
 *
 * These screens are read-only. Acting on a record (refund, recheck, status
 * override) still lives on the service's own management screen.
 */
class HistoryController extends Controller
{
    /** Rows per page across every tab. */
    private const PER_PAGE = 20;

    /* ======================================================================
     | NIN
     | ====================================================================== */

    /**
     * NIN history — Verifications, Validations, IPE clearances and slips.
     */
    public function nin(Request $request)
    {
        $tabs = [
            ['key' => 'verify', 'label' => 'Verifications', 'count' => Validation::verifications()->count()],
            ['key' => 'validation', 'label' => 'Validations', 'count' => Validation::validations()->count()],
            ['key' => 'ipe', 'label' => 'IPE Clearance', 'count' => Ipe::count()],
            ['key' => 'slip', 'label' => 'Slips', 'count' => $this->ninSlipQuery()->count()],
        ];

        $tab = $this->resolveTab($request, $tabs);

        [$records, $stats] = match ($tab) {
            'validation' => $this->ninValidationTab($request),
            'ipe' => $this->ipeTab($request),
            'slip' => $this->ninSlipTab($request),
            default => $this->ninVerifyTab($request),
        };

        return Inertia::render('Admin/History/Nin', [
            'tab' => $tab,
            'tabs' => $tabs,
            'records' => $records,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
        ]);
    }

    /**
     * Instant NIN lookups. Charged per attempt, and a provider-confirmed
     * failure can carry its own smaller fee — which is why every row reports
     * whether it was billed, not just whether it succeeded.
     */
    private function ninVerifyTab(Request $request): array
    {
        $base = Validation::verifications();

        $records = $this->applyFilters(clone $base, $request, ['nin', 'comment'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $charges = $this->chargesFor($records);

        $records->through(fn (Validation $r) => [
            'id' => $r->id,
            'reference' => (string) $r->id,
            'user' => $this->userPayload($r->user),
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

        $revenue = (float) (clone $base)->sum(DB::raw('"oldBal" - "newBal"'));

        return [$records, [
            ['label' => 'Verifications', 'value' => (clone $base)->count()],
            ['label' => 'Successful', 'value' => (clone $base)->where('status', 'completed')->count(), 'tone' => 'success'],
            ['label' => 'Failed', 'value' => (clone $base)->where('status', 'failed')->count(), 'tone' => 'danger'],
            ['label' => 'Revenue', 'value' => $revenue, 'money' => true, 'tone' => 'brass'],
        ]];
    }

    /**
     * Filed NIN validations — the multi-day NIMC job. Not a lookup, and never
     * listed with one.
     */
    private function ninValidationTab(Request $request): array
    {
        $base = Validation::validations();

        $records = $this->applyFilters(clone $base, $request, ['nin', 'comment', 'providerRef'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Validation $r) => $this->asyncJobRow($r, $r->nin, 'NIN'));

        return [$records, $this->asyncJobStats($base)];
    }

    /**
     * IPE clearances, keyed on the 15-character tracking ID.
     */
    private function ipeTab(Request $request): array
    {
        $base = Ipe::query();

        $records = $this->applyFilters(clone $base, $request, ['trkid', 'comment', 'providerRef'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Ipe $r) => $this->asyncJobRow($r, $r->trkid, 'Tracking ID'));

        return [$records, $this->asyncJobStats($base)];
    }

    /**
     * The NIN side of NINDetails — verification slips and slip downloads.
     *
     * Anything that is not a BVN lookup, rather than an allow-list of id types:
     * `idtype` carries the lookup method here ("nin", "phone", "demographic"),
     * so naming them individually would silently drop a method added later.
     */
    private function ninSlipQuery(): Builder
    {
        return NinDetail::where(function (Builder $q) {
            $q->where('idtype', '!=', 'bvn')->orWhereNull('idtype');
        });
    }

    private function ninSlipTab(Request $request): array
    {
        $base = $this->ninSlipQuery();

        $records = $this->applyFilters(clone $base, $request, ['idvalue', 'surname', 'othernames', 'sliptype'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (NinDetail $r) => [
                'id' => $r->id,
                'reference' => (string) $r->id,
                'user' => $this->userPayload($r->user),
                'identity' => $r->idvalue,
                'name' => trim(($r->surname ?? '').' '.($r->othernames ?? '')) ?: '—',
                'id_type' => $r->idtype,
                'slip_type' => $r->sliptype,
                'channel' => $r->channel,
                'status' => $r->status,
                'amount' => (float) ($r->price ?? 0),
                'old_balance' => (float) $r->oldBal,
                'new_balance' => (float) $r->newBal,
                'date' => $r->createdAt?->format('M d, Y H:i'),
            ]);

        return [$records, [
            ['label' => 'Slips', 'value' => (clone $base)->count()],
            ['label' => 'Successful', 'value' => (clone $base)->where('status', 'success')->count(), 'tone' => 'success'],
            ['label' => 'Failed', 'value' => (clone $base)->where('status', 'fail')->count(), 'tone' => 'danger'],
            ['label' => 'Revenue', 'value' => (float) (clone $base)->where('status', 'success')->sum('price'), 'money' => true, 'tone' => 'brass'],
        ]];
    }

    /* ======================================================================
     | BVN
     | ====================================================================== */

    /**
     * BVN history — Verifications, Modifications, Onboarding and Retrievals.
     */
    public function bvn(Request $request)
    {
        $tabs = [
            ['key' => 'verify', 'label' => 'Verifications', 'count' => NinDetail::where('idtype', 'bvn')->count()],
            ['key' => 'modification', 'label' => 'Modifications', 'count' => BvnModification::count()],
            ['key' => 'onboarding', 'label' => 'Onboarding', 'count' => BvnSdkForm::count()],
            ['key' => 'retrieval', 'label' => 'Retrievals', 'count' => BvnRetrieval::count()],
        ];

        $tab = $this->resolveTab($request, $tabs);

        [$records, $stats] = match ($tab) {
            'modification' => $this->bvnModificationTab($request),
            'onboarding' => $this->bvnOnboardingTab($request),
            'retrieval' => $this->bvnRetrievalTab($request),
            default => $this->bvnVerifyTab($request),
        };

        return Inertia::render('Admin/History/Bvn', [
            'tab' => $tab,
            'tabs' => $tabs,
            'records' => $records,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
        ]);
    }

    /**
     * BVN lookups and searches, logged to NINDetails with idtype = "bvn".
     */
    private function bvnVerifyTab(Request $request): array
    {
        $base = NinDetail::where('idtype', 'bvn');

        $records = $this->applyFilters(clone $base, $request, ['idvalue', 'surname', 'othernames'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $charges = $this->chargesFor($records);

        $records->through(fn (NinDetail $r) => [
            'id' => $r->id,
            'reference' => (string) $r->id,
            'user' => $this->userPayload($r->user),
            'identity' => $r->idvalue,
            'name' => trim(($r->surname ?? '').' '.($r->othernames ?? '')) ?: '—',
            'slip_type' => $r->sliptype,
            'channel' => $r->channel,
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
            ['label' => 'Revenue', 'value' => (float) (clone $base)->where('status', 'success')->sum('price'), 'money' => true, 'tone' => 'brass'],
        ]];
    }

    /**
     * BVN modification requests, fulfilled by an admin.
     */
    private function bvnModificationTab(Request $request): array
    {
        $base = BvnModification::query();

        $records = $this->applyFilters(clone $base, $request, ['bvn', 'nin', 'comment'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (BvnModification $r) => [
                'id' => $r->id,
                'reference' => (string) $r->id,
                'user' => $this->userPayload($r->user),
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
            ['label' => 'Revenue', 'value' => $this->sumAsNumber($base, 'amountCharged'), 'money' => true, 'tone' => 'brass'],
        ]];
    }

    /**
     * BVN SDK onboarding registrations.
     */
    private function bvnOnboardingTab(Request $request): array
    {
        $base = BvnSdkForm::query();

        $records = $this->applyFilters(clone $base, $request, ['firstName', 'lastName', 'email', 'phoneNumber'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (BvnSdkForm $r) => [
                'id' => $r->id,
                'reference' => (string) $r->id,
                'user' => $this->userPayload($r->user),
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
    private function bvnRetrievalTab(Request $request): array
    {
        $base = BvnRetrieval::query();

        $records = $this->applyFilters(clone $base, $request, ['ticketId1', 'ticketId2', 'batchId', 'nin', 'bvn'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (BvnRetrieval $r) => [
                'id' => $r->id,
                'reference' => (string) $r->id,
                'user' => $this->userPayload($r->user),
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
     * Wallet history — money in, money spent, and money given back.
     *
     * Every balance movement writes a `wallethistory` row (see
     * {@see \App\Models\Concerns\ManagesWallet}), so these three tabs between
     * them account for the whole ledger: credits that are not refunds, debits,
     * and refunds.
     */
    public function wallet(Request $request)
    {
        $tabs = [
            ['key' => 'funding', 'label' => 'Funding', 'count' => $this->fundingQuery()->count()],
            ['key' => 'debits', 'label' => 'Debits', 'count' => $this->debitQuery()->count()],
            ['key' => 'refunds', 'label' => 'Refunds', 'count' => $this->refundCount()],
        ];

        $tab = $this->resolveTab($request, $tabs);

        [$records, $stats] = match ($tab) {
            'debits' => $this->debitsTab($request),
            'refunds' => $this->refundsTab($request),
            default => $this->fundingTab($request),
        };

        return Inertia::render('Admin/History/Wallet', [
            'tab' => $tab,
            'tabs' => $tabs,
            'records' => $records,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
        ]);
    }

    /**
     * Money paid in: virtual-account transfers, manual and admin credits. A
     * refund is a credit too, but it is not funding — it is a user's own money
     * coming back, and it has its own tab.
     */
    private function fundingQuery(): Builder
    {
        return WalletHistory::where('type', 'credit')
            ->where('fundingtype', '!=', 'refund');
    }

    private function debitQuery(): Builder
    {
        return WalletHistory::where('type', 'debit');
    }

    private function fundingTab(Request $request): array
    {
        $base = $this->fundingQuery();

        $records = $this->applyFilters(clone $base, $request, ['id', 'fundingtype'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (WalletHistory $w) => $this->walletRow($w, $this->fundingLabel($w->fundingtype)));

        return [$records, [
            ['label' => 'Payments', 'value' => (clone $base)->count()],
            ['label' => 'Total funded', 'value' => (float) (clone $base)->sum('amount'), 'money' => true, 'tone' => 'success'],
            ['label' => 'Last funding', 'value' => (clone $base)->max('createdAt')
                ? Carbon::parse((clone $base)->max('createdAt'))->format('M d, Y')
                : '—'],
        ]];
    }

    /**
     * Every debit: service charges, and admin debits/resets. The old Wallet ▸
     * Transactions screen mixed these with funding behind a type dropdown; they
     * answer different questions, so they are different tabs.
     */
    private function debitsTab(Request $request): array
    {
        $base = $this->debitQuery();

        $records = $this->applyFilters(clone $base, $request, ['id', 'fundingtype'])
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (WalletHistory $w) => $this->walletRow($w, $this->debitLabel($w->fundingtype)));

        return [$records, [
            ['label' => 'Debits', 'value' => (clone $base)->count()],
            ['label' => 'Total debited', 'value' => (float) (clone $base)->sum('amount'), 'money' => true, 'tone' => 'brass'],
            ['label' => 'Last debit', 'value' => (clone $base)->max('createdAt')
                ? Carbon::parse((clone $base)->max('createdAt'))->format('M d, Y')
                : '—'],
        ]];
    }

    /**
     * Refunds live in two ledgers and both are a user's money coming back, so
     * the tab reads from both:
     *
     *   wallethistory  — every NIN/BVN/ID-card service reversal
     *   wallet_entries — the data module's own ledger, which is the only place
     *                    an auto-refunded data purchase is recorded
     *
     * Unioned in SQL rather than merged in PHP so the page stays paginated over
     * the whole set instead of over a truncated slice of each source.
     */
    private function refundsUnion(): \Illuminate\Database\Query\Builder
    {
        $fromWallet = DB::table('wallethistory')
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
                DB::raw('"userId" as user_id'),
                DB::raw('"createdAt" as created_at'),
            ]);

        $fromEntries = DB::table('wallet_entries')
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
                'user_id',
                'created_at',
            ]);

        return DB::query()->fromSub($fromWallet->unionAll($fromEntries), 'refunds');
    }

    private function refundCount(): int
    {
        return $this->refundsUnion()->count();
    }

    private function refundsTab(Request $request): array
    {
        $query = $this->refundsUnion();

        // The union has no Eloquent relation to search through, so a search for
        // a person is resolved to ids first and matched on the column.
        if ($search = $request->input('search')) {
            $userIds = $this->userIdsMatching($search);

            $query->where(function ($q) use ($search, $userIds) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('related_id', 'like', "%{$search}%");

                if ($userIds->isNotEmpty()) {
                    $q->orWhereIn('user_id', $userIds);
                }
            });
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $records = $query
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $users = User::whereIn('id', collect($records->items())->pluck('user_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $records->through(fn ($r) => [
            'id' => $r->id,
            'reference' => $r->id,
            'user' => $this->userPayload($users->get($r->user_id)),
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

        $totals = $this->refundsUnion()
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
     * Search across the given columns *and the owning user*, plus an exact
     * status match and a date window. Every tab takes the same parameters so
     * switching tabs keeps the query string meaningful.
     *
     * Searching the user is the difference from the user-side screens: an admin
     * arrives at history with a person in mind at least as often as a reference.
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

                $q->orWhereHas('user', function (Builder $uq) use ($search) {
                    $uq->where('username', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
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
     * Ids of users matching a free-text search, for the queries that cannot
     * reach the users table through a relation.
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function userIdsMatching(string $search): \Illuminate\Support\Collection
    {
        return User::where('username', 'like', "%{$search}%")
            ->orWhere('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->pluck('id');
    }

    /**
     * The failed-verification charge (or the recorded decision not to charge)
     * for every row on the page, in one query. Keyed by the record it belongs to.
     */
    private function chargesFor($records): \Illuminate\Support\Collection
    {
        return FailedVerificationCharge::whereIn(
            'record_id',
            collect($records->items())->pluck('id')->map(fn ($id) => (string) $id),
        )->get()->keyBy('record_id');
    }

    /**
     * Who a row belongs to. Admin history is only useful if every row says so.
     *
     * @return array<string, mixed>|null
     */
    private function userPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name ?: $user->username ?: $user->email,
            'username' => $user->username,
            'email' => $user->email,
        ];
    }

    private function walletRow(WalletHistory $w, string $sourceLabel): array
    {
        return [
            'id' => $w->id,
            'reference' => $w->id,
            'user' => $this->userPayload($w->user),
            'source' => $sourceLabel,
            'status' => $w->status,
            'amount' => (float) $w->amount,
            'old_balance' => (float) $w->oldbal,
            'new_balance' => (float) $w->newbal,
            'date' => $w->createdAt?->format('M d, Y H:i'),
        ];
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
            'user' => $this->userPayload($record->user),
            'identity' => $identity,
            'identity_label' => $identityLabel,
            'status' => $record->status,
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
     * @param  Builder  $base  an unfiltered query
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

    private function debitLabel(?string $fundingType): string
    {
        return match ($fundingType) {
            'service-charge' => 'Service charge',
            'manual-funding' => 'Manual debit',
            'admin-debit' => 'Admin debit',
            'admin-reset' => 'Admin reset',
            default => ucfirst(str_replace(['-', '_'], ' ', (string) $fundingType)) ?: 'Debit',
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
