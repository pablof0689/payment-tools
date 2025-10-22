(function () {
    const toggle = document.querySelector('.menu-toggle');
    const nav = document.querySelector('#primary-navigation');

    if (!toggle || !nav) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isOpen = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });
})();
