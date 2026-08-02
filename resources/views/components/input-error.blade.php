@props(['messages'])

@if ($messages)
    {{-- Error text sits BELOW the input it describes. role=alert so a screen
         reader announces a validation failure instead of silently failing. --}}
    <ul {{ $attributes->merge(['class' => 'error-text space-y-1']) }} role="alert">
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
