<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Application;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApplicationApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = trim((string) $request->headers->get('X-API-Key', ''));

        if ($providedKey === '') {
            throw new ApiException(
                'AUTHENTICATION_FAILED',
                'API key is required.',
                401,
            );
        }

        $hashedKey = hash('sha256', $providedKey);

        $application = Application::query()
            ->where(function ($query) use ($providedKey, $hashedKey) {
                $query
                    ->where('api_key', $hashedKey)
                    ->orWhere('api_key', $providedKey);
            })
            ->first();

        if (! $application) {
            throw new ApiException(
                'AUTHENTICATION_FAILED',
                'API key is invalid.',
                401,
            );
        }

        if (! $application->status) {
            throw new ApiException(
                'APPLICATION_INACTIVE',
                'Application is inactive.',
                403,
            );
        }

        $request->attributes->set('client_application', $application);

        return $next($request);
    }
}
