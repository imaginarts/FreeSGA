@props(['label' => null, 'for' => null, 'hint' => null, 'error' => null])
<div {{ $attributes }}>
    @if ($label)<label class="label" @if ($for) for="{{ $for }}" @endif>{{ $label }}</label>@endif
    {{ $slot }}
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @if ($error)@error($error)<p class="error">{{ $message }}</p>@enderror @endif
</div>
