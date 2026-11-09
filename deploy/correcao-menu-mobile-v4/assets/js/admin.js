/* ============================================================
   ADMIN.JS — ANFERALTA PREMIUM
============================================================ */

document.addEventListener('DOMContentLoaded', () => {
    const readStorage = key => { try { return localStorage.getItem(key); } catch { return null; } };
    const saveStorage = (key, value) => { try { localStorage.setItem(key, value); } catch {} };
    const allowedThemes = ['theme-blue', 'theme-green', 'theme-gray'];

    /* ============================================================
       1) DARK MODE TOGGLE
    ============================================================ */

    const btnDark = document.getElementById('btnDarkMode');

    if (btnDark) {
        btnDark.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            btnDark.setAttribute('aria-pressed', String(document.body.classList.contains('dark-mode')));
            saveStorage('darkMode', document.body.classList.contains('dark-mode'));
        });

        if (readStorage('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
            btnDark.setAttribute('aria-pressed', 'true');
        }
    }


    /* ============================================================
       2) TROCA DE TEMAS
    ============================================================ */

    const themeButtons = document.querySelectorAll('[data-theme]');

    themeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const theme = btn.dataset.theme;
            if (!allowedThemes.includes(theme)) return;

            document.body.classList.remove('theme-blue', 'theme-green', 'theme-gray');
            document.body.classList.add(theme);

            saveStorage('theme', theme);
        });
    });

    const savedTheme = readStorage('theme');
    if (allowedThemes.includes(savedTheme)) {
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


});
