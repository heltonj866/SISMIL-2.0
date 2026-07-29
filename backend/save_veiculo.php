<?php
/**
 * ARQUIVO: backend/save_veiculo.php
 * Endpoint para salvar ou atualizar os dados de um veículo da frota.
 *
 * @package Sismil\Controllers
 */

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Core/Database.php';
require_once __DIR__ . '/src/Services/AuditLogger.php';
require_once __DIR__ . '/security.php';

use Sismil\Core\Database;
use Sismil\Core\Response;
use Sismil\Services\AuditLogger;

session_start();
apply_cors();
require_login(['admin', 'sargenteacao']);
validate_csrf();

try {
    $pdo = Database::getInstance();
    
    $id         = $_POST['veiculo_id'] ?? null;
    $militar_id = filter_var($_POST['v_militar_id'] ?? null, FILTER_VALIDATE_INT);
    $placa      = strtoupper(trim($_POST['v_placa'] ?? ''));

    if (!$militar_id || empty($placa)) {
        Response::error('Identificação do militar e placa são obrigatórios.', 400);
    }

    $pdf_path = null;
    $sqlPdf = "";
    
    // Validação estrita do PDF CRLV
    if (isset($_FILES['v_pdf']) && $_FILES['v_pdf']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/../uploads/documentos/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['v_pdf']['name'], PATHINFO_EXTENSION));
        $mime_real = mime_content_type($_FILES['v_pdf']['tmp_name']);
        
        if ($ext !== 'pdf' || $mime_real !== 'application/pdf') {
            Response::error('O documento anexado deve ser um arquivo PDF válido.', 400);
        }
        
        $pdf_path = 'crlv_' . bin2hex(random_bytes(8)) . '.pdf';
        
        if (move_uploaded_file($_FILES['v_pdf']['tmp_name'], $dir . $pdf_path)) {
            $sqlPdf = ", pdf_veiculo = :pdf";
        } else {
            $pdf_path = null;
        }
    }

    $emissao = (!empty($_POST['v_emissao'])) ? $_POST['v_emissao'] : null;

    $dados = [
        ':militar_id' => $militar_id,
        ':tipo'       => $_POST['v_tipo'] ?? 'Carro',
        ':placa'      => $placa,
        ':marca'      => $_POST['v_marca'] ?? '',
        ':modelo'     => $_POST['v_modelo'] ?? '',
        ':cor'        => $_POST['v_cor'] ?? '',
        ':emissao'    => $emissao,
    ];

    $acaoAuditoria = 'CREATE_VEICULO';

    if ($id && $id !== "") {
        $acaoAuditoria = 'UPDATE_VEICULO';
        $dados[':id'] = $id;
        unset($dados[':militar_id']); 
        
        if ($pdf_path) {
            $dados[':pdf'] = $pdf_path;
        }
        
        $sql = "UPDATE tb_veiculos SET 
                tipo_veiculo=:tipo, placa=:placa, marca=:marca, modelo=:modelo, 
                cor=:cor, emissao_crlv=:emissao, homologado=0 $sqlPdf 
                WHERE id=:id";
    } else {
        $dados[':pdf'] = $pdf_path;
        $sql = "INSERT INTO tb_veiculos 
                (militar_id, tipo_veiculo, placa, marca, modelo, cor, emissao_crlv, pdf_veiculo, homologado)
                VALUES 
                (:militar_id, :tipo, :placa, :marca, :modelo, :cor, :emissao, :pdf, 0)";
    }

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($dados)) {
        $registroId = $id ? $id : $pdo->lastInsertId();
        AuditLogger::log($acaoAuditoria, "Veículo da Frota processado. Placa: {$placa} (ID: {$registroId})");
        Response::json(null, 'Veículo salvo com sucesso!');
    } else {
        throw new \Exception("Falha ao salvar no banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao salvar veículo: ' . $e->getMessage());
    Response::error('Erro ao processar dados do veículo.', 500);
}