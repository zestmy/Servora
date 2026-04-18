{{-- Trigger only. Alpine shared picker (pwSharedPicker) renders the popup. --}}
<button type="button"
        @click.stop="$dispatch('open-pw-picker', { idx: {{ $idx }}, triggerEl: $event.currentTarget })"
        class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 text-[11px] rounded border border-gray-200 bg-white hover:bg-indigo-50 hover:border-indigo-300 transition">
    <span>{{ $currentName ? 'Change' : 'Select ingredient…' }}</span>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
    </svg>
</button>
