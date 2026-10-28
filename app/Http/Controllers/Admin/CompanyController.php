<?php

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Company::class;
    }

    protected function settings(): array
    {
        return [
            'singular' => 'Company',
            'plural' => 'Companies',
            'subtitle' => 'Legal entities that own departments, vessels and user accounts.',
            'route' => 'admin.companies',
            'display' => 'name',
            'search' => ['name', 'code'],
            'orderBy' => ['name'],
            'withCount' => ['departments', 'vessels'],
            'columns' => [
                ['label' => 'Code', 'key' => 'code'],
                ['label' => 'Name', 'key' => 'name'],
                ['label' => 'Departments', 'key' => 'departments_count'],
                ['label' => 'Vessels', 'key' => 'vessels_count'],
                ['label' => 'Status', 'type' => 'status'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Code', 'maxlength' => 30, 'required' => true, 'col' => 4,
                    'help' => 'Short unique code, stored upper-case.'],
                ['name' => 'name', 'label' => 'Name', 'maxlength' => 150, 'required' => true, 'col' => 8],
            ],
        ];
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('companies', 'code')->ignore($model?->id)],
            'name' => ['required', 'string', 'max:150'],
        ];
    }
}
