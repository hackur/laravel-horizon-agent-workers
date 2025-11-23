<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add security headers middleware to web routes
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // API-specific middleware
        $middleware->api(prepend: [
            \App\Http\Middleware\ApiResponseMiddleware::class,
        ]);

        // Configure rate limiting
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API exceptions with consistent JSON responses
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated. Please provide a valid API token.',
                    'errors' => [
                        'authentication' => ['Authentication required. Include a valid Bearer token in the Authorization header.'],
                    ],
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthorized action.',
                    'errors' => [
                        'authorization' => ['You do not have permission to perform this action.'],
                    ],
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Resource not found.',
                    'errors' => [
                        'resource' => ['The requested resource does not exist.'],
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Endpoint not found.',
                    'errors' => [
                        'endpoint' => ['The requested API endpoint does not exist.'],
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Too many requests.',
                    'errors' => [
                        'rate_limit' => ['You have exceeded the rate limit. Please try again later.'],
                    ],
                ], 429);
            }
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') && !config('app.debug')) {
                return response()->json([
                    'message' => 'Server error.',
                    'errors' => [
                        'server' => ['An unexpected error occurred. Please try again later.'],
                    ],
                ], 500);
            }
        });
    })->create();
