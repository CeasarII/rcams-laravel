<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('ADMIN');
    }

    public function rules(): array
    {
        return [
            'employee_no' => ['required', 'string', 'max:30', 'unique:users,employee_no'],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'department_id' => ['required', 'exists:departments,id'],
            'company_id' => ['required', 'exists:companies,id'],
            'is_active' => ['sometimes', 'boolean'],

            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],

            // Only meaningful when the OE role is among the ones selected —
            // the controller ignores these otherwise.
            'vessel_ids' => ['nullable', 'array'],
            'vessel_ids.*' => ['exists:vessels,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
        ];
    }
}
