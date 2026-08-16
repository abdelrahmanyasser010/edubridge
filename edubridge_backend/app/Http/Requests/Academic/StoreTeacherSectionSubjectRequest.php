<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherSectionSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'academic_term_id' => ['required', 'integer', 'exists:tenant.academic_terms,id'],
            'teacher_id' => ['required', 'integer', 'exists:tenant.teachers,id'],
            'section_id' => ['required', 'integer', 'exists:tenant.sections,id'],
            'subject_id' => [
                'required',
                'integer',
                'exists:tenant.subjects,id',
                Rule::unique('tenant.teacher_section_subject', 'subject_id')
                    ->where('academic_term_id', $this->integer('academic_term_id'))
                    ->where('teacher_id', $this->integer('teacher_id'))
                    ->where('section_id', $this->integer('section_id')),
            ],
            'weekly_quota' => ['required', 'integer', 'min:1', 'max:40'],
            'is_homeroom' => ['required', 'boolean'],
        ];
    }
}
