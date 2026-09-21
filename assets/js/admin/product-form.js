/* =========================================================================
   Product form helpers: character counters, the new-product image dropzone
   (local previews queued for upload on save) and the variant repeater.
   The existing image-upload.js still handles already-saved images over AJAX.
   ========================================================================= */
(function () {
    'use strict';

    var $  = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

    /* ------------------------------------------------------ char counters */
    $$('[data-count]').forEach(function (field) {
        var out = document.getElementById(field.getAttribute('data-count'));
        if (!out) { return; }
        var max = field.getAttribute('maxlength');
        function tick() {
            out.textContent = field.value.length + (max ? '/' + max : '');
            out.classList.toggle('is-full', max && field.value.length >= +max);
        }
        field.addEventListener('input', tick);
        tick();
    });

    /* -------------------------------------------- new-product image queue */
    // For a product that doesn't exist yet there is nothing to attach an AJAX
    // upload to, so files are held in the file input and posted with the form.
    var zone = $('[data-dropzone]');
    if (zone) {
        var input = $('#newImages');
        var grid  = $('#newImageGrid');
        var queue = [];   // File objects, mirrored back into the input

        function sync() {
            var dt = new DataTransfer();
            queue.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
            render();
        }

        function render() {
            grid.innerHTML = '';
            queue.forEach(function (file, i) {
                var cell = document.createElement('div');
                cell.className = 'imgq__item' + (i === 0 ? ' is-main' : '');

                var img = document.createElement('img');
                img.alt = file.name;
                var fr = new FileReader();
                fr.onload = function (e) { img.src = e.target.result; };
                fr.readAsDataURL(file);
                cell.appendChild(img);

                if (i === 0) {
                    var flag = document.createElement('span');
                    flag.className = 'imgq__flag';
                    flag.textContent = 'Main';
                    cell.appendChild(flag);
                }

                var x = document.createElement('button');
                x.type = 'button';
                x.className = 'imgq__x';
                x.title = 'Remove ' + file.name;
                x.setAttribute('aria-label', 'Remove ' + file.name);
                x.innerHTML = '&times;';
                x.addEventListener('click', function () {
                    queue.splice(i, 1);
                    sync();
                });
                cell.appendChild(x);
                grid.appendChild(cell);
            });
            grid.hidden = queue.length === 0;
        }

        /* Kept in step with validate_uploaded_image() in includes/functions.php,
           which also accepts image/pjpeg. The two lists had drifted apart, so a
           JPEG the browser reported as image/pjpeg was dropped here — the file
           never reached the server and the save reported no images at all. */
        var IMAGE_MIME = /^image\/(jpeg|pjpeg|png|webp)$/i;
        var IMAGE_EXT  = /\.(jpe?g|png|webp)$/i;

        function looksLikeImage(f) {
            // an empty f.type means the browser could not classify the file at
            // all; fall back to the extension and let the server decide, since
            // finfo + getimagesize there is the real authority either way
            return f.type ? IMAGE_MIME.test(f.type) : IMAGE_EXT.test(f.name);
        }

        function add(files) {
            Array.prototype.forEach.call(files, function (f) {
                if (!looksLikeImage(f)) {
                    if (window.adminToast) { window.adminToast(f.name + ' is not a JPG, PNG or WEBP.', 'err'); }
                    return;
                }
                if (f.size > 5 * 1024 * 1024) {
                    if (window.adminToast) { window.adminToast(f.name + ' is larger than 5MB.', 'err'); }
                    return;
                }
                queue.push(f);
            });
            sync();
        }

        zone.addEventListener('click', function () { input.click(); });
        zone.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); input.click(); }
        });
        input.addEventListener('change', function () { add(input.files); });

        ['dragenter', 'dragover'].forEach(function (t) {
            zone.addEventListener(t, function (ev) { ev.preventDefault(); zone.classList.add('is-over'); });
        });
        ['dragleave', 'drop'].forEach(function (t) {
            zone.addEventListener(t, function (ev) { ev.preventDefault(); zone.classList.remove('is-over'); });
        });
        zone.addEventListener('drop', function (ev) {
            if (ev.dataTransfer && ev.dataTransfer.files.length) { add(ev.dataTransfer.files); }
        });
    }

    /* ----------------------------------------------------- variant rows */
    var vTable = $('#variantTable');
    if (vTable) {
        var tbody = vTable.querySelector('tbody');

        function blankRow() {
            var tr = tbody.rows[0].cloneNode(true);
            tr.querySelectorAll('input').forEach(function (i) {
                i.value = i.name === 'v_id[]' ? '0' : '';
            });
            tr.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
            return tr;
        }
        var addBtn = $('[data-add-variant]');
        if (addBtn) {
            addBtn.addEventListener('click', function () { tbody.appendChild(blankRow()); });
        }
        tbody.addEventListener('click', function (ev) {
            var del = ev.target.closest('[data-del-variant]');
            if (!del) { return; }
            if (tbody.rows.length === 1) {           // keep one empty row around
                var fresh = blankRow();
                tbody.replaceChild(fresh, tbody.rows[0]);
            } else {
                del.closest('tr').remove();
            }
        });
    }

    /* --------------------------------- slug suggestion from product name */
    var nameInput = $('#p_name');
    var slugInput = $('#p_slug');
    if (nameInput && slugInput) {
        nameInput.addEventListener('blur', function () {
            if (slugInput.value.trim() !== '' || nameInput.value.trim() === '') { return; }
            slugInput.value = nameInput.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });
    }

    /* ------------------------------- "Save as Draft" sets status = draft */
    var draftBtn = $('[data-save-draft]');
    if (draftBtn) {
        draftBtn.addEventListener('click', function () {
            var status = $('#p_status');
            if (status) { status.value = 'draft'; }
        });
    }
})();
