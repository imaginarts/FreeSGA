@props(['status'])
@php
    $colors = [
        'slate' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.($colors[$status->color()] ?? $colors['slate'])]) }}>{{ $status->label() }}</span>
