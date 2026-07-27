<?php
// ARQUIVO: backend/check_session.php
require_once __DIR__ . '/security.php';
header('Content-Type: application/json');
apply_cors();

// Garante os mesmos atributos de cookie seguro definidos no login.php
$is_prod = defined('APP_ENV_DEV') && APP_ENV_DEV === false;
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $is_prod,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Verifica se existe um ID de usuário salvo no servidor
if (isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_role'])) {
    echo json_encode([
        'status'     => 'logado',
        'role'       => $_SESSION['usuario_role'],
        'nome'       => $_SESSION['usuario_nome'] ?? 'Militar',
        'csrf_token' => generate_csrf_token(), // Restaura token CSRF após reload
    ]);
} else {
    echo json_encode(['status' => 'nao_logado']);
}
?>