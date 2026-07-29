<?php
// ARQUIVO: backend/export_excel.php
require_once __DIR__ . '/security.php';
session_start();
require_login(); // Qualquer usuário autenticado
require_once __DIR__ . '/src/Repositories/MilitarRepository.php';

use Sismil\Repositories\MilitarRepository;

$arquivo = 'relatorio_efetivo_' . date('d-m-Y') . '.xls';

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$arquivo\"");
header("Pragma: no-cache");
header("Expires: 0");

try {
    $repo = new MilitarRepository();
    
    // As variáveis $_GET são passadas para o Repositório, juntamente com o Role da sessão
    $roleSessao = $_SESSION['usuario_role'] ?? '';
    $lista = $repo->getExportRelatorio($_GET, $roleSessao);

} catch (\Exception $e) {
    error_log('[SISMIL] export_excel.php: ' . $e->getMessage());
    echo "Erro ao gerar relatório.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }
        th { background-color: #ddd; color: #000; border: 1px solid #000; padding: 10px; text-align: left; font-weight: bold; }
        td { border: 1px solid #000; padding: 5px; vertical-align: middle; white-space: nowrap; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>Posto</th>
                <th>Nome Guerra</th>
                <th>Nome Completo</th>
                <th>Nr</th>
                <th>SU</th>
                <th>Pel/Sec</th>
                <th>QMG</th>
                <th>Dt Praça</th>
                <th>CPF</th>
                <th>Idt Mil</th>
                <th>Celular</th>
                <th>Endereço</th>
                <th>Bairro</th>
                <th>Cidade</th>
                <th>CNH</th>
                <th>Validade CNH</th>
                <th>Veículo</th>
                <th>Modelo</th>
                <th>Cor</th>
                <th>Placa</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): 
                // Formata datas somente se existirem e não forem zero
                $dtPraca = (!empty($row['dt_praca']) && $row['dt_praca'] != '0000-00-00') ? date('d/m/Y', strtotime($row['dt_praca'])) : '';
                $dtVal   = (!empty($row['validade_cnh']) && $row['validade_cnh'] != '0000-00-00') ? date('d/m/Y', strtotime($row['validade_cnh'])) : '';
                
                // Concatena Local
                $local = trim(($row['pelotao'] ?? '') . ' ' . ($row['secao'] ?? ''));
            ?>
            <tr>
                <td><?= h($row['posto_grad']) ?></td>
                <td><?= h(mb_strtoupper($row['nome_guerra'] ?? '')) ?></td>
                <td><?= h($row['nome_completo']) ?></td>
                <td class="text-center"><?= h($row['numero']) ?></td>
                <td class="text-center"><?= h($row['subunidade']) ?></td>
                <td><?= h($local) ?></td>
                <td><?= h($row['qmg']) ?></td>
                <td class="text-center"><?= h($dtPraca) ?></td>
                <td class="text-center" style="mso-number-format:'\@'"><?= h($row['identidade']) ?></td>
                <td class="text-center" style="mso-number-format:'\@'"><?= h($row['idt_militar']) ?></td>
                <td class="text-center"><?= h($row['celular_princ']) ?></td>
                <td><?= h($row['endereco']) ?></td>
                <td><?= h($row['bairro']) ?></td>
                <td><?= h($row['cidade']) ?></td>
                <td class="text-center"><?= h($row['cat_cnh']) ?></td>
                <td class="text-center"><?= h($dtVal) ?></td>
                <td><?= h($row['tipo_veiculo']) ?></td>
                <td><?= h($row['modelo']) ?></td>
                <td><?= h($row['cor']) ?></td>
                <td class="text-center"><?= h(mb_strtoupper($row['placa'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>