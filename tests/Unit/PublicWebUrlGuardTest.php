<?php

namespace Tests\Unit;

use App\Services\LeadDiscovery\PublicWebUrlGuard;
use Tests\TestCase;

class PublicWebUrlGuardTest extends TestCase
{
    public function test_it_rejects_private_and_local_targets(): void
    {
        $guard = app(PublicWebUrlGuard::class);

        $this->assertFalse($guard->allows('http://127.0.0.1/admin'));
        $this->assertFalse($guard->allows('http://10.0.0.5/'));
        $this->assertFalse($guard->allows('http://localhost/'));
        $this->assertFalse($guard->allows('file:///etc/passwd'));
        $this->assertFalse($guard->allows('https://example.com:8443/'));
    }

    public function test_it_allows_reserved_test_domains_during_isolated_tests(): void
    {
        $this->assertTrue(app(PublicWebUrlGuard::class)->allows('https://business.example/contact'));
    }
}
