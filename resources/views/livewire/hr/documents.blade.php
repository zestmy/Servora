<div>
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
                    Your company hasn't set up any document folders yet. Please contact your administrator.
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
            {{-- Sidebar: Folder Tabs --}}
            <div class="lg:w-64 flex-shrink-0">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Categories</h3>
                    </div>
                    <div class="divide-y divide-gray-50">
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

            {{-- Main: Document Viewer --}}
            <div class="flex-1 min-w-0">
                @if ($currentFolder)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        {{-- Folder Header --}}
                        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="font-semibold text-gray-800">{{ $currentFolder->name }}</h3>
                                @if ($currentFolder->description)
                                    <p class="text-sm text-gray-500 mt-0.5">{{ $currentFolder->description }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($currentFolder->allow_upload && $canManage)
                                    <a href="{{ $currentFolder->drive_url }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v-8m0 0L8 8m4-4l4 4" />
                                        </svg>
                                        Upload Files
                                    </a>
                                @endif
                                <a href="{{ $currentFolder->drive_url }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Open in Drive
                                </a>
                            </div>
                        </div>

                        {{-- Embedded Folder --}}
                        <div class="relative bg-gray-50">
                            <iframe
                                src="{{ $currentFolder->embed_url }}"
                                class="w-full border-0"
                                style="height: 600px;"
                                loading="lazy"
                                allowfullscreen
                            ></iframe>
                        </div>

                        {{-- Footer Info --}}
                        <div class="px-4 py-2 border-t border-gray-100 bg-gray-50 text-xs text-gray-500">
                            Files are hosted on Google Drive. Click on a file to view, download, or print.
                            @if ($currentFolder->allow_upload && $canManage)
                                <span class="ml-1">Use the "Upload Files" button to add new documents.</span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-gray-500">
                        Select a folder from the sidebar to view documents.
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
