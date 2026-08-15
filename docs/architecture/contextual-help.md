# Contextual Help System

WorkTracker web UI uses one shared modal help system.

## Components

- `resources/views/components/worktracker/help.blade.php` — reusable `!` trigger with a `<template>` payload.
- `resources/views/components/worktracker/context-help.blade.php` — page-level floating help trigger.
- `config/worktracker-help.php` — route-based page help registry.
- `resources/views/layouts/worktracker.blade.php` — one shared modal host and event delegation logic.

## Usage

```blade
<x-worktracker.help title="Project multiplier">
    <p>Explanation...</p>
</x-worktracker.help>
```

The page-level floating `!` is injected by the shared layout for all major authenticated WorkTracker web pages. Field-level help uses the same modal, so no page creates its own dialog implementation.

## Blade safety

Do not aggressively minify Blade directives/components. In particular keep component tags, `@if`, `@foreach`, `@yield` and surrounding HTML structurally readable. CSS/JS may be minified by a real asset build, but Blade source should not be flattened by string compression.
