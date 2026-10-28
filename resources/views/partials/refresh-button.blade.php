{{-- Manual on-demand refresh button: icon only, NO text label (approved
     design). Works with public/js/nx-refresh.js: clicking fetches this
     page's own URL and swaps only #nx-refresh-region — no full browser
     reload, no auto-polling, no background timer. The status line sits
     next to the button and is updated by the script after each refresh. --}}
<span class="nx-refresh-wrap">
    <button type="button" class="nx-refresh-btn" data-nx-refresh-btn title="Refresh" aria-label="Refresh">
        <span data-nx-refresh-icon aria-hidden="true">&#8635;</span>
    </button>
</span>
