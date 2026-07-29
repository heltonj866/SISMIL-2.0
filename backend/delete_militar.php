<?php
/**
 * ARQUIVO: backend/delete_militar.php
 * Controlador para exclusão de militar e seus artefatos associados.
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
require_login(['admin']);
validate_csrf();

$input = json_decode(file_get_contents('php://input'), true);
$id    = filter_var($input['id'] ?? '', FILTER_VALIDATE_INT);

if (empty($id)) {
    Response::error('ID inválido ou não informado.', 400);
}

try {
    $pdo = Database::getInstance();
    
    // Recuperar a foto para apagar do disco e dados para auditoria
    $stmt = $pdo->prepare("SELECT nome_guerra, foto_path FROM tb_militares WHERE id = ?");
    $stmt->execute([$id]);
    $militar = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$militar) {
        Response::error('Militar não encontrado.', 404);
    }

    // Apagar o registro
    $stmtDel = $pdo->prepare("DELETE FROM tb_militares WHERE id = ?");
    if ($stmtDel->execute([$id])) {
        // Apagar foto do disco se existir
        if (!empty($militar['foto_path'])) {
            $arquivo = __DIR__ . '/../uploads/' . basename($militar['foto_path']);
            if (file_exists($arquivo)) {
                unlink($arquivo);
            }
        }
        
        // Registrar na Trilha de Auditoria
        AuditLogger::log('DELETE_MILITAR', "Militar ID {$id} ({$militar['nome_guerra']}) excluído do sistema permanentemente.");

        Response::json(null, 'Excluído com sucesso.');
    } else {
        throw new \Exception("Erro ao executar a exclusão no banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] ' . $e->getMessage());
    Response::error('Erro ao excluir militar. Contate o suporte.', 500);
}