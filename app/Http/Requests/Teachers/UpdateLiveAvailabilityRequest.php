<?php

namespace App\Http\Requests\Teachers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLiveAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'status' => ['required', 'string', Rule::in(['online', 'offline', 'in-call'])],
            'start_time' => ['required_if:status,online', 'date', 'before:end_time'],
            'end_time' => ['required_if:status,online', 'date', 'after:start_time'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'start_time.required_if' => 'Start time is required when status is online.',
            'end_time.required_if' => 'End time is required when status is online.',
            'start_time.before' => 'Start time must be before end time.',
            'end_time.after' => 'End time must be after start time.',
        ];
    }
}
