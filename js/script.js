document.addEventListener('DOMContentLoaded', () => {
    /**
     * Dashboard Interactions
     */
    const btnNuevaSesion = document.getElementById('btn-nueva-sesion');

    if (btnNuevaSesion) {
        btnNuevaSesion.addEventListener('click', () => {
            btnNuevaSesion.classList.add('scale-95');
            setTimeout(() => {
                btnNuevaSesion.classList.remove('scale-95');
                window.location.href = 'plan_nuevo.php';
            }, 100);
        });
    }

    /**
     * Animaciones de Entrada (Staggered Entrance)
     */
    const animateElements = document.querySelectorAll('.glass-panel');
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '20px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.remove('opacity-0', 'translate-y-4');
                    entry.target.classList.add('opacity-100', 'translate-y-0');
                }, index * 50);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    animateElements.forEach(el => {
        el.classList.add('opacity-0', 'translate-y-4', 'transition-all', 'duration-700', 'ease-out');
        observer.observe(el);
    });
});
