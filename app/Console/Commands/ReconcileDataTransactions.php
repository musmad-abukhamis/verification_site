<?php

namespace App\Console\Commands;

use App\Jobs\ReconcilePendingTransactions;
use App\Services\Vendors\VendorDispatcher;
use App\Services\WalletLedger;
use Illuminate\Console\Command;

class ReconcileDataTransactions extends Command
{
    protected $signature = 'data:reconcile';

    protected $description = 'Requery and settle any data purchases stuck in the processing state';

    public function handle(VendorDispatcher $dispatcher, WalletLedger $ledger): int
    {
        (new ReconcilePendingTransactions)->handle($dispatcher, $ledger);

        $this->info('Data transaction reconciliation complete.');

        return self::SUCCESS;
    }
}
