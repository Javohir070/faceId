<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FindsIntegrationEmployee;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Integratsiya API orqali xodimni o'chirish: tanasi yo'q, faqat `{jshshir}` bo'yicha xodim (topilmasa 404).
 */
class DestroyIntegrationEmployeeRequest extends FormRequest
{
    use FindsIntegrationEmployee;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
