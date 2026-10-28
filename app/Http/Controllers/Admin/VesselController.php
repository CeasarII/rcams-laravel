<?php

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use App\Models\Vessel;
use App\Models\VesselType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VesselController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Vessel::class;
    }

    protected function settings(): array
    {
        return [
            'singular' => 'Vessel',
            'plural' => 'Vessels',
            'subtitle' => 'Vessels per company. Sea Base requests and OE scoping reference these.',
            'route' => 'admin.vessels',
            'display' => 'name',
            'search' => ['name', 'imo_no'],
            'orderBy' => ['name'],
            'with' => ['vesselType', 'company'],
            'columns' => [
                ['label' => 'Name', 'key' => 'name'],
                ['label' => 'IMO No.', 'key' => 'imo_no'],
                ['label' => 'Type', 'key' => 'vesselType.label'],
                ['label' => 'Company', 'key' => 'company.name'],
                ['label' => 'Status', 'type' => 'status'],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Vessel Name', 'maxlength' => 150, 'required' => true, 'col' => 6,
                    'help' => 'Unique within the company.'],
                ['name' => 'imo_no', 'label' => 'IMO No.', 'maxlength' => 30, 'required' => false, 'col' => 6],
                ['name' => 'vessel_type_id', 'label' => 'Vessel Type', 'type' => 'select', 'required' => true, 'col' => 6,
                    'options' => fn (?Model $m) => VesselType::query()
                        ->where('is_active', true)
                        ->when($m, fn ($q) => $q->orWhere('id', $m->vessel_type_id))
                        ->orderBy('label')->pluck('label', 'id')],
                ['name' => 'company_id', 'label' => 'Company', 'type' => 'select', 'required' => true, 'col' => 6,
                    'options' => fn (?Model $m) => Company::query()
                        ->where('is_active', true)
                        ->when($m, fn ($q) => $q->orWhere('id', $m->company_id))
                        ->orderBy('name')->pluck('name', 'id')],
            ],
        ];
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'vessel_type_id' => ['required', 'exists:vessel_types,id'],
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:150',
                Rule::unique('vessels', 'name')
                    ->where('company_id', $request->input('company_id'))
                    ->ignore($model?->id)],
            'imo_no' => ['nullable', 'string', 'max:30'],
        ];
    }
}
