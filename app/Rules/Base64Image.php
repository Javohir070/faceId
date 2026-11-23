<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * JSON da base64 bo'lib kelgan rasm: JPEG yoki PNG, berilgan hajmdan oshmaydi.
 * `data:image/...;base64,` prefiksi bo'lsa olib tashlanadi (App\Rules\Base64Image::decode).
 */
class Base64Image implements ValidationRule
{
    private const MIME_TURLARI = ['image/jpeg', 'image/png'];

    public function __construct(private readonly int $maxKilobytes = 1024) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $baytlar = is_string($value) ? self::decode($value) : null;

        if ($baytlar === null || $baytlar === '') {
            $fail('Yuz rasmi base64 ko‘rinishida bo‘lishi kerak.');

            return;
        }

        if (strlen($baytlar) > $this->maxKilobytes * 1024) {
            $fail('Yuz rasmi hajmi '.intdiv($this->maxKilobytes, 1024).' MB dan oshmasligi kerak.');

            return;
        }

        if (! in_array(self::mime($baytlar), self::MIME_TURLARI, true)) {
            $fail('Yuz rasmi JPEG yoki PNG bo‘lishi kerak.');
        }
    }

    /** Base64 matnni baytlarga aylantiradi; buzuq bo'lsa `null`. */
    public static function decode(string $value): ?string
    {
        $toza = preg_replace('/^data:image\/[a-z0-9.+-]+;base64,/i', '', trim($value));
        $baytlar = base64_decode((string) $toza, true);

        return $baytlar === false ? null : $baytlar;
    }

    public static function mime(string $baytlar): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($baytlar);
    }
}
