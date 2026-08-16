<?php

namespace App\Http\Requests\Dashboard\Transport;

use App\Models\BusRouteAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardUpdateBusRouteAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'valid_from' => ['sometimes', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'status' => ['sometimes', 'string', Rule::in([BusRouteAssignment::STATUS_ACTIVE, BusRouteAssignment::STATUS_ARCHIVED])],
        ];
    }
}
