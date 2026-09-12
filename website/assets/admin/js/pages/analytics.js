/* Web Analytics — not built yet. */
(function () {
    'use strict';
    window.TMH.boot(function () {
        document.getElementById('pageHead').innerHTML = window.TMH.layout.pageHead({
            title: 'Web Analytics',
        });
        document.getElementById('view').innerHTML =
            '<article class="card"><div class="empty">'
            + '<div class="empty__art"><i class="fa-solid fa-hammer"></i></div>'
            + '<h3>Screen not built yet</h3>'
            + '<p>The shell is generated; the page script is next.</p></div></article>';
    });
}());
