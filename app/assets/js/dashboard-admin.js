/* ============================================================================
   Admin dashboard charts (Chart.js)
   Data arrives in window.FSTP_DASHBOARD, printed by admin/dashboard.php.
   ========================================================================= */
(function () {
    'use strict';

    var data = window.FSTP_DASHBOARD;
    if (!data || typeof Chart === 'undefined') { return; }

    var NAVY = '#1a3a6b';
    var PALETTE = ['#1e4f9c', '#2f7d4f', '#6a3fb5', '#c98a2b', '#b8242a',
                   '#0f8b8d', '#5c6b80', '#8e44ad', '#2980b9', '#16a085'];

    Chart.defaults.font.family = '"Segoe UI", Roboto, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6b7a90';

    /* ---------------- Expertise distribution (horizontal bar) --------------- */
    var expertiseCanvas = document.getElementById('chartExpertise');
    if (expertiseCanvas && data.expertise.length) {
        new Chart(expertiseCanvas, {
            type: 'bar',
            data: {
                labels: data.expertise.map(function (r) { return r.label; }),
                datasets: [{
                    label: 'Lecturers',
                    data: data.expertise.map(function (r) { return Number(r.total); }),
                    backgroundColor: PALETTE,
                    borderRadius: 6,
                    maxBarThickness: 34
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.parsed.x + ' lecturer(s)';
                            }
                        }
                    }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef1f5' } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    /* ----------------- Lecturers by department (doughnut) ------------------- */
    var deptCanvas = document.getElementById('chartDepartment');
    if (deptCanvas && data.department.length) {
        var total = data.department.reduce(function (sum, r) { return sum + Number(r.total); }, 0);

        new Chart(deptCanvas, {
            type: 'doughnut',
            data: {
                labels: data.department.map(function (r) {
                    var pct = total ? Math.round(Number(r.total) / total * 100) : 0;
                    return r.label + ' (' + pct + '%)';
                }),
                datasets: [{
                    data: data.department.map(function (r) { return Number(r.total); }),
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
                    legend: { position: 'right', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.parsed + ' lecturer(s)';
                            }
                        }
                    }
                }
            }
        });
    }
})();
