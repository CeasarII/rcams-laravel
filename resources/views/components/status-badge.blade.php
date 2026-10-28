@props(['status'])

@php
    $variant = match ($status->code) {
        'DRAFT' => 'secondary',
        'RETURNED_TO_REQUESTOR', 'RETURNED_TO_OE', 'RETURNED_TO_STAFF',
        'RETURNED_FOR_EDIT', 'RETURNED_FOR_REVISION', 'QUESTIONABLE', 'WAITING_HARD_COPY', 'TAGGED' => 'warning',
        'CANCELLED', 'SETTLED_BY_DEDUCTION' => 'dark',
        'COMPLETED', 'CLOSED', 'EXPENSE', 'ACTIVE' => 'success',
        default => 'info',
    };
@endphp

<span {{ $attributes->merge(['class' => "badge text-bg-{$variant}"]) }}>{{ $status->label }}</span>
