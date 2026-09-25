<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-page-header title="Audit Schedule" eyebrow="Outlet Audits"
                   subtitle="Which outlet is due which audit, and when. Starting an audit from here rolls its next due date forward.">
        <x-slot:actions>
            <a href="{{ route('audits.index') }}" wire:navigate class="btn-secondary">Audits</a>
            @if ($canManage)
                <button wire:click="openCreate" class="btn-primary">+ Schedule</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 block overflow-x-auto">
        <div class="seg">
            <button wire:click="$set('filter', 'active')"  class="seg-item {{ $filter === 'active' ? 'seg-item-on' : '' }}">Active</button>
            <button wire:click="$set('filter', 'overdue')" class="seg-item {{ $filter === 'overdue' ? 'seg-item-on' : '' }}">Overdue{{ $overdueCount ? " ({$overdueCount})" : '' }}</button>
            <button wire:click="$set('filter', 'all')"     class="seg-item {{ $filter === 'all' ? 'seg-item-on' : '' }}">All</button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[820px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left w-32">Next due</th>
                        <th class="px-4 py-3 text-left">Outlet</th>
                        <th class="px-4 py-3 text-left">Form</th>
                        <th class="px-4 py-3 text-left w-32">Every</th>
                        <th class="px-4 py-3 text-left w-36">Auditor</th>
                        <th class="px-4 py-3 text-left w-40">Last audit</th>
                        <th class="px-4 py-3 w-44"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schedules as $s)
                        <tr wire:key="sch-{{ $s->id }}" class="hover:bg-gray-50 {{ $s->is_active ? '' : 'opacity-60' }}">
                            <td class="px-4 py-3 tabular-nums">
                                <span class="{{ $s->isOverdue() ? 'font-semibold text-danger-700' : ($s->isDueSoon() ? 'font-medium text-warning-700' : 'text-gray-700') }}">
                                    {{ $s->next_due_on->format('d M Y') }}
                                </span>
                                @if ($s->isOverdue())
                                    <span class="block text-xs text-danger-700">{{ $s->next_due_on->diffForHumans() }}</span>
                                @elseif ($s->isDueSoon())
                                    <span class="block text-xs text-warning-700">{{ $s->next_due_on->diffForHumans() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $s->outlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $s->template?->code ? $s->template->code . ' · ' : '' }}{{ $s->template?->name ?? '(deleted form)' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $s->frequencyLabel() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $s->assignee?->name ?? 'Anyone' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($s->lastAudit)
                                    <a href="{{ route('audits.show', $s->lastAudit->id) }}" wire:navigate class="hover:text-brand-700">
                                        {{ $s->lastAudit->audit_date->format('d M Y') }}
                                        @if (! $s->lastAudit->isDraft() && $s->lastAudit->score_percent !== null)
                                            · <span class="tabular-nums">{{ number_format($s->lastAudit->score_percent, 1) }}%</span>
                                        @else
                                            · {{ $s->lastAudit->statusLabel() }}
                                        @endif
                                    </a>
                                @else
                                    <span class="text-gray-500">Never</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($canConduct && $s->is_active && $s->template)
                                        <button wire:click="startNow({{ $s->id }})" class="btn-primary text-xs">Start now</button>
                                    @endif
                                    @if ($canManage)
                                        <button wire:click="openEdit({{ $s->id }})" class="icon-btn" title="Edit"><x-icon name="pencil" size="h-4 w-4" /></button>
                                    @endif
                                    @canDo('audits.delete')
                                        <button wire:click="delete({{ $s->id }})" class="icon-btn icon-btn-danger" title="Remove"
                                                data-confirm-delete="Remove the {{ $s->template?->code ?: 'audit' }} schedule for {{ $s->outlet?->name }}. Audits already conducted stay.">
                                            <x-icon name="trash" size="h-4 w-4" />
                                        </button>
                                    @endcanDo
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12">
                                <div class="empty-state">
                                    <p class="empty-title">{{ $filter === 'overdue' ? 'Nothing overdue' : 'No schedules yet' }}</p>
                                    <p class="empty-body">
                                        {{ $filter === 'overdue' ? 'Every scheduled audit is on time.' : 'Put each outlet on a rhythm — quarterly ROSE, monthly halal spot-check — and this page tells you who is due.' }}
                                    </p>
                                    @if ($canManage && $filter !== 'overdue')
                                        <button wire:click="openCreate" class="btn-primary">+ Schedule</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-data="{}" x-show="$wire.showForm" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.set('showForm', false)"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <form wire:submit="save" class="relative z-10 w-full max-w-lg rounded-panel bg-white p-6 shadow-e3">
            <h3 class="text-base font-semibold text-gray-900">{{ $editingId ? 'Edit schedule' : 'New schedule' }}</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="sch-template">Audit form</label>
                    <select id="sch-template" wire:model="templateId" class="input">
                        <option value="">Choose…</option>
                        @foreach ($templates as $t)
                            <option value="{{ $t->id }}">{{ $t->code ? $t->code . ' · ' : '' }}{{ $t->name }}</option>
                        @endforeach
                    </select>
                    @error('templateId') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="sch-outlet">Outlet</label>
                    <select id="sch-outlet" wire:model="outletId" class="input">
                        <option value="">Choose…</option>
                        @foreach ($outlets as $o)
                            <option value="{{ $o->id }}">{{ $o->name }}</option>
                        @endforeach
                    </select>
                    @error('outletId') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="sch-freq">Every</label>
                    <select id="sch-freq" wire:model="frequency" class="input">
                        @foreach ($frequencies as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="sch-due">Next due</label>
                    <input id="sch-due" type="date" wire:model="nextDueOn" class="input" />
                    @error('nextDueOn') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="sch-auditor">Auditor</label>
                    <select id="sch-auditor" wire:model="assigneeId" class="input">
                        <option value="">Anyone</option>
                        @foreach ($auditors as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="sch-notes">Notes</label>
                    <input id="sch-notes" type="text" wire:model="notes" class="input" placeholder="Optional" />
                </div>
                @if ($editingId)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 sm:col-span-2">
                        <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                        Active — paused schedules stay listed under All but never show as due
                    </label>
                @endif
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
        </div>
        </div>
    </div>
</div>
