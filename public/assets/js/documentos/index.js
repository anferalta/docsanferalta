function getValue(selector) {
    const el = document.querySelector(selector);
    return el ? el.value : "";
}

function construirQuery(page = 1) {
    const params = new URLSearchParams();

    const tipo = getValue("select[name='tipo_id']");
    if (tipo) params.set("tipo_id", tipo);

    const utilizador = getValue("select[name='utilizador']");
    if (utilizador) params.set("utilizador", utilizador);

    const dataInicio = getValue("input[name='data_inicio']");
    if (dataInicio) params.set("data_inicio", dataInicio);

    const dataFim = getValue("input[name='data_fim']");
    if (dataFim) params.set("data_fim", dataFim);

    const q = getValue("input[name='q']");
    if (q) params.set("q", q);

    const estado = getValue("select[name='estado_atual']");
    if (estado) params.set("estado_atual", estado);

    const area = getValue("select[name='area_atual_id']");
    if (area) params.set("area_atual_id", area);

    params.set("page", page);

    return params.toString();
}

function aplicarFiltros(page = 1) {
    const query = construirQuery(page);
    window.location.href = "/admin/documentos?" + query;
}

function initPaginacao() {
    const temFiltros = document.querySelector("form[method='get']");

    document.querySelectorAll(".pagination a.page-link").forEach(a => {
        a.addEventListener("click", e => {

            if (!temFiltros) return;

            e.preventDefault();

            const url = new URL(a.href);
            const page = url.searchParams.get("page") || 1;

            aplicarFiltros(page);
        });
    });
}

document.addEventListener("DOMContentLoaded", () => {
    initPaginacao();
});
