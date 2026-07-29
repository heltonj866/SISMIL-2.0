<?php
/**
 * ARQUIVO: backend/excluir_alteracao.php
 * Endpoint para remover uma alteração do histórico do militar.
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
        Response::error('ID inválido ou ausente.', 400);
    }
    
    // Obter dados antes de excluir para auditoria
    $stmtSelect = $pdo->prepare("SELECT militar_id, categoria FROM tb_alteracoes WHERE id = ?");
    $stmtSelect->execute([$id]);
    $alt = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if (!$alt) {
        Response::error('Alteração não encontrada.', 404);
    }

    $stmt = $pdo->prepare("DELETE FROM tb_alteracoes WHERE id = ?");
    if ($stmt->execute([$id])) {
        AuditLogger::log('DELETE_ALTERACAO', "Sargenteação: O registro {$id} ({$alt['categoria']}) do Militar {$alt['militar_id']} foi excluído.");
        Response::json(null, 'Registro excluído com sucesso.');
    } else {
        throw new \Exception("Falha ao deletar do banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao excluir alteracao: ' . $e->getMessage());
    Response::error('Erro ao excluir alteração do histórico.', 500);
}