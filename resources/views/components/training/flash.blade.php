{{--
    The success/error strip every Training screen opens with.

    Eleven screens would otherwise each carry their own copy of the same
    Alpine auto-dismiss block, which is how the label screens ended up with
    three different dismiss timings. One component, one timing.

    Errors do NOT auto-dismiss. A success message is a receipt and can fade;
    an error is usually the only explanation of why the thing the user just
    did did not happen, and taking it away after three seconds means they
    press the button again and get the same silence.
--}}

@if (session()->has('success'))
    <div wire:key="flash-ok-{{ microtime(true) }}"
         x-data="{ show: true }" x-show="show"
         x-init="setTimeout(() => show = false, 4000)"
         class="alert-success mb-4">
        <x-icon name="check" size="h-5 w-5" class="flex-shrink-0" />
        <p>{{ session('success') }}</p>
    </div>
@endif

@if (session()->has('error'))
    <div wire:key="flash-err-{{ microtime(true) }}" class="alert-danger mb-4">
        <x-icon name="alert" size="h-5 w-5" class="flex-shrink-0" />
        <p>{{ session('error') }}</p>
    </div>
@endif
