<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    */

    'driver' => 'bcrypt',

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | "verify" is FALSE here on purpose, and must stay that way.
    |
    | The ~2250 accounts migrated from nimcweb were hashed by bcryptjs, which
    | emits $2a$ and $2b$ prefixes. PHP's password_verify() handles all three
    | bcrypt prefixes correctly, but password_get_info() only recognises $2y$
    | and reports $2a$/$2b$ as "unknown". Laravel's BcryptHasher::check()
    | consults that before verifying, so with verification on, every migrated
    | user's login attempt throws
    |
    |     RuntimeException: This password does not use the Bcrypt algorithm.
    |
    | which surfaces as a 500, not a failed login.
    |
    | This is not a downgrade: the algorithm check is a guard against a hash
    | from a *different* family (argon2) reaching the bcrypt hasher. Every hash
    | in this database is bcrypt, verified at migration time -- $2a$12$ (1546),
    | $2b$10$ (713), $2a$10$ (4), all 60 chars.
    |
    | These hashes are re-hashed to $2y$ automatically on each successful login
    | (password_needs_rehash returns true for them and rehash_on_login is on),
    | so the population converts itself over time as users sign in.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => false,
        'limit' => env('BCRYPT_LIMIT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | Left on deliberately: it is what migrates the $2a$/$2b$ hashes to $2y$.
    |
    */

    'rehash_on_login' => true,

];
