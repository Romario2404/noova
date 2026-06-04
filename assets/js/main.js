const App = {
  csrf: '',
  user: null,
  config: {
        apiUrl: (window.BASE_PATH || './') + 'api',
    debug: false
  },

  init() {
    this.setupNavigation();
    this.setupScrollEffects();
    this.setupCounters();
    this.setupPortfolioFilters();
    this.setupForms();
    this.setupParticles();
    this.setupThreeHero();
    this.setupMobileMenu();
    this.loadUserConfig();
    this.log('App initialized');
  },

  setupNavigation() {
    // Only handle same-page anchor links (with #)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  },

  setupScrollEffects() {
    const navbar = document.querySelector('.navbar');
    const scrollBtn = document.querySelector('.hero-scroll');
    
    if (scrollBtn) {
      scrollBtn.addEventListener('click', () => {
        document.querySelector('.indicators')?.scrollIntoView({ behavior: 'smooth' });
      });
    }

    let ticking = false;
    window.addEventListener('scroll', () => {
      if (!ticking) {
        requestAnimationFrame(() => {
          if (navbar) {
            navbar.classList.toggle('scrolled', window.scrollY > 80);
          }
          this.handleRevealAnimations();
          ticking = false;
        });
        ticking = true;
      }
    });

    const revealElements = document.querySelectorAll('[data-reveal]');
    revealElements.forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(30px)';
      el.style.transition = 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
    });
    this.handleRevealAnimations();
  },

  handleRevealAnimations() {
    document.querySelectorAll('[data-reveal]').forEach(el => {
      const rect = el.getBoundingClientRect();
      const isVisible = rect.top < window.innerHeight * 0.85;
      if (isVisible) {
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
      }
    });
  },

  setupCounters() {
    const counters = document.querySelectorAll('[data-count]');
    if (!counters.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const el = entry.target;
          const target = parseInt(el.dataset.count);
          const duration = parseInt(el.dataset.duration) || 2000;
          const suffix = el.dataset.suffix || '';
          this.animateCounter(el, target, duration, suffix);
          observer.unobserve(el);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach(el => observer.observe(el));
  },

  animateCounter(el, target, duration, suffix) {
    const start = performance.now();
    const step = (timestamp) => {
      const progress = Math.min((timestamp - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(eased * target);
      el.textContent = current.toLocaleString() + suffix;
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = target.toLocaleString() + suffix;
      }
    };
    requestAnimationFrame(step);
  },

  setupPortfolioFilters() {
    const filterContainer = document.querySelector('.portfolio-filters');
    if (!filterContainer) return;

    filterContainer.addEventListener('click', (e) => {
      const btn = e.target.closest('.filter-btn');
      if (!btn) return;

      filterContainer.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.dataset.filter;
      document.querySelectorAll('.portfolio-item').forEach(item => {
        if (filter === 'all' || item.dataset.category === filter) {
          item.style.display = 'block';
          item.style.animation = 'fadeInUp 0.5s ease';
        } else {
          item.style.display = 'none';
        }
      });
    });
  },

  setupForms() {
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn?.innerHTML;

        if (btn) {
          btn.disabled = true;
          btn.innerHTML = 'Enviando...';
        }

        try {
          const formData = new FormData(form);
          const response = await fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': App.csrf }
          });

          if (response.ok) {
            this.showToast('Mensaje enviado con éxito', 'success');
            form.reset();
          } else {
            throw new Error('Error al enviar');
          }
        } catch (err) {
          this.showToast('Error al enviar el mensaje. Intente nuevamente.', 'error');
        } finally {
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText || 'Enviar';
          }
        }
      });
    });
  },

  setupParticles() {
    const container = document.getElementById('hero-particles');
    if (!container) return;

    const count = 50;
    for (let i = 0; i < count; i++) {
      const particle = document.createElement('div');
      particle.className = 'particle';
      const size = Math.random() * 4 + 2;
      particle.style.width = size + 'px';
      particle.style.height = size + 'px';
      particle.style.left = Math.random() * 100 + '%';
      particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
      particle.style.animationDelay = (Math.random() * 10) + 's';
      particle.style.opacity = Math.random() * 0.5 + 0.2;
      container.appendChild(particle);
    }
  },

  setupThreeHero() {
    if (typeof THREE === 'undefined' || !document.getElementById('hero-3d')) return;

    const container = document.getElementById('hero-3d');
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);

    const geometry = new THREE.IcosahedronGeometry(1.5, 1);
    const material = new THREE.MeshPhongMaterial({
      color: 0x4db8e8,
      wireframe: true,
      transparent: true,
      opacity: 0.3,
      emissive: 0x1a7bc4,
      emissiveIntensity: 0.1
    });
    const mesh = new THREE.Mesh(geometry, material);
    scene.add(mesh);

    const wireframeGeo = new THREE.IcosahedronGeometry(2, 0);
    const wireframeMat = new THREE.MeshBasicMaterial({
      color: 0x4db8e8,
      wireframe: true,
      transparent: true,
      opacity: 0.1
    });
    const wireframeMesh = new THREE.Mesh(wireframeGeo, wireframeMat);
    scene.add(wireframeMesh);

    const particlesGeo = new THREE.BufferGeometry();
    const particlesCount = 1000;
    const posArray = new Float32Array(particlesCount * 3);
    for (let i = 0; i < particlesCount * 3; i++) {
      posArray[i] = (Math.random() - 0.5) * 20;
    }
    particlesGeo.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
    const particlesMat = new THREE.PointsMaterial({
      size: 0.02,
      color: 0xffffff,
      transparent: true,
      opacity: 0.4,
      blending: THREE.AdditiveBlending
    });
    const particlesMesh = new THREE.Points(particlesGeo, particlesMat);
    scene.add(particlesMesh);

    const light1 = new THREE.DirectionalLight(0x4db8e8, 1);
    light1.position.set(2, 2, 2);
    scene.add(light1);
    const light2 = new THREE.DirectionalLight(0xffffff, 0.5);
    light2.position.set(-2, -2, -2);
    scene.add(light2);

    camera.position.z = 4;

    let mouseX = 0, mouseY = 0;
    document.addEventListener('mousemove', (e) => {
      mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
      mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
    });

    const animate = () => {
      requestAnimationFrame(animate);
      mesh.rotation.x += 0.003;
      mesh.rotation.y += 0.005;
      wireframeMesh.rotation.x -= 0.002;
      wireframeMesh.rotation.y -= 0.003;
      particlesMesh.rotation.y += 0.0005;
      
      mesh.position.x += (mouseX * 0.5 - mesh.position.x) * 0.02;
      mesh.position.y += (-mouseY * 0.5 - mesh.position.y) * 0.02;
      wireframeMesh.position.x = mesh.position.x;
      wireframeMesh.position.y = mesh.position.y;

      renderer.render(scene, camera);
    };
    animate();

    window.addEventListener('resize', () => {
      camera.aspect = container.clientWidth / container.clientHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(container.clientWidth, container.clientHeight);
    });
  },

  setupMobileMenu() {
    const toggle = document.querySelector('.navbar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const close = document.querySelector('.sidebar-close');

    if (toggle && sidebar) {
      toggle.addEventListener('click', () => {
        sidebar.classList.add('open');
        overlay?.classList.add('open');
        document.body.style.overflow = 'hidden';
      });
    }

    const closeMenu = () => {
      sidebar?.classList.remove('open');
      overlay?.classList.remove('open');
      document.body.style.overflow = '';
    };

    close?.addEventListener('click', closeMenu);
    overlay?.addEventListener('click', closeMenu);

    document.querySelectorAll('.sidebar-nav a').forEach(link => {
      link.addEventListener('click', closeMenu);
    });
  },

  async loadUserConfig() {
    try {
      const resp = await fetch((window.BASE_PATH || './') + 'api/auth/session.php');
      if (resp.ok) {
        const data = await resp.json();
        if (data.user) {
          this.user = data.user;
          if (data.user.theme) ThemeManager.setTheme(data.user.theme);
          if (data.user.mode) ThemeManager.setMode(data.user.mode);
        }
      }
    } catch (e) {
      // Not logged in
    }
  },

  showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    Object.assign(toast.style, {
      position: 'fixed',
      bottom: '24px',
      right: '24px',
      padding: '14px 24px',
      background: type === 'success' ? 'var(--theme-success)' : type === 'error' ? 'var(--theme-danger)' : 'var(--theme-primary)',
      color: '#ffffff',
      borderRadius: '12px',
      fontWeight: '500',
      fontSize: '0.9rem',
      boxShadow: '0 8px 32px rgba(0,0,0,0.15)',
      zIndex: '9999',
      opacity: '0',
      transform: 'translateY(20px)',
      transition: 'all 0.3s ease',
      maxWidth: '400px'
    });
    document.body.appendChild(toast);

    requestAnimationFrame(() => {
      toast.style.opacity = '1';
      toast.style.transform = 'translateY(0)';
    });

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(20px)';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  },

  log(...args) {
    if (this.config.debug) console.log('[Noova]', ...args);
  },

  utils: {
    formatDate(date) {
      return new Date(date).toLocaleDateString('es-PE', {
        year: 'numeric', month: 'long', day: 'numeric'
      });
    },

    formatCurrency(n) {
      return new Intl.NumberFormat('es-PE', {
        style: 'currency', currency: 'PEN'
      }).format(n);
    },

    getStatusClass(status) {
      const map = {
        'activo': 'badge-active',
        'pendiente': 'badge-pending',
        'completado': 'badge-completed',
        'en_progreso': 'badge-active',
        'revisado': 'badge-completed'
      };
      return map[status] || 'badge-pending';
    },

    debounce(fn, delay = 300) {
      let timer;
      return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
      };
    },

    async loadScript(src) {
      return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      });
    },

    async loadStyle(href) {
      return new Promise((resolve, reject) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.onload = resolve;
        link.onerror = reject;
        document.head.appendChild(link);
      });
    },

    updateCharts() {
      document.querySelectorAll('.chart-container canvas').forEach(canvas => {
        const chartId = canvas.id;
        if (window['chart_' + chartId] && typeof window['chart_' + chartId].update === 'function') {
          window['chart_' + chartId].update();
        }
      });
    }
  }
};

document.addEventListener('DOMContentLoaded', () => App.init());
