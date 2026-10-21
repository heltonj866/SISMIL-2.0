<?php
// ARQUIVO: backend/dossier_militar.php
require_once __DIR__ . '/security.php';
session_start();
require_login(); // Apenas usuários autenticados
require_once __DIR__ . '/src/Services/AuditLogger.php';
require_once __DIR__ . '/src/Repositories/MilitarRepository.php';
require_once __DIR__ . '/src/Repositories/VeiculoRepository.php';
require_once __DIR__ . '/src/Repositories/AlteracaoRepository.php';

use Sismil\Repositories\MilitarRepository;
use Sismil\Repositories\VeiculoRepository;
use Sismil\Repositories\AlteracaoRepository;

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    die("ID do militar não informado.");
}

try {
    $repoMilitar = new MilitarRepository();
    $repoVeiculo = new VeiculoRepository();
    $repoAlteracao = new AlteracaoRepository();

    $m = $repoMilitar->findById($id);
    if (!$m) {
        die("Militar não encontrado.");
    }

    $veiculos = $repoVeiculo->getByMilitarId($id);
    $historico = $repoAlteracao->getByMilitarId($id);

} catch (\Exception $e) {
    error_log("[SISMIL] Erro ao carregar dossier: " . $e->getMessage());
    die("Ocorreu um erro interno ao carregar o dossier militar.");
}

function dataBR($data) { 
    return (!empty($data) && $data != '0000-00-00') ? date('d/m/Y', strtotime($data)) : '---'; 
}

function valOrDash($val, $upper = false) {
    $v = trim((string)($val ?? ''));
    if ($v === '') return '---';
    return $upper ? mb_strtoupper($v, 'UTF-8') : $v;
}

$pelSecaoParts = array_filter([trim((string)($m['pelotao'] ?? '')), trim((string)($m['secao'] ?? ''))], fn($p) => $p !== '');
$pelSecaoTexto = !empty($pelSecaoParts) ? implode(' / ', $pelSecaoParts) : '---';

$endParts = [];
if (!empty(trim((string)($m['endereco'] ?? '')))) {
    $endParts[] = trim($m['endereco']);
}
if (!empty(trim((string)($m['num_residencia'] ?? '')))) {
    $endParts[] = 'Nº ' . trim($m['num_residencia']);
}
$endFormatado = !empty($endParts) ? implode(', ', $endParts) : '';
if (!empty(trim((string)($m['bairro'] ?? '')))) {
    $endFormatado .= ($endFormatado !== '' ? ' - ' : '') . trim($m['bairro']);
}
if ($endFormatado === '') {
    $endFormatado = '---';
}

$cidUfParts = array_filter([trim((string)($m['cidade'] ?? '')), trim((string)($m['estado'] ?? ''))], fn($p) => $p !== '');
$cidUfTexto = !empty($cidUfParts) ? implode('/', $cidUfParts) : '---';

$fotoNome = trim((string)($m['foto_path'] ?? ''));
$temFoto = ($fotoNome !== '' && !in_array(strtolower($fotoNome), ['sem_foto.png', 'sem_foto.svg'], true));
$foto_src = $temFoto ? '../uploads/' . $fotoNome : '';

