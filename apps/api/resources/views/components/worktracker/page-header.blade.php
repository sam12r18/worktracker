@props(['title', 'subtitle' => null, 'helpTitle' => null])
<div class="wt-top">
    <div>
        <div class="wt-help-inline-title">
            <h2 style="margin:0">{{ $title }}</h2>
            @isset($help)
                <x-worktracker.help :title="$helpTitle ?: $title">{{ $help }}</x-worktracker.help>
            @endisset
        </div>
        @if($subtitle)
            <div class="wt-muted" style="margin-top:4px">{{ $subtitle }}</div>
        @endif
    </div>
    @isset($actions)
        <div class="wt-row">{{ $actions }}</div>
    @endisset
</div>
