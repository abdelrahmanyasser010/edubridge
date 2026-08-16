<?php

namespace App\Http\Requests\Dashboard\Broadcasts;

use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'string', 'in:announcement,alert,reminder'],
            'target' => ['required', 'array'],
            'target.type' => ['required', 'string', 'in:all,grade_level,section,students,parents,teachers,roles,custom_users'],
            'target.ids' => ['nullable', 'array', 'max:500'],
            'target.ids.*' => ['required', 'string', 'max:128'],
            'channels' => ['required', 'array', 'min:1', 'max:3'],
            'channels.*' => ['required', 'string', 'in:database,push,sms'],
            'scheduled_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ];
    }
}
