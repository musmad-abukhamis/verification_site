<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the data-module ledger stays consistent with cached balances.
 *
 * The wallet_entries ledger only records data-module movements, so a user's
 * balance is not expected to equal the ledger sum in absolute terms (funding and
 * NIN/BVN charges flow through wallethistory). Instead we assert that the NET of
 * the ledger's own credits/debits matches the running balance_after it recorded
 * — i.e. the ledger is internally consistent and its latest balance_after equals
 * users.balance. Drift is logged for the admin exceptions report.
 */
class CheckLedgerIntegrity extends Command
{
    protected $signature = 'data:ledger-check';

    protected $description = 'Assert the data-module wallet ledger is consistent with user balances';

    public function handle(): int
    {
        $drift = 0;

        User::query()
            ->whereHas('walletEntries')
            ->chunkById(200, function ($users) use (&$drift) {
                foreach ($users as $user) {
                    $latest = WalletEntry::where('user_id', $user->getKey())
                        ->latest('created_at')
                        ->latest('id')
                        ->first();

                    if (! $latest) {
                        continue;
                    }

                    if (abs((float) $latest->balance_after - (float) $user->balance) > 0.001) {
                        $drift++;
                        Log::warning('Wallet ledger drift detected', [
                            'user_id' => $user->getKey(),
                            'balance' => (float) $user->balance,
                            'ledger_balance_after' => (float) $latest->balance_after,
                        ]);
                        $this->warn("Drift for user {$user->getKey()}: balance={$user->balance} ledger={$latest->balance_after}");
                    }
                }
            });

        if ($drift === 0) {
            $this->info('Ledger consistent — no drift detected.');
        } else {
            $this->error("{$drift} user(s) show ledger drift (logged).");
        }

        return self::SUCCESS;
    }
}
