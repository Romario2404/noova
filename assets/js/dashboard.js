/**
 * NOOVA S.A.C. - Dashboard Shared Logic
 */
const NOOVA = {
    API_URL: (window.BASE_PATH || './') + 'api',

    async request(endpoint, options = {}) {
        const config = {
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.getCSRF() },
            ...options
        };

        if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
            config.body = JSON.stringify(config.body);
        }

        if (config.body instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        const response = await fetch(this.API_URL + endpoint, config);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Error de conexión');
        }
        return data;
    },

    getCSRF() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    async loadUser() {
        try {
            const data = await this.request('/auth/session.php');
            if (data.logged_in) {
                this.user = data.user;
                document.documentElement.setAttribute('data-theme', data.user.theme || 'azul-corporativo');
                document.documentElement.setAttribute('data-mode', data.user.modo || 'light');
                return data.user;
            }
            return null;
        } catch {
            return null;
        }
    },

    async loadNotifications() {
        try {
            const data = await this.request('/notificaciones/listar.php?solo_no_leidas=1');
            const badge = document.getElementById('notif-badge');
            if (badge) {
                badge.textContent = data.no_leidas || 0;
                badge.style.display = (data.no_leidas || 0) > 0 ? 'flex' : 'none';
            }
            return data;
        } catch { return null; }
    },

    showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) {
            const div = document.createElement('div');
            div.id = 'toast-container';
            div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
            document.body.appendChild(div);
        }

        const toast = document.createElement('div');
        toast.style.cssText = `
            padding: 14px 20px; border-radius: 12px; background: var(--theme-surface, #fff);
            color: var(--theme-text, #333); box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            border-left: 4px solid ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#ffc107'};
            display: flex; align-items: center; gap: 10px;
            animation: slideInRight 0.3s ease; font-size: 14px; min-width: 280px;
            border: 1px solid var(--theme-border, #e0e0e0);
        `;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}" style="color:${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#ffc107'}"></i> ${message}`;

        document.getElementById('toast-container').appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    },

    renderTable(data, columns, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (!data || !data.length) {
            container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--theme-text-secondary)"><i class="fas fa-inbox"></i><p>No hay datos disponibles</p></div>';
            return;
        }

        let html = '<div class="table-container"><table class="table"><thead><tr>';
        columns.forEach(col => {
            html += `<th>${col.label}</th>`;
        });
        html += '<th>Acciones</th></tr></thead><tbody>';

        data.forEach(row => {
            html += '<tr>';
            columns.forEach(col => {
                let value = col.render ? col.render(row) : row[col.key];
                html += `<td>${value ?? '-'}</td>`;
            });
            html += `<td>${col.actions ? col.actions(row) : ''}</td>`;
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    },

    formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('es-PE', { year: 'numeric', month: 'short', day: 'numeric' });
    },

    formatCurrency(amount) {
        return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(amount || 0);
    },

    getStatusClass(status) {
        const map = {
            'planificacion': 'badge-info',
            'en_progreso': 'badge-primary',
            'completado': 'badge-success',
            'suspendido': 'badge-warning',
            'cancelado': 'badge-danger',
            'pendiente': 'badge-warning',
            'baja': 'badge-info',
            'media': 'badge-primary',
            'alta': 'badge-warning',
            'critica': 'badge-danger'
        };
        return map[status] || 'badge-default';
    },

    async logout() {
        try {
            await this.request('/auth/logout.php', { method: 'POST' });
        } catch {}
        window.location.href = '../../../';
    },

    async initCharts() {
        if (typeof Chart === 'undefined') return;

        // Line chart
        const lineCtx = document.getElementById('chart-line');
        if (lineCtx) {
            new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    datasets: [{
                        label: 'Proyectos',
                        data: [12, 19, 15, 22, 18, 25, 20, 28, 24, 30, 26, 35],
                        borderColor: getComputedStyle(document.documentElement).getPropertyValue('--theme-primary').trim() || '#0a4b7a',
                        backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--theme-primary-light').trim() || 'rgba(10,75,122,0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
                }
            });
        }

        // Doughnut chart
        const doughnutCtx = document.getElementById('chart-doughnut');
        if (doughnutCtx) {
            new Chart(doughnutCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Planificación', 'En Progreso', 'Completado', 'Suspendido'],
                    datasets: [{
                        data: [15, 30, 55, 5],
                        backgroundColor: ['#ffc107', '#0a4b7a', '#28a745', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } },
                    cutout: '70%'
                }
            });
        }
    }
};

// Style inject for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
`;
document.head.appendChild(style);

// Auto-init
document.addEventListener('DOMContentLoaded', async () => {
    await NOOVA.loadUser();

    // Toast container
    if (!document.getElementById('toast-container')) {
        const div = document.createElement('div');
        div.id = 'toast-container';
        div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
        document.body.appendChild(div);
    }

    // Notifications badge
    const notifBtn = document.querySelector('[data-notifications]');
    if (notifBtn) {
        const badge = document.createElement('span');
        badge.id = 'notif-badge';
        badge.style.cssText = 'position:absolute;top:-4px;right:-4px;background:#dc3545;color:white;font-size:10px;width:18px;height:18px;border-radius:50%;display:none;align-items:center;justify-content:center;font-weight:700;';
        notifBtn.style.position = 'relative';
        notifBtn.appendChild(badge);
        NOOVA.loadNotifications();
        setInterval(() => NOOVA.loadNotifications(), 30000);
    }

    // Logout buttons
    document.querySelectorAll('[data-logout]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            NOOVA.logout();
        });
    });

    // Sidebar toggle
    const toggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('show');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }
});
