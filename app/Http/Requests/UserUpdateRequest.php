<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('ADMIN');
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'employee_no' => ['required', 'string', 'max:30', Rule::unique('users', 'employee_no')->ignore($userId)],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            // Blank = keep current password.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'department_id' => ['required', 'exists:departments,id'],
            'company_id' => ['required', 'exists:companies,id'],

            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],

            'vessel_ids' => ['nullable', 'array'],
            'vessel_ids.*' => ['exists:vessels,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
        ];
    }
}
