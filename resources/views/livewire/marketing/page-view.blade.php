<div>
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $page->title }}</h1>
        <p class="text-xs text-gray-400 mb-8">Last updated {{ $page->updated_at->format('d M Y') }}</p>

        <div class="prose prose-sm sm:prose max-w-none prose-headings:text-gray-900 prose-p:text-gray-600 prose-a:text-indigo-600 prose-strong:text-gray-800 prose-li:text-gray-600">
            {!! $page->content !!}
        </div>
    </section>
</div>
