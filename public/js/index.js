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
})();
