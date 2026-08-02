{{-- Primary action. Styling lives in .btn-primary (resources/css/app.css) so
     every submit button in the app moves together. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary']) }}>
    {{ $slot }}
</button>
