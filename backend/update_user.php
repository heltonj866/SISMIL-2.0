<?php
/**
 * ARQUIVO: backend/update_user.php
 * Endpoint para atualização de dados ou senha de um operador.
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
    
    $id         = filter_var($input['edit_id'] ?? '', FILTER_VALIDATE_INT);
    $role       = $input['new_user_role'] ?? 'user';
    $nova_senha = $input['new_user_pass'] ?? '';
    $ativo      = isset($input['ativo']) ? (int)$input['ativo'] : null;
    $subunidade = $input['new_user_subunidade'] ?? null;
    $nome       = $input['nome'] ?? null;
    $posto_grad = $input['posto_grad'] ?? null;

    if (empty($id)) {
        Response::error('ID do usuário não informado ou inválido.', 400);
    }

    $params = [];
    $updateFields = [];

    $updateFields[] = "role = ?";
    $params[] = $role;

    if (!empty($nova_senha)) {
        $updateFields[] = "senha_hash = ?";
        $params[] = password_hash($nova_senha, PASSWORD_DEFAULT);
    }

    if ($ativo !== null) {
        $updateFields[] = "ativo = ?";
        $params[] = $ativo;
    }

    $updateFields[] = "subunidade = ?";
    $params[] = $subunidade;

    $updateFields[] = "nome = ?";
    $params[] = $nome;

    $updateFields[] = "posto_grad = ?";
    $params[] = $posto_grad;

    $params[] = $id;

    $sql = "UPDATE tb_usuarios SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute($params)) {
        AuditLogger::log('UPDATE_USER', "Usuário ID {$id} atualizado. Role: {$role}, Subunidade: {$subunidade}");
        Response::json(null, 'Atualizado com sucesso!');
    } else {
        throw new \Exception("Falha na atualização do banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao atualizar usuário: ' . $e->getMessage());
    Response::error('Erro ao atualizar usuário.', 500);
}