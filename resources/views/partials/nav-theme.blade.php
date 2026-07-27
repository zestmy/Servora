{{-- Sidebar light ("clear") theme. The sidebars are authored dark; setting
     data-nav-theme="light" on the <aside> remaps the neutral greys to a white
     palette. Brand-colored elements (bg-indigo-*/bg-purple-*/bg-emerald-*)
     are deliberately untouched, as is the .bg-gray-800 logo strip — the
     two-tone header keeps the white logo readable in both themes. --}}
<style>
    aside[data-nav-theme='light'] { background-color: #ffffff !important; color: #111827; border-right: 1px solid #e5e7eb; }
    aside[data-nav-theme='light'] .bg-gray-900 { background-color: #ffffff !important; }
    aside[data-nav-theme='light'] .text-white:not([class*='bg-']) { color: #111827 !important; }
    aside[data-nav-theme='light'] .text-gray-300 { color: #4b5563 !important; }
    aside[data-nav-theme='light'] .text-gray-400 { color: #6b7280 !important; }
    aside[data-nav-theme='light'] .text-gray-500 { color: #9ca3af !important; }
    aside[data-nav-theme='light'] .hover\:bg-gray-700:hover { background-color: #f3f4f6 !important; }
    aside[data-nav-theme='light'] .hover\:bg-gray-800:hover { background-color: #f3f4f6 !important; }
    aside[data-nav-theme='light'] .hover\:text-white:hover { color: #111827 !important; }
    aside[data-nav-theme='light'] .hover\:text-gray-300:hover { color: #374151 !important; }
    aside[data-nav-theme='light'] .border-gray-700 { border-color: #e5e7eb !important; }
    aside[data-nav-theme='light'] .border-gray-600 { border-color: #d1d5db !important; }
    aside[data-nav-theme='light'] .bg-gray-700:not(:hover) { background-color: #e5e7eb !important; }
    aside[data-nav-theme='light'] .divide-gray-700 > * + * { border-color: #e5e7eb !important; }
    aside[data-nav-theme='light'] .bg-purple-900\/40 { background-color: #f5f3ff !important; }
    aside[data-nav-theme='light'] .bg-purple-900\/60 { background-color: #ede9fe !important; }
    aside[data-nav-theme='light'] .text-purple-300 { color: #7c3aed !important; }
    aside[data-nav-theme='light'] .text-purple-200 { color: #6d28d9 !important; }
    aside[data-nav-theme='light'] .border-purple-700 { border-color: #ddd6fe !important; }
    aside[data-nav-theme='light'] .bg-indigo-900\/40 { background-color: #eef2ff !important; }
    aside[data-nav-theme='light'] .text-indigo-300 { color: #4f46e5 !important; }
    aside[data-nav-theme='light'] .text-indigo-200 { color: #4338ca !important; }
</style>
