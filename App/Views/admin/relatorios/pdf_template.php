<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório SLA</title>

    <style>
        @page {
            margin: 25px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        h2 {
            text-align: center;
            margin-bottom: 5px;
        }

        .subtitulo {
            text-align: center;
            font-size: 12px;
            margin-bottom: 15px;
            color: #555;
        }

        .box {
            border: 1px solid #ccc;
            background: #fafafa;
            padding: 10px;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            page-break-inside: auto;
        }

        table th, table td {
            border: 1px solid #444;
            padding: 6px;
            text-align: left;
        }

        table th {
            background: #f0f0f0;
        }

        tr {
            page-break-inside: avoid;
        }

        .sla-ok { color: #0a7d00; font-weight: bold; }
        .sla-alerta { color: #c99700; font-weight: bold; }
        .sla-atrasado { color: #b30000; font-weight: bold; }
        .sla-indefinido { color: #555; font-weight: bold; }

        .footer {
            position: fixed;
            bottom: 5px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 11px;
            color: #777;
        }

        .footer:after {
            content: "Página " counter(page) " de " counter(pages);
        }
    </style>
</head>
<body>

    <h2>Relatório SLA — Documentos</h2>

    <div class="subtitulo">
        Gerado em: <?= date('d/m/Y H:i') ?>
    </div>

    <!-- FILTROS -->
    <div class="box">
        <strong>Filtros aplicados:</strong><br>
        Área: <?= htmlspecialchars($area ?: '—') ?><br>
        Estado: <?= htmlspecialchars($estado ?: '—') ?><br>
        Data início: <?= htmlspecialchars($dataInicio ?: '—') ?><br>
        Data fim: <?= htmlspecialchars($dataFim ?: '—') ?><br>
    </div>

    <!-- RESUMO SLA -->
    <div class="box">
        <strong>Resumo SLA:</strong><br>
        OK: <?= $totais['ok'] ?><br>
        ALERTA: <?= $totais['alerta'] ?><br>
        ATRASADO: <?= $totais['atrasado'] ?><br>
    </div>

    <!-- GRÁFICO SLA (SVG) -->
    <?php
        $ok = $totais['ok'];
        $alerta = $totais['alerta'];
        $atrasado = $totais['atrasado'];

        $max = max(1, $ok, $alerta, $atrasado);
        $scale = 100 / $max;
    ?>

    <div class="box" style="text-align:center;">
        <strong>Gráfico SLA</strong><br><br>

        <svg width="400" height="150">

            <!-- OK -->
            <rect x="50" y="<?= 120 - ($ok * $scale) ?>" width="60" height="<?= $ok * $scale ?>" fill="#0a7d00"></rect>
            <text x="80" y="140" font-size="12" text-anchor="middle">OK (<?= $ok ?>)</text>

            <!-- ALERTA -->
            <rect x="160" y="<?= 120 - ($alerta * $scale) ?>" width="60" height="<?= $alerta * $scale ?>" fill="#c99700"></rect>
            <text x="190" y="140" font-size="12" text-anchor="middle">Alerta (<?= $alerta ?>)</text>

            <!-- ATRASADO -->
            <rect x="270" y="<?= 120 - ($atrasado * $scale) ?>" width="60" height="<?= $atrasado * $scale ?>" fill="#b30000"></rect>
            <text x="300" y="140" font-size="12" text-anchor="middle">Atrasado (<?= $atrasado ?>)</text>

        </svg>
    </div>

    <!-- TABELA -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Área</th>
                <th>Estado</th>
                <th>SLA</th>
                <th>Dias Parado</th>
                <th>Criado em</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($docs as $d): ?>
            <tr>
                <td><?= $d['id'] ?></td>
                <td><?= htmlspecialchars($d['titulo'] ?? '') ?></td>
                <td><?= htmlspecialchars($d['area_nome'] ?? '') ?></td>
                <td><?= htmlspecialchars($d['estado_atual'] ?? '') ?></td>

                <td>
                    <?php if ($d['sla'] === 'ok'): ?>
                        <span class="sla-ok">OK</span>
                    <?php elseif ($d['sla'] === 'alerta'): ?>
                        <span class="sla-alerta">ALERTA</span>
                    <?php elseif ($d['sla'] === 'atrasado'): ?>
                        <span class="sla-atrasado">ATRASADO</span>
                    <?php else: ?>
                        <span class="sla-indefinido">—</span>
                    <?php endif; ?>
                </td>

                <td><?= $d['dias_parado'] !== null ? $d['dias_parado'] : '—' ?></td>
                <td><?= date('d/m/Y', strtotime($d['criado_em'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer"></div>

</body>
</html>
