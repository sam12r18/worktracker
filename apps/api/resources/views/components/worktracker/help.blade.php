@props(['title' => 'راهنما', 'label' => null])
@php($helpId = 'wt-help-'.str_replace('-', '', (string) \Illuminate\Support\Str::uuid()))
<button
    type="button"
    {{ $attributes->class(['wt-help-trigger']) }}
    data-wt-help-target="{{ $helpId }}"
    aria-label="{{ $label ?: 'نمایش راهنما: '.$title }}"
    title="{{ $label ?: 'راهنما' }}"
>!</button>
<template id="{{ $helpId }}">
    <div class="wt-help-template" data-title="{{ $title }}">
        {{ $slot }}
    </div>
</template>
