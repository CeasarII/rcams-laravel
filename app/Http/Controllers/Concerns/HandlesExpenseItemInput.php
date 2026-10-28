<?php

namespace App\Http\Controllers\Concerns;

use App\Support\PhVat;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validation and VAT computation for expense lines. The client sends the
 * VAT-inclusive receipt amount and the VAT nature; net and input tax are
 * always computed here with PhVat (never trusted from the form).
 */
trait HandlesExpenseItemInput
{
    private function itemRules(Request $request): array
    {
        return [
            'item_date' => ['required', 'date', 'before_or_equal:today'],
            'purpose' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:100'],
            'supplier_name' => ['required', 'string', 'max:150'],
            // A VAT invoice must carry the supplier's TIN; Non-VAT receipts may omit it.
            'tin' => [
                Rule::requiredIf($request->input('vat_nature') === PhVat::VAT),
                'nullable', 'string', 'max:20', 'regex:'.PhVat::TIN_PATTERN,
            ],
            'nature_of_cost_id' => ['required', 'exists:nature_of_costs,id'],
            'vat_nature' => ['required', Rule::in([PhVat::VAT, PhVat::NON_VAT])],
            'gross_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    private function fileMessages(): array
    {
        return [
            'files.*.max' => 'Each file must be 5 MB or smaller.',
            'files.*.mimes' => 'Allowed types: PDF, JPG, PNG.',
            'files.*.uploaded' => 'Upload failed — the file may exceed the server upload limit (php.ini upload_max_filesize).',
        ];
    }

    private function itemData(array $data): array
    {
        $split = PhVat::split($data['gross_amount'], $data['vat_nature']);

        return [
            'item_date' => $data['item_date'],
            'purpose' => $data['purpose'],
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
            'supplier_name' => $data['supplier_name'],
            'tin' => $data['tin'] ?? null,
            'nature_of_cost_id' => $data['nature_of_cost_id'],
            'vat_nature' => $data['vat_nature'],
            'vat_rate' => $split['rate'],
            'gross_amount' => number_format((float) $data['gross_amount'], 2, '.', ''),
            'net_amount' => $split['net'],
            'input_tax' => $split['input_tax'],
        ];
    }
}
