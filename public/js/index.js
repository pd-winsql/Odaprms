(() => {
    'use strict';
    const header = document.querySelector('.vd-landing-header');
    const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 12);
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    const revealElements = document.querySelectorAll('[data-landing-reveal]');
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px' });
        revealElements.forEach(element => observer.observe(element));
    } else {
        revealElements.forEach(element => element.classList.add('is-visible'));
    }

    const navMenu = document.getElementById('navMenu');
    document.querySelectorAll('#navMenu a[href^="#"]').forEach(link => {
        link.addEventListener('click', () => {
            if (!navMenu?.classList.contains('show') || typeof bootstrap === 'undefined') return;
            bootstrap.Collapse.getOrCreateInstance(navMenu, { toggle: false }).hide();
        });
    });

    const sectionLinks = [...document.querySelectorAll('.vd-landing-links a[href^="#"]')];
    const observedSections = sectionLinks
        .map(link => document.querySelector(link.getAttribute('href')))
        .filter(Boolean);
    if ('IntersectionObserver' in window && observedSections.length) {
        const visibleSections = new Map();
        const updateCurrentSection = () => {
            const current = [...visibleSections.entries()]
                .filter(([, ratio]) => ratio > 0)
                .sort((a, b) => b[1] - a[1])[0]?.[0];
            sectionLinks.forEach(link => {
                if (link.getAttribute('href') === `#${current}`) {
                    link.setAttribute('aria-current', 'true');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        };
        const sectionObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => visibleSections.set(entry.target.id, entry.intersectionRatio));
            updateCurrentSection();
        }, { rootMargin: '-20% 0px -55%', threshold: [0, 0.15, 0.35, 0.6] });
        observedSections.forEach(section => sectionObserver.observe(section));
    }
})();
