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
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="flash-err-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="max-w-2xl space-y-6">

        {{-- OpenRouter AI --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-5">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">OpenRouter AI</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Powers all AI features: Analytics, Invoice Extraction, Z-Report OCR, and Email Report Insights.</p>
                </div>
            </div>

            {{-- API Key --}}
            <div x-data="{ visible: false }" class="mb-4">
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
                    Get your key from <a href="https://openrouter.ai/keys" target="_blank" class="text-indigo-600 hover:underline">openrouter.ai/keys</a>.
                </p>
            </div>

            {{-- Model Selection --}}
            <div class="mb-4" x-data="{ custom: @js(!in_array($openrouter_model, ['', 'anthropic/claude-sonnet-4', 'anthropic/claude-haiku-3.5', 'google/gemini-2.5-flash-preview', 'google/gemini-2.5-pro-preview', 'openai/gpt-4.1', 'openai/gpt-4.1-mini', 'meta-llama/llama-4-maverick', 'deepseek/deepseek-r1'])) }">
                <x-input-label for="openrouter_model" value="Model" />
                <select x-show="!custom"
                        x-on:change="if ($event.target.value === '__custom__') { custom = true; $wire.set('openrouter_model', ''); $nextTick(() => $refs.customModel.focus()); }"
                        wire:model="openrouter_model"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Default (Claude Sonnet 4)</option>
                    <optgroup label="Anthropic">
                        <option value="anthropic/claude-sonnet-4">Claude Sonnet 4</option>
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
                           placeholder="e.g. anthropic/claude-opus-4"
                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <button type="button" @click="custom = false; $wire.set('openrouter_model', '')"
                            class="px-2 py-2 text-gray-400 hover:text-gray-600 transition" title="Back to list">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('openrouter_model')" class="mt-1" />
                <p class="mt-1.5 text-xs text-gray-400">
                    Browse all models at <a href="https://openrouter.ai/models" target="_blank" class="text-indigo-600 hover:underline">openrouter.ai/models</a>. Leave blank for default.
                </p>
            </div>

            <button wire:click="saveOpenRouter"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Save OpenRouter Settings
            </button>

            @if ($openrouter_key)
                <div class="mt-3 flex items-center gap-1.5 text-xs text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    API key is configured
                </div>
            @else
                <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    No API key set — AI features will not work
                </div>
            @endif
        </div>

        {{-- EngineMailer --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-5">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">EngineMailer</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Transactional email service for sending PO approval emails, scheduled reports, and notifications.</p>
                </div>
            </div>

            {{-- API Key --}}
            <div x-data="{ visible: false }" class="mb-4">
                <x-input-label for="em_key" value="API Key (UserKey)" />
                <div class="mt-1 flex items-center gap-2">
                    <div class="flex-1 relative">
                        <input id="em_key"
                               wire:model="enginemailer_key"
                               :type="visible ? 'text' : 'password'"
                               placeholder="Your EngineMailer API key…"
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
                <x-input-error :messages="$errors->get('enginemailer_key')" class="mt-1" />
                <p class="mt-2 text-xs text-gray-400">
                    Get your key from <span class="text-gray-500 font-medium">EngineMailer Portal &rarr; Account &rarr; API Keys</span>.
                </p>
            </div>

            {{-- Sender Email --}}
            <div class="mb-4">
                <x-input-label for="em_sender" value="Sender Email" />
                <x-text-input id="em_sender" wire:model="enginemailer_sender_email" type="email" class="mt-1 block w-full" placeholder="noreply@yourcompany.com" />
                <x-input-error :messages="$errors->get('enginemailer_sender_email')" class="mt-1" />
                <p class="mt-1 text-xs text-gray-400">The "From" email address. Must be a verified sender in EngineMailer.</p>
            </div>

            <div class="flex items-center gap-3">
                <button wire:click="saveEngineMailer"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Save EngineMailer Settings
                </button>
                @if ($enginemailer_key && $enginemailer_sender_email)
                    <button wire:click="testEngineMailer"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="testEngineMailer">Test Connection</span>
                        <span wire:loading wire:target="testEngineMailer">Sending test email…</span>
                    </button>
                @endif
            </div>

            @if ($testResult)
                <div class="mt-3 px-4 py-3 rounded-lg text-sm {{ $testSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' }}">
                    {{ $testResult }}
                </div>
            @endif

            @if ($enginemailer_key)
                <div class="mt-3 flex items-center gap-1.5 text-xs text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    API key is configured
                </div>
            @else
                <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    No API key set — Email notifications will not be sent
                </div>
            @endif
        </div>

    </div>
</div>
