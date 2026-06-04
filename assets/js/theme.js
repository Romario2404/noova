const ThemeManager = {
  currentTheme: localStorage.getItem('noova-theme') || 'azul-corporativo',
  currentMode: localStorage.getItem('noova-mode') || 'light',

  themes: {
    'azul-corporativo': { name: 'Azul Corporativo', color: '#0a4b7a' },
    'celeste': { name: 'Celeste Profesional', color: '#0088cc' },
    'verde': { name: 'Verde Ejecutivo', color: '#1a7a4a' },
    'turquesa': { name: 'Turquesa Moderno', color: '#0a8a8a' },
    'morado': { name: 'Morado Corporativo', color: '#5a2a8a' },
    'azul-marino': { name: 'Azul Marino', color: '#0a2a5a' },
    'gris': { name: 'Gris Ejecutivo', color: '#3a4a5a' },
    'arena': { name: 'Arena Profesional', color: '#8a7a5a' },
    'cian': { name: 'Cian Tecnológico', color: '#0a6a8a' },
    'esmeralda': { name: 'Esmeralda', color: '#0a6a4a' },
    'indigo': { name: 'Índigo', color: '#2a3a8a' },
    'grafito': { name: 'Grafito Claro', color: '#4a5a6a' },
    'industrial': { name: 'Industrial Moderno', color: '#2a4a6a' },
    'premium': { name: 'Premium Corporativo', color: '#1a2a4a' },
    'empresarial': { name: 'Empresarial Claro', color: '#2a5a7a' }
  },

  init() {
    this.applyTheme(this.currentTheme);
    this.applyMode(this.currentMode);
    this.renderThemeSelector();
    this.renderModeToggle();
  },

  applyTheme(theme) {
    this.currentTheme = theme;
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('noova-theme', theme);
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (metaTheme && this.themes[theme]) {
      metaTheme.content = this.themes[theme].color;
    }
    document.querySelectorAll('.theme-option').forEach(el => {
      el.classList.toggle('active', el.dataset.theme === theme);
    });
    if (window.App && App.utils && App.utils.updateCharts) {
      App.utils.updateCharts();
    }
  },

  applyMode(mode) {
    this.currentMode = mode;
    if (mode === 'auto') {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-mode', prefersDark ? 'dark' : 'light');
      this.autoModeListener = (e) => {
        document.documentElement.setAttribute('data-mode', e.matches ? 'dark' : 'light');
      };
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', this.autoModeListener);
    } else {
      if (this.autoModeListener) {
        window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', this.autoModeListener);
        this.autoModeListener = null;
      }
      document.documentElement.setAttribute('data-mode', mode);
    }
    localStorage.setItem('noova-mode', mode);
    document.querySelectorAll('.mode-btn').forEach(el => {
      el.classList.toggle('active', el.dataset.mode === mode);
    });
    const bodyBg = mode === 'dark' ? '#1a1a2a' : '#f4f7fc';
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', bodyBg);
  },

  setTheme(theme) {
    this.applyTheme(theme);
    if (App.user && App.user.id) {
      fetch((window.BASE_PATH || './') + 'api/configuraciones/theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrf },
        body: JSON.stringify({ theme, user_id: App.user.id })
      }).catch(() => {});
    }
  },

  setMode(mode) {
    this.applyMode(mode);
    if (App.user && App.user.id) {
      fetch((window.BASE_PATH || './') + 'api/configuraciones/mode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrf },
        body: JSON.stringify({ mode, user_id: App.user.id })
      }).catch(() => {});
    }
  },

  renderThemeSelector() {
    const container = document.getElementById('theme-selector');
    if (!container) return;
    container.innerHTML = Object.entries(this.themes).map(([key, val]) => `
      <button class="theme-option ${key === this.currentTheme ? 'active' : ''}"
              data-theme="${key}"
              style="background: ${val.color}"
              title="${val.name}"
              onclick="ThemeManager.setTheme('${key}')">
      </button>
    `).join('');
  },

  renderModeToggle() {
    const container = document.getElementById('mode-toggle');
    if (!container) return;
    container.innerHTML = ['light', 'dark', 'auto'].map(mode => `
      <button class="mode-btn ${mode === this.currentMode ? 'active' : ''}"
              data-mode="${mode}"
              onclick="ThemeManager.setMode('${mode}')">
        ${mode === 'light' ? '☀️' : mode === 'dark' ? '🌙' : '🔄'} ${mode.charAt(0).toUpperCase() + mode.slice(1)}
      </button>
    `).join('');
  }
};

document.addEventListener('DOMContentLoaded', () => ThemeManager.init());
