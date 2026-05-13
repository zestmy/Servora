@switch($icon)
    @case('pdf')
        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4zM9.5 13v5H8v-5h1.5zm2.5 0c.83 0 1.5.67 1.5 1.5v2c0 .83-.67 1.5-1.5 1.5h-1v-5h1zm3 0h2v1h-1v1h1v1h-1v2h-1v-5zM10 14h.5v3H10v-3z"/></svg>
        @break
    @case('doc')
        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4zM8.5 17.5v-5l1.5 3 1.5-3v5h1v-6h-1l-1.5 3-1.5-3h-1v6h1z"/></svg>
        @break
    @case('sheet')
        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4zM8 13h3v2H8v-2zm0 3h3v2H8v-2zm5-3h3v2h-3v-2zm0 3h3v2h-3v-2z"/></svg>
        @break
    @case('slides')
        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4zM7 12h10v6H7v-6z"/></svg>
        @break
    @case('image')
        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
        @break
    @case('video')
        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V4h-4z"/></svg>
        @break
    @case('audio')
        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v9.28a4.39 4.39 0 0 0-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.2-1.75 4.45-4H15V6h4V3h-7z"/></svg>
        @break
    @case('text')
        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4zM7 17v-2h10v2H7zm0-4v-2h10v2H7z"/></svg>
        @break
    @default
        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4z"/></svg>
@endswitch