\Sismil\Services\AuditLogger::log('DOSSIE_MILITAR', 'Dossiê consultado/impresso do militar ID ' . (int)$id . '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dossier Militar - <?php echo h($m['posto_grad']) . ' ' . h($m['nome_guerra']); ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 14mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            background: #525659;
            color: #000;
        }
        .page {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 15mm 16mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
        }
        .header {
            position: relative;
            min-height: 78px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            border-bottom: 2px solid #000;
            padding: 4px 75px 12px 75px;
            margin-bottom: 14px;
        }
        .header img {
            width: 62px;
            height: auto;
            position: absolute;
            left: 4px;
            top: 50%;
            transform: translateY(-54%);
        }
        .header h3, .header h4 {
            margin: 2px 0;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 13.5px;
            line-height: 1.25;
        }
        .section-title {
            background: #e0e0e0;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 8px;
            margin: 14px 0 8px 0;
            font-size: 12px;
            border: 1px solid #000;
            clear: both;
            page-break-after: avoid;
            break-after: avoid;
        }
        .row {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            margin-bottom: 8px;
        }
        .col { flex: 1; min-width: 0; }
        .col-2 { flex: 2; min-width: 0; }
        .col-3 { flex: 3; min-width: 0; }
        .label {
            font-size: 9.5px;
            color: #444;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .value {
            font-size: 12.5px;
            font-weight: normal;
            border-bottom: 1px dotted #999;
            padding-bottom: 2px;
            min-height: 17px;
            word-break: break-word;
        }
        .fw-bold { font-weight: bold; }

        .foto-container {
            width: 108px;
            height: 142px;
            border: 1px solid #000;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            background: #f8f9fa;
            flex-shrink: 0;
        }
        .foto-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 15%;
            display: block;
        }
        .sem-foto-label {
            font-size: 10px;
            font-weight: bold;
            color: #666;
            text-align: center;
            padding: 4px;
        }

        ul.doc-list {
            margin: 6px 0 10px 0;
            padding-left: 22px;
            font-size: 12px;
        }
        ul.doc-list li {
            margin-bottom: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 11px;
            page-break-inside: auto;
        }
        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f0f0f0;
            text-transform: uppercase;
            font-size: 10.5px;
        }

        .status-stamp {
            color: #d32f2f;
            font-size: 22px;
            font-weight: bold;
            border: 3px solid #d32f2f;
            padding: 4px 14px;
            display: inline-block;
            transform: rotate(-10deg);
            position: absolute;
            top: 22mm;
            right: 45mm;
            opacity: 0.85;
            z-index: 10;
        }

        .footer-assinatura {
            margin-top: 28px;
            text-align: center;
            font-size: 11px;
            color: #555;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        @media print {
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .page {
                width: 100% !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; padding: 15px; background: #333; position: sticky; top: 0; z-index: 1000;">
    <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; font-weight: bold; cursor: pointer;">🖨️ IMPRIMIR / SALVAR PDF</button>
</div>

<div class="page">
    <div class="header">
        <img src="../uploads/brasao.png" alt="Brasão" onerror="this.style.display='none'">
        <h4>MINISTÉRIO DA DEFESA</h4>
        <h4>EXÉRCITO BRASILEIRO</h4>
        <h4>2º BATALHÃO DE ENGENHARIA DE CONSTRUÇÃO</h4>
        <h4 style="margin-top: 10px; text-decoration: underline;">DOSSIER INDIVIDUAL DE PESSOAL (SISMIL)</h4>
    </div>

    <?php if($m['status_ativo'] == 0): ?>
        <div class="status-stamp">DESLIGADO</div>
    <?php endif; ?>

    <div class="section-title">1. Dados Pessoais e Organizacionais</div>
    
    <div style="display: flex; gap: 16px; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
        
        <div style="flex: 1; min-width: 0;">
            <div class="row">
                <div class="col-3"><div class="label">Nome Completo</div><div class="value fw-bold"><?php echo h(valOrDash($m['nome_completo'], true)); ?></div></div>
            </div>
            <div class="row">
                <div class="col"><div class="label">Posto / Grad</div><div class="value"><?php echo h(valOrDash($m['posto_grad'], true)); ?></div></div>
                <div class="col"><div class="label">Nome de Guerra</div><div class="value fw-bold"><?php echo h(valOrDash($m['nome_guerra'], true)); ?></div></div>
                <div class="col"><div class="label">Número</div><div class="value"><?php echo h(valOrDash($m['numero'])); ?></div></div>
            </div>
            <div class="row">
                <div class="col"><div class="label">Idt Militar</div><div class="value"><?php echo h(valOrDash($m['idt_militar'])); ?></div></div>
                <div class="col"><div class="label">CPF</div><div class="value"><?php echo h(valOrDash($m['cpf'])); ?></div></div>
                <div class="col"><div class="label">Nascimento</div><div class="value"><?php echo h(dataBR($m['dt_nascimento'])); ?></div></div>
                <div class="col"><div class="label">Tipo Sanguíneo</div><div class="value"><?php echo h(valOrDash($m['tipo_sanguineo'])); ?></div></div>
            </div>
            <div class="row" style="margin-bottom: 0;">
                <div class="col"><div class="label">Subunidade</div><div class="value"><?php echo h(valOrDash($m['subunidade'])); ?></div></div>
                <div class="col"><div class="label">Pelotão/Seção</div><div class="value"><?php echo h($pelSecaoTexto); ?></div></div>
                <div class="col"><div class="label">Arma / QMG</div><div class="value"><?php echo h(valOrDash($m['qmg'])); ?></div></div>
                <div class="col"><div class="label">Data de Praça</div><div class="value"><?php echo h(dataBR($m['dt_praca'])); ?></div></div>
            </div>
        </div>

        <div class="foto-container">
            <?php if ($temFoto): ?>
                <img src="<?php echo h($foto_src); ?>" alt="Foto 3x4" class="foto-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <span class="sem-foto-label" style="display: none;">SEM FOTO</span>
            <?php else: ?>
                <span class="sem-foto-label">SEM FOTO</span>
            <?php endif; ?>
        </div>
        
    </div>

    <div class="section-title">2. Contato e Plano de Chamada</div>
    <div class="row">
        <div class="col"><div class="label">Celular Principal</div><div class="value"><?php echo h(valOrDash($m['celular_princ'])); ?></div></div>
        <div class="col"><div class="label">Celular Secundário</div><div class="value"><?php echo h(valOrDash($m['celular_sec'])); ?></div></div>
        <div class="col"><div class="label">Email</div><div class="value"><?php echo h(valOrDash($m['email'])); ?></div></div>
    </div>
    <div class="row">
        <div class="col-3"><div class="label">Endereço</div><div class="value"><?php echo h($endFormatado); ?></div></div>
        <div class="col"><div class="label">Cidade/UF</div><div class="value"><?php echo h($cidUfTexto); ?></div></div>
    </div>
    <div class="row">
        <div class="col"><div class="label">Pessoa de Referência (Emergência)</div><div class="value"><?php echo h(valOrDash($m['nome_resp'])); ?></div></div>
        <div class="col"><div class="label">Telefone da Referência</div><div class="value"><?php echo h(valOrDash($m['tel_resp'])); ?></div></div>
    </div>

    <div class="section-title">3. Documentação Anexada ao SISMIL</div>
    <ul class="doc-list">
        <?php if($m['pdf_habilitacao']): ?> <li>CNH do Militar (Digitalizada)</li> <?php endif; ?>
        <?php if($m['status_ativo'] == 0 && $m['pdf_nada_consta']): ?> <li style="color:red; font-weight:bold;">Ficha de Nada Consta (Desligamento) anexada ao sistema.</li> <?php endif; ?>
        <?php foreach($veiculos as $v): ?>
            <?php if($v['pdf_veiculo']): ?> <li>CRLV - Placa <?php echo h(strtoupper($v['placa'])); ?></li> <?php endif; ?>
            <?php if(!empty($v['pdf_comprovante_vinculo'])): ?> <li>Doc. Comprobatório de Vínculo (Proprietário) - Placa <?php echo h(strtoupper($v['placa'])); ?></li> <?php endif; ?>
        <?php endforeach; ?>
        <?php
            // Documentos anexados ao Histórico S1
            foreach($historico as $h):
                if(!empty($h['arquivo_path'])):
        ?>
            <li>
                Documento de Histórico S1 — 
                <strong><?php echo h(strtoupper($h['categoria'] . ' / ' . $h['tipo_detalhe'])); ?></strong>
                (<?php echo h(dataBR($h['data_fato'])); ?>)
                <?php if(!empty($h['documento_ref'])): ?> — Ref: <?php echo h($h['documento_ref']); ?> <?php endif; ?>
            </li>
        <?php
                endif;
            endforeach;
        ?>
        <?php
            $temAlgumDoc = $m['pdf_habilitacao'] || $m['pdf_nada_consta'];
            if (!$temAlgumDoc) {
                foreach ($veiculos as $v) {
                    if ($v['pdf_veiculo'] || $v['pdf_comprovante_vinculo']) { $temAlgumDoc = true; break; }
                }
            }
            if (!$temAlgumDoc) {
                foreach ($historico as $h) {
                    if (!empty($h['arquivo_path'])) { $temAlgumDoc = true; break; }
                }
            }
        ?>
        <?php if(!$temAlgumDoc): ?> <li style="color:#666; font-style:italic;">Nenhum documento PDF anexado.</li> <?php endif; ?>
    </ul>

    <div class="section-title">4. Veículos Cadastrados e Trânsito</div>
    <div class="row">
        <div class="col"><div class="label">Categoria da CNH</div><div class="value"><?php echo !empty(trim((string)($m['cat_cnh'] ?? ''))) ? h($m['cat_cnh']) : 'NÃO POSSUI'; ?></div></div>
        <div class="col"><div class="label">Validade CNH</div><div class="value"><?php echo h(dataBR($m['validade_cnh'])); ?></div></div>
    </div>
    <table>
        <thead><tr><th>Tipo</th><th>Placa</th><th>Marca/Modelo/Cor</th><th>Proprietário</th><th>Validade CRLV</th><th>Status S2</th></tr></thead>
        <tbody>
            <?php if(count($veiculos) > 0): foreach($veiculos as $v): ?>
                <tr>
                    <td><?php echo h(strtoupper($v['tipo_veiculo'])); ?></td>
                    <td style="font-family:monospace; font-weight:bold;"><?php echo h(strtoupper($v['placa'])); ?></td>
                    <td><?php echo h(strtoupper($v['marca'] . ' ' . $v['modelo'] . ' - ' . $v['cor'])); ?></td>
                    <td>
                        <?php if(!empty($v['proprietario_nome'])): ?>
                            <strong><?php echo h(strtoupper($v['proprietario_nome'])); ?></strong> (<?php echo h(strtoupper($v['proprietario_parentesco'] ?? 'TERCEIRO')); ?>)
                        <?php else: ?>
                            O PRÓPRIO MILITAR
                        <?php endif; ?>
                    </td>
                    <td><?php echo h(dataBR($v['validade_crlv'])); ?></td>
                    <td><?php echo $v['homologado'] == 1 ? 'HOMOLOGADO' : 'PENDENTE'; ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center; color:#555;">Nenhum veículo cadastrado na base de dados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">5. Histórico de Alterações (S1 / Sargenteação)</div>
    <table>
        <thead><tr><th width="80">Data Fato</th><th width="100">Categoria</th><th>Tipo / Detalhe</th><th>Descrição da Alteração</th></tr></thead>
        <tbody>
            <?php if(count($historico) > 0): foreach($historico as $h): ?>
                <tr>
                    <td><?php echo h(dataBR($h['data_fato'])); ?></td>
                    <td><?php echo h(strtoupper($h['categoria'])); ?></td>
                    <td style="font-weight:bold;"><?php echo h(strtoupper($h['tipo_detalhe'])); ?></td>
                    <td><?php echo h($h['descricao']); ?> <?php if($h['documento_ref']) echo "<br><i>Ref: " . h($h['documento_ref']) . "</i>"; ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" style="text-align:center; color:#555;">Nenhuma alteração registrada no histórico.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer-assinatura">
        <p style="margin-bottom: 4px;">______________________________________________________</p>
        <p style="margin-top: 0;">Gerado pelo SISMIL em <?php echo date('d/m/Y H:i'); ?><br>Documento de uso interno administrativo.</p>
    </div>
</div>

</body>
</html>
