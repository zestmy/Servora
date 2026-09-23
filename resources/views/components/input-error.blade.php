@props(['messages'])

@php
    // $errors->get('foo.*') returns one array of messages PER matching key, so a
    // wildcard lookup arrives nested — and {{ $message }} on an inner array is a
    // 500, not a validation message. Flatten so every call site, wildcard or not,
    // hands the loop plain strings.
    $messages = \Illuminate\Support\Arr::flatten((array) $messages);
@endphp

@if ($messages)
    {{-- Error text sits BELOW the input it describes. role=alert so a screen
         reader announces a validation failure instead of silently failing. --}}
    <ul {{ $attributes->merge(['class' => 'error-text space-y-1']) }} role="alert">
        @foreach ($messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
