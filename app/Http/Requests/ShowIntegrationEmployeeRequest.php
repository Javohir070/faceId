<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FindsIntegrationEmployee;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Integratsiya API: `{jshshir}` bo'yicha bitta xodim (yo'q yoki boshqa bo'limniki — 404). Tanasi yo'q.
 */
class ShowIntegrationEmployeeRequest extends FormRequest
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
