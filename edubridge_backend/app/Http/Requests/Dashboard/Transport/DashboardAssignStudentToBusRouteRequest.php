<?php

namespace App\Http\Requests\Dashboard\Transport;

use Illuminate\Foundation\Http\FormRequest;

class DashboardAssignStudentToBusRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}
