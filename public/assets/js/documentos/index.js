document.addEventListener("DOMContentLoaded", () => {

    const filtroTipo = document.getElementById("filtroTipo");
    const filtroUser = document.getElementById("filtroUser");
    const filtroDataInicio = document.getElementById("filtroDataInicio");
    const filtroDataFim = document.getElementById("filtroDataFim");
    const pesquisa = document.getElementById("pesquisa");

    function aplicarFiltros(page = 1) {
        const params = new URLSearchParams();

        if (filtroTipo?.value) params.set("tipo_id", filtroTipo.value);
        if (filtroUser?.value) params.set("utilizador", filtroUser.value);
        if (filtroDataInicio?.value) params.set("data_inicio", filtroDataInicio.value);
        if (filtroDataFim?.value) params.set("data_fim", filtroDataFim.value);
        if (pesquisa?.value.trim() !== "") params.set("q", pesquisa.value.trim());

        params.set("page", page);

        window.location.search = params.toString();
    }

    if (filtroTipo) filtroTipo.addEventListener("change", () => aplicarFiltros());
    if (filtroUser) filtroUser.addEventListener("change", () => aplicarFiltros());
    if (filtroDataInicio) filtroDataInicio.addEventListener("change", () => aplicarFiltros());
    if (filtroDataFim) filtroDataFim.addEventListener("change", () => aplicarFiltros());

    if (pesquisa) {
        pesquisa.addEventListener("keydown", e => {
            if (e.key === "Enter") {
                e.preventDefault();
                aplicarFiltros();
            }
        });
    }

    document.querySelectorAll(".pagination a.page-link").forEach(a => {
        a.addEventListener("click", e => {
            e.preventDefault();
            const url = new URL(a.href);
            const page = url.searchParams.get("page") || 1;
            aplicarFiltros(page);
        });
    });
});