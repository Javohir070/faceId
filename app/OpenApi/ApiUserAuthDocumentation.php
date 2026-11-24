<?php

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * /docs/api: panel foydalanuvchisi bilan kiriladigan API lar (`api.user:*` — parking, integratsiya) uchun token yoki
 * email + parol kiritish maydoni (docs/api-auth.md). Scramble bu middleware’ni o'zi tanimaydi.
 *
 * Faqat shu route’larga qo'yiladi: legacy (IP), ma'lumotnoma (o'z Basic’i) va webhook’lar o'zgarmaydi.
 */
final class ApiUserAuthDocumentation implements DocumentTransformer, OperationTransformer
{
    public const BEARER = 'bearerAuth';

    public const BASIC = 'basicAuth';

    private const TOKEN_BEKOR_QILISH = 'api.auth.token.destroy';

    public function handle(OpenApi|Operation $target, OpenApiContext|RouteInfo $context): void
    {
        if ($target instanceof OpenApi) {
            $this->sxemalar($target);

            return;
        }

        if ($context instanceof RouteInfo) {
            $this->operatsiya($target, $context);
        }
    }

    private function sxemalar(OpenApi $document): void
    {
        $document->components->addSecurityScheme(
            self::BEARER,
            SecurityScheme::http('bearer')->setDescription(
                'POST /api/auth/token dan olingan token, masalan `15|Xy8Lk...`. Faqat tokenning o‘zini kiriting — '
                .'`Bearer` so‘zi o‘zi qo‘shiladi. Token 24 soat amal qiladi.',
            ),
        );

        $document->components->addSecurityScheme(
            self::BASIC,
            SecurityScheme::http('basic')->setDescription(
                'Panel foydalanuvchisining email va paroli. Rolida shu API ruxsati (Parking API yoki Integratsiya API) bo‘lishi kerak.',
            ),
        );
    }

    private function operatsiya(Operation $operation, RouteInfo $routeInfo): void
    {
        if ($routeInfo->route->getName() === self::TOKEN_BEKOR_QILISH) {
            $operation->addSecurity(new SecurityRequirement([self::BEARER => []]));

            return;
        }

        $apiUser = collect($routeInfo->route->gatherMiddleware())
            ->contains(fn ($middleware): bool => is_string($middleware) && Str::startsWith($middleware, 'api.user:'));

        if (! $apiUser) {
            return;
        }

        // Ikki talab — "token YOKI email + parol": sahifada ikkalasidan birini tanlash mumkin.
        $operation->addSecurity(new SecurityRequirement([self::BEARER => []]));
        $operation->addSecurity(new SecurityRequirement([self::BASIC => []]));
    }
}
