<?php
/**
 * ARQUIVO: backend/login.php
 * Controlador de Autenticação de Usuários.
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

apply_cors();

// Configuração segura de sessão
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $is_https,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

$input      = json_decode(file_get_contents('php://input'), true);
$identidade = trim($input['identidade'] ?? '');
$senha      = $input['senha'] ?? '';

if (empty($identidade) || empty($senha)) {
    Response::error('Preencha o login e a senha.', 400);
}

// Proteção contra força bruta (Rate Limiting)
$rate_id = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rate_id, 10, 900)) {
    AuditLogger::log('LOGIN_BLOCKED', "Tentativas excedidas para identidade: $identidade", null, $identidade);
    Response::error('Muitas tentativas de login. Tente novamente em 15 minutos.', 429);
}

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM tb_usuarios WHERE identidade = ? LIMIT 1");
    $stmt->execute([$identidade]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($senha, $user['senha_hash'])) {
        if ($user['ativo'] == 0) {
            AuditLogger::log('LOGIN_FAILED_INACTIVE', "Tentativa de login em conta inativa", $user['id'], $identidade);
            Response::error('Conta inativa. Contate o administrador.', 403);
        }

        reset_rate_limit($rate_id);
        
        // Prevenção contra Session Fixation
        session_regenerate_id(true);

        $_SESSION['usuario_id']    = $user['id'];
        $_SESSION['usuario_role']  = $user['role'];
        $_SESSION['usuario_login'] = $user['identidade'];
        $_SESSION['usuario_sub']   = $user['subunidade'] ?? '';

        $csrf_token = generate_csrf_token();
        
        AuditLogger::log('LOGIN_SUCCESS', "Login efetuado com sucesso (Role: {$user['role']})", $user['id'], $identidade);

        Response::json([
            'role'       => $user['role'],
            'subunidade' => $_SESSION['usuario_sub'],
            'csrf_token' => $csrf_token,
        ], 'Login efetuado com sucesso.');
    } else {
        AuditLogger::log('LOGIN_FAILED', "Credenciais inválidas para identidade: $identidade", null, $identidade);
        Response::error('Login ou senha incorretos.', 401);
    }
} catch (Exception $e) {
    error_log('[SISMIL] Erro interno: ' . $e->getMessage());
    Response::error('Erro interno do servidor.', 500);
}