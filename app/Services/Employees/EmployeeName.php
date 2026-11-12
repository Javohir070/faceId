<?php

namespace App\Services\Employees;

/**
 * Xodimning to‘liq ismi (`employees.name`) va uning qismlari: Familiya, Ism, Otasining ismi.
 *
 * To‘liq ism doim "Familiya Ism Otasining ismi" tartibida. Faqat bitta ism keladigan yo‘llar (legacy API,
 * qurilmadan import, eski qatorlarni ko‘chirish) uni shu qoida bilan bo‘ladi: 1-so‘z familiya, 2-so‘z ism,
 * qolgani otasining ismi ("Akmal o‘g‘li" butunligicha).
 */
final class EmployeeName
{
    /**
     * @return array{last_name: ?string, first_name: ?string, middle_name: ?string}
     */
    public static function split(?string $name): array
    {
        $cleaned = self::clean($name);
        $words = $cleaned === null ? [] : explode(' ', $cleaned);

        return [
            'last_name' => $words[0] ?? null,
            'first_name' => $words[1] ?? null,
            'middle_name' => count($words) > 2 ? implode(' ', array_slice($words, 2)) : null,
        ];
    }

    public static function join(?string $lastName, ?string $firstName, ?string $middleName): string
    {
        return implode(' ', array_filter(
            [self::clean($lastName), self::clean($firstName), self::clean($middleName)],
            fn (?string $part): bool => $part !== null,
        ));
    }

    /**
     * Bo‘shliqlarni bitta qiladi; bo‘sh qism null bo‘ladi.
     */
    public static function clean(?string $part): ?string
    {
        $cleaned = trim((string) preg_replace('/\s+/u', ' ', (string) $part));

        return $cleaned === '' ? null : $cleaned;
    }
}
