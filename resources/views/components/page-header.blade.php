@props(['title', 'subtitle' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)<p class="page-subtitle">{{ $subtitle }}</p>@endif
    </div>
    @if ($slot->isNotEmpty())<div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
</div>
