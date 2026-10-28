<?php

namespace App\Http\Controllers\Admin;

use App\Models\VesselType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VesselTypeController extends MasterDataController
{
    protected function modelClass(): string
    {
        return VesselType::class;
    }

    protected function settings(): array
    {
        return [
            'singular' => 'Vessel Type',
            'plural' => 'Vessel Types',
            'subtitle' => 'Classification of vessels (e.g. Tanker, Tug, Barge).',
            'route' => 'admin.vessel-types',
            'display' => 'label',
            'search' => ['label', 'code'],
            'orderBy' => ['label'],
            'withCount' => ['vessels'],
            'columns' => [
                ['label' => 'Code', 'key' => 'code'],
                ['label' => 'Label', 'key' => 'label'],
                ['label' => 'Vessels', 'key' => 'vessels_count'],
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
            'code' => ['required', 'string', 'max:30', Rule::unique('vessel_types', 'code')->ignore($model?->id)],
            'label' => ['required', 'string', 'max:100'],
        ];
    }
}
