@props(['label', 'value', 'hint' => null])
<div {{ $attributes->class(['wt-card']) }}><div class="wt-muted">{{ $label }}</div><div class="wt-metric">{{ $value }}</div>@if($hint)<div class="wt-muted">{{ $hint }}</div>@endif</div>
