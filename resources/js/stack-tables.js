// Tables marked `stack-cards` become one card per row on a phone (see the
// STACKED TABLE CARDS section of resources/css/app.css). This labels each
// cell with its column heading, so the card can show "Class  JSS 3", and
// gives the cells their part on the card:
//
//   lead     the S/N and a row's tick box, on the card's first line
//   title    the first real column (usually the name), beside them
//   actions  an "Actions" or unnamed last column, along the card's foot
//   full     a cell spanning the whole row, such as "No students yet"
//   field    everything else, one labelled line each
//
// A cell can set its own data-label or data-stack, and those are kept.
// Rows added later (filters, Alpine lists) are labelled as they appear.

const SERIAL = /^(s\/?n|#|no\.?)$/i;
const ACTIONS = /^actions?$/i;

function headings(table) {
    const row = table.querySelector('thead tr:last-child');

    if (!row) {
        return [];
    }

    const names = [];

    for (const th of row.children) {
        const text = th.textContent.replace(/\s+/g, ' ').trim();
        const span = th.colSpan || 1;

        for (let i = 0; i < span; i++) {
            names.push({ text, checkbox: !!th.querySelector('input[type="checkbox"]') });
        }
    }

    return names;
}

function label(table) {
    const heads = headings(table);
    const titleIndex = heads.findIndex((head) => head.text !== '' && !SERIAL.test(head.text) && !ACTIONS.test(head.text));

    for (const tbody of table.tBodies) {
        for (const tr of tbody.rows) {
            const cells = [...tr.cells];
            let column = 0;

            cells.forEach((td, position) => {
                const span = td.colSpan || 1;
                const head = heads[column] ?? { text: '', checkbox: false };

                if (!td.hasAttribute('data-label')) {
                    td.setAttribute('data-label', head.text);
                }

                if (!td.dataset.stack) {
                    let role = 'field';

                    if (span > 1 && span >= heads.length - 1) {
                        role = 'full';
                    } else if (SERIAL.test(head.text) || head.checkbox || (head.text === '' && td.querySelector('input[type="checkbox"]') && td.textContent.trim() === '')) {
                        role = 'lead';
                    } else if (column === titleIndex) {
                        role = 'title';
                    } else if (ACTIONS.test(head.text) || (head.text === '' && position === cells.length - 1)) {
                        role = 'actions';
                    }

                    td.dataset.stack = role;
                }

                column += span;
            });
        }
    }
}

function watch(table) {
    label(table);

    // At most once a frame: typing in a cell (a counter updating) changes
    // the table too, and needs no relabelling of its own.
    let queued = false;

    new MutationObserver(() => {
        if (queued) {
            return;
        }

        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            label(table);
        });
    }).observe(table, { childList: true, subtree: true });
}

function init() {
    document.querySelectorAll('table.stack-cards').forEach((table) => {
        if (table.dataset.stackReady) {
            return;
        }

        table.dataset.stackReady = '1';
        watch(table);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

// Pages that swap content in without a full reload.
document.addEventListener('alpine:initialized', init);
window.addEventListener('pageshow', init);
