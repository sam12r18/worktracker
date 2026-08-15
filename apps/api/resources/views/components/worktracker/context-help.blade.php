@php
    $routeName = request()->route()?->getName();
    $help = $routeName ? config('worktracker-help.pages.'.$routeName) : null;
@endphp
@if($help)
    <x-worktracker.help :title="$help['title']" class="wt-help-floating" label="راهنمای این صفحه">
        @foreach($help['body'] as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    </x-worktracker.help>
@endif
