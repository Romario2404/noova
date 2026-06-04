/**
 * NOOVA S.A.C. - GSAP Advanced Animations
 */
(function() {
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
        document.addEventListener('DOMContentLoaded', initGSAP);
        return;
    }
    initGSAP();

    function initGSAP() {
        gsap.registerPlugin(ScrollTrigger);

        // Hero parallax
        const hero = document.querySelector('.hero');
        if (hero) {
            gsap.to('.hero-bg', {
                scale: 1.1,
                ease: 'none',
                scrollTrigger: {
                    trigger: hero,
                    start: 'top top',
                    end: 'bottom top',
                    scrub: true
                }
            });

            gsap.from('.hero-content', {
                opacity: 0,
                y: 60,
                duration: 1.2,
                ease: 'power3.out'
            });

            gsap.from('.hero-badge', {
                opacity: 0,
                y: 30,
                duration: 0.8,
                delay: 0.2,
                ease: 'power3.out'
            });

            gsap.from('.hero-title', {
                opacity: 0,
                y: 40,
                duration: 1,
                delay: 0.4,
                ease: 'power3.out'
            });

            gsap.from('.hero-description', {
                opacity: 0,
                y: 30,
                duration: 0.8,
                delay: 0.6,
                ease: 'power3.out'
            });

            gsap.from('.hero-actions .btn', {
                opacity: 0,
                y: 20,
                duration: 0.6,
                stagger: 0.15,
                delay: 0.8,
                ease: 'back.out(1.7)'
            });

            gsap.from('.hero-scroll', {
                opacity: 0,
                y: -20,
                duration: 0.6,
                delay: 1.4,
                ease: 'power3.out'
            });
        }

        // Stagger cards
        const staggerCards = (selector, trigger) => {
            const cards = document.querySelectorAll(selector);
            if (!cards.length) return;
            gsap.from(cards, {
                opacity: 0,
                y: 50,
                duration: 0.6,
                stagger: 0.1,
                ease: 'power3.out',
                scrollTrigger: {
                    trigger: trigger || cards[0].parentElement,
                    start: 'top 85%',
                    toggleActions: 'play none none reverse'
                }
            });
        };

        staggerCards('.service-card', '#servicios');
        staggerCards('.portfolio-item');
        staggerCards('.testimonial-card');
        staggerCards('.news-card', '#noticias');
        staggerCards('.indicator-card', '.indicators');

        // Contact form
        const contactForm = document.querySelector('.contact-grid');
        if (contactForm) {
            gsap.from('.contact-info', {
                opacity: 0,
                x: -50,
                duration: 0.8,
                scrollTrigger: {
                    trigger: contactForm,
                    start: 'top 85%'
                }
            });
            gsap.from('.contact-grid .card', {
                opacity: 0,
                x: 50,
                duration: 0.8,
                scrollTrigger: {
                    trigger: contactForm,
                    start: 'top 85%'
                }
            });
        }

        // Counter animation with GSAP
        document.querySelectorAll('.indicator-number[data-count]').forEach(el => {
            gsap.from(el, {
                innerText: 0,
                duration: 2,
                ease: 'power2.out',
                snap: { innerText: 1 },
                scrollTrigger: {
                    trigger: el,
                    start: 'top 90%'
                },
                onUpdate: function() {
                    const target = parseInt(el.dataset.count);
                    const current = Math.round(this.progress() * target);
                    el.textContent = current + (el.dataset.suffix || '');
                }
            });
        });

        // Navbar scroll effect
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            ScrollTrigger.create({
                start: 'top -80px',
                onUpdate: (self) => {
                    if (self.progress > 0) {
                        navbar.classList.add('navbar-scrolled');
                    } else {
                        navbar.classList.remove('navbar-scrolled');
                    }
                }
            });
        }

        // Section reveal
        document.querySelectorAll('[data-reveal]').forEach(section => {
            gsap.from(section.querySelector('.section-header'), {
                opacity: 0,
                y: 40,
                duration: 0.6,
                scrollTrigger: {
                    trigger: section,
                    start: 'top 80%',
                    toggleActions: 'play none none reverse'
                }
            });
        });

        // Footer
        gsap.from('.footer-grid > div', {
            opacity: 0,
            y: 30,
            duration: 0.6,
            stagger: 0.1,
            ease: 'power3.out',
            scrollTrigger: {
                trigger: '.footer',
                start: 'top 90%'
            }
        });
    }
})();
