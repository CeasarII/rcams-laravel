<?php

namespace App\Http\Controllers\Admin;

use App\Models\NatureOfCost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NatureOfCostController extends MasterDataController
{
    protected function modelClass(): string
    {
        return NatureOfCost::class;
    }

    protected function settings(): array
    {
        return [
            'singular' => 'Nature of Cost',
            'plural' => 'Natures of Cost',
            'subtitle' => 'Expense classification used on cash advance liquidation items.',
            'route' => 'admin.nature-of-costs',
            'display' => 'label',
            'search' => ['label', 'code'],
            'orderBy' => ['label'],
            'withCount' => ['items'],
            'columns' => [
                ['label' => 'Code', 'key' => 'code'],
                ['label' => 'Label', 'key' => 'label'],
                ['label' => 'Items', 'key' => 'items_count'],
                ['label' => 'Status', 'type' => 'status'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Code', 'maxlength' => 30, 'required' => true, 'col' => 4,
                    'help' => 'Short unique code, stored upper-case.'],
                ['name' => 'label', 'label' => 'Label', 'maxlength' => 100, 'required' => true, 'col' => 8],
            ],
        ];
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('nature_of_costs', 'code')->ignore($model?->id)],
            'label' => ['required', 'string', 'max:100'],
        ];
    }
}
