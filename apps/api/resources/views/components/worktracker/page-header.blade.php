@props(['title', 'subtitle' => null])
<div class="wt-top"><div><h2 style="margin:0">{{ $title }}</h2>@if($subtitle)<div class="wt-muted" style="margin-top:4px">{{ $subtitle }}</div>@endif</div>@isset($actions)<div class="wt-row">{{ $actions }}</div>@endisset</div>
