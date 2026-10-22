{{--
    Public client chat — rendered for every visitor (guest, client, manager).
    Launcher + panel + vanilla-JS chat. Talks to POST chat.ask; nothing is
    stored. All theme-driven surfaces use public --nx-* tokens so the widget
    follows the site's light/dark theme automatically.
--}}
@php
    $clientChatFaqs = collect(config('faqs.categories', []))
        ->map(fn (array $faq) => [
            'question' => $faq['question'],
            'answer' => $faq['answer'],
        ])
        ->values();
@endphp
<div id="nxChatWidget">
    <style>
        #nxChatLauncher {
            position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 2000;
            width: 58px; height: 58px; border-radius: 50%;
            border: 1px solid var(--nx-border);
            background: var(--nx-primary);
            color: var(--nx-text-on-primary);
            font-size: 1.35rem;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .28);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        #nxChatLauncher:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(0, 0, 0, .34); }
        #nxChatLauncher:focus-visible { outline: 3px solid var(--nx-border); outline-offset: 2px; }
        #nxChatPanel {
            position: fixed; right: 1.25rem; bottom: 5.5rem; z-index: 2000;
            width: min(370px, calc(100vw - 2rem));
            max-height: min(590px, calc(100dvh - 7rem));
            display: flex; flex-direction: column;
            background: var(--nx-bg-surface); color: var(--nx-text-primary);
            border: 1px solid var(--nx-border); border-radius: 1rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .34);
            overflow: hidden;
        }
        #nxChatPanel[hidden] { display: none; }
        .nx-chat-head {
            display: flex; align-items: center; gap: .6rem;
            padding: .8rem .9rem;
            background: linear-gradient(135deg, #2E1B10 0%, #3D2817 100%);
            color: #F3E7D7;
        }
        .nx-chat-mark {
            width: 38px; height: 38px; flex: 0 0 38px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            background: #C9A24B; color: #2E1B10; font-size: 1.05rem;
        }
        .nx-chat-head .nx-chat-title { line-height: 1.1; min-width: 0; }
        .nx-chat-head .nx-chat-name { display: block; font-weight: 700; font-size: 1rem; letter-spacing: .02em; }
        .nx-chat-head .nx-chat-sub { display: block; font-size: .74rem; opacity: .8; }
        .nx-chat-close {
            margin-left: auto; background: transparent; border: 0; color: #F3E7D7;
            font-size: 1.3rem; line-height: 1; cursor: pointer; padding: .15rem .35rem; border-radius: .4rem;
        }
        .nx-chat-close:hover { background: rgba(243, 231, 215, .14); }
        #nxChatMessages {
            flex: 1 1 auto; overflow-y: auto; padding: .85rem .85rem .4rem;
            display: flex; flex-direction: column; gap: .5rem;
            background: var(--nx-bg-surface);
        }
        .nx-chat-msg { display: flex; }
        .nx-chat-msg.user { justify-content: flex-end; }
        .nx-chat-bubble {
            max-width: 82%; padding: .5rem .7rem; border-radius: .85rem;
            font-size: .88rem; line-height: 1.35; white-space: pre-wrap; word-break: break-word;
        }
        .nx-chat-msg.bot .nx-chat-bubble {
            background: var(--nx-bg-surface-muted); color: var(--nx-text-primary);
            border: 1px solid var(--nx-border); border-bottom-left-radius: .25rem;
        }
        .nx-chat-msg.user .nx-chat-bubble {
            background: var(--nx-primary); color: var(--nx-text-on-primary);
            border-bottom-right-radius: .25rem;
        }
        .nx-chat-thinking { font-style: italic; color: var(--nx-text-primary); opacity: .65; animation: nx-chat-pulse 1.2s ease-in-out infinite; }
        @keyframes nx-chat-pulse { 0%, 100% { opacity: .45; } 50% { opacity: 1; } }
        #nxChatChips { display: flex; flex-wrap: wrap; gap: .35rem; padding: .1rem .85rem .5rem; }
        .nx-chat-chip {
            font-size: .76rem; padding: .3rem .6rem; border-radius: 999px;
            border: 1px solid var(--nx-border); background: var(--nx-bg-surface);
            color: var(--nx-primary); cursor: pointer; font-family: inherit;
        }
        .nx-chat-chip:hover { background: var(--nx-bg-surface-muted); }
        #nxChatForm {
            display: flex; gap: .4rem; padding: .6rem .7rem;
            border-top: 1px solid var(--nx-border); background: var(--nx-bg-surface);
        }
        #nxChatInput {
            flex: 1 1 auto; border: 1px solid var(--nx-border); border-radius: .6rem;
            padding: .5rem .6rem; font-size: .88rem; font-family: inherit;
            background: var(--nx-bg-surface); color: var(--nx-text-primary);
        }
        #nxChatInput:focus { outline: none; border-color: var(--nx-primary); }
        #nxChatSend {
            border: 0; border-radius: .6rem; padding: .5rem .85rem; font-weight: 600;
            background: var(--nx-primary); color: var(--nx-text-on-primary);
            cursor: pointer; font-family: inherit;
        }
        #nxChatSend:hover { opacity: .9; }
        #nxChatSend:disabled { opacity: .6; cursor: default; }
        @media (max-width: 575px) {
            #nxChatPanel { left: .6rem; right: .6rem; width: auto; }
        }
    </style>

    <button type="button" id="nxChatLauncher" aria-label="Open chat assistant" aria-expanded="false" aria-controls="nxChatPanel">
        <i class="bi bi-patch-question-fill" aria-hidden="true"></i>
    </button>

    <section id="nxChatPanel" role="dialog" aria-label="Perfect Nails chat assistant" hidden>
        <div class="nx-chat-head">
            <span class="nx-chat-mark" aria-hidden="true"><i class="bi bi-patch-question-fill"></i></span>
            <span class="nx-chat-title">
                <span class="nx-chat-name">Perfect Nails</span>
                <span class="nx-chat-sub">Ask us anything about the branch</span>
            </span>
            <button type="button" class="nx-chat-close" id="nxChatClose" aria-label="Close chat">&times;</button>
        </div>
        <div id="nxChatMessages" aria-live="polite"></div>
        <div id="nxChatChips">
            @foreach($clientChatFaqs as $index => $faq)
                <button type="button" class="nx-chat-chip" data-chat-chip="{{ $index }}">{{ config('faqs.categories')[$index]['category'] }}</button>
            @endforeach
        </div>
        <form id="nxChatForm" autocomplete="off">
            <input id="nxChatInput" type="text" name="question" maxlength="500" placeholder="Ask about services, prices, or booking…" aria-label="Ask a question">
            <button type="submit" id="nxChatSend">Send</button>
        </form>
    </section>

    <script>
        (function () {
            const launcher = document.getElementById('nxChatLauncher');
            const panel = document.getElementById('nxChatPanel');
            const closeBtn = document.getElementById('nxChatClose');
            const form = document.getElementById('nxChatForm');
            const input = document.getElementById('nxChatInput');
            const sendBtn = document.getElementById('nxChatSend');
            const messages = document.getElementById('nxChatMessages');
            const chips = document.getElementById('nxChatChips');
            if (!launcher || !panel || !form || !messages) return;

            const endpoint = @json(route('chat.ask'));
            const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            const faqEntries = @json($clientChatFaqs);
            const phones = @json(config('contact_info.phones', []));
            const unavailableMessage = 'Our chat assistant is unavailable right now. You can tap a topic above, browse our FAQs, or contact the branch at ' + phones.join(' / ') + '.';
            const history = [];
            let sending = false;

            const scrollDown = () => { messages.scrollTop = messages.scrollHeight; };

            function addMessage(role, text) {
                const row = document.createElement('div');
                row.className = 'nx-chat-msg ' + (role === 'user' ? 'user' : 'bot');
                const bubble = document.createElement('div');
                bubble.className = 'nx-chat-bubble';
                bubble.textContent = text;
                row.appendChild(bubble);
                messages.appendChild(row);
                scrollDown();
            }

            function showThinking() {
                const row = document.createElement('div');
                row.className = 'nx-chat-msg bot';
                row.id = 'nxChatThinking';
                const bubble = document.createElement('div');
                bubble.className = 'nx-chat-bubble nx-chat-thinking';
                bubble.textContent = 'Thinking…';
                row.appendChild(bubble);
                messages.appendChild(row);
                scrollDown();
            }
            function hideThinking() {
                const el = document.getElementById('nxChatThinking');
                if (el) el.remove();
            }

            function openPanel() {
                panel.hidden = false;
                launcher.setAttribute('aria-expanded', 'true');
                setTimeout(() => input.focus(), 50);
            }
            function closePanel() {
                panel.hidden = true;
                launcher.setAttribute('aria-expanded', 'false');
            }

            launcher.addEventListener('click', () => (panel.hidden ? openPanel() : closePanel()));
            if (closeBtn) closeBtn.addEventListener('click', closePanel);

            async function ask(rawQuestion) {
                const question = (rawQuestion || '').trim();
                if (question === '' || sending) return;

                sending = true;
                if (sendBtn) sendBtn.disabled = true;
                addMessage('user', question);
                if (input) input.value = '';

                const priorHistory = history.slice();
                history.push({ role: 'user', text: question });

                showThinking();
                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ question: question, history: priorHistory }),
                    });
                    const data = await res.json();
                    hideThinking();

                    if (data && data.answer) {
                        addMessage('bot', data.answer);
                        history.push({ role: 'assistant', text: data.answer });
                    } else {
                        addMessage('bot', unavailableMessage);
                    }
                } catch (e) {
                    hideThinking();
                    addMessage('bot', unavailableMessage);
                } finally {
                    sending = false;
                    if (sendBtn) sendBtn.disabled = false;
                    if (input) input.focus();
                }
            }

            form.addEventListener('submit', (ev) => { ev.preventDefault(); ask(input.value); });

            if (chips) {
                chips.addEventListener('click', (ev) => {
                    const chip = ev.target.closest('[data-chat-chip]');
                    if (!chip) return;

                    const entry = faqEntries[Number(chip.getAttribute('data-chat-chip'))];
                    if (!entry) return;

                    addMessage('user', entry.question);
                    addMessage('bot', entry.answer);
                    history.push({ role: 'user', text: entry.question });
                    history.push({ role: 'assistant', text: entry.answer });
                });
            }

            addMessage('bot', "Hi! I'm the Perfect Nails assistant. Ask me about our services, prices, promos, or how to book — or tap a topic below.");
        })();
    </script>
</div>
