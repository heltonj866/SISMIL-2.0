<?php
// ARQUIVO: backend/export_excel.php
require_once __DIR__ . '/security.php';
session_start();
require_login(); // Qualquer usuário autenticado
require 'db_connect.php';

$arquivo = 'relatorio_efetivo_' . date('d-m-Y') . '.xls';

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$arquivo\"");
header("Pragma: no-cache");
header("Expires: 0");

try {
    $tipo = $_GET['tipo_busca'] ?? 'geral';
    $inativos = isset($_GET['inativos']) && $_GET['inativos'] == '1';
    $params = [];
    $sql = "SELECT * FROM tb_militares WHERE 1=1";
    if (!$inativos) {
        $sql .= " AND status_ativo = 1";
    }

    // Lógica de Filtros
    if ($tipo === 'geral') {
        $termo = $_GET['termo'] ?? '';
        $posto = $_GET['posto'] ?? '';
        $su    = $_GET['su'] ?? '';
        $qmg   = $_GET['qmg'] ?? '';
        $sem_foto = isset($_GET['sem_foto']) && $_GET['sem_foto'] == '1';
        $mes_aniversario = $_GET['mes_aniversario'] ?? '';

        if (!empty($termo)) {
            $t = "%" . trim($termo) . "%";
            $sql .= " AND (nome_guerra LIKE :t1 OR nome_completo LIKE :t2 OR numero LIKE :t3)";
            $params[':t1'] = $t; $params[':t2'] = $t; $params[':t3'] = $t;
        }
        if (!empty($posto)) { $sql .= " AND posto_grad = :posto"; $params[':posto'] = $posto; }
        if (!empty($su)) { $sql .= " AND subunidade = :su"; $params[':su'] = $su; }
        if (!empty($qmg)) { $sql .= " AND qmg = :qmg"; $params[':qmg'] = $qmg; }

        if ($sem_foto) {
            $role_sessao = strtolower(trim($_SESSION['usuario_role'] ?? ''));
            if (in_array($role_sessao, ['admin', 'sargenteacao'])) {
                $sql .= " AND (foto_path IS NULL OR foto_path = '' OR foto_path = 'sem_foto.png' OR foto_path = 'sem_foto.PNG')";
            }
        }
        if (!empty($mes_aniversario) && is_numeric($mes_aniversario) && $mes_aniversario >= 1 && $mes_aniversario <= 12) {
            $sql .= " AND MONTH(dt_nascimento) = :mes_aniversario";
            $params[':mes_aniversario'] = (int)$mes_aniversario;
        }
    } 
    else if ($tipo === 'cnh') {
        $filtro = $_GET['filtro_cnh'] ?? 'TODAS';
        $sql .= " AND cat_cnh IS NOT NULL AND cat_cnh != ''"; // Usando o nome correto
        
        if ($filtro === 'PENDENTE') $sql .= " AND (homologado IS NULL OR homologado = 0)";
        elseif ($filtro === 'VEICULOS') $sql .= " AND placa IS NOT NULL AND placa != ''";
        elseif ($filtro === 'A') $sql .= " AND cat_cnh LIKE '%A%'";
        elseif ($filtro === 'B') $sql .= " AND cat_cnh LIKE '%B%'";
        elseif ($filtro === 'PRO') $sql .= " AND (cat_cnh LIKE '%C%' OR cat_cnh LIKE '%D%' OR cat_cnh LIKE '%E%' OR cat_cnh LIKE '%AD%' OR cat_cnh LIKE '%AE%')";
    }

    $sql .= " ORDER BY nome_guerra ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
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