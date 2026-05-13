<div>
    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">HR / Documents</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Company Documents</h2>
        </div>
        @if ($canManage)
            <a href="{{ route('settings.document-folders') }}"
               class="px-3 md:px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="hidden sm:inline">Manage Folders</span>
            </a>
        @endif
    </div>

    {{-- Not Configured Warning --}}
    @if (!$isConfigured)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-6">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <h3 class="font-semibold text-amber-800">Google Drive API Not Configured</h3>
                    <p class="text-sm text-amber-700 mt-1">
                        To enable the file browser, please set up Google Drive API credentials.
                    </p>
                    @if ($canManage)
                        <div class="mt-3 p-3 bg-white rounded-lg border border-amber-100">
                            <p class="text-xs font-semibold text-gray-700 mb-2">Setup Instructions:</p>
                            <ol class="text-xs text-gray-600 list-decimal list-inside space-y-1">
                                <li>Go to <a href="https://console.cloud.google.com/" target="_blank" class="text-indigo-600 hover:underline">Google Cloud Console</a></li>
                                <li>Create a project and enable "Google Drive API"</li>
                                <li>Create a Service Account and download the JSON key</li>
                                <li>Upload the JSON file to the server</li>
                                <li>Add to .env: <code class="bg-gray-100 px-1 rounded">GOOGLE_DRIVE_CREDENTIALS=/path/to/credentials.json</code></li>
                                <li>Share your Drive folders with the service account email</li>
                            </ol>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($folders->isEmpty())
        {{-- Empty State --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
            </div>
            <h3 class="text-sm font-semibold text-gray-700 mb-1">No document folders configured</h3>
            <p class="text-sm text-gray-500 mb-4">
                @if ($canManage)
                    Get started by adding Google Drive folders to share documents with your team.
                @else
                    Your company hasn't set up any document folders yet.
                @endif
            </p>
            @if ($canManage)
                <a href="{{ route('settings.document-folders') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Configure Document Folders
                </a>
            @endif
        </div>
    @else
        <div class="flex flex-col lg:flex-row gap-4">
            {{-- Sidebar: Folder Categories --}}
            <div class="lg:w-56 flex-shrink-0">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-4">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Categories</h3>
                    </div>
                    <div class="divide-y divide-gray-50 max-h-[60vh] overflow-y-auto">
                        @foreach ($folders as $folder)
                            <button wire:click="setActiveFolder({{ $folder->id }})"
                                    class="w-full px-4 py-3 text-left transition hover:bg-gray-50 {{ $activeFolder === $folder->id ? 'bg-indigo-50 border-l-2 border-indigo-600' : '' }}">
                                <div class="font-medium text-sm {{ $activeFolder === $folder->id ? 'text-indigo-700' : 'text-gray-700' }}">
                                    {{ $folder->name }}
                                </div>
                                @if ($folder->description)
                                    <div class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $folder->description }}</div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Main: File Browser --}}
            <div class="flex-1 min-w-0">
                @if ($currentFolder && $isConfigured)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        {{-- Toolbar --}}
                        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
                            {{-- Breadcrumbs --}}
                            <div class="flex-1 flex items-center gap-1 text-sm overflow-x-auto">
                                <button wire:click="navigateToRoot" class="text-indigo-600 hover:underline whitespace-nowrap">
                                    {{ $currentFolder->name }}
                                </button>
                                @foreach ($breadcrumbs as $index => $crumb)
                                    <svg class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    <button wire:click="navigateToBreadcrumb({{ $index }})" class="text-indigo-600 hover:underline whitespace-nowrap">
                                        {{ $crumb['name'] }}
                                    </button>
                                @endforeach
                            </div>

                            {{-- View Toggle --}}
                            <button wire:click="toggleViewMode" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" title="Toggle view">
                                @if ($viewMode === 'grid')
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                @else
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                @endif
                            </button>
                        </div>

                        {{-- Search Bar --}}
                        <div class="px-4 py-2 border-b border-gray-100 bg-gray-50">
                            <div class="relative">
                                <input type="text"
                                       wire:model.live.debounce.300ms="searchQuery"
                                       placeholder="Search files..."
                                       class="w-full pl-9 pr-8 py-2 text-sm rounded-lg border-gray-200 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                @if ($searchQuery)
                                    <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- File List --}}
                        <div class="p-4 min-h-[400px]" wire:loading.class="opacity-50">
                            @if (empty($files))
                                <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                                    <svg class="h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                                    </svg>
                                    <p class="text-sm">{{ $searchQuery ? 'No files found' : 'This folder is empty' }}</p>
                                </div>
                            @elseif ($viewMode === 'grid')
                                {{-- Grid View --}}
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                    @foreach ($files as $file)
                                        @if ($file['isFolder'])
                                            <button wire:click="navigateToFolder('{{ $file['id'] }}')"
                                                    class="group flex flex-col items-center p-3 rounded-lg hover:bg-gray-50 transition text-center">
                                                <div class="w-12 h-12 flex items-center justify-center text-amber-500 mb-2">
                                                    <svg class="h-10 w-10" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                                                </div>
                                                <span class="text-xs font-medium text-gray-700 line-clamp-2 group-hover:text-indigo-600">{{ $file['name'] }}</span>
                                            </button>
                                        @else
                                            <button wire:click="openPreview('{{ $file['id'] }}')"
                                                    class="group flex flex-col items-center p-3 rounded-lg hover:bg-gray-50 transition text-center">
                                                @if ($file['isImage'] && $file['thumbnailLink'])
                                                    <div class="w-16 h-16 mb-2 rounded overflow-hidden bg-gray-100">
                                                        <img src="https://drive.google.com/thumbnail?id={{ $file['id'] }}&sz=s200" alt="" class="w-full h-full object-cover">
                                                    </div>
                                                @else
                                                    <div class="w-12 h-12 flex items-center justify-center mb-2 {{ $file['icon'] === 'pdf' ? 'text-red-500' : ($file['icon'] === 'doc' ? 'text-blue-500' : ($file['icon'] === 'sheet' ? 'text-green-500' : ($file['icon'] === 'slides' ? 'text-yellow-500' : ($file['icon'] === 'image' ? 'text-purple-500' : ($file['icon'] === 'video' ? 'text-pink-500' : 'text-gray-400'))))) }}">
                                                        @include('livewire.hr.partials.file-icon', ['icon' => $file['icon']])
                                                    </div>
                                                @endif
                                                <span class="text-xs font-medium text-gray-700 line-clamp-2 group-hover:text-indigo-600">{{ $file['name'] }}</span>
                                                @if ($file['size'])
                                                    <span class="text-[10px] text-gray-400 mt-0.5">{{ number_format($file['size'] / 1024, 0) }} KB</span>
                                                @endif
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                {{-- List View --}}
                                <div class="divide-y divide-gray-100">
                                    @foreach ($files as $file)
                                        <div class="flex items-center gap-3 py-2 px-2 hover:bg-gray-50 rounded-lg transition {{ $file['isFolder'] ? 'cursor-pointer' : '' }}"
                                             @if ($file['isFolder']) wire:click="navigateToFolder('{{ $file['id'] }}')" @endif>
                                            {{-- Icon --}}
                                            <div class="flex-shrink-0 w-10 h-10 flex items-center justify-center {{ $file['isFolder'] ? 'text-amber-500' : ($file['icon'] === 'pdf' ? 'text-red-500' : ($file['icon'] === 'doc' ? 'text-blue-500' : ($file['icon'] === 'sheet' ? 'text-green-500' : ($file['icon'] === 'slides' ? 'text-yellow-500' : ($file['icon'] === 'image' ? 'text-purple-500' : ($file['icon'] === 'video' ? 'text-pink-500' : 'text-gray-400')))))) }}">
                                                @if ($file['isFolder'])
                                                    <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                                                @else
                                                    @include('livewire.hr.partials.file-icon', ['icon' => $file['icon']])
                                                @endif
                                            </div>

                                            {{-- Name --}}
                                            <div class="flex-1 min-w-0">
                                                @if ($file['isFolder'])
                                                    <span class="text-sm font-medium text-gray-800 hover:text-indigo-600">{{ $file['name'] }}</span>
                                                @else
                                                    <button wire:click="openPreview('{{ $file['id'] }}')" class="text-sm font-medium text-gray-800 hover:text-indigo-600 truncate block w-full text-left">
                                                        {{ $file['name'] }}
                                                    </button>
                                                @endif
                                            </div>

                                            {{-- Size --}}
                                            <div class="hidden sm:block text-xs text-gray-400 w-20 text-right">
                                                @if ($file['size'])
                                                    {{ number_format($file['size'] / 1024, 0) }} KB
                                                @elseif ($file['isFolder'])
                                                    —
                                                @endif
                                            </div>

                                            {{-- Actions --}}
                                            @if (!$file['isFolder'])
                                                <div class="flex items-center gap-1">
                                                    <button wire:click="openPreview('{{ $file['id'] }}')" class="p-1.5 text-gray-400 hover:text-indigo-600 rounded" title="Preview">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    </button>
                                                    <a href="https://drive.google.com/uc?export=download&id={{ $file['id'] }}" target="_blank" class="p-1.5 text-gray-400 hover:text-green-600 rounded" title="Download">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif (!$isConfigured)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-gray-500">
                        Configure Google Drive API to browse files.
                    </div>
                @else
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-gray-500">
                        Select a folder from the sidebar to view documents.
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Preview Modal (Fullscreen with Navigation) --}}
    @if ($showPreview && $previewFile)
        <div x-data="{ open: @entangle('showPreview') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak
                 @keydown.escape.window="$wire.closePreview()"
                 @keydown.left.window="$wire.prevFile()"
                 @keydown.right.window="$wire.nextFile()"
                 class="fixed inset-0 z-[100] flex items-center justify-center">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-black/90" @click="$wire.closePreview()"></div>

                {{-- Modal (Fullscreen) --}}
                <div class="relative bg-white w-full h-full flex flex-col" @click.stop>
                    {{-- Header --}}
                    <div class="flex items-center justify-between px-4 py-2 bg-gray-900 text-white flex-shrink-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex-shrink-0 {{ $previewFile['icon'] === 'pdf' ? 'text-red-400' : ($previewFile['icon'] === 'doc' ? 'text-blue-400' : ($previewFile['icon'] === 'sheet' ? 'text-green-400' : 'text-gray-400')) }}">
                                @include('livewire.hr.partials.file-icon', ['icon' => $previewFile['icon']])
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-white truncate">{{ $previewFile['name'] }}</h3>
                                <p class="text-xs text-gray-400">
                                    {{ $previewIndex + 1 }} of {{ count($previewableFiles) }}
                                    @if ($previewFile['size'])
                                        &bull; {{ number_format($previewFile['size'] / 1024, 0) }} KB
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- Navigation buttons (for header) --}}
                            @if (count($previewableFiles) > 1)
                                <div class="hidden sm:flex items-center gap-1 mr-2">
                                    <button wire:click="prevFile" class="p-2 text-gray-400 hover:text-white rounded-lg hover:bg-gray-700" title="Previous (Left Arrow)">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                    <button wire:click="nextFile" class="p-2 text-gray-400 hover:text-white rounded-lg hover:bg-gray-700" title="Next (Right Arrow)">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>
                            @endif
                            {{-- Present button for presentations --}}
                            @if ($previewFile['isPresentation'])
                                <a href="https://docs.google.com/presentation/d/{{ $previewFile['id'] }}/present"
                                   target="_blank"
                                   class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition flex items-center gap-1"
                                   title="Open in presentation mode">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="hidden sm:inline">Present</span>
                                </a>
                            @endif
                            <a href="https://drive.google.com/uc?export=download&id={{ $previewFile['id'] }}"
                               target="_blank"
                               class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition flex items-center gap-1">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span class="hidden sm:inline">Download</span>
                            </a>
                            <button @click="$wire.closePreview()" class="p-2 text-gray-400 hover:text-white rounded-lg hover:bg-gray-700" title="Close (Esc)">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Preview Content (Full Height) with Side Navigation --}}
                    <div class="flex-1 overflow-hidden bg-gray-900 relative">
                        {{-- Previous Button (Left Side) --}}
                        @if (count($previewableFiles) > 1)
                            <button wire:click="prevFile"
                                    class="absolute left-2 top-1/2 -translate-y-1/2 z-10 p-3 bg-black/50 hover:bg-black/70 text-white rounded-full transition opacity-70 hover:opacity-100"
                                    title="Previous (Left Arrow)">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                        @endif

                        {{-- File Content --}}
                        @if ($previewFile['isImage'])
                            <div class="h-full flex items-center justify-center p-4">
                                <img src="https://drive.google.com/uc?id={{ $previewFile['id'] }}" alt="{{ $previewFile['name'] }}" class="max-w-full max-h-full object-contain">
                            </div>
                        @elseif ($previewFile['isVideo'])
                            <div class="h-full flex items-center justify-center p-4">
                                <video controls class="max-w-full max-h-full">
                                    <source src="https://drive.google.com/uc?id={{ $previewFile['id'] }}" type="{{ $previewFile['mimeType'] }}">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        @elseif ($previewFile['isAudio'])
                            <div class="h-full flex items-center justify-center p-8">
                                <audio controls class="w-full max-w-lg">
                                    <source src="https://drive.google.com/uc?id={{ $previewFile['id'] }}" type="{{ $previewFile['mimeType'] }}">
                                    Your browser does not support the audio tag.
                                </audio>
                            </div>
                        @elseif ($previewFile['isGoogleSlides'])
                            {{-- Google Slides: Use embed URL with slide controls --}}
                            <iframe src="https://docs.google.com/presentation/d/{{ $previewFile['id'] }}/embed?start=false&loop=false&delayms=3000"
                                    class="w-full h-full border-0"
                                    frameborder="0"
                                    allowfullscreen="true"
                                    mozallowfullscreen="true"
                                    webkitallowfullscreen="true"
                                    loading="lazy"></iframe>
                        @elseif ($previewFile['isPresentation'])
                            {{-- Uploaded PPT/PPTX: Use Google Docs viewer --}}
                            <div class="h-full flex flex-col">
                                <div class="flex-1">
                                    <iframe src="https://drive.google.com/file/d/{{ $previewFile['id'] }}/preview"
                                            class="w-full h-full border-0"
                                            allow="autoplay"
                                            loading="lazy"></iframe>
                                </div>
                                <div class="bg-gray-800 px-4 py-2 text-center text-sm text-gray-300">
                                    <span>Use arrow keys to navigate slides</span>
                                    <span class="mx-2">|</span>
                                    <a href="https://docs.google.com/presentation/d/{{ $previewFile['id'] }}/present"
                                       target="_blank"
                                       class="text-indigo-400 hover:text-indigo-300 underline">
                                        Open in fullscreen presentation mode
                                    </a>
                                </div>
                            </div>
                        @elseif ($previewFile['isPreviewable'])
                            <iframe src="https://drive.google.com/file/d/{{ $previewFile['id'] }}/preview"
                                    class="w-full h-full border-0"
                                    allow="autoplay"
                                    loading="lazy"></iframe>
                        @else
                            <div class="h-full flex flex-col items-center justify-center text-gray-400 p-8">
                                <svg class="h-20 w-20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-lg mb-4">Preview not available for this file type</p>
                                <a href="https://drive.google.com/uc?export=download&id={{ $previewFile['id'] }}"
                                   target="_blank"
                                   class="px-6 py-3 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                                    Download File
                                </a>
                            </div>
                        @endif

                        {{-- Next Button (Right Side) --}}
                        @if (count($previewableFiles) > 1)
                            <button wire:click="nextFile"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 z-10 p-3 bg-black/50 hover:bg-black/70 text-white rounded-full transition opacity-70 hover:opacity-100"
                                    title="Next (Right Arrow)">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </template>
        </div>
    @endif
</div>
