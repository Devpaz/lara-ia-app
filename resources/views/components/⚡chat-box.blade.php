
<?php

use Livewire\Component;
use App\Ai\Agents\ChatAgent;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Crypt;

new class extends Component {
    use WithFileUploads;

    // State
    public string $input = '';
    public bool $loading = false;
    public array $messages = [];

    public ?string $conversationId = null;
    public array $initialMessages = [];

    public $attachment = null;

    public function mount(?string $conversationId = null, array $initialMessages = []): void
    {
        $this->conversationId = $conversationId;
        $this->initialMessages = $initialMessages;
        $this->messages = $initialMessages;
    }

    // Send message functionality
    public function send(): void
    {
        $this->validate([
            'input' => ['required_without:attachment', 'nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);

        $userMessage = $this->input;
        $this->input = '';
        $this->loading = true;

        // Keep attachments on the S3 disk so conversation history can read them again.
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentToken = null;

        if ($this->attachment) {
            $attachmentPath = $this->attachment->store('chat-attachments', 's3');
            $attachmentMime = $this->attachment->getMimeType();
            $attachmentName = $this->attachment->getClientOriginalName();
            $attachmentToken = Crypt::encrypt([
                'path' => $attachmentPath,
                'mime' => $attachmentMime,
                'user_id' => auth()->id(),
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);
            $this->attachment = null;
        }

        // User Prompt
        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage ?: $attachmentName,
            'attachment_path' => $attachmentPath,
            'attachment_mime' => $attachmentMime,
            'attachment_name' => $attachmentName,
            'attachment_disk' => $attachmentPath ? 's3' : null,
        ];

        try {
            // $response = new ChatAgent()->prompt($userMessage);

            // AI Response
            $this->messages[] = [
                'role' => 'assistant',
                'content' => '',
                'streaming' => true,
            ];

            // Dispatch browser event to start streaming
            $this->dispatch('start-stream', message: $userMessage, attachmentToken: $attachmentToken);
        } catch (Throwable $e) {
            report($e);

            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Sorrry, something went wrong. Please try again!',
            ];
        }
    }

    // Clear chat
    #[On('new-conversation')]
    public function clearChat(): void
    {
        $this->messages = [];
        $this->input = '';
        $this->loading = false;

        ## Dispatch event to clear conversation id
        $this->dispatch('conversation-reset');
    }

    #[On('stream-complete')]
    public function onStreamComplete(string $content, array $usage = []): void
    {
        $lastIndex = array_key_last($this->messages);

        $this->messages[$lastIndex] = [
            'role' => 'assistant',
            'content' => $content,
            'streaming' => false,
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
        ];
        $this->loading = false;
    }

    #[On('stream-error')]
    public function onStreamError()
    {
        $lastIndex = array_key_last($this->messages);

        $this->messages[$lastIndex] = [
            'role' => 'assistant',
            'content' => 'Sorry, something went wrong. Please try again!',
            'streaming' => false,
        ];

        $this->loading = false;
    }
};
?>

<div class="flex-1 flex flex-col min-w-0" x-data="chatStream">

    {{-- Header --}}

    <header class="px-6 py-4 border-b border-zinc-800 bg-zinc-900/50 flex items-center justify-between flex-shrink-0">

        <div>
            <h1 class="text-sm font-semibold text-white">New Conversation</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Ask me anything</p>
        </div>

        {{-- Clear button --}}
        <button wire:click="clearChat"
            class="text-xs text-zinc-500 hover:text-zinc-300 px-3 py-1.5 rounded-md hover:bg-zinc-800 transition-colors cursor-pointer">Clear</button>
    </header>

    <div class="flex-1 overflow-y-auto px-4 py-6 space-y-6 scroll-smooth" x-ref="messagesContainer" id="messages">
        {{-- Welcome screen --}}
        @if (count($messages) === 0)
            <div class="flex flex-col items-center justify-center h-full text-center py-20">

                <div
                    class="w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center text-2xl font-bold text-white shadow-2xl shadow-violet-900/40 mb-5">
                    LC
                </div>

                <h2 class="text-xl font-semibold text-white mb-2">Welcome to LaraChat</h2>
                <p class="text-zinc-400 text-sm max-w-sm leading-relaxed mb-7">AI assistant powered by Google Gemini &
                    Laravel 13
                    AI SDK</p>

                {{-- Suggestion chips --}}
                <div class="flex flex-wrap gap-2 justify-center max-w-sm">
                    @foreach (['What is Laravel 13?', 'Explain Livewire 4', 'Write a PHP function', 'Tell me a fun fact'] as $suggestion)
                        <button wire:click="$set('input', '{{ $suggestion }}')"
                            class="cursor-pointer px-3 py-1.5 rounded-full bg-zinc-800 border border-zinc-700 text-xs text-zinc-300 hover:border-violet-500 hover:text-white transition-all duration-200">{{ $suggestion }}</button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Message lists --}}
        @foreach ($messages as $index => $message)
            {{-- User Prompt Message --}}
            @if ($message['role'] === 'user')
                <div class="flex justify-end">
                    <div class="max-w-md lg:max-w-xl">

                        @if (!empty($message['attachment_path'] ?? null))
                            @php
                                $isImage = str_starts_with($message['attachment_mime'] ?? '', 'image/');
                                $attachmentUrl = \Illuminate\Support\Facades\Storage::disk($message['attachment_disk'] ?? 'local')->temporaryUrl(
                                    $message['attachment_path'],
                                    now()->addMinutes(30),
                                );
                            @endphp

                            @if ($isImage)
                                <a href="{{ $attachmentUrl }}" target="_blank" class="block">
                                    <img src="{{ $attachmentUrl }}" class="rounded-xl max-h-48 border border-zinc-700"
                                        alt="{{ $message['attachment_name'] }}" />

                                </a>
                            @else
                                <a href="{{ $attachmentUrl }}" target="_blank"
                                    class="flex items-center gap-2 bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2 text-xs text-zinc-200 hover:border-violet-500 transition-colors">
                                    <svg class="w-4 h-4 text-violet-400 flex-shrink-0" fill="none"
                                        stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>

                                    <span>{{ Str::limit($message['attachment_name'] ?? 'Document', 24) }}</span>
                                </a>
                            @endif
                        @endif

                        @if (filled($message['content']))
                            <div
                                class="bg-violet-600 text-white px-4 py-3 rounded-2xl rounded-tr-sm text-sm leading-relaxed">
                                {{ $message['content'] }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- AI Response Messages --}}
            @else
                <div class="flex items-start gap-3">

                    {{-- AI Avatar --}}
                    <div
                        class="w-7 h-7 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 mt-0.5">
                        AI
                    </div>

                    {{-- If loading is there then this will rende this text --}}
                    <div
                        class="bg-zinc-800 border border-zinc-700/50 px-4 py-3 rounded-2xl rounded-tl-sm text-sm text-zinc-100 leading-relaxed">
                        @if ($message['streaming'] ?? false)
                            {{-- Streaming Indicator --}}
                            <template x-if="!hasStartedStreaming">
                                <span class="text-zinc-400">LaraChat is thinking... </span>
                            </template>

                            <span x-show="hasStartedStreaming" x-html="renderMarkdown(streamingText)"
                                class="markdown-body block"></span>
                        @else
                            {{-- Messages --}}
                            <div class="max-w-md lg:max-w-2xl">
                                <span class="markdown-body"
                                    x-html="renderMarkdown(@js($message['content']))"></span>
                            </div>
                        @endif

                        @if (!($message['streaming'] ?? false) && (($message['input_tokens'] ?? 0) > 0 || ($message['output_tokens'] ?? 0) > 0))
                            <div class="mt-2 border-t border-zinc-700/60 pt-2 text-[10px] text-zinc-500">
                                <span class="text-sky-400">{{ number_format($message['input_tokens']) }}</span> input
                                <span class="px-1">·</span>
                                <span class="text-violet-400">{{ number_format($message['output_tokens']) }}</span> output
                                <span class="px-1">·</span>
                                {{ number_format($message['input_tokens'] + $message['output_tokens']) }} total
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach

    </div>

    {{-- Input text --}}
    <div class="flex-shrink-0 px-4 pb-5 pt-3 border-t border-zinc-800 bg-zinc-900/30">
        <div class="max-w-3xl mx-auto">

            <div class="flex items-center gap-2 bg-zinc-800 border border-zinc-700 rounded-2xl px-4 py-3 focus-within:border-violet-500 transition-colors duration-200"
                :class="{ 'opacity-60 pointer-events-none': $wire.loading }">

                {{-- Textarea --}}
                <textarea :disabled="$wire.loading" wire:model="input" wire:keydown.enter.prevent="send" rows="1"
                    placeholder="Message Lara AI Chat Agent..."
                    class="flex-1 bg-transparent text-sm text-zinc-100 placeholder-zinc-500 resize-none outline-none leading-relaxed"
                    style="min-height: 28px; height: 28px;"
                    x-on:input="$el.style.height='auto'; $el.style.height=Math.min($el.scrollHeight, 144) + 'px' "></textarea>


                {{-- File attachment --}}
                <input type="file" wire:model="attachment" id="chat-attachment" class="hidden"
                    accept=".pdf,.jpg,.jpeg,.png" />

                {{-- File icon --}}
                <label for="chat-attachment"
                    class="cursor-pointer text-zinc-400 hover:text-white flex-shrink-0 w-8 h-8 rounded-xl hover:bg-zinc-700 flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4 text-zinc-400 hover:text-white" fill="none" stroke="currentColor"
                        stroke-width="2" viewBox="0 0 22 22">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                    </svg>
                </label>

                {{-- Preview --}}
                @if ($attachment)
                    <div wire:loading.remove wire:target="attachment"
                        class="flex items-center gap-2 bg-zinc-700/60 rounded-lg pl-1.5 pr-2 py-1 flex-shrink-0">

                        @if (str_starts_with($attachment->getMimeType(), 'image/'))
                            <img src="{{ $attachment->temporaryUrl() }}" class="w-8 h-8 rounded object-cover"
                                alt="Image Attachment" />
                        @else
                            <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        @endif
                        <span
                            class="text-xs text-zinc-400 flex items-center gap-1 flex-shrink-0">{{ Str::limit($attachment->getClientOriginalName(), 20) }}
                        </span>
                        <button class="text-zinc-400 hover:text-white" type="button"
                            wire:click="$set('attachment', null)">X</button>

                    </div>
                @endif

                {{-- File uploading indicator --}}
                <div wire:loading wire:target="attachment" class="tex-xs text-zinc-500 flex-shrink-0 px-1">Uploading...
                </div>

                {{-- Send button --}}
                <button wire:click="send"
                    :disabled="$wire.loading || (!$wire.input.trim() && {{ $attachment ? 'false' : 'true' }})"
                    class="cursor-pointer flex-shrink-0 w-8 h-8 rounded-xl bg-violet-600 hover:bg-violet-500 disabled:bg-zinc-700 disabled-opacity-50 disabled:cursor-not-allowed flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                </button>
            </div>

            <p class="text-center text-xs text-zinc-600 mt-2">LaraChat can make mistakes. Always verify important info.
            </p>
        </div>
    </div>
