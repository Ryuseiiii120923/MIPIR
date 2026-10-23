@props(['current' => 'inspection'])

@if (app(\App\Auth\Services\CurrentActor::class)->isGl())
<div class="mb-3 inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm font-medium">
    <a href="{{ route('landing-page') }}"
        @class([
            'rounded-md px-4 py-1.5',
            'bg-blue-600 text-white' => $current === 'inspection',
            'text-gray-600 hover:bg-gray-50' => $current !== 'inspection',
        ])>
        Inspection
    </a>
    <a href="{{ route('lot-confirmation') }}"
        @class([
            'rounded-md px-4 py-1.5',
            'bg-blue-600 text-white' => $current === 'lot-confirmation',
            'text-gray-600 hover:bg-gray-50' => $current !== 'lot-confirmation',
        ])>
        Lot Confirmation
    </a>
</div>
@endif