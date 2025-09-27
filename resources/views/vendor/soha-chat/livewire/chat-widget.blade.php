@php($variableStyles = collect($themeVariables ?? [])->map(fn ($value, $variable) => sprintf('%s: %s', $variable, $value))->implode('; '))

    <div
        x-data="sohaChatWidget()"
        x-init="init({
            theme: @js($activeTheme),
            commands: @js($commands),
            show_reset: @js($showReset),
            show_theme_toggle: @js($showThemeToggle),
        })"
    @if($variableStyles)
        style="{{ $variableStyles }}"
    @endif
    class="fixed bottom-6 right-6 flex flex-col items-end gap-3 text-sm z-50"
>
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transform transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transform transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="w-96 max-w-full border border-slate-200/40 overflow-hidden flex flex-col h-[32rem]"
        style="background-color: var(--soha-bg); color: var(--soha-fg); border-radius: var(--soha-radius); box-shadow: var(--soha-shadow);"
    >
        <header class="px-4 py-3 border-b border-slate-200/60 flex items-center justify-between gap-3">
            <div class="font-semibold">SOHA Support</div>
            <div class="flex items-center gap-2 text-[color:var(--soha-fg)]">
                <template x-if="showThemeToggle">
                    <x-flux::button-or-link
                        type="button"
                        x-on:click="toggleTheme()"
                        class="px-2 py-1 rounded-md border flex items-center justify-center transition"
                        x-bind:class="themeButtonClasses"
                        x-bind:style="themeButtonStyle"
                        aria-label="Toggle theme"
                    >
                        <svg x-show="theme === 'system'" xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                        </svg>

                        <svg x-show="theme === 'light'" xmlns="http://www.w3.org/2000/svg" class="size-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" />
                            <path d="M12 2a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 16a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1Zm9-7a1 1 0 0 1-1 1h-1a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1Zm-16 0a1 1 0 0 1-1 1H3a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1Zm13.071 6.071a1 1 0 0 1-1.414 1.414l-.707-.707a1 1 0 0 1 1.414-1.414l.707.707Zm-11.314 0-.707.707A1 1 0 0 1 3.636 17.95l.707-.707a1 1 0 0 1 1.414 1.414Zm11.314-11.314a1 1 0 0 1-1.414-1.414l.707-.707a1 1 0 1 1 1.414 1.414l-.707.707ZM6.343 6.343a1 1 0 0 1-1.414-1.414l.707-.707A1 1 0 1 1 7.05 5.636l-.707.707Z" />
                        </svg>
                        <svg x-show="theme === 'dark'" xmlns="http://www.w3.org/2000/svg" class="size-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.077 2.252a1 1 0 0 1 1.222.707A8 8 0 1 0 21.04 11.7a1 1 0 0 1 1.36-1.228 10 10 0 1 1-11.109-8.32 1 1 0 0 1 .786.1Z" />
                        </svg>
                    </x-flux::button-or-link>
                </template>
                <template x-if="showReset">
                    <x-flux::button-or-link
                        type="button"
                        wire:click="resetConversation"
                        class="px-2 py-1 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50"
                        aria-label="Reset conversation"
                    >
                        Reset
                    </x-flux::button-or-link>
                </template>
                <x-flux::button-or-link
                    type="button"
                    x-on:click="closeChat"
                    class="px-2 py-1 rounded-lg border border-white/20 hover:bg-black/5"
                    aria-label="Close chat"
                >
                    ✕
                </x-flux::button-or-link>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-4 py-3 space-y-4" x-ref="scrollArea">
            @forelse ($messages as $message)
                <div class="flex flex-col gap-1" wire:key="message-{{ $loop->index }}">
                    <span class="text-xs uppercase tracking-wide text-slate-400">{{ $message['role'] }}</span>
                    <div class="rounded-lg px-3 py-2 whitespace-pre-wrap" style="background-color: color-mix(in srgb, var(--soha-bg) 85%, #0f172a 15%); color: var(--soha-fg);">{{ $message['content'] }}</div>
                </div>
            @empty
                <div class="text-center text-slate-400">Start a conversation with SOHA</div>
            @endforelse

            <template x-if="streaming">
                <div class="flex flex-col gap-1">
                    <span class="text-xs uppercase tracking-wide text-slate-400">assistant</span>
                    <div class="rounded-lg px-3 py-2 whitespace-pre-wrap" style="background-color: color-mix(in srgb, var(--soha-bg) 85%, #0f172a 15%); color: var(--soha-fg);" x-text="streamingMessage"></div>
                </div>
            </template>
        </div>

        <form wire:submit="send" class="p-4 border-t border-slate-200/60 space-y-3">
            <label class="sr-only" for="soha-chat-prompt">Prompt</label>
            <x-flux::textarea
                id="soha-chat-prompt"
                wire:model.defer="input"
                x-on:keydown.enter.prevent="maybeSubmit($event)"
                rows="3"
                class="!border-black/20 !bg-[color-mix(in_srgb,var(--soha-bg)_95%,_#ffffff_5%)] !text-[color:var(--soha-fg)] !resize-none"
                placeholder="Ask SOHA a question..."
                x-ref="prompt"
            />

            <div class="flex items-center justify-between text-xs text-slate-400">
                <x-flux::button-or-link
                    type="button"
                    x-on:click="openCommandPalette"
                    class="flex items-center gap-2 text-slate-500 hover:text-slate-800"
                >
                    <span>/</span>
                    <span>Commands</span>
                </x-flux::button-or-link>

                <x-flux::button-or-link
                    type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white disabled:opacity-50"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70"
                    wire:target="send"
                    style="background-color: var(--soha-accent); border-radius: var(--soha-radius); box-shadow: var(--soha-shadow);"
                >
                    <span wire:loading.remove wire:target="send">Send</span>
                    <span wire:loading wire:target="send">Sending…</span>
                </x-flux::button-or-link>
            </div>
        </form>
    </div>

    <div
        x-show="commandPalette"
        x-cloak
        class="absolute bottom-full right-0 mb-3 w-72 rounded-xl border border-slate-200/60 shadow-xl"
        style="background-color: color-mix(in srgb, var(--soha-bg) 95%, #111827 5%); color: var(--soha-fg); box-shadow: var(--soha-shadow);"
    >
        <div class="px-3 py-2 border-b border-slate-200/60 text-xs uppercase text-slate-400">
            Slash Commands
        </div>
        <ul class="max-h-60 overflow-y-auto py-2">
            <template x-for="command in commands" :key="command.name">
                <li>
                    <x-flux::button-or-link
                        type="button"
                        class="w-full text-left px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800"
                        x-on:click="applyCommand(command)"
                    >
                        <span class="font-medium text-slate-700 dark:text-slate-200" x-text="'/' + command.name"></span>
                        <p class="text-slate-400" x-text="command.description"></p>
                    </x-flux::button-or-link>
                </li>
            </template>
        </ul>
    </div>

    <x-flux::button-or-link
        type="button"
        x-show="!open"
        x-cloak
        x-transition:enter="transform transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transform transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        x-on:click="openChat"
        class="inline-flex items-center gap-2 rounded-full border px-4 py-3 shadow-lg hover:bg-black/5"
        style="background-color: color-mix(in srgb, var(--soha-bg) 95%, #ffffff 5%); color: var(--soha-fg); border-color: rgba(255,255,255,0.2); box-shadow: var(--soha-shadow);"
    >
        <span class="inline-flex size-3 rounded-full" style="background-color: var(--soha-accent);"></span>
        <span class="font-semibold">Chat with SOHA</span>
    </x-flux::button-or-link>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('sohaChatWidget', () => ({
                theme: 'system',
                commands: [],
                open: false,
                streaming: false,
                streamingMessage: '',
                commandPalette: false,
                systemThemeHandler: null,
                livewire: null,
                livewireEventsRegistered: false,
                showReset: true,
                showThemeToggle: true,

                init(payload = {}) {
                    this.resolveLivewireInstance()

                    const persistedTheme = window.localStorage.getItem('soha-chat.theme')
                    const persistedOpen = window.localStorage.getItem('soha-chat.open')
                    this.showReset = payload.show_reset ?? true
                    this.showThemeToggle = payload.show_theme_toggle ?? true

                    this.theme = persistedTheme || payload.theme || 'system'
                    this.commands = payload.commands || []
                    this.open = persistedOpen === null
                        ? (payload.open ?? false)
                        : persistedOpen === 'true'

                    this.applyTheme()

                    this.$watch('theme', () => this.applyTheme())
                    this.$watch('open', (value) => {
                        window.localStorage.setItem('soha-chat.open', value ? 'true' : 'false')

                        if (value) {
                            this.commandPalette = false
                            queueMicrotask(() => {
                                this.$refs.prompt?.focus()
                            })
                        }
                    })

                    window.addEventListener('keydown', (event) => {
                        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                            event.preventDefault()
                            if (! this.open) {
                                this.openChat()
                            }

                            this.commandPalette = ! this.commandPalette
                        }

                        if (event.key === 'Escape') {
                            if (this.commandPalette) {
                                this.commandPalette = false

                                return
                            }

                            if (this.open) {
                                event.preventDefault()
                                this.closeChat()
                            }
                        }
                    })

                    this.registerLivewireEvents()
                },

                initTheme(theme) {
                    this.theme = theme
                },

                applyTheme() {
                    const root = document.documentElement
                    const media = window.matchMedia('(prefers-color-scheme: dark)')

                    const applyScheme = (isDark) => {
                        root.classList.toggle('dark', isDark)
                    }

                    if (this.systemThemeHandler) {
                        media.removeEventListener('change', this.systemThemeHandler)
                        this.systemThemeHandler = null
                    }

                    if (this.theme === 'dark') {
                        applyScheme(true)
                    } else if (this.theme === 'light') {
                        applyScheme(false)
                    } else {
                        applyScheme(media.matches)
                        this.systemThemeHandler = (event) => {
                            if (this.theme === 'system') {
                                applyScheme(event.matches)
                            }
                        }
                        media.addEventListener('change', this.systemThemeHandler)
                    }
                },

                toggleTheme() {
                    const modes = ['system', 'light', 'dark']
                    const index = modes.indexOf(this.theme)
                    this.theme = modes[(index + 1) % modes.length]
                    window.localStorage.setItem('soha-chat.theme', this.theme)
                },

                get themeButtonClasses() {
                    if (this.theme === 'dark') {
                        return 'shadow-inner'
                    }

                    if (this.theme === 'light') {
                        return 'shadow-sm'
                    }

                    return 'shadow'
                },

                get themeButtonStyle() {
                    if (this.theme === 'dark') {
                        return 'background-color: rgba(15,23,42,0.65); color: #f8fafc; border-color: rgba(248,250,252,0.35);'
                    }

                    if (this.theme === 'light') {
                        return 'background-color: rgba(255,255,255,0.9); color: #0f172a; border-color: rgba(15,23,42,0.12);'
                    }

                    return 'background-color: color-mix(in srgb, var(--soha-accent) 22%, transparent); color: var(--soha-fg); border-color: color-mix(in srgb, var(--soha-accent) 38%, transparent);'
                },

                openCommandPalette() {
                    if (! this.open) {
                        this.openChat()
                    }

                    this.commandPalette = true
                },

                applyCommand(command) {
                    this.commandPalette = false

                    if (! command?.name) {
                        return
                    }

                    this.$wire?.executeCommand(command.name)
                },

                maybeSubmit(event) {
                    if (! event.shiftKey) {
                        if (! this.open) {
                            this.openChat()

                            return
                        }

                        this.$wire?.send()
                    }
                },

                openChat() {
                    this.open = true
                },

                closeChat() {
                    this.commandPalette = false
                    this.open = false
                },

                toggleOpen() {
                    this.open = ! this.open
                },

                resolveLivewireInstance() {
                    if (! window.Livewire) {
                        document.addEventListener('livewire:load', () => this.resolveLivewireInstance(), { once: true })

                        return
                    }

                    const host = this.$el.closest('[wire\\:id]')

                    if (! host || ! window.Livewire) {
                        return
                    }

                    const componentId = host.getAttribute('wire:id')

                    this.livewire = window.Livewire.find(componentId)
                },

                registerLivewireEvents() {
                    if (this.livewireEventsRegistered) {
                        return
                    }

                    if (! window.Livewire) {
                        document.addEventListener('livewire:load', () => this.registerLivewireEvents(), { once: true })

                        return
                    }

                    Livewire.on('scroll-to-latest', () => {
                        queueMicrotask(() => {
                            this.$refs.scrollArea?.lastElementChild?.scrollIntoView({ behavior: 'smooth' })
                        })
                    })

                    Livewire.on('streaming-chunk', ({ content }) => {
                        if (! this.open) {
                            this.openChat()
                        }

                        this.streaming = true
                        this.streamingMessage = content
                        queueMicrotask(() => {
                            this.$refs.scrollArea?.lastElementChild?.scrollIntoView({ behavior: 'smooth' })
                        })
                    })

                    Livewire.on('streaming-complete', () => {
                        this.streaming = false
                        this.streamingMessage = ''
                    })

                    this.livewireEventsRegistered = true
                },
            }))
    })
</script>
