<?php
/**
 * JOBMINGTON - stacked data tables on narrow screens.
 *
 * A table with a header row scrolls sideways on a phone, which is how you end
 * up dragging a seven-column list sideways to read one person's email. Given
 * class="jm-stacktable" it becomes one card per row instead, and the column
 * names are copied onto the cells at load so each value still says what it is.
 *
 * Emitted as its own style and script rather than living inside the admin
 * header, because admin/operations.php builds its own document and would
 * otherwise need a second copy of all of this.
 *
 * Selectors lead with `table.jm-stacktable` deliberately: that outranks both
 * the shared nowrap rule and the single-class Tailwind utilities some of these
 * tables carry, without depending on which body class the page happens to use.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

// A page may pull this in through more than one include path.
if (defined('JM_STACKTABLE_EMITTED')) {
    return;
}
define('JM_STACKTABLE_EMITTED', true);

$jmStackNonce = isset($cspNonce) && $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES) . '"' : '';
?>
<style<?= $jmStackNonce ?>>
@media (max-width: 768px) {
    .jm-tablewrap > table.jm-stacktable { min-width: 0; }

    table.jm-stacktable,
    table.jm-stacktable tbody,
    table.jm-stacktable tr,
    table.jm-stacktable td {
        display: block; width: 100%; box-sizing: border-box;
    }
    /* min-width here as well as on the wrapper: a table may set its own
       (operations sets 820px) and stacking has to override that too. An inline
       style="min-width:..." would still win, so those are moved into a
       desktop-only rule at the page instead. */
    table.jm-stacktable { border: 0; background: transparent; min-width: 0; }
    table.jm-stacktable thead { display: none; }

    table.jm-stacktable tr {
        background: #fff; border: 1px solid #e4eaf3; border-radius: 12px;
        margin-bottom: 10px; padding: 14px;
        overflow: hidden;   /* contains the floated thumbnail below */
    }
    /* Thumbnail beside the title, so the card opens with a proper heading
       rather than a stray rectangle on its own line. */
    table.jm-stacktable td.jm-cell-thumb {
        float: left; width: auto; margin: 0 12px 4px 0; padding: 0;
    }
    table.jm-stacktable td {
        border: 0; padding: 0;
        white-space: normal; overflow-wrap: anywhere;
    }
    table.jm-stacktable td[data-label] {
        display: flex; align-items: baseline; justify-content: space-between;
        gap: 14px; padding: 8px 0; border-top: 1px solid #f0f4f9;
    }
    table.jm-stacktable td[data-label]::before {
        content: attr(data-label);
        flex: none; font-size: 11px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .06em; color: #5b6b82;
    }
    /* A trailing actions cell has no column name, so it gets its own rule. */
    table.jm-stacktable td:last-child:not([data-label]) {
        padding-top: 12px; border-top: 1px solid #f0f4f9;
    }
    table.jm-stacktable td:first-child { padding-bottom: 2px; }
    /* An empty-state cell spans the card rather than becoming a labelled row. */
    table.jm-stacktable td[colspan] { padding: 10px 0; text-align: center; }
    /* Nothing inside a card may push it wider than the screen. A leading
       thumbnail column keeps a sensible size instead of filling the card. */
    table.jm-stacktable td img { max-width: 100%; height: auto; }

    /* A log is dense by nature and reads better staying a table: one card per
       line would turn a screenful of entries into a page of scrolling. It keeps
       the table shape and drops the columns that are context rather than
       content, so it still fits without going sideways. */
    table.jm-densetable { min-width: 0; }
    table.jm-densetable th, table.jm-densetable td {
        white-space: normal; overflow-wrap: anywhere;
        padding-left: 10px; padding-right: 10px; font-size: 12px;
    }
    table.jm-densetable .jm-col-optional { display: none; }
}
</style>
<script<?= $jmStackNonce ?>>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('table.jm-stacktable').forEach(function (table) {
        var heads = Array.prototype.map.call(
            table.querySelectorAll('thead th'),
            function (th) { return th.textContent.trim(); }
        );
        if (!heads.length) { return; }

        // The row's title is the first column that actually has a name. Several
        // of these tables lead with an unnamed thumbnail column, so assuming
        // column zero labelled the course title "Course" instead of letting it
        // head the card.
        var titleIndex = 0;
        for (var h = 0; h < heads.length; h++) {
            if (heads[h] !== '') { titleIndex = h; break; }
        }

        table.querySelectorAll('tbody tr').forEach(function (row) {
            Array.prototype.forEach.call(row.children, function (cell, i) {
                // Any unnamed column before the title is a thumbnail. Mark it so
                // the card can sit it beside the title rather than stranding a
                // lone rectangle on a line of its own.
                if (i < titleIndex && !cell.hasAttribute('colspan')) {
                    cell.classList.add('jm-cell-thumb');
                    return;
                }
                // An empty-state cell spans the card, and anything already
                // labelled by hand was labelled deliberately.
                if (i === titleIndex || cell.hasAttribute('data-label') || cell.hasAttribute('colspan')) {
                    return;
                }
                if (heads[i]) {
                    cell.setAttribute('data-label', heads[i]);
                }
            });
        });
    });
});
</script>
