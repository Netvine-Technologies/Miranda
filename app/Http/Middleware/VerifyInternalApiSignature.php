<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allows local/dev bypass when explicitly disabled.
        if (! config('internal_api.require_signature', true)) {
            return $next($request);
        }

        $secret = (string) config('internal_api.secret', '');
        if ($secret === '') {
            return response()->json(['message' => 'Internal API secret not configured.'], 500);
        }

        $clientId = $request->header('X-Client-Id', '');
        $timestamp = $request->header('X-Timestamp', '');
        $requestId = $request->header('X-Request-Id', '');
        $signature = $request->header('X-Signature', '');

        if ($clientId === '' || $timestamp === '' || $requestId === '' || $signature === '') {
            return response()->json(['message' => 'Missing signature headers.'], 401);
        }

        $allowedClientId = (string) config('internal_api.client_id', '');
        if ($allowedClientId !== '' && ! hash_equals($allowedClientId, $clientId)) {
            return response()->json(['message' => 'Invalid client id.'], 401);
        }

        $allowedIps = config('internal_api.allowed_ips', []);
        if (is_array($allowedIps) && $allowedIps !== [] && ! in_array((string) $request->ip(), $allowedIps, true)) {
            return response()->json(['message' => 'IP not allowed.'], 403);
        }

        if (! ctype_digit((string) $timestamp)) {
            return response()->json(['message' => 'Invalid timestamp.'], 401);
        }

        $now = now()->timestamp;
        $skew = (int) config('internal_api.max_skew_seconds', 300);
        // Reject stale or far-future requests to reduce replay window.
        if (abs($now - (int) $timestamp) > $skew) {
            return response()->json(['message' => 'Signature expired.'], 401);
        }

        $requestIdTtl = (int) config('internal_api.request_id_ttl_seconds', 300);
        $requestIdKey = "internal-api:request-id:{$clientId}:{$requestId}";
        // Cache::add is atomic; if key already exists this is a replay.
        if (! Cache::add($requestIdKey, 1, $requestIdTtl)) {
            return response()->json(['message' => 'Replay detected.'], 401);
        }

        $bodyHash = hash('sha256', $request->getContent());
        $method = strtoupper($request->getMethod());
        $pathWithQuery = '/'.ltrim($request->path(), '/');
        $query = $request->server->get('QUERY_STRING');
        if (is_string($query) && $query !== '') {
            $pathWithQuery .= '?'.$query;
        }

        $canonical = implode("\n", [
            $method,
            $pathWithQuery,
            $bodyHash,
            (string) $timestamp,
            (string) $requestId,
        ]);

        // Signature must match method + route + body + freshness headers.
        $expected = hash_hmac('sha256', $canonical, $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
