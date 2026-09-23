@props(['active'])
@if ($active)
    <span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Ativo</span>
@else
    <span class="badge bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">Inativo</span>
@endif
