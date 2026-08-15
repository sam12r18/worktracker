@props(['tone'=>'neutral'])<span {{ $attributes->merge(['class'=>'wt-badge wt-badge-'.$tone]) }}>{{ $slot }}</span>
