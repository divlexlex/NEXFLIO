{{-- Reusable image upload field for admin modals. Expects: $prefix (string).
     The show.bs.modal handler in each view sets the preview src (data-image)
     and toggles the remove checkbox. --}}
<div class="mb-2">
    <label class="form-label">Image</label>
    <div class="d-flex align-items-center gap-3 mb-2">
        <img id="{{ $prefix }}-image-preview" src="" alt=""
             class="rounded border" style="width:64px;height:64px;object-fit:cover;display:none;background:var(--spa-tan);">
        <input type="file" name="image" id="{{ $prefix }}-image" accept="image/*" class="form-control">
    </div>
    <div class="form-check" id="{{ $prefix }}-remove-wrap" style="display:none;">
        <input type="checkbox" name="remove_image" id="{{ $prefix }}-remove-image" class="form-check-input" value="1">
        <label class="form-check-label small text-muted" for="{{ $prefix }}-remove-image">Remove current image</label>
    </div>
</div>
<script>
    (function () {
        const input = document.getElementById('{{ $prefix }}-image');
        if (!input) return;
        input.addEventListener('change', function () {
            const img = document.getElementById('{{ $prefix }}-image-preview');
            if (this.files && this.files[0]) {
                img.src = URL.createObjectURL(this.files[0]);
                img.style.display = 'block';
            }
        });
    })();
</script>
