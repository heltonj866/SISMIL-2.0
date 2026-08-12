<?php
// ARQUIVO: backend/print_arranchamento.php
require_once __DIR__ . '/security.php';
session_start();
require_login(['admin', 'enc_mat']);
require_once __DIR__ . '/src/Repositories/ArranchamentoRepository.php';

use Sismil\Repositories\ArranchamentoRepository;

$data = $_GET['data'] ?? date('Y-m-d');
$dataBr = date('d/m/Y', strtotime($data));
$diaSemana = date('w', strtotime($data));
$dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$nomeDia = $dias[$diaSemana];

$role = $_SESSION['usuario_role'];
$sub = $_SESSION['usuario_sub'] ?? '';

try {
    $repo = new ArranchamentoRepository();
    
    if ($role === 'enc_mat' && !empty($sub)) {
        $resultado = $repo->getRelatorioImpressao($data, $sub);
    } else {
        $resultado = $repo->getRelatorioImpressao($data);
    }
    
    $ofSgt = $resultado['ofSgt'];
    $cbSd = $resultado['cbSd'];
} catch (\Exception $e) {
    error_log("[SISMIL] Erro ao carregar arranchamento para impressão: " . $e->getMessage());
    die("Ocorreu um erro interno ao carregar a planilha de arranchamento.");
}

