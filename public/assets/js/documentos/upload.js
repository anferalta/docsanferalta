document.addEventListener("DOMContentLoaded", () => {
    console.log("UPLOAD.JS carregado (versão finalíssima)");

    const form = document.getElementById("upload-form");
    const dropZone = document.getElementById("dropZone");
    const fileInput = document.getElementById("ficheiros");
    const fileList = document.getElementById("fileList");
    const fileCounter = document.getElementById("fileCounter");
    const fileLimitWarning = document.getElementById("fileLimitWarning");

    const MAX_FILES = 10;
    const MAX_SIZE_MB = 10;
    const MAX_TOTAL_MB = 100;

    const ALLOWED_EXT = [
        "pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx",
        "jpg", "jpeg", "png", "gif", "webp", "txt"
    ];

    const PAGE_SIZE = 5;
    let currentPage = 1;

    // Buffer real dos ficheiros
    let fileBuffer = new DataTransfer();

    const bytesToMB = b => b / (1024 * 1024);
    const getExt = name => name.includes(".") ? name.split(".").pop().toLowerCase() : "";

    const isDuplicate = file =>
        Array.from(fileBuffer.files).some(f => f.name === file.name && f.size === file.size);

    function validateFile(file) {
        const ext = getExt(file.name);
        const sizeMB = bytesToMB(file.size);

        if (!ALLOWED_EXT.includes(ext)) {
            alert(`Tipo de ficheiro não permitido: ${file.name}`);
            return false;
        }

        if (sizeMB > MAX_SIZE_MB) {
            alert(`Ficheiro demasiado grande (${sizeMB.toFixed(2)} MB): ${file.name}`);
            return false;
        }

        return true;
    }

    function updateFileInput() {
        fileInput.files = fileBuffer.files;
    }

    function renderFileList() {
        fileList.innerHTML = "";

        const total = fileBuffer.files.length;

        fileCounter.classList.toggle("d-none", total === 0);
        fileCounter.textContent = `${total} ficheiro(s) selecionado(s)`;

        fileLimitWarning.classList.toggle("d-none", total <= MAX_FILES);

        if (total === 0) return;

        const files = Array.from(fileBuffer.files);
        const start = (currentPage - 1) * PAGE_SIZE;
        const pageFiles = files.slice(start, start + PAGE_SIZE);

        pageFiles.forEach((file, indexOnPage) => {
            const globalIndex = start + indexOnPage;

            const li = document.createElement("li");
            li.classList.add("list-group-item", "d-flex", "justify-content-between");

            li.innerHTML = `
                <span>${file.name} (${bytesToMB(file.size).toFixed(2)} MB)</span>
                <button class="btn btn-danger btn-sm">Remover</button>
            `;

            li.querySelector("button").onclick = () => {
                const newBuffer = new DataTransfer();
                Array.from(fileBuffer.files)
                    .filter((_, idx) => idx !== globalIndex)
                    .forEach(f => newBuffer.items.add(f));

                fileBuffer = newBuffer;

                setTimeout(updateFileInput, 0);
                renderFileList();
            };

            fileList.appendChild(li);
        });
    }

    async function addFiles(files) {
        for (let f of files) {
            if (!validateFile(f)) continue;

            if (fileBuffer.files.length >= MAX_FILES) {
                alert(`Máximo de ${MAX_FILES} ficheiros atingido.`);
                break;
            }

            if (isDuplicate(f)) {
                alert(`Ficheiro duplicado: ${f.name}`);
                continue;
            }

            const totalAfter = Array.from(fileBuffer.files).reduce((t, f2) => t + f2.size, 0) + f.size;
            if (bytesToMB(totalAfter) > MAX_TOTAL_MB) {
                alert(`Limite total de ${MAX_TOTAL_MB} MB excedido.`);
                continue;
            }

            fileBuffer.items.add(f);
        }

        // Garantir atualização do input
        await new Promise(resolve => setTimeout(resolve, 20));
        updateFileInput();
        renderFileList();
    }

    // ============================
    // EVENTOS DO DROPZONE
    // ============================

    dropZone.addEventListener("click", () => fileInput.click());

    dropZone.addEventListener("dragover", e => {
        e.preventDefault();
        dropZone.classList.add("dragover");
    });

    dropZone.addEventListener("dragleave", () => {
        dropZone.classList.remove("dragover");
    });

    dropZone.addEventListener("drop", e => {
        e.preventDefault();
        dropZone.classList.remove("dragover");
        addFiles(e.dataTransfer.files);
    });

    // ============================
    // INPUT DE FICHEIROS
    // ============================

    fileInput.addEventListener("change", async () => {
        await addFiles(fileInput.files);

        // FIX CRÍTICO DO PRIMEIRO CLICK
        await new Promise(resolve => setTimeout(resolve, 20));
        updateFileInput();
    });

    // ============================
    // SUBMISSÃO DO FORMULÁRIO
    // ============================

    form.addEventListener("submit", async e => {
        e.preventDefault(); // impedir submissão imediata

        // Garantir que o input atualiza ANTES de enviar
        await new Promise(resolve => setTimeout(resolve, 50));
        updateFileInput();

        if (fileBuffer.files.length === 0) {
            alert("Selecione pelo menos um ficheiro.");
            return;
        }

        // Agora sim, submeter com ficheiros corretos
        form.submit();
    });
});
