<div class="mx-auto max-w-xl">
    <x-page-header title="New audit" eyebrow="Outlet Audits">
        <x-slot:actions>
            <a href="{{ route('audits.index') }}" wire:navigate class="btn-secondary">Back</a>
        </x-slot:actions>
    </x-page-header>

    @if ($templates->isEmpty())
        <div class="card p-6">
            <div class="empty-state">
                <p class="empty-title">No active audit form</p>
                <p class="empty-body">An audit is conducted against a form. Build one, or install the ROSE starter, under Audit Forms.</p>
                @canDo('audits.manage')
                    <a href="{{ route('audits.templates') }}" wire:navigate class="btn-primary">Audit Forms</a>
                @endcanDo
            </div>
        </div>
    @else
        <form wire:submit="start" class="card space-y-5 p-5">
            @if ($scheduleId)
                <div class="alert-info">Starting from the schedule. Its next due date rolls forward when you start.</div>
            @endif
            <div>
                <label class="label" for="start-template">Audit form</label>
                <select id="start-template" wire:model="templateId" class="input">
                    <option value="">Choose…</option>
                    @foreach ($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->code ? $t->code . ' · ' : '' }}{{ $t->name }} ({{ $t->sections_count }} sections)</option>
                    @endforeach
                </select>
                @error('templateId') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="start-outlet">Outlet</label>
                @if ($hasOutletChoice)
                    <select id="start-outlet" wire:model="outlet_id" class="input">
                        <option value="">Choose…</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                @else
                    <p class="input bg-gray-50">{{ $outlets->firstWhere('id', $outlet_id)?->name ?? '—' }}</p>
                @endif
                @error('outlet_id') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="start-date">Date</label>
                    <input id="start-date" type="date" wire:model="auditDate" class="input" />
                    @error('auditDate') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="start-ref">Reference</label>
                    <input id="start-ref" type="text" wire:model="reference" class="input" placeholder="Optional" />
                </div>
            </div>

            <p class="help">
                The form is copied into the audit as it stands today. Time in is stamped now; time out when you submit.
            </p>

            <div class="flex justify-end gap-2">
                <button type="submit" class="btn-primary">
                    <span wire:loading.remove wire:target="start">Start audit</span>
                    <span wire:loading wire:target="start">Preparing…</span>
                </button>
            </div>
        </form>
    @endif
</div>
