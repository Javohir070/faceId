<?php

namespace Tests\Unit\Http;

use App\Http\ClientIp;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ClientIpTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);

        parent::tearDown();
    }

    public function test_remote_client_cannot_spoof_forwarded_for(): void
    {
        $request = $this->request('203.0.113.50', '192.168.100.115');

        $this->assertSame('192.168.100.115', $request->ip());
        $this->assertSame('203.0.113.50', ClientIp::from($request));
    }

    public function test_ipv4_loopback_proxy_forwards_client_ip(): void
    {
        $this->assertSame('192.168.100.115', ClientIp::from($this->request('127.0.0.1', '192.168.100.115')));
    }

    public function test_ipv6_loopback_proxy_forwards_client_ip(): void
    {
        $this->assertSame('192.168.100.115', ClientIp::from($this->request('::1', '192.168.100.115')));
    }

    public function test_missing_remote_address_is_null(): void
    {
        $this->assertNull(ClientIp::from($this->request('', '192.168.100.115')));
    }

    private function request(string $remoteAddr, string $forwardedFor): Request
    {
        // bootstrap/app.php dagi trustProxies(at: '*') kabi: bevosita ulangan manzil proksi deb hisoblanadi.
        Request::setTrustedProxies(array_filter([$remoteAddr]), Request::HEADER_X_FORWARDED_FOR);

        return Request::create('/api/webhooks/hikvision/events', 'POST', server: [
            'REMOTE_ADDR' => $remoteAddr,
            'HTTP_X_FORWARDED_FOR' => $forwardedFor,
        ]);
    }
}
