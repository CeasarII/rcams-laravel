<?php

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function settings(): array
    {
        return [
            'singular' => 'Department',
            'plural' => 'Departments',
            'subtitle' => 'Departments per company. Used for Shore Base routing and OE scoping.',
            'route' => 'admin.departments',
            'display' => 'name',
            'search' => ['name', 'code'],
            'orderBy' => ['name'],
            'with' => ['company'],
            'withCount' => ['users'],
            'columns' => [
                ['label' => 'Code', 'key' => 'code'],
                ['label' => 'Name', 'key' => 'name'],
                ['label' => 'Company', 'key' => 'company.name'],
                ['label' => 'Users', 'key' => 'users_count'],
                ['label' => 'Status', 'type' => 'status'],
            ],
            'fields' => [
                ['name' => 'company_id', 'label' => 'Company', 'type' => 'select', 'required' => true, 'col' => 6,
                    // Active companies, plus the currently assigned one even if it was deactivated.
                    'options' => fn (?Model $m) => Company::query()
                        ->where('is_active', true)
                        ->when($m, fn ($q) => $q->orWhere('id', $m->company_id))
                        ->orderBy('name')->pluck('name', 'id')],
                ['name' => 'code', 'label' => 'Code', 'maxlength' => 30, 'required' => true, 'col' => 6,
                    'help' => 'Unique within the company, stored upper-case.'],
                ['name' => 'name', 'label' => 'Name', 'maxlength' => 150, 'required' => true, 'col' => 12],
            ],
        ];
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:30',
                Rule::unique('departments', 'code')
                    ->where('company_id', $request->input('company_id'))
                    ->ignore($model?->id)],
            'name' => ['required', 'string', 'max:150'],
        ];
    }
}
