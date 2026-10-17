{{--
    Flio — floating AI business assistant for the admin portal (Owner/Manager).
    Launcher + panel + vanilla-JS chat. Rendered only for roles 1 and 2.
    Talks to POST admin.flio.ask; the conversation is never stored.
--}}
@php
    $flioUser = auth()->user();
    $flioRoles = [\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_MANAGER];
@endphp

@if($flioUser && in_array((int) $flioUser->role_id, $flioRoles, true))
<div id="flio-widget" data-flio-widget>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&display=swap');
        #flio-widget { font-family: 'Jost', 'Segoe UI', system-ui, -apple-system, sans-serif; }
        #flio-launcher {
            position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 1080;
            width: 58px; height: 58px; border-radius: 50%; border: 0;
            background: radial-gradient(circle at 30% 25%, #E0BC63 0%, var(--nex-gold, #C9A24B) 55%, #A97C50 100%);
            color: #2E1B10; font-weight: 700; font-size: 1.1rem;
            box-shadow: 0 10px 24px rgba(46, 27, 16, .38);
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: transform .15s ease, box-shadow .15s ease;
        }
        #flio-launcher:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(46, 27, 16, .46); }
        #flio-launcher:focus-visible { outline: 3px solid rgba(201,162,75,.55); outline-offset: 2px; }
        #flio-panel {
            position: fixed; right: 1.25rem; bottom: 5.5rem; z-index: 1080;
            width: min(370px, calc(100vw - 2rem));
            max-height: min(590px, calc(100vh - 7rem));
            display: flex; flex-direction: column;
            background: var(--nex-surface, #fff); color: var(--nex-text, #2B1A12);
            border: 1px solid var(--nex-border, #E4D5C3); border-radius: 1rem;
            box-shadow: 0 24px 60px rgba(46, 27, 16, .34);
            overflow: hidden;
        }
        #flio-panel[hidden] { display: none; }
        .flio-head {
            display: flex; align-items: center; gap: .6rem;
            padding: .8rem .9rem;
            background: linear-gradient(135deg, #2E1B10 0%, #3D2817 100%);
            color: #F3E7D7;
        }
        .flio-mark {
            width: 38px; height: 38px; flex: 0 0 38px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            background: radial-gradient(circle at 30% 25%, #E0BC63, var(--nex-gold, #C9A24B));
            color: #2E1B10; font-weight: 700; font-size: 1.05rem;
        }
        .flio-head .flio-title { line-height: 1.1; }
        .flio-head .flio-name { font-weight: 700; font-size: 1rem; letter-spacing: .02em; }
        .flio-head .flio-sub { font-size: .74rem; opacity: .8; }
        .flio-close {
            margin-left: auto; background: transparent; border: 0; color: #F3E7D7;
            font-size: 1.3rem; line-height: 1; cursor: pointer; padding: .15rem .35rem; border-radius: .4rem;
        }
        .flio-close:hover { background: rgba(243,231,215,.14); }
        #flio-messages {
            flex: 1 1 auto; overflow-y: auto; padding: .85rem .85rem .4rem;
            display: flex; flex-direction: column; gap: .5rem;
            background: var(--nex-surface, #fff);
        }
        .flio-msg { display: flex; }
        .flio-msg.user { justify-content: flex-end; }
        .flio-bubble {
            max-width: 82%; padding: .5rem .7rem; border-radius: .85rem;
            font-size: .88rem; line-height: 1.35; white-space: pre-wrap; word-break: break-word;
        }
        .flio-msg.bot .flio-bubble { background: var(--nex-zebra, #FCF8F2); color: var(--nex-text, #2B1A12); border: 1px solid var(--nex-border, #E4D5C3); border-bottom-left-radius: .25rem; }
        .flio-msg.user .flio-bubble { background: var(--nex-gold, #C9A24B); color: #2E1B10; border-bottom-right-radius: .25rem; }
        .flio-thinking { font-style: italic; color: var(--nex-muted, #71604F); animation: flio-pulse 1.2s ease-in-out infinite; }
        @keyframes flio-pulse { 0%, 100% { opacity: .55; } 50% { opacity: 1; } }
        #flio-chips { display: flex; flex-wrap: wrap; gap: .35rem; padding: .1rem .85rem .5rem; }
        .flio-chip {
            font-size: .76rem; padding: .3rem .6rem; border-radius: 999px;
            border: 1px solid var(--nex-border, #E4D5C3); background: var(--nex-surface, #fff);
            color: var(--nex-link, #8A5A2B); cursor: pointer; font-family: inherit;
        }
        .flio-chip:hover { background: var(--nex-gold, #C9A24B); color: #2E1B10; border-color: var(--nex-gold, #C9A24B); }
        #flio-form { display: flex; gap: .4rem; padding: .6rem .7rem; border-top: 1px solid var(--nex-border, #E4D5C3); background: var(--nex-surface, #fff); }
        #flio-input {
            flex: 1 1 auto; border: 1px solid var(--nex-border, #E4D5C3); border-radius: .6rem;
            padding: .5rem .6rem; font-size: .88rem; font-family: inherit;
            background: var(--nex-raised, #fff); color: var(--nex-text, #2B1A12);
        }
        #flio-input:focus { outline: none; border-color: var(--nex-accent, #B5895A); box-shadow: 0 0 0 .2rem rgba(181,137,90,.25); }
        #flio-send {
            border: 0; border-radius: .6rem; padding: .5rem .85rem; font-weight: 600;
            background: var(--nex-primary-bg, #A97C50); color: var(--nex-primary-text, #fff); cursor: pointer; font-family: inherit;
        }
        #flio-send:hover { background: var(--nex-primary-hover, #8F653B); }
        #flio-send:disabled { opacity: .6; cursor: default; }
        @media (max-width: 575px) { #flio-panel { right: .6rem; left: .6rem; width: auto; } }
    </style>

    <button type="button" id="flio-launcher" aria-label="Open Flio AI assistant" aria-expanded="false" aria-controls="flio-panel">F</button>

    <section id="flio-panel" role="dialog" aria-label="Flio AI assistant" hidden>
        <div class="flio-head">
            <span class="flio-mark" aria-hidden="true">F</span>
            <span class="flio-title">
                <span class="flio-name d-block">Flio</span>
                <span class="flio-sub d-block">AI business assistant</span>
            </span>
            <button type="button" class="flio-close" id="flio-close" aria-label="Close Flio">&times;</button>
        </div>
        <div id="flio-messages" aria-live="polite"></div>
        <div id="flio-chips">
            <button type="button" class="flio-chip" data-flio-question="Revenue this week?">Revenue this week?</button>
            <button type="button" class="flio-chip" data-flio-question="Peak day &amp; hours?">Peak day &amp; hours?</button>
            <button type="button" class="flio-chip" data-flio-question="What's low on stock?">What's low on stock?</button>
            <button type="button" class="flio-chip" data-flio-question="Pending verifications?">Pending verifications?</button>
        </div>
        <form id="flio-form" autocomplete="off">
            <input id="flio-input" type="text" name="question" maxlength="500" placeholder="Ask Flio about the business…" aria-label="Ask Flio a question">
            <button type="submit" id="flio-send">Send</button>
        </form>
    </section>

    <script>
        (function () {
            const launcher = document.getElementById('flio-launcher');
            const panel = document.getElementById('flio-panel');
            const closeBtn = document.getElementById('flio-close');
            const form = document.getElementById('flio-form');
            const input = document.getElementById('flio-input');
            const sendBtn = document.getElementById('flio-send');
            const messages = document.getElementById('flio-messages');
            const chips = document.getElementById('flio-chips');
            if (!launcher || !panel || !form || !messages) return;

            const endpoint = @json(route('admin.flio.ask'));
            const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            const history = [];
            let sending = false;

            const scrollDown = () => { messages.scrollTop = messages.scrollHeight; };

            function addMessage(role, text) {
                const row = document.createElement('div');
                row.className = 'flio-msg ' + (role === 'user' ? 'user' : 'bot');
                const bubble = document.createElement('div');
                bubble.className = 'flio-bubble';
                bubble.textContent = text;
                row.appendChild(bubble);
                messages.appendChild(row);
                scrollDown();
            }

            function showThinking() {
                const row = document.createElement('div');
                row.className = 'flio-msg bot';
                row.id = 'flio-thinking';
                const bubble = document.createElement('div');
                bubble.className = 'flio-bubble flio-thinking';
                bubble.textContent = 'Flio is thinking…';
                row.appendChild(bubble);
                messages.appendChild(row);
                scrollDown();
            }
            function hideThinking() {
                const el = document.getElementById('flio-thinking');
                if (el) el.remove();
            }

            function openPanel() { panel.hidden = false; launcher.setAttribute('aria-expanded', 'true'); setTimeout(() => input.focus(), 50); }
            function closePanel() { panel.hidden = true; launcher.setAttribute('aria-expanded', 'false'); }

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
                    } else if (data && data.configured === false) {
                        addMessage('bot', 'Flio is not configured yet — ask an owner to add a Gemini API key.');
                    } else {
                        addMessage('bot', 'Flio is unavailable right now — please try again in a bit.');
                    }
                } catch (e) {
                    hideThinking();
                    addMessage('bot', 'Flio is unavailable right now — please try again in a bit.');
                } finally {
                    sending = false;
                    if (sendBtn) sendBtn.disabled = false;
                    if (input) input.focus();
                }
            }

            form.addEventListener('submit', (ev) => { ev.preventDefault(); ask(input.value); });

            if (chips) {
                chips.addEventListener('click', (ev) => {
                    const chip = ev.target.closest('[data-flio-question]');
                    if (chip) ask(chip.getAttribute('data-flio-question'));
                });
            }

            addMessage('bot', "Hi, I'm Flio — your business assistant. Ask me about revenue, bookings, stock, or pending work.");
        })();
    </script>
</div>
@endif
