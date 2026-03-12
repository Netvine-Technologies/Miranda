<?php

namespace Tests\Feature;

use App\Models\CanonicalProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalApiSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_watch_subscription_endpoint_rejects_missing_signature_headers(): void
    {
        config()->set('internal_api.require_signature', true);
        config()->set('internal_api.secret', 'test-secret');
        config()->set('internal_api.client_id', 'node-app');
        config()->set('internal_api.allowed_ips', []);

        CanonicalProduct::create([
            'slug' => 'sample-product',
            'title' => 'Sample Product',
            'brand' => 'Brand',
            'normalized_key' => 'sample product',
            'product_type' => 'Fragrance',
        ]);

        $response = $this->postJson('/api/watch-subscriptions', [
            'email' => 'watcher@example.com',
            'canonical_slug' => 'sample-product',
        ]);

        $response->assertStatus(401);
    }

    public function test_watch_subscription_endpoint_accepts_valid_signature_and_rejects_replay(): void
    {
        config()->set('internal_api.require_signature', true);
        config()->set('internal_api.secret', 'test-secret');
        config()->set('internal_api.client_id', 'node-app');
        config()->set('internal_api.allowed_ips', []);
        config()->set('internal_api.max_skew_seconds', 300);
        config()->set('internal_api.request_id_ttl_seconds', 300);

        CanonicalProduct::create([
            'slug' => 'sample-product',
            'title' => 'Sample Product',
            'brand' => 'Brand',
            'normalized_key' => 'sample product',
            'product_type' => 'Fragrance',
        ]);

        $payload = [
            'email' => 'watcher@example.com',
            'canonical_slug' => 'sample-product',
        ];

        $timestamp = (string) now()->timestamp;
        $requestId = 'req-123';
        $headers = $this->signedHeaders('POST', '/api/watch-subscriptions', $payload, $timestamp, $requestId, 'test-secret');

        $first = $this->callJsonWithHeaders('POST', '/api/watch-subscriptions', $payload, $headers);
        $first->assertStatus(201);

        $second = $this->callJsonWithHeaders('POST', '/api/watch-subscriptions', $payload, $headers);
        $second->assertStatus(401)->assertJson(['message' => 'Replay detected.']);
    }

    private function signedHeaders(
        string $method,
        string $pathWithQuery,
        array $payload,
        string $timestamp,
        string $requestId,
        string $secret
    ): array {
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $bodyHash = hash('sha256', $rawBody ?: '');
        $canonical = implode("\n", [
            strtoupper($method),
            $pathWithQuery,
            $bodyHash,
            $timestamp,
            $requestId,
        ]);

        $signature = hash_hmac('sha256', $canonical, $secret);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CLIENT_ID' => 'node-app',
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_REQUEST_ID' => $requestId,
            'HTTP_X_SIGNATURE' => $signature,
        ];
    }

    private function callJsonWithHeaders(string $method, string $uri, array $payload, array $server): \Illuminate\Testing\TestResponse
    {
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return $this->call($method, $uri, [], [], [], $server, $rawBody ?: '');
    }
}

