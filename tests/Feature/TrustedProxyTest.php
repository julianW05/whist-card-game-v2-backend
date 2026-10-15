<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/ip', fn (Request $request): string => (string) $request->ip());
    }

    public function test_it_uses_the_forwarded_ip_from_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1,159.195.156.162']);

        $this->withServerVariables(['REMOTE_ADDR' => '159.195.156.162'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->get('/_test/ip')
            ->assertSeeText('203.0.113.7');
    }

    public function test_it_ignores_the_forwarded_ip_from_an_untrusted_client(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1,159.195.156.162']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->get('/_test/ip')
            ->assertSeeText('198.51.100.9');
    }

    public function test_it_trusts_no_proxy_when_none_are_configured(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->withServerVariables(['REMOTE_ADDR' => '159.195.156.162'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->get('/_test/ip')
            ->assertSeeText('159.195.156.162');
    }
}
