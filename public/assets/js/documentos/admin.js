(() => {

    // ============================
    // REMOVER LISTENERS ANTIGOS DA PAGINAÇÃO
    // ============================
    document.querySelectorAll(".pagination a.page-link").forEach(a => {
        const clone = a.cloneNode(true);
        a.parentNode.replaceChild(clone, a);
    });

    // ============================
    // PREVIEW INLINE
    // ============================
    const previewModalEl = document.getElementById('previewModal');
    const previewModal = previewModalEl ? new bootstrap.Modal(previewModalEl) : null;
    const previewContainer = document.getElementById('previewContainer');

    document.querySelectorAll('.btn-preview-inline').forEach(btn => {
        btn.addEventListener('click', () => {

            if (!previewModal || !previewContainer)
                return;

            const caminho = btn.dataset.caminho;   // URL pública absoluta
            const mime = btn.dataset.mime;

            previewContainer.innerHTML = '<span class="text-muted">A carregar preview...</span>';

            let html = '';

            // IMAGENS
            if (mime.startsWith('image/')) {
                html = `<img src="${caminho}" class="img-fluid">`;
            }

            // PDF / TEXTOS
            else if (mime === 'application/pdf' || mime.startsWith('text/')) {
                html = `<iframe src="${caminho}" class="w-100" style="height:600px;border:none;"></iframe>`;
            }

            // DOCUMENTOS OFFICE
            else if (
                mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
                mime === 'application/msword' ||
                mime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
                mime === 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            ) {
                const officeUrl =
                    "https://view.officeapps.live.com/op/view.aspx?src=" +
                    encodeURIComponent(caminho);

                html = `<iframe src="${officeUrl}" class="w-100" style="height:600px;border:none;"></iframe>`;
            }

            // OUTROS TIPOS
            else {
                html = `
                    <div class="text-center text-muted">
                        Preview não disponível para este tipo de ficheiro.
                        <br><br>
                        <a href="${caminho}" class="btn btn-primary" download>Download</a>
                    </div>`;
            }

            previewContainer.innerHTML = html;
            previewModal.show();
        });
    });

    // ============================
    // MODAL DE DETALHES
    // ============================
    const detalhesModalEl = document.getElementById('detalhesModal');
    const detalhesModal = detalhesModalEl ? new bootstrap.Modal(detalhesModalEl) : null;

    document.querySelectorAll('.btn-ver-detalhes').forEach(btn => {
        btn.addEventListener('click', () => {

            if (!detalhesModal)
                return;

            document.getElementById('detDocTitulo').textContent = btn.getAttribute('data-titulo') || '';
            document.getElementById('detDocTipo').textContent = btn.getAttribute('data-tipo') || '';
            document.getElementById('detDocEstado').textContent = btn.getAttribute('data-estado') || '';
            document.getElementById('detDocArea').textContent = btn.getAttribute('data-area') || '';
            document.getElementById('detDocCriado').textContent = btn.getAttribute('data-criado') || '';
            document.getElementById('detDocCriador').textContent = btn.getAttribute('data-criador') || '';

            detalhesModal.show();
        });
    });

})();
