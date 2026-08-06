@props([
    // Where to go when there is nothing sensible to go back to. Always a real
    // URL, so the link works with JavaScript off and right-click → open in new
    // tab does something reasonable.
    'fallback' => null,
    'label'    => 'Back',
])

{{--
    Back arrow that returns to the page you actually came from.

    The href is the FALLBACK, not the destination: it is what a crawler, a
    middle-click or a JS-less browser follows, and what happens when this page
    was opened directly — from a bookmark, a pasted link, or as the first page
    of the session. In all of those "back" has no meaning and the old
    hardcoded parent is the right answer.

    Otherwise it goes back one step in history, which is what someone pressing
    a back arrow means. The referrer check is what separates the two: a
    same-origin referrer means there IS a previous page in this app to return
    to. `history.length` alone is not usable — a fresh tab reports 1 in some
    browsers and 2 in others, and it counts forward entries too.

    Deliberately NOT storing a "previous page" server-side. A session-held
    breadcrumb goes wrong the moment two tabs are open, which is exactly how
    someone comparing two employees works.
--}}
<a href="{{ $fallback }}"
   x-data="{
       canGoBack() {
           const ref = document.referrer;
           if (! ref) return false;
           try {
               const url = new URL(ref);
               // Same site, and not this very page — returning to yourself
               // looks like the button did nothing.
               return url.origin === window.location.origin
                   && url.href !== window.location.href;
           } catch (e) {
               return false;
           }
       }
   }"
   x-on:click="if (canGoBack()) { $event.preventDefault(); window.history.back(); }"
   title="{{ $label }}"
   {{ $attributes->merge(['class' => 'mt-0.5 p-1.5 rounded-control text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition']) }}>
    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
    </svg>
    <span class="sr-only">{{ $label }}</span>
</a>
