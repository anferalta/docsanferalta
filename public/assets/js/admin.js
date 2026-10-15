/* ============================================================
   ADMIN.JS — ANFERALTA PREMIUM
============================================================ */

document.addEventListener('DOMContentLoaded', () => {

    /* ============================================================
       1) DARK MODE TOGGLE
    ============================================================ */

    const btnDark = document.getElementById('btnDarkMode');

    if (btnDark) {
        btnDark.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        });

        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    }


    /* ============================================================
       2) TROCA DE TEMAS
    ============================================================ */

    const themeButtons = document.querySelectorAll('[data-theme]');

    themeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const theme = btn.dataset.theme;

            document.body.classList.remove('theme-blue', 'theme-green', 'theme-gray');
            document.body.classList.add(theme);

            localStorage.setItem('theme', theme);
        });
    });

    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.body.classList.add(savedTheme);
    }


    /* ============================================================
       3) MINI-MAPA DA TIMELINE
    ============================================================ */

    const scroll = document.getElementById('timelineScroll');
    const bar = document.getElementById('timelineMinimapBar');

    if (scroll && bar) {

        let ticking = false;

        function updateMinimap() {
            const total = scroll.scrollHeight - scroll.clientHeight;
            const ratio = scroll.scrollTop / (total || 1);
            const height = (scroll.clientHeight / scroll.scrollHeight) * 100;

            bar.style.height = `${height}%`;
            bar.style.top = `${ratio * (100 - height)}%`;
        }

        scroll.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    updateMinimap();
                    ticking = false;
                });
                ticking = true;
            }
        });

        window.addEventListener('resize', updateMinimap);

        updateMinimap();
    }


    /* ============================================================
       4) FILTROS AVANÇADOS
    ============================================================ */

    const filtroArea = document.getElementById('filtroArea');
    const filtroEstado = document.getElementById('filtroEstado');
    const timelineItems = document.querySelectorAll('.timeline-item');

    function aplicarFiltros() {
        const area = filtroArea ? filtroArea.value.toLowerCase() : '';
        const estado = filtroEstado ? filtroEstado.value.toLowerCase() : '';

        timelineItems.forEach(item => {
            const areaItem = item.dataset.area?.toLowerCase() || '';
            const estadoItem = item.dataset.estado?.toLowerCase() || '';

            const mostrar =
                (area === '' || areaItem.includes(area)) &&
                (estado === '' || estadoItem.includes(estado));

            item.style.display = mostrar ? '' : 'none';
        });
    }

    if (filtroArea) filtroArea.addEventListener('change', aplicarFiltros);
    if (filtroEstado) filtroEstado.addEventListener('change', aplicarFiltros);


    /* ============================================================
       5) ANIMAÇÕES JS SUAVES
    ============================================================ */

    document.body.style.opacity = 0;
    setTimeout(() => {
        document.body.style.transition = "opacity .5s ease";
        document.body.style.opacity = 1;
    }, 30);

    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.style.transition = "transform .15s ease, box-shadow .15s ease";
        card.addEventListener('mouseenter', () => {
            card.style.transform = "translateY(-2px)";
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = "translateY(0)";
        });
    });


    /* ============================================================
       6) SIDEBAR MOBILE
    ============================================================ */

    const btnSidebar = document.getElementById('btnToggleSidebar');
    const sidebarWrapper = document.getElementById('sidebar-wrapper');

    if (btnSidebar && sidebarWrapper) {
        btnSidebar.addEventListener('click', () => {
            sidebarWrapper.classList.toggle('open');
        });
    }

});
