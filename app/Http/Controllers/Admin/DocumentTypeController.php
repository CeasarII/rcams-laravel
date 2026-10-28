<?php

namespace App\Http\Controllers\Admin;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentTypeController extends MasterDataController
{
    protected function modelClass(): string
    {
        return DocumentType::class;
    }

    protected function settings(): array
    {
        return [
            'singular' => 'Document Type',
            'plural' => 'Document Types',
            'subtitle' => 'Kinds of supporting documents attached to liquidation items (OR, invoice, etc.).',
            'route' => 'admin.document-types',
            'display' => 'label',
            'search' => ['label', 'code'],
            'orderBy' => ['label'],
            'withCount' => ['attachments'],
            'columns' => [
                ['label' => 'Code', 'key' => 'code'],
                ['label' => 'Label', 'key' => 'label'],
                ['label' => 'Attachments', 'key' => 'attachments_count'],
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
            'code' => ['required', 'string', 'max:30', Rule::unique('document_types', 'code')->ignore($model?->id)],
            'label' => ['required', 'string', 'max:100'],
        ];
    }
}
