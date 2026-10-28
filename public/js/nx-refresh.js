/**
 * Manual on-demand refresh for admin/manager/staff pages.
 *
 * The refresh button (see partials/refresh-button) lives in the layout
 * topbar beside the user dropdown and fetches THIS page's own URL —
 * including its current query string, so active filters/tabs are
 * preserved — and swaps only the #nx-refresh-region markup in place.
 * No full browser reload, no auto-polling, no background timer.
 *
 * What survives a refresh by design: sidebar, page header, scroll position,
 * any typed form input, and any filter form kept outside the region.
 * Page scripts that must react to a refresh (e.g. the Appointments
 * calendar) listen for the `nx:refreshed` event on document.
 */
document.addEventListener('DOMContentLoaded', function () {
    var button = document.querySelector('[data-nx-refresh-btn]');
    var region = document.getElementById('nx-refresh-region');

    // The button renders on every page (it lives in the layout topbar), but
    // it only has work to do where the page marks a refresh region — hide
    // it elsewhere instead of leaving a dead button behind.
    if (!button) return;
    if (!region) { button.style.display = 'none'; return; }

    button.addEventListener('click', function () {
        if (button.disabled) return;

        button.disabled = true;
        button.classList.add('nx-spinning');

        // Same path + query, no hash: the server re-runs the page with the
        // filters the user already has (several index actions also persist
        // filters in session, so the fresh markup matches the current view).
        var url = window.location.pathname + window.location.search;

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.getElementById('nx-refresh-region');

                if (!fresh) {
                    // Not a page anymore (e.g. session expired and the
                    // server returned the login page): fall back to a real
                    // navigation so the user lands where they should.
                    window.location.reload();
                    return;
                }

                region.innerHTML = fresh.innerHTML;

                document.dispatchEvent(new CustomEvent('nx:refreshed'));
            })
            .catch(function () {
                // Silent by design (no status line exists anymore): the
                // button just stops spinning and the old data stays.
            })
            .finally(function () {
                button.disabled = false;
                button.classList.remove('nx-spinning');
            });
    });
});
