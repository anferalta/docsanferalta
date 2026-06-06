<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório SLA</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 10px;
        }

        .subtitulo {
            text-align: center;
            font-size: 13px;
            margin-bottom: 20px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table th, table td {
            border: 1px solid #444;
            padding: 6px;
            text-align: left;
        }

        table th {
            background: #f0f0f0;
        }

        .sla-ok {
            color: #0a7d00;
            font-weight: bold;
        }

        .sla-alerta {
            color: #c99700;
            font-weight: bold;
        }

        .sla-atrasado {
            color: #b30000;
            font-weight: bold;
        }

        .footer {
            position: fixed;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 11px;
            color: #777;
        }
    </style>
</head>
<body>

    <h2>Relatório SLA — Documentos</h2>

    <div class="subtitulo">
        Gerado em: <?= date('d/m/Y H:i') ?>
    </div>

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
                <td><?= htmlspecialchars($d['titulo']) ?></td>
                <td><?= htmlspecialchars($d['area_nome']) ?></td>
                <td><?= htmlspecialchars($d['estado_atual']) ?></td>

                <td>
                    <?php if ($d['sla'] === 'ok'): ?>
                        <span class="sla-ok">OK</span>
                    <?php elseif ($d['sla'] === 'alerta'): ?>
                        <span class="sla-alerta">ALERTA</span>
                    <?php else: ?>
                        <span class="sla-atrasado">ATRASADO</span>
                    <?php endif; ?>
                </td>

                <td><?= $d['dias_parado'] ?></td>
                <td><?= date('d/m/Y', strtotime($d['criado_em'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Sistema de Tramitação — Relatório SLA • Página automática
    </div>

</body>
</html>
