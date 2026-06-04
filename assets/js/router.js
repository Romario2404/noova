/**
 * NOOVA S.A.C. - SPA Router
 * Single Page Application router for smooth navigation without full page reloads.
 */
(function() {
  'use strict';

  const ROUTER = {
    initialized: false,
    contentId: 'app-content',
    shellSelectors: ['.navbar', '.sidebar', '.sidebar-overlay', '.footer', '.whatsapp-float', '#sidebar', '#navbar'],

    init() {
      if (this.initialized) return;
      this.initialized = true;

      // Mark current content area
      this.markContent();

      // Intercept all internal navigation clicks
      document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        if (link.getAttribute('target') === '_blank') return;
        if (link.getAttribute('href') === '#') { e.preventDefault(); return; }
        if (link.getAttribute('href')?.startsWith('tel:') || link.getAttribute('href')?.startsWith('mailto:')) return;
        if (link.getAttribute('href')?.startsWith('#') || link.getAttribute('href')?.startsWith('javascript:')) return;
        if (link.hasAttribute('data-router-disabled')) return;
        if (!this.isInternalLink(link.href)) return;
        if (link.getAttribute('href') === window.location.pathname) { e.preventDefault(); return; }

        e.preventDefault();
        this.navigate(link.href);
      });

      // Handle back/forward browser buttons
      window.addEventListener('popstate', (e) => {
        if (e.state && e.state.url) {
          this.load(e.state.url, true);
        }
      });

      // Skip router for dashboard/portal pages (they are standalone SPAs)
      this.isDashboardPage = window.location.pathname.includes('/aula_virtual/');
    },

    isInternalLink(href) {
      try {
        const url = new URL(href, window.location.origin);
        // Only handle same-origin links
        if (url.hostname !== window.location.hostname) return false;
        // Skip dashboard pages
        if (url.pathname.includes('/aula_virtual/')) return false;
        // Skip links with #hash (same-page anchors)
        if (url.pathname === window.location.pathname && url.hash) return false;
        return true;
      } catch {
        return false;
      }
    },

    markContent() {
      // Wrap all content between navbar and footer in #app-content
      const navbar = document.querySelector('.navbar');
      const footer = document.querySelector('.footer');
      if (!navbar || !footer) return;
      if (document.getElementById(this.contentId)) return;

      let current = navbar.nextElementSibling;
      const contentNodes = [];
      while (current && current !== footer) {
        const next = current.nextElementSibling;
        contentNodes.push(current);
        current = next;
      }

      if (contentNodes.length) {
        const wrapper = document.createElement('main');
        wrapper.id = this.contentId;
        contentNodes.forEach(node => wrapper.appendChild(node));
        navbar.parentNode.insertBefore(wrapper, footer);
      }
    },

    getBasePath() {
      const path = window.location.pathname;
      const parts = path.split('/').filter(Boolean);
      return '/' + parts.slice(0, parts.length - 1).join('/');
    },

    async navigate(url) {
      const fullUrl = new URL(url, window.location.origin);
      await this.load(fullUrl.href);
      window.history.pushState({ url: fullUrl.href }, '', fullUrl.href);
      this.updateActiveNav(fullUrl.pathname);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    async load(url, isPopState = false) {
      const contentEl = document.getElementById(this.contentId);
      if (!contentEl) return;

      try {
        // Show loading state
        contentEl.style.opacity = '0.4';
        contentEl.style.transition = 'opacity 0.2s';

        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Page load failed');

        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // Extract new content
        const newContent = doc.getElementById(this.contentId);
        if (newContent) {
          contentEl.innerHTML = newContent.innerHTML;
        } else {
          // Fallback: try to find main content in the page body
          const body = doc.querySelector('body');
          if (body) {
            // Remove shell elements
            const tempContent = document.createElement('div');
            Array.from(body.children).forEach(child => {
              const tag = child.tagName?.toLowerCase();
              const id = child.id || '';
              const isShell = this.shellSelectors.some(s => {
                if (s.startsWith('.')) return child.classList.contains(s.slice(1));
                if (s.startsWith('#')) return id === s.slice(1);
                return false;
              });
              if (!isShell && tag !== 'script' && tag !== 'style') {
                tempContent.appendChild(child.cloneNode(true));
              }
            });
            contentEl.innerHTML = tempContent.innerHTML;
          }
        }

        // Inject page-specific styles
        this.injectPageStyles(doc);

        // Update document title
        const title = doc.querySelector('title');
        if (title) document.title = title.textContent;

        // Update meta tags
        const description = doc.querySelector('meta[name="description"]');
        if (description) {
          let existing = document.querySelector('meta[name="description"]');
          if (!existing) {
            existing = document.createElement('meta');
            existing.name = 'description';
            document.head.appendChild(existing);
          }
          existing.content = description.content;
        }

        // Re-execute page-specific scripts
        this.executePageScripts(doc, url);

        // Re-initialize page behaviors
        this.reinitPage(url);

        if (!isPopState) {
          this.updateActiveNav(new URL(url).pathname);
        }

        contentEl.style.opacity = '1';

      } catch (err) {
        console.warn('Router fallback - loading page directly:', err.message);
        contentEl.style.opacity = '1';
        // Fallback: direct navigation
        if (!isPopState) {
          window.location.href = url;
        }
      }
    },

    executePageScripts(doc, url) {
      // Ensure Chart.js is loaded globally for pages that need it
      if (!window.Chart && doc.querySelector('script[src*="chart.js"]')) {
        const chartSrc = doc.querySelector('script[src*="chart.js"]').src;
        const script = document.createElement('script');
        script.src = chartSrc;
        document.head.appendChild(script);
      }

      // Execute page-specific inline scripts (skip common/shared scripts)
      const scripts = doc.querySelectorAll('script:not([src*="theme.js"]):not([src*="main.js"]):not([src*="dashboard.js"]):not([src*="router.js"]):not([src*="gsap-animations.js"]):not([src*="font-awesome"])');
      scripts.forEach(oldScript => {
        if (oldScript.src && oldScript.src.includes('chart.js')) return;
        if (oldScript.src) return; // Skip other external scripts

        if (oldScript.textContent.trim()) {
          try {
            const newScript = document.createElement('script');
            newScript.textContent = oldScript.textContent;
            document.body.appendChild(newScript);
            document.body.removeChild(newScript);
          } catch (e) {
            // Ignore execution errors
          }
        }
      });
    },

    reinitPage(url) {
      const pathname = new URL(url).pathname;

      setTimeout(() => {
        // Re-init App features
        if (typeof App !== 'undefined') {
          App.setupScrollEffects();
          App.setupCounters();
          App.setupPortfolioFilters();
          App.setupForms();
          App.setupParticles();
        }

        // Re-init GSAP
        if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
          ScrollTrigger.refresh();
          setTimeout(() => ScrollTrigger.refresh(), 300);
        }

        // Re-init counters
        document.querySelectorAll('.indicator-number[data-count]').forEach(el => {
          if (typeof App !== 'undefined') {
            App.animateCounter(el, parseInt(el.dataset.count), 2000, el.dataset.suffix || '');
          }
        });

        // Re-trigger section reveal animations
        document.querySelectorAll('[data-reveal]').forEach(el => {
          el.style.opacity = '0';
          el.style.transform = 'translateY(30px)';
        });
        if (typeof App !== 'undefined') {
          App.handleRevealAnimations();
        }

        // Re-setup Three.js for pages with hero-3d
        if (pathname === '/' || pathname === '/index.html' || pathname === '') {
          if (typeof App !== 'undefined') {
            App.setupThreeHero();
          }
        }
      }, 50);
    },

    injectPageStyles(doc) {
      // Remove previously injected page styles
      document.querySelectorAll('[data-router-style]').forEach(s => s.remove());

      // Collect page-specific styles from loaded document
      const styles = doc.querySelectorAll('style:not([data-global])');
      styles.forEach(style => {
        const newStyle = document.createElement('style');
        newStyle.setAttribute('data-router-style', '');
        newStyle.textContent = style.textContent;
        document.head.appendChild(newStyle);
      });
    },

    updateActiveNav(pathname) {
      document.querySelectorAll('.navbar-links a, .sidebar-nav a').forEach(link => {
        const href = link.getAttribute('href');
        link.classList.remove('active');
        if (href === pathname || 
            (pathname.startsWith(href) && href !== '/') ||
            (href === '/' && (pathname === '/' || pathname === ''))) {
          link.classList.add('active');
        }
      });
    },

    // Public: manually navigate to a URL
    go(url) {
      this.navigate(url);
    }
  };

  // Auto-init when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ROUTER.init());
  } else {
    ROUTER.init();
  }

  // Expose globally
  window.Router = ROUTER;

})();
