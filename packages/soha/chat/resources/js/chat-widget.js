const CHAT_STORAGE_KEY = 'chat-agent-history';
const MESSAGE_ENDPOINT = '/chat-agent/message';
const HISTORY_ENDPOINT = '/chat-agent/history';

const loadHistory = () => {
    try {
        const cached = sessionStorage.getItem(CHAT_STORAGE_KEY);
        return cached ? JSON.parse(cached) : [];
    } catch (error) {
        console.warn('Unable to load cached chat history', error);
        return [];
    }
};

const saveHistory = (history) => {
    try {
        sessionStorage.setItem(CHAT_STORAGE_KEY, JSON.stringify(history));
    } catch (error) {
        console.warn('Unable to persist chat history', error);
    }
};

const escapeHtml = (value) =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

const renderToolDataset = (toolOutput) => {
    if (!toolOutput) {
        return '';
    }

    if (Array.isArray(toolOutput.data?.rows)) {
        const rows = toolOutput.data.rows.slice(0, 5);

        if (!rows.length) {
            return 'No rows returned.';
        }

        const headers = Object.keys(rows[0]);
        const tableHeader = headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('');
        const body = rows
            .map((row) =>
                `<tr>${headers
                    .map((header) => `<td>${escapeHtml(row[header])}</td>`)
                    .join('')}</tr>`
            )
            .join('');

        return `
            <div class="chat-agent-tool">
                <div class="chat-agent-tool__heading">Query Result &middot; ${escapeHtml(toolOutput.data.row_count ?? rows.length)} rows</div>
                <div class="chat-agent-tool__sql">${escapeHtml(toolOutput.data.statement ?? '')}</div>
                <div class="chat-agent-tool__table">
                    <table>
                        <thead><tr>${tableHeader}</tr></thead>
                        <tbody>${body}</tbody>
                    </table>
                </div>
            </div>
        `;
    }

    return `<pre class="chat-agent-tool__json">${escapeHtml(toolOutput.content)}</pre>`;
};

const expandHistory = (messages = []) => {
    const expanded = [];

    messages.forEach((message) => {
        const base = {
            role: message.role,
            content: message.content,
            meta: message.meta ?? null,
        };

        expanded.push(base);

        const toolOutputs = message.meta?.tool_outputs ?? [];
        if (message.role === 'assistant' && Array.isArray(toolOutputs)) {
            toolOutputs.forEach((output) => {
                expanded.push({
                    role: 'assistant',
                    content: 'Here is the dataset I referenced:',
                    meta: {
                        skipSubmission: true,
                        type: 'tool',
                        toolOutput: output,
                    },
                });
            });
        }
    });

    return expanded;
};

