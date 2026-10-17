{{-- Package inclusions for a "Packages"-category service, one per line as
     entered on the admin form. Renders nothing for any other category (their
     Description behaviour is unchanged) or when details are empty.
     Expects: $service — a Service model or a ServiceCatalog array. --}}
@php
    $pkgCategory = is_array($service) ? ($service['category'] ?? null) : $service->category;
    $pkgDetails = is_array($service) ? ($service['package_details'] ?? null) : $service->package_details;
    $pkgInclusions = filled($pkgDetails)
        ? collect(preg_split('/\r\n|\r|\n/', (string) $pkgDetails))->map(fn ($line) => trim($line))->filter()->values()
        : collect();
@endphp
@if($pkgCategory === 'Packages' && $pkgInclusions->isNotEmpty())
    <ul class="nx-package-inclusions small mt-2 mb-0 ps-3">
        @foreach($pkgInclusions as $inclusion)
            <li>{{ $inclusion }}</li>
        @endforeach
    </ul>
@endif
