{{-- System-wide flash banners — the SINGLE render point for session flash
     messages (included once per layout). Covers the flash keys the codebase
     actually flashes ('success' everywhere; 'error' defensively — nothing
     flashes it today). Auth 'status' is intentionally NOT here: auth pages
     render it inside their own form card as their only copy. Validation
     $errors summaries are separate and stay where they are. --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" data-nx-flash>
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" data-nx-flash>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
<script>
    // Auto-dismiss every flash banner: fade it out shortly after render,
    // then remove it from the DOM. Dependency-free vanilla JS (no bootstrap
    // object) so load order can never defeat it. Runs on DOMContentLoaded,
    // or immediately when the document is already loaded.
    (function () {
        function dismiss() {
            document.querySelectorAll('[data-nx-flash]').forEach(function (el) {
                setTimeout(function () {
                    el.style.transition = 'opacity .4s ease';
                    el.style.opacity = '0';
                    setTimeout(function () {
                        el.remove();
                    }, 500);
                }, 4500);
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', dismiss);
        } else {
            dismiss();
        }
    })();
</script>
