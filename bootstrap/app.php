<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(function () {
                require __DIR__.'/../routes/admin.php';
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Allow the first-party Inertia SPA to authenticate to /api routes
        // using the session cookie (Sanctum stateful) + axios XSRF token.
        $middleware->statefulApi();

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'api.token' => \App\Http\Middleware\ApiTokenMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Illuminate\Http\Middleware\ValidatePostSize throws this from the
        // global stack, before any controller runs, whenever CONTENT_LENGTH
        // exceeds post_max_size. Left unhandled it renders Symfony's bare
        // "413 Content Too Large" page, which tells an admin uploading a large
        // enrolment spreadsheet nothing about what to do next.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $limit = ini_get('post_max_size') ?: 'the server limit';
            $sent = (int) $request->server('CONTENT_LENGTH', 0);
            $sentLabel = $sent > 0
                ? round($sent / 1024 / 1024, 1).'MB'
                : 'The upload';

            $message = "{$sentLabel} exceeds the server's maximum request size (post_max_size = {$limit}). "
                .'Raise post_max_size and upload_max_filesize in php.ini — and client_max_body_size in nginx — then restart PHP-FPM.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->withErrors(['file' => $message]);
        });
    })->create();
