<?php

namespace App\Exceptions;

use App\Services\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (ApiException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->status(),
                $exception->details(),
            );
        });

        $this->renderable(function (ValidationException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'VALIDATION_ERROR',
                'The given data was invalid.',
                422,
                $exception->errors(),
            );
        });

        $this->renderable(function (AuthenticationException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'AUTHENTICATION_FAILED',
                'Authentication failed.',
                401,
            );
        });

        $this->renderable(function (ThrottleRequestsException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'RATE_LIMIT_EXCEEDED',
                'Too many requests. Please try again later.',
                429,
            )->withHeaders($exception->getHeaders());
        });

        $this->renderable(function (NotFoundHttpException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'NOT_FOUND',
                'Resource not found.',
                404,
            );
        });

        $this->renderable(function (Throwable $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'INTERNAL_ERROR',
                'An unexpected internal error occurred.',
                500,
            );
        });
    }
}
