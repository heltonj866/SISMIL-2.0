<?php
/**
 * ARQUIVO: backend/desligar_militar.php
 * Endpoint para registrar o desligamento de um militar.
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
    $id = filter_var($_POST['militar_id'] ?? null, FILTER_VALIDATE_INT);
    $arquivo = $_FILES['nada_consta'] ?? null;

    if (!$id || !$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
        Response::error('ID inválido ou documento PDF ("Nada Consta") não anexado.', 400);
    }

    $extensao  = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $mime_real = mime_content_type($arquivo['tmp_name']);
    
    if ($extensao !== 'pdf' || $mime_real !== 'application/pdf') {
        Response::error('O arquivo de "Nada Consta" deve ser um PDF válido.', 400);
    }

    $novoNome        = 'nada_consta_' . $id . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $caminhoDestino  = __DIR__ . '/../uploads/documentos/';

    if (!is_dir($caminhoDestino)) {
        mkdir($caminhoDestino, 0755, true);
    }

    if (move_uploaded_file($arquivo['tmp_name'], $caminhoDestino . $novoNome)) {
        $sql = "UPDATE tb_militares SET status_ativo = 0, pdf_nada_consta = ?, data_desligamento = CURDATE() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$novoNome, $id])) {
            AuditLogger::log('DESLIGAR_MILITAR', "Militar ID {$id} desligado. Status inativado e documento anexado.");
            Response::json(null, 'Militar desligado com sucesso. Histórico mantido.');
        } else {
            throw new \Exception("Falha ao atualizar status no banco de dados.");
        }
    } else {
        Response::error('Falha ao processar o upload do arquivo no servidor.', 500);
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao desligar militar: ' . $e->getMessage());
    Response::error('Erro interno ao tentar desligar militar.', 500);
}