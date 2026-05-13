document.addEventListener("DOMContentLoaded", () => {

    const csrf = document.querySelector('input[name="_csrf"]').value;

    // ============================
    // CRIAR TIPO
    // ============================
    const btnCriar = document.getElementById("btnCriarTipo");
    if (btnCriar) {
        btnCriar.addEventListener("click", async () => {

            const nome = document.getElementById("novoTipoNome").value.trim();
            if (nome === "") {
                alert("O nome do tipo é obrigatório.");
                return;
            }

            const data = new FormData();
            data.append("_csrf", csrf);
            data.append("nome", nome);

            const resp = await fetch("/admin/documento-tipos/criar-ajax", {
                method: "POST",
                body: data
            });

            const json = await resp.json();

            if (json.erro) {
                alert(json.erro);
                return;
            }

            // Atualizar dropdown
            const select = document.getElementById("tipo_id");
            const opt = document.createElement("option");
            opt.value = json.id;
            opt.textContent = json.nome;
            opt.selected = true;
            select.appendChild(opt);

            // Fechar modal
            bootstrap.Modal.getInstance(document.getElementById("modalCriarTipo")).hide();

            // Limpar campo
            document.getElementById("novoTipoNome").value = "";
        });
    }

    // ============================
    // EDITAR TIPO
    // ============================
    const btnEditar = document.getElementById("btnEditarTipo");
    const btnEditarGuardar = document.getElementById("btnEditarTipoGuardar");

    if (btnEditar) {
        btnEditar.addEventListener("click", () => {
            const select = document.getElementById("tipo_id");
            const id = select.value;

            if (!id) {
                alert("Selecione um tipo para editar.");
                return;
            }

            document.getElementById("editarTipoId").value = id;
            document.getElementById("editarTipoNome").value =
                select.options[select.selectedIndex].text;

            new bootstrap.Modal(document.getElementById("modalEditarTipo")).show();
        });
    }

    if (btnEditarGuardar) {
        btnEditarGuardar.addEventListener("click", async () => {

            const id = document.getElementById("editarTipoId").value;
            const nome = document.getElementById("editarTipoNome").value.trim();

            if (nome === "") {
                alert("O nome do tipo é obrigatório.");
                return;
            }

            const data = new FormData();
            data.append("_csrf", csrf);
            data.append("nome", nome);

            const resp = await fetch(`/admin/documento-tipos/editar-ajax/${id}`, {
                method: "POST",
                body: data
            });

            const json = await resp.json();

            if (json.erro) {
                alert(json.erro);
                return;
            }

            // Atualizar dropdown
            const select = document.getElementById("tipo_id");
            const opt = select.querySelector(`option[value="${id}"]`);
            if (opt) opt.textContent = nome;

            bootstrap.Modal.getInstance(document.getElementById("modalEditarTipo")).hide();
        });
    }

    // ============================
    // APAGAR TIPO
    // ============================
    const btnApagar = document.getElementById("btnApagarTipo");
    if (btnApagar) {
        btnApagar.addEventListener("click", async () => {

            const select = document.getElementById("tipo_id");
            const id = select.value;

            if (!id) {
                alert("Selecione um tipo para apagar.");
                return;
            }

            if (!confirm("Tem a certeza que deseja apagar este tipo?")) return;

            const data = new FormData();
            data.append("_csrf", csrf);

            const resp = await fetch(`/admin/documento-tipos/apagar-ajax/${id}`, {
                method: "POST",
                body: data
            });

            const json = await resp.json();

            if (json.sucesso) {
                select.querySelector(`option[value="${id}"]`).remove();
            }
        });
    }

});