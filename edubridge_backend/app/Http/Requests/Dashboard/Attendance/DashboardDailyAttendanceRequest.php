<?php
namespace App\Http\Requests\Dashboard\Attendance;
use Illuminate\Foundation\Http\FormRequest;
class DashboardDailyAttendanceRequest extends FormRequest {
 public function authorize(): bool { return true; }
 public function rules(): array { return [
  'date'=>['nullable','date_format:Y-m-d'], 'section_id'=>['nullable','integer','min:1'],
  'status'=>['nullable','string','in:has_absence,excused,late,complete,incomplete,full_day_absence'],
  'q'=>['nullable','string','max:120'],
 ]; }
}
