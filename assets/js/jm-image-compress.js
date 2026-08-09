/**
 * JOBMINGTON - Admin image compression, in the browser.
 *
 * The server has no GD, Imagick or ImageMagick, so images cannot be resized
 * after upload. This shrinks them before they are sent instead: the file that
 * reaches the server is already the optimised one, which also means a much
 * smaller upload over a slow connection.
 *
 * Applies to every image file input inside the admin. Opt out with
 * data-jm-compress="off"; override limits with data-jm-max / data-jm-quality.
 *
 * Transparency is preserved: a PNG that actually uses its alpha channel stays
 * a PNG, so logos and badges do not gain a black or white box.
 */
(function () {
    'use strict';

    var MAX_EDGE = 1600;   // long edge in px; covers hero and banner use
    var QUALITY  = 0.82;
    var SKIP_UNDER = 200 * 1024;   // already small enough to leave alone

    if (!window.HTMLCanvasElement || !window.DataTransfer || !window.FileReader) { return; }

    function readable(bytes) {
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + 'MB'
                                : Math.round(bytes / 1024) + 'KB';
    }

    /** True if any pixel is not fully opaque. */
    function hasAlpha(ctx, w, h) {
        try {
            var step = Math.max(1, Math.floor(Math.min(w, h) / 100));
            var d = ctx.getImageData(0, 0, w, h).data;
            for (var y = 0; y < h; y += step) {
                for (var x = 0; x < w; x += step) {
                    if (d[((y * w) + x) * 4 + 3] < 255) { return true; }
                }
            }
        } catch (e) { return true; }   // tainted canvas: assume alpha, keep PNG
        return false;
    }

    function note(input, text, tone) {
        var el = input.parentNode.querySelector('.jm-compress-note');
        if (!el) {
            el = document.createElement('p');
            el.className = 'jm-compress-note';
            el.style.cssText = 'margin:6px 0 0;font-size:12px;font-weight:600;';
            input.parentNode.appendChild(el);
        }
        el.style.color = tone === 'bad' ? '#b45309' : '#047857';
        el.textContent = text;
    }

    function compress(input, file) {
        var maxEdge = parseInt(input.getAttribute('data-jm-max'), 10) || MAX_EDGE;
        var quality = parseFloat(input.getAttribute('data-jm-quality')) || QUALITY;

        var url = URL.createObjectURL(file);
        var img = new Image();

        img.onload = function () {
            var w = img.naturalWidth, h = img.naturalHeight;
            var scale = Math.min(1, maxEdge / Math.max(w, h));
            var tw = Math.round(w * scale), th = Math.round(h * scale);

            var canvas = document.createElement('canvas');
            canvas.width = tw; canvas.height = th;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, tw, th);
            URL.revokeObjectURL(url);

            var keepPng = (file.type === 'image/png') && hasAlpha(ctx, tw, th);
            var type = keepPng ? 'image/png' : 'image/jpeg';
            var name = file.name.replace(/\.[^.]+$/, '') + (keepPng ? '.png' : '.jpg');

            canvas.toBlob(function (blob) {
                if (!blob || blob.size >= file.size) {
                    // Re-encoding made it larger (already well optimised): keep the original.
                    note(input, 'Already optimised (' + readable(file.size) + ')');
                    return;
                }
                try {
                    var dt = new DataTransfer();
                    dt.items.add(new File([blob], name, { type: type, lastModified: Date.now() }));
                    input.files = dt.files;
                    note(input, 'Optimised ' + readable(file.size) + ' → ' + readable(blob.size)
                               + ' (' + tw + '×' + th + ')');
                } catch (e) {
                    note(input, 'Could not optimise automatically — uploading original.', 'bad');
                }
            }, type, quality);
        };

        img.onerror = function () {
            URL.revokeObjectURL(url);
            note(input, 'Could not read that image — uploading original.', 'bad');
        };

        img.src = url;
    }

    function handle(e) {
        var input = e.target;
        if (!input.files || !input.files.length) { return; }
        if (input.getAttribute('data-jm-compress') === 'off') { return; }

        var file = input.files[0];
        if (!/^image\//.test(file.type) || file.type === 'image/gif' || file.type === 'image/svg+xml') {
            return;   // animation and vectors would be destroyed by a canvas round-trip
        }
        if (file.size <= SKIP_UNDER) {
            note(input, 'Already small (' + readable(file.size) + ')');
            return;
        }
        compress(input, file);
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.tagName === 'INPUT' && e.target.type === 'file') { handle(e); }
    }, true);
})();
