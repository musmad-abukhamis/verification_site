<?php

use App\Http\Controllers\WalletController;
use App\Models\User;
use App\Services\Wallet\BillstackService;
use Illuminate\Http\Request;

$u = User::first();
auth()->login($u);

$controller = new WalletController;

try {
    $r = $controller->index();
    echo 'index       -> '.class_basename($r).' ok'.PHP_EOL;
} catch (\Throwable $e) {
    echo 'index ERROR: '.$e->getMessage().PHP_EOL;
}

try {
    $r = $controller->fund();
    echo 'fund        -> ok'.PHP_EOL;
} catch (\Throwable $e) {
    echo 'fund ERROR: '.$e->getMessage().PHP_EOL;
}

try {
    $req = Request::create('/wallet/transactions', 'GET', ['search' => '', 'status' => 'all', 'type' => 'credit']);
    $r = $controller->transactions($req);
    echo 'transactions-> ok'.PHP_EOL;
} catch (\Throwable $e) {
    echo 'transactions ERROR: '.$e->getMessage().PHP_EOL;
}
