<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1">
            <h1 class="text-lg font-bold text-gray-800">Announcements</h1>
            <p class="text-xs text-gray-600 mt-0.5">Show banners at the top of the dashboard for all users.</p>
        </div>
        <button wire:click="openCreate" class="btn-primary">+ New Announcement</button>
    </div>

    <div class="card overflow-hidden">
        <table class="table-surface min-w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Title</th>
                    <th class="px-4 py-3 text-center">Type</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Schedule</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($announcements as $ann)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $ann->title }}</p>
                            <p class="text-xs text-gray-600 truncate max-w-xs">{{ Str::limit($ann->body, 60) }}</p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php $c = $ann->typeColor(); @endphp
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">{{ ucfirst($ann->type) }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $ann->is_active ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $ann->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            @if ($ann->starts_at || $ann->ends_at)
                                {{ $ann->starts_at?->format('d M Y') ?? 'Now' }} — {{ $ann->ends_at?->format('d M Y') ?? 'Ongoing' }}
                            @else
                                Always
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="openEdit({{ $ann->id }})" class="text-brand-500 hover:text-brand-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="toggleActive({{ $ann->id }})" class="{{ $ann->is_active ? 'text-success-500' : 'text-gray-600' }} hover:text-gray-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                <button wire:click="delete({{ $ann->id }})" data-confirm-delete="Delete this announcement?" class="text-danger-400 hover:text-danger-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-gray-600">No announcements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">{{ $editingId ? 'Edit Announcement' : 'New Announcement' }}</h3>
                <button @click="$wire.closeModal()" class="text-gray-600 hover:text-gray-900"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form wire:submit="save">
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <x-input-label for="ann_title" value="Title *" />
                        <x-text-input id="ann_title" wire:model="title" type="text" class="mt-1 block w-full" placeholder="e.g. New feature: AI Analytics" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="ann_body" value="Body *" />
                        <textarea id="ann_body" wire:model="body" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Announcement content…"></textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="ann_type" value="Type" />
                            <select id="ann_type" wire:model="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="info">Info</option>
                                <option value="success">Success</option>
                                <option value="warning">Warning</option>
                                <option value="promo">Promo</option>
                            </select>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" />
                                <span class="text-sm text-gray-700 font-medium">Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="ann_start" value="Starts At (optional)" />
                            <input id="ann_start" wire:model="starts_at" type="datetime-local" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" />
                        </div>
                        <div>
                            <x-input-label for="ann_end" value="Ends At (optional)" />
                            <input id="ann_end" wire:model="ends_at" type="datetime-local" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" />
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <button type="button" @click="$wire.closeModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </div>
        </div>
        </div>
    </div>
    @endteleport
</div>
