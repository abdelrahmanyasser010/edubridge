<?php
namespace App\Http\Requests\Dashboard\Broadcasts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class UpdateMessageTemplateRequest extends FormRequest {
 public function authorize(): bool { return true; }
 public function rules(): array { return [
  'name'=>['sometimes','required','string','max:160', Rule::unique('tenant.message_templates','name')->ignore($this->route('template'))],
  'title'=>['sometimes','required','string','max:200'], 'body'=>['sometimes','required','string','max:8000'],
  'type'=>['sometimes','required','string',Rule::in(['announcement','alert','reminder'])],
  'default_target_type'=>['nullable','string',Rule::in(['all','grade_level','section','roles','custom_users'])],
  'is_active'=>['sometimes','boolean'],
 ]; }
}
