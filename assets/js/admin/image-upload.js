/**
 * AJAX product image manager: drag/drop + browse upload with progress,
 * instant thumbnails, set-primary, delete, and drag-to-reorder - no page reload.
 */
(function () {
    'use strict';
    var mgr = document.getElementById('imageManager');
    if (!mgr) return;

    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var productId = mgr.getAttribute('data-product-id');
    var grid = document.getElementById('imageGrid');
    var input = document.getElementById('imageInput');
    var progress = document.getElementById('imageProgress');
    var bar = progress.querySelector('div');

    // matches validate_uploaded_image() in includes/functions.php, which also
    // accepts image/pjpeg; an empty type means the browser could not classify
    // the file, so defer to the server rather than dropping it here
    var ALLOWED = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp'];
    var MAX = 5 * 1024 * 1024;

    /**
     * Report back through the shared toast stack so image feedback looks like
     * every other admin action. `ok` may be true/false (legacy) or a type name
     * ('ok' | 'err' | 'warn' | 'info').
     */
    function flash(msg, ok) {
        var type = typeof ok === 'string' ? ok : (ok === false ? 'err' : 'ok');
        if (window.adminToast) { window.adminToast(msg, type); return; }
        var n = document.createElement('div');
        n.className = 'notice notice--' + type;
        n.textContent = msg;
        mgr.parentNode.insertBefore(n, mgr);
        setTimeout(function () { n.remove(); }, 3500);
    }

    /* ---------- upload ---------- */
    function uploadFiles(files) {
        var queue = Array.prototype.slice.call(files).filter(function (f) {
            if (f.type && ALLOWED.indexOf(f.type) === -1) { flash(f.name + ': unsupported type.', false); return false; }
            if (f.size > MAX) { flash(f.name + ': larger than 5MB.', false); return false; }
            return true;
        });
        if (!queue.length) return;

        var done = 0;
        progress.style.display = 'block';
        bar.style.width = '0%';

        var uploaded = 0;

        function next() {
            if (!queue.length) {
                progress.style.display = 'none';
                if (uploaded) {
                    flash(uploaded + ' image' + (uploaded === 1 ? '' : 's') + ' uploaded.', 'ok');
                }
                return;
            }
            var file = queue.shift();
            var fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('product_id', productId);
            fd.append('image', file);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '../ajax/admin/product-image-upload.php');
            xhr.setRequestHeader('X-CSRF-Token', CSRF);
            xhr.upload.onprogress = function (e) {
                if (e.lengthComputable) {
                    var pct = ((done + e.loaded / e.total) / (done + queue.length + 1)) * 100;
                    bar.style.width = pct.toFixed(0) + '%';
                }
            };
            xhr.onload = function () {
                done++;
                var res;
                try { res = JSON.parse(xhr.responseText); } catch (e) { res = { success: false, message: 'Upload failed.' }; }
                if (res.success) {
                    uploaded++;
                    addThumb(res.data.image);
                } else {
                    flash(file.name + ': ' + (res.message || 'upload failed.'), 'err');
                }
                next();
            };
            xhr.onerror = function () {
                done++;
                flash('Network problem — ' + file.name + ' was not uploaded.', 'err');
                next();
            };
            xhr.send(fd);
        }
        next();
    }

    function addThumb(img) {
        var el = document.createElement('div');
        el.className = 'imgmgr__item' + (img.is_primary ? ' is-primary' : '');
        el.setAttribute('data-id', img.id);
        el.setAttribute('draggable', 'true');
        el.innerHTML =
            (img.is_primary ? '<span class="imgmgr__flag">Primary</span>' : '') +
            '<img src="' + img.url + '" alt="">' +
            '<div class="imgmgr__bar">' +
            '<button type="button" data-act="primary" title="Set primary">&#9733;</button>' +
            '<button type="button" data-act="delete" title="Delete">&#128465;</button>' +
            '</div>';
        grid.appendChild(el);
        bindDrag(el);
    }

    function post(url, data) {
        var body = new URLSearchParams();
        body.append('csrf_token', CSRF);
        Object.keys(data).forEach(function (k) {
            if (Array.isArray(data[k])) data[k].forEach(function (v) { body.append(k + '[]', v); });
            else body.append(k, data[k]);
        });
        return fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: body })
            .then(function (r) { return r.json(); })
            .catch(function () {
                flash('Network problem — that change was not saved.', 'err');
                return { success: false, message: '', _handled: true };
            });
    }

    /* ---------- delete / set primary ---------- */
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn) return;
        var item = btn.closest('.imgmgr__item');
        var id = item.getAttribute('data-id');

        if (btn.getAttribute('data-act') === 'delete') {
            if (!confirm('Delete this image?')) return;
            post('../ajax/admin/product-image-delete.php', { image_id: id }).then(function (res) {
                if (!res.success) {
                    if (!res._handled) { flash(res.message || 'That image could not be deleted.', 'err'); }
                    return;
                }
                item.remove();
                syncPrimaryFlags(res.data.images);
                flash('Image deleted.', 'ok');
                if (!res.data.images || !res.data.images.length) {
                    flash('This product has no images left — the storefront will show a placeholder.', 'warn');
                }
            });
        }

        if (btn.getAttribute('data-act') === 'primary') {
            post('../ajax/admin/product-image-set-primary.php', { image_id: id }).then(function (res) {
                if (!res.success) {
                    if (!res._handled) { flash(res.message || 'Could not set the main image.', 'err'); }
                    return;
                }
                grid.querySelectorAll('.imgmgr__item').forEach(function (it) {
                    var isP = it.getAttribute('data-id') === id;
                    it.classList.toggle('is-primary', isP);
                    var flag = it.querySelector('.imgmgr__flag');
                    if (isP && !flag) { var s = document.createElement('span'); s.className = 'imgmgr__flag'; s.textContent = 'Primary'; it.insertBefore(s, it.firstChild); }
                    if (!isP && flag) flag.remove();
                });
                flash('Main image updated.', 'ok');
            });
        }
    });

    function syncPrimaryFlags(images) {
        var primaryId = null;
        images.forEach(function (i) { if (i.is_primary) primaryId = String(i.id); });
        grid.querySelectorAll('.imgmgr__item').forEach(function (it) {
            var isP = it.getAttribute('data-id') === primaryId;
            it.classList.toggle('is-primary', isP);
            var flag = it.querySelector('.imgmgr__flag');
            if (isP && !flag) { var s = document.createElement('span'); s.className = 'imgmgr__flag'; s.textContent = 'Primary'; it.insertBefore(s, it.firstChild); }
            if (!isP && flag) flag.remove();
        });
    }

    /* ---------- drag & drop upload ---------- */
    ['dragenter', 'dragover'].forEach(function (ev) {
        mgr.addEventListener(ev, function (e) { e.preventDefault(); mgr.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        mgr.addEventListener(ev, function (e) { e.preventDefault(); if (ev === 'dragleave' && e.target !== mgr) return; mgr.classList.remove('dragover'); });
    });
    mgr.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
    });
    /* #imageInput is declared as <input type="hidden">, which has no .files
       and can never fire change from a file dialog — this binding was dead.
       Guard it so the manager keeps working through drag and drop, and only
       wire the browse path when the element really is a file input. */
    if (input && input.type === 'file') {
        input.addEventListener('change', function () { uploadFiles(input.files); input.value = ''; });
    }

    /* ---------- drag to reorder ---------- */
    var dragEl = null;
    function bindDrag(el) {
        el.addEventListener('dragstart', function (e) {
            // Only reorder when the drag starts on an existing thumb, not a file drop.
            dragEl = el;
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', el.getAttribute('data-id')); } catch (x) {}
        });
        el.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragEl || dragEl === el) return;
            var rect = el.getBoundingClientRect();
            var after = (e.clientX - rect.left) > rect.width / 2;
            grid.insertBefore(dragEl, after ? el.nextSibling : el);
        });
        el.addEventListener('drop', function (e) { e.preventDefault(); e.stopPropagation(); });
        el.addEventListener('dragend', function () {
            if (!dragEl) return;
            dragEl = null;
            var order = Array.prototype.map.call(grid.querySelectorAll('.imgmgr__item'), function (it) {
                return it.getAttribute('data-id');
            });
            post('../ajax/admin/product-image-reorder.php', { product_id: productId, order: order }).then(function (res) {
                if (res.success) {
                    flash('Image order saved.', 'ok');
                } else if (!res._handled) {
                    flash(res.message || 'Could not save the new image order.', 'err');
                }
            });
        });
    }
    grid.querySelectorAll('.imgmgr__item').forEach(bindDrag);
})();
