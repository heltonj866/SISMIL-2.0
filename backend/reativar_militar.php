<?php
/**
 * ARQUIVO: backend/reativar_militar.php
 * Endpoint para reativar um militar previamente desligado.
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
    $data = json_decode(file_get_contents('php://input'), true);
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        Response::error('ID do militar inválido ou não informado.', 400);
    }

    $sql = "UPDATE tb_militares SET status_ativo = 1, data_desligamento = NULL WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$id])) {
        AuditLogger::log('REATIVAR_MILITAR', "Militar ID {$id} reativado e reintegrado ao Efetivo Pronto.");
        Response::json(null, 'Militar reativado e integrado ao Efetivo Pronto.');
    } else {
        throw new \Exception("Erro ao atualizar o status de reativação.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao reativar militar: ' . $e->getMessage());
    Response::error('Erro ao reativar militar.', 500);
}