const createWidget = () => {
    const state = {
        open: false,
        sending: false,
        history: loadHistory(),
    };

    const root = document.createElement('div');
    root.id = 'chat-agent-widget';
    root.innerHTML = `
        <button type="button" class="chat-agent-toggle" aria-expanded="false" aria-controls="chat-agent-panel">
            <span class="chat-agent-toggle__dot"></span>
            <span class="chat-agent-toggle__label">Ask SOHA</span>
        </button>
        <section id="chat-agent-panel" class="chat-agent-panel" aria-hidden="true">
            <header class="chat-agent-panel__header">
                <div>
                    <p class="chat-agent-panel__title">SOHA Support</p>
                    <p class="chat-agent-panel__subtitle">I can surface fresh numbers straight from the database.</p>
                </div>
                <button type="button" class="chat-agent-close" aria-label="Close chat">&times;</button>
            </header>
            <div class="chat-agent-panel__body">
                <div class="chat-agent-messages"></div>
            </div>
            <footer class="chat-agent-panel__footer">
                <form class="chat-agent-form" autocomplete="off">
                    <textarea
                        class="chat-agent-input"
                        rows="1"
                        placeholder="Ask about metrics, orders, users…"
                        aria-label="Message SOHA"
                        required
                    ></textarea>
                    <button class="chat-agent-send" type="submit" title="Send message">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M3 12L21 3L15 21L11 13L3 12Z" />
                        </svg>
                    </button>
                </form>
                <p class="chat-agent-hint">Answers are generated with live data. Double-check numbers that look off.</p>
            </footer>
        </section>
    `;

    document.body.appendChild(root);

    const toggle = root.querySelector('.chat-agent-toggle');
    const panel = root.querySelector('#chat-agent-panel');
    const close = root.querySelector('.chat-agent-close');
    const form = root.querySelector('.chat-agent-form');
    const textarea = root.querySelector('.chat-agent-input');
    const messagesContainer = root.querySelector('.chat-agent-messages');

    const renderMessages = () => {
        messagesContainer.innerHTML = '';

        state.history.forEach((message) => {
            const wrapper = document.createElement('div');
            wrapper.className = `chat-agent-message chat-agent-message--${message.role}${message.meta?.type ? ` chat-agent-message--${message.meta.type}` : ''}`;

            const bubble = document.createElement('div');
            bubble.className = 'chat-agent-bubble';
            bubble.textContent = message.content;
            wrapper.appendChild(bubble);

            if (message.meta?.toolOutput) {
                const detail = document.createElement('div');
                detail.className = 'chat-agent-bubble chat-agent-bubble--tool';
                detail.innerHTML = renderToolDataset(message.meta.toolOutput);
                wrapper.appendChild(detail);
            }

            messagesContainer.appendChild(wrapper);
        });

        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    };

    const setOpen = (open) => {
        state.open = open;
        panel.setAttribute('aria-hidden', String(!open));
        toggle.setAttribute('aria-expanded', String(open));
        root.classList.toggle('chat-agent-open', open);

        if (open) {
            setTimeout(() => textarea.focus(), 120);
        }
    };

    const hydrateFromServer = async () => {
        try {
            const response = await fetch(HISTORY_ENDPOINT, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`History request failed with status ${response.status}`);
            }

            const payload = await response.json();

            if (Array.isArray(payload.messages)) {
                state.history = expandHistory(payload.messages);
                saveHistory(state.history);
                renderMessages();
            }
        } catch (error) {
            console.warn('Unable to load chat history from server', error);
        }
    };

    toggle.addEventListener('click', () => setOpen(!state.open));
    close.addEventListener('click', () => setOpen(false));

    textarea.addEventListener('input', () => {
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
    });

    textarea.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    const appendMessage = (message) => {
        state.history.push(message);
        saveHistory(state.history.filter((item) => !item.meta?.skipSubmission));
        renderMessages();
    };

    const submitMessage = async (content) => {
        if (!content || state.sending) {
            return;
        }

        appendMessage({ role: 'user', content });

        state.sending = true;
        textarea.value = '';
        textarea.style.height = 'auto';

        const client = window.axios ?? null;

        try {
            let payload;

            if (client) {
                const response = await client.post(MESSAGE_ENDPOINT, { message: content });
                payload = response.data;
            } else {
                const response = await fetch(MESSAGE_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ message: content }),
                });

                if (!response.ok) {
                    throw new Error(`Request failed with status ${response.status}`);
                }

                payload = await response.json();
            }

            if (Array.isArray(payload.history)) {
                state.history = expandHistory(payload.history);
                saveHistory(state.history);
                renderMessages();
                return;
            }

            if (payload?.reply) {
                appendMessage({ role: 'assistant', content: payload.reply });
            } else {
                appendMessage({ role: 'assistant', content: 'I could not generate a response. Please try again.' });
            }

            if (Array.isArray(payload?.tool_outputs) && payload.tool_outputs.length) {
                payload.tool_outputs.forEach((output) => {
                    appendMessage({
                        role: 'assistant',
                        content: 'Here is the dataset I referenced:',
                        meta: {
                            skipSubmission: true,
                            type: 'tool',
                            toolOutput: output,
                        },
                    });
                });
            }
        } catch (error) {
            console.error('Chat request failed', error);
            appendMessage({
                role: 'assistant',
                content: 'Something went wrong while fetching live data. Please retry in a moment.',
            });
        } finally {
            state.sending = false;
            renderMessages();
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const content = textarea.value.trim();
        submitMessage(content);
    });

    renderMessages();
    hydrateFromServer();
};

document.addEventListener('DOMContentLoaded', () => {
    if (!document.body) {
        return;
    }

    createWidget();
});
