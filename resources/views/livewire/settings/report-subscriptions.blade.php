<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-lg font-semibold text-gray-700">Scheduled Reports</h2>
                <p class="text-sm text-gray-400 mt-0.5">Configure automated analytics reports delivered to your email</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="openTestModal"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                Send Test Report
            </button>
            <button wire:click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                + New Subscription
            </button>
        </div>
    </div>

    {{-- Subscriptions Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
        <div class="overflow-x-auto">
            <table class="min-w-[900px] divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Report Type</th>
                        <th class="px-4 py-3 text-left">Outlet</th>
                        <th class="px-4 py-3 text-left">Frequency</th>
                        <th class="px-4 py-3 text-left">Delivery Time</th>
                        <th class="px-4 py-3 text-center">AI Insights</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Last Sent</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($subscriptions as $subscription)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-700">{{ $subscription->getReportTypeLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $subscription->outlet?->name ?? 'All Outlets' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $subscription->getFrequencyLabel() }}
                                @if($subscription->delivery_day)
                                    <span class="text-gray-400 text-xs">({{ $subscription->getDeliveryDayLabel() }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $subscription->delivery_time?->format('H:i') ?? '06:00' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($subscription->include_ai_insights)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                        AI On
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                        Off
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleActive({{ $subscription->id }})" class="focus:outline-none">
                                    @if($subscription->is_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 cursor-pointer hover:bg-green-200">
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 cursor-pointer hover:bg-gray-200">
                                            Paused
                                        </span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                {{ $subscription->last_sent_at?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <button wire:click="openEdit({{ $subscription->id }})" title="Edit"
                                            class="text-indigo-500 hover:text-indigo-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button wire:click="delete({{ $subscription->id }})"
                                            wire:confirm="Delete this report subscription?"
                                            title="Delete"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="font-medium">No report subscriptions</p>
                                    <p class="text-xs mt-1">Click <button wire:click="openCreate" class="text-indigo-500 underline">+ New Subscription</button> to schedule your first automated report</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($subscriptions->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div>

    {{-- Recent Report Logs --}}
    @if($recentLogs->count() > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Recent Report History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Report</th>
                        <th class="px-4 py-2 text-left">Outlet</th>
                        <th class="px-4 py-2 text-left">Recipient</th>
                        <th class="px-4 py-2 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($recentLogs as $log)
                    <tr>
                        <td class="px-4 py-2 text-gray-600">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ ucwords(str_replace('_', ' ', $log->report_type)) }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $log->outlet?->name ?? 'All Outlets' }}</td>
                        <td class="px-4 py-2 text-gray-600 text-xs">{{ $log->recipient_email }}</td>
                        <td class="px-4 py-2 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $log->getStatusBadgeClass() }}">
                                {{ ucfirst($log->delivery_status) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Create/Edit Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10" @click.stop>
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">
                            {{ $editingId ? 'Edit Report Subscription' : 'New Report Subscription' }}
                        </h3>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        {{-- Report Type --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Report Type</label>
                            <select wire:model.live="report_type"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($this->getReportTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">
                                @if($report_type === 'daily_sales')
                                    Yesterday's sales, meal period breakdown, top items, AI analysis
                                @elseif($report_type === 'weekly_performance')
                                    Weekly summary, daily trends, best/worst days, AI insights
                                @else
                                    Monthly overview, weekly breakdown, trends, recommendations
                                @endif
                            </p>
                        </div>

                        {{-- Outlet --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Outlet</label>
                            <select wire:model="outlet_id"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Outlets (Combined)</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Frequency --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Frequency</label>
                            <select wire:model.live="frequency"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($this->getFrequencyOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Delivery Day (for weekly/monthly) --}}
                        @if($frequency === 'weekly')
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Day of Week</label>
                            <select wire:model="delivery_day"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($this->getDayOfWeekOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        @if($frequency === 'monthly')
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Day of Month</label>
                            <select wire:model="delivery_day"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @for($i = 1; $i <= 28; $i++)
                                    <option value="{{ $i }}">{{ ordinal($i) }}</option>
                                @endfor
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Reports sent on the selected day each month</p>
                        </div>
                        @endif

                        {{-- Delivery Time --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Delivery Time</label>
                            <input type="time" wire:model="delivery_time"
                                   class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p class="text-xs text-gray-400 mt-1">Report will be sent at this time (MYT)</p>
                        </div>

                        {{-- Options --}}
                        <div class="flex items-center gap-6 pt-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="include_ai_insights"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-600">Include AI insights</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="is_active"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-600">Active</span>
                            </label>
                        </div>

                        {{-- Recipient Emails --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Recipient Emails (Optional)</label>
                            <textarea wire:model="recipient_emails_input"
                                      rows="3"
                                      placeholder="Enter email addresses, one per line or separated by commas..."
                                      class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            <p class="text-xs text-gray-400 mt-1">
                                Leave empty to send to your email. Add multiple recipients separated by commas, semicolons, or new lines.
                            </p>
                        </div>
                    </div>
                    <div class="px-6 py-3 bg-gray-50 rounded-b-xl flex items-center justify-end gap-2">
                        <button wire:click="closeModal"
                                class="px-4 py-2 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-white transition">
                            Cancel
                        </button>
                        <button wire:click="save"
                                class="px-4 py-2 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                            {{ $editingId ? 'Update Subscription' : 'Create Subscription' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endteleport

    {{-- Test Report Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showTestModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeTestModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10" @click.stop>
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">Send Test Report</h3>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        {{-- Report Type --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Report Type</label>
                            <select wire:model="testReportType"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($this->getReportTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Outlet --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Outlet</label>
                            <select wire:model="testOutletId"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Outlets</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Recipient Email --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Send To</label>
                            <input type="email" wire:model="testEmail"
                                   class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>

                        {{-- Include AI --}}
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="testIncludeAi"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-600">Include AI insights</span>
                        </label>

                        {{-- Result --}}
                        @if($testResult)
                            <div class="p-3 rounded-lg text-sm {{ $testSuccess ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                {{ $testResult }}
                            </div>
                        @endif
                    </div>
                    <div class="px-6 py-3 bg-gray-50 rounded-b-xl flex items-center justify-end gap-2">
                        <button wire:click="closeTestModal"
                                class="px-4 py-2 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-white transition">
                            Close
                        </button>
                        <button wire:click="sendTestReport"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50"
                                class="px-4 py-2 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition flex items-center gap-2">
                            <span wire:loading wire:target="sendTestReport">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </span>
                            <span wire:loading.remove wire:target="sendTestReport">Send Test</span>
                            <span wire:loading wire:target="sendTestReport">Sending...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endteleport
</div>
