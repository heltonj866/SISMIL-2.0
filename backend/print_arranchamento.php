<?php
// ARQUIVO: backend/print_arranchamento.php
require_once __DIR__ . '/security.php';
session_start();
require_login(['admin', 'enc_mat']);
require 'db_connect.php';

$data = $_GET['data'] ?? date('Y-m-d');
$dataBr = date('d/m/Y', strtotime($data));
$diaSemana = date('w', strtotime($data));
$dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$nomeDia = $dias[$diaSemana];

$role = $_SESSION['usuario_role'];
$sub = $_SESSION['usuario_sub'] ?? '';

$sqlFilter = "";
$params = [$data];

if ($role === 'enc_mat' && !empty($sub)) {
    $sqlFilter = " AND subunidade = ?";
    $params[] = $sub;
}

try {
    // Separar Oficiais/Sargentos e Cabos/Soldados
    $sqlOfSgt = "SELECT * FROM tb_arranchamento 
                 WHERE data_refeicao = ?" . $sqlFilter . " 
                 AND posto_grad IN ('Cel', 'Ten Cel', 'Maj', 'Cap', '1º Ten', '2º Ten', 'Asp', 'Subten', '1º Sgt', '2º Sgt', '3º Sgt')
                 ORDER BY FIELD(posto_grad, 'Cel', 'Ten Cel', 'Maj', 'Cap', '1º Ten', '2º Ten', 'Asp', 'Subten', '1º Sgt', '2º Sgt', '3º Sgt'), nome_guerra ASC";

    $sqlCbSd = "SELECT * FROM tb_arranchamento 
                WHERE data_refeicao = ?" . $sqlFilter . " 
                AND posto_grad IN ('Cb', 'Sd EP', 'Sd EV', 'SC')
                ORDER BY FIELD(posto_grad, 'Cb', 'Sd EP', 'Sd EV', 'SC'), CAST(numero AS UNSIGNED) ASC, nome_guerra ASC";

    $stmt1 = $pdo->prepare($sqlOfSgt);
    $stmt1->execute($params);
    $ofSgt = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare($sqlCbSd);
    $stmt2->execute($params);
    $cbSd = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("[SISMIL] Erro ao carregar arranchamento para impressão: " . $e->getMessage());
    die("Ocorreu um erro interno ao carregar a planilha de arranchamento.");
}

function renderTable($title, $registros) {
    echo "<h3 style='margin-top: 30px; border-bottom: 2px solid #000; padding-bottom: 5px;'>" . h($title) . "</h3>";
    echo "<table>";
    echo "<thead><tr><th width='15%'>Subunidade</th><th width='15%'>Posto/Grad</th><th width='10%'>Número</th><th width='30%'>Nome de Guerra</th><th width='15%'>Café</th><th width='15%'>Almoço</th></tr></thead>";
    echo "<tbody>";
    if (count($registros) == 0) {
        echo "<tr><td colspan='6' style='text-align:center;'>Nenhum arranchado.</td></tr>";
    } else {
        foreach ($registros as $r) {
            $cafe = $r['cafe'] ? 'X' : '';
            $almoco = $r['almoco'] ? 'X' : '';
            $num = $r['numero'] ? h($r['numero']) : '---';
            echo "<tr>
                    <td style='text-align:center;'>" . h($r['subunidade']) . "</td>
                    <td style='text-align:center;'>" . h($r['posto_grad']) . "</td>
                    <td style='text-align:center;'>" . $num . "</td>
                    <td>" . h($r['nome_guerra']) . "</td>
                    <td style='text-align:center; font-weight:bold;'>" . h($cafe) . "</td>
                    <td style='text-align:center; font-weight:bold;'>" . h($almoco) . "</td>
                  </tr>";
        }
    }
    echo "</tbody></table>";
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Arranchamento - <?php echo h($dataBr); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #555; }
        .page { background: white; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 20mm; box-shadow: 0 0 10px rgba(0,0,0,0.5); box-sizing: border-box; position: relative; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { width: 70px; position: absolute; left: 20mm; top: 15mm; }
        .header h4 { margin: 3px 0; font-weight: bold; text-transform: uppercase; font-size: 14px;}
        .title-box { background: #e0e0e0; padding: 10px; border: 1px solid #000; text-align: center; margin-bottom: 20px; }
        .title-box h3 { margin: 0; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #f0f0f0; text-transform: uppercase; }
        
        .no-print { text-align: center; padding: 15px; background: #333; position: sticky; top: 0; z-index: 1000; }
        .btn-print { padding: 10px 20px; font-size: 16px; font-weight: bold; cursor: pointer; }
        
        @media print { 
            body { background: white; } 
            .page { margin: 0; padding: 10mm; box-shadow: none; } 
            .no-print { display: none; } 
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ IMPRIMIR PLANILHA DE ARRANCHAMENTO</button>
</div>

<div class="page">
    <div class="header">
        <img src="../uploads/brasao.png" alt="Brasão">
        <h4>MINISTÉRIO DA DEFESA</h4>
        <h4>EXÉRCITO BRASILEIRO</h4>
        <h4>2º BATALHÃO DE ENGENHARIA DE CONSTRUÇÃO</h4>
    </div>
    
    <div class="title-box">
        <h3>RELAÇÃO DE ARRANCHAMENTO</h3>
        <p style="margin: 5px 0 0 0; font-weight: bold; font-size: 16px;"><?php echo h($nomeDia) . ' - ' . h($dataBr); ?></p>
    </div>

    <?php 
    renderTable("Oficiais, Subtenentes e Sargentos", $ofSgt); 
    renderTable("Cabos e Soldados", $cbSd); 
    ?>
    
    <div style="margin-top: 50px; text-align: center;">
        <p>___________________________________________________</p>
        <p style="font-weight: bold; font-size: 14px;">FISCAL DE DIA / ENCARREGADO DE MATERIAL</p>
        <p style="font-size: 12px; color: #555;">Impresso pelo SISMIL em <?php echo date('d/m/Y H:i'); ?></p>
    </div>
</div>

</body>
</html>
