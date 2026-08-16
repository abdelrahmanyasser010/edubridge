<?php

namespace App\Http\Requests\Dashboard\Calendar;

class UpdateCalendarEventRequest extends StoreCalendarEventRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $field => $fieldRules) {
            if (! is_array($fieldRules)) {
                continue;
            }

            $optionalRules = [];
            foreach ($fieldRules as $rule) {
                if ($rule !== 'required') {
                    $optionalRules[] = $rule;
                }
            }

            array_unshift($optionalRules, 'sometimes');
            $rules[$field] = $optionalRules;
        }

        return $rules;
    }
}
