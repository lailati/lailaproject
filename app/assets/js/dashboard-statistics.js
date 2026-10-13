/* ============================================================================
   Statistics page charts (Chart.js)
   Data arrives in window.FSTP_STATS, printed by admin/statistics.php.
   Loaded AFTER chart.umd.min.js (see $extra_js in that page).
   ========================================================================= */
(function () {
    'use strict';

    var data = window.FSTP_STATS;
    if (!data || typeof Chart === 'undefined') { return; }

    var PALETTE = ['#1e4f9c', '#2f7d4f', '#6a3fb5', '#c98a2b', '#b8242a',
                   '#0f8b8d', '#5c6b80', '#8e44ad', '#2980b9', '#16a085',
                   '#d35400', '#7f8c8d'];

    Chart.defaults.font.family = '"Segoe UI", Roboto, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6b7a90';

    function horizontalBar(id, rows, colourShift) {
        var canvas = document.getElementById(id);
        if (!canvas || !rows.length) { return; }
        var colours = rows.map(function (r, i) { return PALETTE[(i + (colourShift || 0)) % PALETTE.length]; });

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(function (r) { return r.label; }),
                datasets: [{
                    data: rows.map(function (r) { return Number(r.total); }),
                    backgroundColor: colours,
                    borderRadius: 6,
                    maxBarThickness: 30
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef1f5' } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    function doughnut(id, rows) {
        var canvas = document.getElementById(id);
        if (!canvas || !rows.length) { return; }
        var total = rows.reduce(function (s, r) { return s + Number(r.total); }, 0);

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: rows.map(function (r) {
                    var pct = total ? Math.round(Number(r.total) / total * 100) : 0;
                    return r.label + ' (' + pct + '%)';
                }),
                datasets: [{
                    data: rows.map(function (r) { return Number(r.total); }),
                    backgroundColor: PALETTE,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } }
                }
            }
        });
    }

    horizontalBar('chartCategory', data.category);
    doughnut('chartLevel', data.level);
    doughnut('chartDepartment', data.department);

    /* Publications vs research by year - grouped columns */
    var yearsCanvas = document.getElementById('chartYears');
    if (yearsCanvas) {
        var years = Array.from(new Set(
            data.publicationsByYear.map(function (r) { return r.label; })
                .concat(data.researchByYear.map(function (r) { return r.label; }))
        )).sort();

        function series(rows) {
            var map = {};
            rows.forEach(function (r) { map[r.label] = Number(r.total); });
            return years.map(function (y) { return map[y] || 0; });
        }

        new Chart(yearsCanvas, {
            type: 'bar',
            data: {
                labels: years,
                datasets: [
                    { label: 'Publications', data: series(data.publicationsByYear), backgroundColor: '#1e4f9c', borderRadius: 4, maxBarThickness: 26 },
                    { label: 'Research',     data: series(data.researchByYear),     backgroundColor: '#2f7d4f', borderRadius: 4, maxBarThickness: 26 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef1f5' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
})();
