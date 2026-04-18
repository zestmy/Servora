@props([
    'message' => 'This screen is designed for desktop — pinch-zoom or switch to a computer for the best experience.',
    'storageKey' => 'desktop-hint-dismissed',
])

{{--
    Dismissable banner shown only on <md viewports for screens that are too dense
    to use comfortably on a phone (mass-edit tables, large forms, etc.). Once
    dismissed, stays dismissed on that browser for 24 hours.
--}}
<div x-data="{
        show: (() => {
            try {
                const raw = localStorage.getItem(@js($storageKey));
                if (!raw) return true;
                const ts = parseInt(raw, 10);
                return !ts || (Date.now() - ts) > 24*60*60*1000;
            } catch (e) { return true; }
        })(),
        dismiss() {
            this.show = false;
            try { localStorage.setItem(@js($storageKey), String(Date.now())); } catch (e) {}
        }
     }"
     x-show="show" x-cloak
     class="md:hidden mb-4 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-2">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 21l3-2 3 2-.75-4M3 12a9 9 0 1118 0 9 9 0 01-18 0z" />
    </svg>
    <p class="text-[11px] text-amber-800 flex-1 leading-snug">{{ $message }}</p>
    <button @click="dismiss()" class="text-amber-500 hover:text-amber-700 -mt-0.5 -mr-1 p-1" aria-label="Dismiss">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>
