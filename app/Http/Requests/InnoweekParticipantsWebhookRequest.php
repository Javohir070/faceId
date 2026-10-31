<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Innoweek webhook: karta berilganda/profil o‘zgarganda ishtirokchilar (API dagi bilan bir xil shakl).
 * Token — INNOWEEK_API_TOKEN (ikkala yo‘nalish uchun bitta umumiy maxfiy qator).
 */
class InnoweekParticipantsWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = (string) config('innoweek.api_token');

        return $token !== '' && hash_equals($token, (string) $this->bearerToken());
    }

    protected function failedAuthorization(): void
    {
        abort(401, 'Unauthorized.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // validated() faqat qoidasi bor maydonlarni qaytaradi — API dagi hamma maydon shu yerda sanalgan.
        return [
            'data' => ['required', 'array', 'min:1', 'max:500'],
            'data.*' => ['array'],
            'data.*.id' => ['nullable', 'integer'],
            'data.*.card' => ['required', 'string', 'max:20'],
            'data.*.first_name' => ['nullable', 'string', 'max:255'],
            'data.*.last_name' => ['nullable', 'string', 'max:255'],
            'data.*.middle_name' => ['nullable', 'string', 'max:255'],
            'data.*.position' => ['nullable', 'string', 'max:1000'],
            'data.*.gender' => ['nullable', 'integer'],
            'data.*.country' => ['nullable', 'array'],
            'data.*.country.name_uz' => ['nullable', 'string', 'max:255'],
            'data.*.country.name_en' => ['nullable', 'string', 'max:255'],
            'data.*.profession' => ['nullable', 'string', 'max:255'],
            'data.*.year' => ['nullable', 'integer'],
            'data.*.status' => ['nullable', 'string', 'max:20'],
            'data.*.registered_at' => ['nullable', 'date'],
        ];
    }
}