function renderTable($title, $registros) {
    echo "<h3 style='margin-top: 30px; border-bottom: 2px solid #000; padding-bottom: 5px;'>" . h($title) . "</h3>";
    echo "<table>";
    echo "<thead><tr><th width='15%'>Subunidade</th><th width='15%'>Posto/Grad</th><th width='10%'>Número</th><th width='30%'>Nome de Guerra</th><th width='10%'>Café</th><th width='10%'>Almoço</th><th width='10%'>Jantar</th></tr></thead>";
    echo "<tbody>";
    if (count($registros) == 0) {
        echo "<tr><td colspan='7' style='text-align:center;'>Nenhum arranchado.</td></tr>";
    } else {
        foreach ($registros as $r) {
            $cafe = $r['cafe'] ? 'X' : '';
            $almoco = $r['almoco'] ? 'X' : '';
            $jantar = isset($r['jantar']) && $r['jantar'] ? 'X' : '';
            $num = $r['numero'] ? h($r['numero']) : '---';
            echo "<tr>
                    <td style='text-align:center;'>" . h($r['subunidade']) . "</td>
                    <td style='text-align:center;'>" . h($r['posto_grad']) . "</td>
                    <td style='text-align:center;'>" . $num . "</td>
                    <td>" . h($r['nome_guerra']) . "</td>
                    <td style='text-align:center; font-weight:bold;'>" . h($cafe) . "</td>
                    <td style='text-align:center; font-weight:bold;'>" . h($almoco) . "</td>
                    <td style='text-align:center; font-weight:bold;'>" . h($jantar) . "</td>
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
    // Calculate totals for cover page
    $oficiais_c = 0; $oficiais_a = 0; $oficiais_j = 0;
    $st_sgt_c = 0; $st_sgt_a = 0; $st_sgt_j = 0;
    
    foreach ($ofSgt as $r) {
        if (in_array($r['posto_grad'], ['Cel', 'Ten Cel', 'Maj', 'Cap', '1º Ten', '2º Ten', 'Asp'])) {
            if ($r['cafe']) $oficiais_c++;
            if ($r['almoco']) $oficiais_a++;
            if (isset($r['jantar']) && $r['jantar']) $oficiais_j++;
        } else {
            if ($r['cafe']) $st_sgt_c++;
            if ($r['almoco']) $st_sgt_a++;
            if (isset($r['jantar']) && $r['jantar']) $st_sgt_j++;
        }
    }

    $cbsd_c = 0; $cbsd_a = 0; $cbsd_j = 0;
    foreach ($cbSd as $r) {
        if ($r['cafe']) $cbsd_c++;
        if ($r['almoco']) $cbsd_a++;
        if (isset($r['jantar']) && $r['jantar']) $cbsd_j++;
    }

    $soma_c = $oficiais_c + $st_sgt_c + $cbsd_c;
    $soma_a = $oficiais_a + $st_sgt_a + $cbsd_a;
    $soma_j = $oficiais_j + $st_sgt_j + $cbsd_j;

    $subunidade_text = !empty($sub) ? strtoupper($sub) : "TODAS AS SUBUNIDADES";
    ?>

    <!-- CAPA -->
    <div style="border: 1px solid #000; padding: 20px; margin-bottom: 20px; text-align: center; font-size: 14px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 20px; text-align: left;">
            <div style="width: 30%;">
                <p style="margin: 0;">VISTO:</p>
                <br>
                <p style="margin: 0;">________________________</p>
                <p style="margin: 5px 0 0 0; font-weight: bold;">Fisc Adm 2º BEC</p>
            </div>
            <div style="width: 70%; text-align: center; padding-top: 10px;">
                <p style="margin: 0; font-weight: bold;">SUBUNIDADE: <?php echo $subunidade_text; ?></p>
                <p style="margin: 10px 0 0 0;">ARRANCHAMENTO DO DIA <?php echo h($dataBr); ?></p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; text-align: center; margin-top: 20px;">
            <tr>
                <th rowspan="2" style="border: 1px solid #000; padding: 8px;">ETAPAS A ALIMENTAR</th>
                <th rowspan="2" style="border: 1px solid #000; padding: 8px;">C</th>
                <th rowspan="2" style="border: 1px solid #000; padding: 8px;">A</th>
                <th rowspan="2" style="border: 1px solid #000; padding: 8px;">J</th>
                <th rowspan="2" style="border: 1px solid #000; padding: 8px;">ETAPAS<br>COMP.</th>
                <th rowspan="2" style="border: 1px solid #000; padding: 8px;">ALIM.</th>
                <th rowspan="2" style="border: 1px solid #000; padding: 8px;">OUTRA OM</th>
                <th rowspan="2" style="border: 1px solid #000; padding: 8px;">SOMA</th>
                <th colspan="2" style="border: 1px solid #000; padding: 8px;">QUANTITATIVO</th>
            </tr>
            <tr>
                <th style="border: 1px solid #000; padding: 8px;">TIPO</th>
                <th style="border: 1px solid #000; padding: 8px;">QTD</th>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; font-weight: bold;">OFICIAIS</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $oficiais_c; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $oficiais_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $oficiais_j; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">OFICIAIS</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $oficiais_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">0</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $oficiais_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">QR</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $oficiais_a; ?></td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; font-weight: bold;">ST/SGT</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $st_sgt_c; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $st_sgt_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $st_sgt_j; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">ST/SGT</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $st_sgt_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">0</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $st_sgt_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">QR</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $st_sgt_a; ?></td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; font-weight: bold;">CB/SD</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $cbsd_c; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $cbsd_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $cbsd_j; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">CB/SD</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $cbsd_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">0</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $cbsd_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">QR</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $cbsd_a; ?></td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; font-weight: bold;">SOMA</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $soma_c; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $soma_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $soma_j; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">SOMA</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $soma_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">0</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $soma_a; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">SOMA</td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $soma_a; ?></td>
            </tr>
        </table>

        <div style="display: flex; justify-content: space-between; margin-top: 50px; text-align: center; font-weight: bold;">
            <div style="width: 33%; text-align: left; padding-left: 20px;">
                <p style="text-transform: uppercase;"><?php echo h($nomeDia) . ' ' . h($dataBr); ?></p>
            </div>
            <div style="width: 33%;">
                <p>___________________________</p>
                <p>Furriel <?php echo $subunidade_text; ?></p>
            </div>
            <div style="width: 33%;">
                <p>___________________________</p>
                <p>Cmt <?php echo $subunidade_text; ?></p>
            </div>
        </div>
    </div>

    <!-- QUEBRA DE PÁGINA APÓS A CAPA -->
    <div style="page-break-after: always;"></div>

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
