@props(['title' => null])
<section {{ $attributes->class(['wt-panel']) }}>
    @if($title)<h3 style="margin-top:0">{{ $title }}</h3>@endif
    {{ $slot }}
</section>
