<?php

namespace App\Http\Requests\Dashboard\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class DashboardEarlyWarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['section_id' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:120'], 'min_score' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
