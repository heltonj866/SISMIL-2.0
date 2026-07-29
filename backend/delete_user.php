<?php
/**
 * ARQUIVO: backend/delete_user.php
 * Endpoint para exclusão de operadores do sistema.
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
require_login(['admin']);
validate_csrf();

try {
    $pdo = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Tratamento de segurança para aceitar diversos padrões legados
    $idParaApagar = filter_var($input['id'] ?? $_POST['id_user'] ?? $_POST['id'] ?? $input['id_user'] ?? null, FILTER_VALIDATE_INT);
    $idLogado     = $_SESSION['usuario_id'] ?? null;

    if (empty($idParaApagar)) {
        Response::error('ID de usuário inválido.', 400);
    }
    
    if ($idParaApagar === $idLogado) {
        Response::error('Ação bloqueada de autoexclusão. Você não pode apagar seu próprio usuário.', 403);
    }

    // Trava de Segurança: Impedir exclusão do último administrador
    $stmtCheck = $pdo->prepare("SELECT role, identidade FROM tb_usuarios WHERE id = ?");
    $stmtCheck->execute([$idParaApagar]);
    $alvo = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$alvo) {
        Response::error('Usuário alvo não encontrado.', 404);
    }

    if ($alvo['role'] === 'admin') {
        $total = $pdo->query("SELECT COUNT(*) FROM tb_usuarios WHERE role='admin'")->fetchColumn();
        if ($total <= 1) {
            AuditLogger::log('DELETE_USER_BLOCKED', "Tentativa de apagar o último administrador do sistema.");
            Response::error('Operação não permitida. O sistema requer no mínimo um administrador.', 403);
        }
    }

    if ($pdo->prepare("DELETE FROM tb_usuarios WHERE id = ?")->execute([$idParaApagar])) {
        AuditLogger::log('DELETE_USER', "Usuário excluído. ID: {$idParaApagar}, Identidade: {$alvo['identidade']}");
        Response::json(null, 'Excluído com sucesso.');
    } else {
        throw new \Exception("Falha ao deletar o registro no banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao excluir usuário: ' . $e->getMessage());
    Response::error('Erro interno ao tentar excluir o usuário.', 500);
}