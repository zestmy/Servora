<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / API Keys</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-2xl space-y-6">

        {{-- Google Vision AI --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-5">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Google Vision AI</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Used for Z-Report OCR — extracts dept breakdown and session totals from uploaded Z-report images.</p>
                </div>
            </div>

            <div x-data="{ visible: false }">
                <x-input-label for="gv_key" value="API Key" />
                <div class="mt-1 flex items-center gap-2">
                    <div class="flex-1 relative">
                        <input id="gv_key"
                               wire:model="google_vision_key"
                               :type="visible ? 'text' : 'password'"
                               placeholder="AIzaSy…"
                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 pr-10"
                               autocomplete="off" />
                        <button type="button" @click="visible = !visible"
                                class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                            <svg x-show="!visible" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="visible" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <button wire:click="saveVision"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition flex-shrink-0">
                        Save
                    </button>
                </div>
                <x-input-error :messages="$errors->get('google_vision_key')" class="mt-1" />
                <p class="mt-2 text-xs text-gray-400">
                    Get your key from
                    <span class="text-gray-500 font-medium">Google Cloud Console → APIs &amp; Services → Credentials</span>.
                    Enable the <span class="font-medium">Cloud Vision API</span> on your project.
                </p>
            </div>

            @if ($google_vision_key)
                <div class="mt-3 flex items-center gap-1.5 text-xs text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    API key is configured
                </div>
            @else
                <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    No API key set — Z-Report import will not work
                </div>
            @endif
        </div>

        {{-- AI Analytics Provider --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-5">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">AI Analytics</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Powers the AI Analytics module — generates operational insights, trend analysis, and cost optimization reports.</p>
                </div>
            </div>

            {{-- Provider Selector --}}
            <div class="mb-5">
                <x-input-label value="AI Provider" />
                <div class="mt-2 flex gap-3">
                    <label class="flex-1 relative cursor-pointer">
                        <input type="radio" wire:model.live="ai_provider" value="anthropic" class="peer sr-only" />
                        <div class="border-2 rounded-xl p-3 text-center transition
                                    peer-checked:border-purple-500 peer-checked:bg-purple-50
                                    border-gray-200 hover:border-gray-300">
                            <p class="text-sm font-semibold text-gray-800">Anthropic Direct</p>
                            <p class="text-xs text-gray-400 mt-0.5">Direct API access</p>
                        </div>
                    </label>
                    <label class="flex-1 relative cursor-pointer">
                        <input type="radio" wire:model.live="ai_provider" value="openrouter" class="peer sr-only" />
                        <div class="border-2 rounded-xl p-3 text-center transition
                                    peer-checked:border-emerald-500 peer-checked:bg-emerald-50
                                    border-gray-200 hover:border-gray-300">
                            <p class="text-sm font-semibold text-gray-800">OpenRouter</p>
                            <p class="text-xs text-gray-400 mt-0.5">Multi-model gateway</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Anthropic API Key --}}
            <div class="mb-5 pb-5 border-b border-gray-100 {{ $ai_provider !== 'anthropic' ? 'opacity-50' : '' }}">
                <div class="flex items-center gap-2 mb-2">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Anthropic API Key</h4>
                    @if ($ai_provider === 'anthropic')
                        <span class="px-1.5 py-0.5 bg-purple-100 text-purple-700 text-[10px] font-bold uppercase rounded">Active</span>
                    @endif
                </div>
                <div x-data="{ visible: false }">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 relative">
                            <input id="anthropic_key"
                                   wire:model="anthropic_key"
                                   :type="visible ? 'text' : 'password'"
                                   placeholder="sk-ant-…"
                                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 pr-10"
                                   autocomplete="off" />
                            <button type="button" @click="visible = !visible"
                                    class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                                <svg x-show="!visible" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="visible" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <button wire:click="saveAnthropic"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition flex-shrink-0">
                            Save
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('anthropic_key')" class="mt-1" />
                    <p class="mt-2 text-xs text-gray-400">
                        Get your key from
                        <span class="text-gray-500 font-medium">console.anthropic.com &rarr; API Keys</span>.
                    </p>
                </div>
                @if ($anthropic_key)
                    <div class="mt-2 flex items-center gap-1.5 text-xs text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Key configured
                    </div>
                @endif
            </div>

            {{-- OpenRouter Settings --}}
            <div class="{{ $ai_provider !== 'openrouter' ? 'opacity-50' : '' }}">
                <div class="flex items-center gap-2 mb-2">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">OpenRouter</h4>
                    @if ($ai_provider === 'openrouter')
                        <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase rounded">Active</span>
                    @endif
                </div>

                {{-- API Key --}}
                <div x-data="{ visible: false }" class="mb-3">
                    <x-input-label for="openrouter_key" value="API Key" />
                    <div class="mt-1 flex items-center gap-2">
                        <div class="flex-1 relative">
                            <input id="openrouter_key"
                                   wire:model="openrouter_key"
                                   :type="visible ? 'text' : 'password'"
                                   placeholder="sk-or-…"
                                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 pr-10"
                                   autocomplete="off" />
                            <button type="button" @click="visible = !visible"
                                    class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                                <svg x-show="!visible" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="visible" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('openrouter_key')" class="mt-1" />
                    <p class="mt-1.5 text-xs text-gray-400">
                        Get your key from <span class="text-gray-500 font-medium">openrouter.ai &rarr; Keys</span>.
                    </p>
                    @if ($openrouter_key)
                        <div class="mt-1.5 flex items-center gap-1.5 text-xs text-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Key configured
                        </div>
                    @endif
                </div>

                {{-- Model Selection --}}
                <div class="mb-3" x-data="{ custom: @js(!in_array($openrouter_model, ['', 'anthropic/claude-sonnet-4-5-20250514', 'anthropic/claude-haiku-3.5', 'google/gemini-2.5-flash-preview', 'google/gemini-2.5-pro-preview', 'openai/gpt-4.1', 'openai/gpt-4.1-mini', 'meta-llama/llama-4-maverick', 'deepseek/deepseek-r1'])) }">
                    <x-input-label for="openrouter_model" value="Model" />
                    <select x-show="!custom"
                            x-on:change="if ($event.target.value === '__custom__') { custom = true; $wire.set('openrouter_model', ''); $nextTick(() => $refs.customModel.focus()); }"
                            wire:model="openrouter_model"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Default (Claude Sonnet 4.5)</option>
                        <optgroup label="Anthropic">
                            <option value="anthropic/claude-sonnet-4-5-20250514">Claude Sonnet 4.5</option>
                            <option value="anthropic/claude-haiku-3.5">Claude Haiku 3.5</option>
                        </optgroup>
                        <optgroup label="Google">
                            <option value="google/gemini-2.5-flash-preview">Gemini 2.5 Flash</option>
                            <option value="google/gemini-2.5-pro-preview">Gemini 2.5 Pro</option>
                        </optgroup>
                        <optgroup label="OpenAI">
                            <option value="openai/gpt-4.1">GPT-4.1</option>
                            <option value="openai/gpt-4.1-mini">GPT-4.1 Mini</option>
                        </optgroup>
                        <optgroup label="Open Source">
                            <option value="meta-llama/llama-4-maverick">Llama 4 Maverick</option>
                            <option value="deepseek/deepseek-r1">DeepSeek R1</option>
                        </optgroup>
                        <optgroup label="Other">
                            <option value="__custom__">Enter custom model ID...</option>
                        </optgroup>
                    </select>
                    <div x-show="custom" class="mt-1 flex items-center gap-2">
                        <input x-ref="customModel"
                               wire:model="openrouter_model"
                               type="text"
                               placeholder="e.g. anthropic/claude-opus-4-0-20250514"
                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <button type="button" @click="custom = false; $wire.set('openrouter_model', '')"
                                class="px-2 py-2 text-gray-400 hover:text-gray-600 transition" title="Back to list">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('openrouter_model')" class="mt-1" />
                    <p class="mt-1.5 text-xs text-gray-400">
                        Browse all models at <span class="text-gray-500 font-medium">openrouter.ai/models</span>. Leave blank for default.
                    </p>
                </div>

                {{-- Save --}}
                <button wire:click="saveOpenRouter"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Save OpenRouter Settings
                </button>
            </div>

            {{-- Overall status --}}
            @php
                $activeKey = $ai_provider === 'openrouter' ? $openrouter_key : $anthropic_key;
            @endphp
            @unless ($activeKey)
                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-1.5 text-xs text-amber-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    No API key set for the active provider — AI Analytics will not work
                </div>
            @endunless
        </div>

    </div>
</div>
