<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * /docs/api: panel foydalanuvchisi bilan kiriladigan API larda (parking, integratsiya) token yoki email + parol
 * kiritish maydoni chiqishi kerak (docs/api-auth.md). Boshqa API lar (legacy — IP, ma'lumotnoma — o'z Basic’i,
 * webhook’lar) o'zgarmaydi.
 */
class ApiAuthDocumentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // /docs/api faqat local muhitda yoki viewApiDocs ruxsati bilan ochiladi.
        Gate::define('viewApiDocs', fn ($user = null): bool => true);
    }

    public function test_bearer_va_basic_sxemalari_bor(): void
    {
        $sxemalar = $this->hujjat()['components']['securitySchemes'] ?? [];

        $this->assertSame('http', $sxemalar['bearerAuth']['type'] ?? null);
        $this->assertSame('bearer', $sxemalar['bearerAuth']['scheme'] ?? null);
        $this->assertSame('http', $sxemalar['basicAuth']['type'] ?? null);
        $this->assertSame('basic', $sxemalar['basicAuth']['scheme'] ?? null);
        $this->assertStringContainsString('/api/auth/token', $sxemalar['bearerAuth']['description'] ?? '');
    }

    public function test_parking_va_integratsiya_token_yoki_parol_soraydi(): void
    {
        $yollar = $this->hujjat()['paths'];
        $kutilgan = [['bearerAuth' => []], ['basicAuth' => []]];

        $this->assertSame($kutilgan, $yollar['/parking/employees']['get']['security'] ?? null);

        foreach (['get', 'post'] as $metod) {
            $this->assertSame($kutilgan, $yollar['/integration/employees'][$metod]['security'] ?? null, $metod);
        }

        foreach (['put', 'delete'] as $metod) {
            $this->assertSame($kutilgan, $yollar['/integration/employees/{jshshir}'][$metod]['security'] ?? null, $metod);
        }
    }

    /** Token olish ochiq; bekor qilish faqat token bilan. */
    public function test_token_endpointlari(): void
    {
        $token = $this->hujjat()['paths']['/auth/token'];

        $this->assertArrayNotHasKey('security', $token['post']);
        $this->assertSame([['bearerAuth' => []]], $token['delete']['security'] ?? null);
    }

    public function test_boshqa_api_larga_tegilmaydi(): void
    {
        $yollar = $this->hujjat()['paths'];

        $this->assertArrayNotHasKey('security', $yollar['/legacy']['post']);
        $this->assertArrayNotHasKey('security', $yollar['/v1/employees']['get']);
    }

    /**
     * @return array<string, mixed>
     */
    private function hujjat(): array
    {
        return $this->getJson('/docs/api.json')->assertOk()->json();
    }
}
