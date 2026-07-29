<?php
/**
 * ARQUIVO: backend/create_user.php
 * Endpoint para criação de operadores do sistema (Controle de Acesso).
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
    
    $identidade = trim($input['new_user_idt'] ?? '');
    $senha      = $input['new_user_pass'] ?? '';
    $role       = $input['new_user_role'] ?? 'user';
    $subunidade = $input['new_user_subunidade'] ?? null;
    $nome       = $input['nome'] ?? null;
    $posto_grad = $input['posto_grad'] ?? null;

    if (empty($identidade) || empty($senha)) {
        Response::error('Preencha Login e Senha.', 400);
    }

    $check = $pdo->prepare("SELECT id FROM tb_usuarios WHERE identidade = ?");
    $check->execute([$identidade]);
    
    if ($check->rowCount() > 0) {
        Response::error('Identidade de login já existe no sistema.', 409);
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $sql  = "INSERT INTO tb_usuarios (identidade, senha_hash, role, ativo, subunidade, nome, posto_grad) VALUES (?, ?, ?, 1, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$identidade, $hash, $role, $subunidade, $nome, $posto_grad])) {
        $novoUsuarioId = $pdo->lastInsertId();
        
        AuditLogger::log('CREATE_USER', "Usuário criado: {$identidade}, Função: {$role}, Subunidade: {$subunidade}");
        
        Response::json(null, 'Usuário criado com sucesso!', 201);
    } else {
        throw new \Exception("Falha ao inserir no banco de dados.");
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao criar usuário: ' . $e->getMessage());
    Response::error('Erro ao criar usuário.', 500);
}