/* =========================================================================
   Minimal rich-text editor for the admin.
   Enhances <textarea data-editor> into a toolbar + contenteditable surface and
   keeps the textarea in sync, so the form still posts a normal field and the
   page degrades to a plain textarea without JS.

   Uses document.execCommand: formally deprecated, but it is still the only
   thing every browser implements for this, and the output is whitelist-
   sanitised server-side by sanitize_html() before it is ever rendered.
   ========================================================================= */
(function () {
    'use strict';

    var BLOCKS = [
        ['p', 'Paragraph'],
        ['h2', 'Heading 2'],
        ['h3', 'Heading 3'],
        ['h4', 'Heading 4'],
        ['blockquote', 'Quote']
    ];

    var TOOLS = [
        { cmd: 'bold', label: 'Bold', key: 'B', svg: '<path d="M7 5h6a3.5 3.5 0 0 1 0 7H7z"/><path d="M7 12h7a3.5 3.5 0 0 1 0 7H7z"/>' },
        { cmd: 'italic', label: 'Italic', svg: '<path d="M15 4h-5M14 20H9M14 4 10 20"/>' },
        { cmd: 'underline', label: 'Underline', svg: '<path d="M7 4v7a5 5 0 0 0 10 0V4"/><path d="M5 20h14"/>' },
        { sep: true },
        { cmd: 'insertUnorderedList', label: 'Bulleted list', svg: '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1.2"/><circle cx="4.5" cy="12" r="1.2"/><circle cx="4.5" cy="18" r="1.2"/>' },
        { cmd: 'insertOrderedList', label: 'Numbered list', svg: '<path d="M10 6h10M10 12h10M10 18h10"/><path d="M4 5h1v4M3.6 15.2c0-1.4 1.9-1.2 1.9 0C5.5 16 4 16.6 3.6 18H5.6"/>' },
        { sep: true },
        { cmd: 'justifyLeft', label: 'Align left', svg: '<path d="M4 6h16M4 12h10M4 18h14"/>' },
        { cmd: 'justifyCenter', label: 'Align centre', svg: '<path d="M4 6h16M7 12h10M5 18h14"/>' },
        { sep: true },
        { cmd: 'createLink', label: 'Insert link', svg: '<path d="M10 13a5 5 0 0 0 7.5.5l2-2A5 5 0 0 0 12.5 4.5l-1.2 1.1"/><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2A5 5 0 0 0 11.5 19.5l1.2-1.1"/>' },
        { cmd: 'insertImage', label: 'Insert image by URL', svg: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-5-5-6 6"/>' },
        { sep: true },
        { cmd: 'removeFormat', label: 'Clear formatting', svg: '<path d="M4 7V5h12v2M9 5l-2 14M6 19h8M15 13l6 6M21 13l-6 6"/>' }
    ];

    function icon(path) {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            + ' stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg>';
    }

    function build(area) {
        var wrap = document.createElement('div');
        wrap.className = 'rte';
        area.parentNode.insertBefore(wrap, area);

        var bar = document.createElement('div');
        bar.className = 'rte__bar';

        var blockSel = document.createElement('select');
        blockSel.className = 'rte__block';
        blockSel.setAttribute('aria-label', 'Text style');
        BLOCKS.forEach(function (b) {
            var o = document.createElement('option');
            o.value = b[0];
            o.textContent = b[1];
            blockSel.appendChild(o);
        });
        bar.appendChild(blockSel);

        TOOLS.forEach(function (t) {
            if (t.sep) {
                var s = document.createElement('span');
                s.className = 'rte__sep';
                bar.appendChild(s);
                return;
            }
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'rte__btn';
            b.title = t.label + (t.key ? ' (Ctrl+' + t.key + ')' : '');
            b.setAttribute('aria-label', t.label);
            b.dataset.cmd = t.cmd;
            b.innerHTML = icon(t.svg);
            bar.appendChild(b);
        });

        var body = document.createElement('div');
        body.className = 'rte__body';
        body.contentEditable = 'true';
        body.setAttribute('role', 'textbox');
        body.setAttribute('aria-multiline', 'true');
        if (area.placeholder) { body.dataset.placeholder = area.placeholder; }
        body.innerHTML = area.value.trim() || '';

        wrap.appendChild(bar);
        wrap.appendChild(body);
        area.classList.add('rte__source');
        area.setAttribute('aria-hidden', 'true');
        area.tabIndex = -1;

        function sync() {
            var html = body.innerHTML.trim();
            // an empty contenteditable still reports <br> in some browsers
            area.value = (html === '<br>' || html === '<p><br></p>') ? '' : html;
        }
        body.addEventListener('input', sync);
        body.addEventListener('blur', sync);
        if (area.form) { area.form.addEventListener('submit', sync); }

        function run(cmd) {
            body.focus();
            if (cmd === 'createLink') {
                var url = prompt('Link URL', 'https://');
                if (!url) { return; }
                document.execCommand('createLink', false, url);
            } else if (cmd === 'insertImage') {
                var src = prompt('Image URL', '');
                if (!src) { return; }
                document.execCommand('insertImage', false, src);
            } else {
                document.execCommand(cmd, false, null);
            }
            sync();
            refresh();
        }

        bar.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.rte__btn');
            if (btn) { ev.preventDefault(); run(btn.dataset.cmd); }
        });
        blockSel.addEventListener('change', function () {
            body.focus();
            document.execCommand('formatBlock', false, '<' + blockSel.value + '>');
            sync();
        });

        // reflect the caret's current formatting in the toolbar
        function refresh() {
            bar.querySelectorAll('.rte__btn').forEach(function (b) {
                var c = b.dataset.cmd;
                if (c === 'createLink' || c === 'insertImage' || c === 'removeFormat') { return; }
                var on = false;
                try { on = document.queryCommandState(c); } catch (e) {}
                b.classList.toggle('is-on', on);
            });
            var block = '';
            try { block = (document.queryCommandValue('formatBlock') || '').toLowerCase(); } catch (e) {}
            if (block === 'div') { block = 'p'; }
            if (block) { blockSel.value = BLOCKS.some(function (b) { return b[0] === block; }) ? block : 'p'; }
        }
        body.addEventListener('keyup', refresh);
        body.addEventListener('mouseup', refresh);
        body.addEventListener('focus', refresh);

        // paste as plain text so pasted Word/HTML junk never reaches the field
        body.addEventListener('paste', function (ev) {
            ev.preventDefault();
            var text = (ev.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });
    }

    document.querySelectorAll('textarea[data-editor]').forEach(build);
})();
