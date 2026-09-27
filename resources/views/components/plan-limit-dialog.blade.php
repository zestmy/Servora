{{--
    Shown when a Livewire action hits a plan cap (LimitReachedException →
    `plan-limit` browser event, wired in AppServiceProvider). The action has
    already stopped; this says why and where the bigger plan is.
--}}
<div x-data="{ open: false, message: '' }"
     x-on:plan-limit.window="message = $event.detail.message; open = true"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-overlay flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="plan-limit-title">
    <div class="absolute inset-0 bg-gray-900/50" @click="open = false"></div>
    <div class="panel relative w-full max-w-sm p-6" x-trap.noscroll="open">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                <x-icon name="lock" size="h-5 w-5" />
            </span>
            <div>
                <h2 id="plan-limit-title" class="text-base font-semibold text-gray-900">Plan limit reached</h2>
                <p class="mt-1 text-sm text-gray-600" x-text="message"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="btn-ghost" @click="open = false">Not now</button>
            <a href="{{ route('billing.index') }}" class="btn-primary">See plans</a>
        </div>
    </div>
</div>