</div>

<style>
    .markdown-body h1,
    .markdown-body h2,
    .markdown-body h3 {
        font-weight: 600;
        margin: 0.75rem 0 0.5rem;
        color: #fff;
    }

    .markdown-body p {
        margin: 0.5rem 0;
    }

    .markdown-body ul,
    .markdown-body ol {
        margin: 0.5rem 0;
        padding-left: 1.25rem;
    }

    .markdown-body ul {
        list-style-type: disc;
    }

    .markdown-body ol {
        list-style-type: decimal;
    }

    .markdown-body strong {
        font-weight: 600;
        color: #fff;
    }

    .markdown-body code {
        background: rgb(39 39 42);
        padding: 0.1rem 0.3rem;
        border-radius: 0.25rem;
        font-size: 0.85rem;
    }

    .markdown-body .code-block pre {
        margin: 0;
        padding: 0;
        background: #09090b;
        overflow-x: auto;
    }

    .markdown-body .code-block pre code {
        background: transparent;
        padding: 1rem;
        border-radius: 0;
        display: block;
        line-height: 1.6;
        white-space: pre;
        font-size: 0.875rem;
    }
</style>

@script
    <script>
        function escapeHtml(text) {
            return text.replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        const renderer = new window.marked.Renderer();
        renderer.code = function(token) {
            const code = (token.text || '').replace(/^\n+|\n+$/g, '');
            const language = (token.lang || 'plaintext').trim();

            return `<div class="code-block my-3 rounded-xl overflow-hidden border border-zinc-700">
                <div class="flex items-center justify-between px-4 py-2 bg-zinc-800 border-b border-zinc-700">
                    <span class="text-xs text-zinc-400 font-mono">${language}</span>

                    <button type="button" data-copy-code class="text-xs text-zinc-400 hover:text-white px-2 py-1 rounded hover:bg-zinc-700 transition-colors">Copy</button>
                    </div>

                <pre class="!m-0 !rounded-none bg-zinc-900 overflow-x-auto"><code class="hljs language-${language} block p-4 text-sm text-zinc-100 font-mono leading-relaxed">${escapeHtml(code)}</code></pre></div>`;
        };


        window.marked.use({
            renderer,
            breaks: true,
            gfm: true,
        });

        // Markdown
        window.renderMarkdown = function(text) {
            const html = DOMPurify.sanitize(window.marked.parse(text || ''));

            requestAnimationFrame(() => {
                document.querySelectorAll('.code-block pre code').forEach((block) => {
                    if (!window.hljs || block.dataset.highlighted) {
                        return;
                    }

                    window.hljs.highlightElement(block);
                });
            });

            return html;
        };

        // Copy Button Click
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy-code]');

            if (!button) {
                return;
            }

            const code = button.closest('.code-block')?.querySelector('code')?.innerText || '';

            await navigator.clipboard.writeText(code);
            button.innerText = 'Copied';

            setTimeout(() => {
                button.innerText = 'Copy';
            }, 2000);
        });

        Alpine.data('chatStream', () => ({
            streamingText: '',
            hasStartedStreaming: false,
            tokenUsage: null,
            conversationId: @js($conversationId),

            init() {
                this.$wire.on('start-stream', ({
                    message,
                    attachmentToken
                }) => {
                    this.startStream(message, attachmentToken);
                });

                // Listening event
                this.$wire.on('conversation-reset', () => {
                    this.conversationId = null;
                    this.streamingText = '';
                    this.hasStartedStreaming = false;
                    this.tokenUsage = null;
                });

                // Scroll to bottom
                this.scrollToBottom();

                setTimeout(() => {
                    this.scrollToBottom();
                }, 100);
            },

            // Function start streaming
            async startStream(message, attachmentToken = null) {
                this.streamingText = '';
                this.hasStartedStreaming = false;
                this.tokenUsage = null;

                try {
                    const response = await fetch('{{ route('chat.stream') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'text/event-stream',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .content,
                        },
                        body: JSON.stringify({
                            message: message,
                            conversation_id: this.conversationId,
                            attachment_token: attachmentToken,
                        }),
                    });

                    if (!response.ok) {
                        throw new Error(`Stream request failed: ${response.status}`);
                    }

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();

                    let buffer = '';

                    while (true) {
                        const {
                            done,
                            value
                        } = await reader.read();

                        if (done) break;

                        buffer += decoder.decode(value, {
                            stream: true,
                        });

                        const lines = buffer.split('\n');
                        buffer = lines.pop();

                        for (const line of lines) {

                            if (!line.startsWith('data: ')) {
                                continue;
                            }

                            const data = line.slice(6).trim();

                            if (data === '[DONE]') {
                                this.$wire.dispatch('stream-complete', {
                                    content: this.streamingText,
                                    usage: this.tokenUsage ?? {},
                                });

                                Livewire.dispatch('usage-updated');

                                return;
                            }

                            try {

                                const parsed = JSON.parse(data);

                                if (parsed.content) {
                                    this.hasStartedStreaming = true;
                                    this.streamingText += parsed.content;

                                    // Auto scroll to latest message/response
                                    this.scrollToBottom();
                                }

                                // Capture conversation id
                                if (parsed.conversation_id) {
                                    this.conversationId = parsed.conversation_id;

                                    // Dispatch event to refresh the sidebar
                                    Livewire.dispatch('conversation-saved', {
                                        conversationId: this.conversationId
                                    });

                                    console.log('Stored conversation id: ', this.conversationId);
                                }

                                if (parsed.usage) {
                                    this.tokenUsage = parsed.usage;
                                }

                                if (parsed.error) {
                                    this.$wire.dispatch('stream-error');
                                    return;
                                }

                            } catch (error) {
                                console.error('Invalid stream data: ', error);
                            }
                        }
                    }

                    this.$wire.dispatch('stream-complete', {
                        content: this.streamingText,
                        usage: this.tokenUsage ?? {},
                    });

                    Livewire.dispatch('usage-updated');

                } catch (error) {
                    console.error(error);
                    this.$wire.dispatch('stream-error');
                }
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const container = this.$refs.messagesContainer;
                    container.scrollTop = container.scrollHeight;

                    requestAnimationFrame(() => {
                        container.scrollTop = container.scrollHeight;
                    });
                });
            },
        }));
    </script>
@endscript
