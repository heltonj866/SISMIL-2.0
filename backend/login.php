<?php
// ARQUIVO: backend/login.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
require_once 'db_connect.php';
apply_cors();

// --- MED-01 + HIGH-01: Iniciar sessão com cookies seguros ---
// O flag 'secure' é ativado automaticamente quando APP_ENV_DEV = false (produção com HTTPS)
$is_prod = defined('APP_ENV_DEV') && APP_ENV_DEV === false;
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $is_prod,  // true em produção (HTTPS), false em desenvolvimento local
    'httponly' => true,       // Bloqueia acesso via JavaScript (XSS mitigation)
    'samesite' => 'Strict'   // Bloqueia envio em requisições cross-site (CSRF mitigation)
]);
session_start();

$input      = json_decode(file_get_contents('php://input'), true);
$identidade = trim($input['identidade'] ?? '');
$senha      = $input['senha'] ?? '';

if (empty($identidade) || empty($senha)) {
    send_error('Preencha o login e a senha.');
}

// --- HIGH-04: Rate limiting por IP (máx 10 tentativas em 15 min) ---
$rate_id = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rate_id, 10, 900)) {
    send_error('Muitas tentativas de login. Tente novamente em 15 minutos.');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tb_usuarios WHERE identidade = ? LIMIT 1");
    $stmt->execute([$identidade]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($senha, $user['senha_hash'])) {
        if ($user['ativo'] == 0) {
            send_error('Conta inativa. Contate o administrador.');
        }

        // Login bem-sucedido: zera o rate limit
        reset_rate_limit($rate_id);

        // --- MED-01: Regenerar ID de sessão para prevenir session fixation ---
        session_regenerate_id(true);

        $_SESSION['usuario_id']    = $user['id'];
        $_SESSION['usuario_role']  = $user['role'];
        $_SESSION['usuario_login'] = $user['identidade'];
        $_SESSION['usuario_sub']   = $user['subunidade'] ?? '';

        // --- HIGH-01: Gerar token CSRF para esta sessão ---
        $csrf_token = generate_csrf_token();

        echo json_encode([
            'status'     => 'sucesso',
            'role'       => $user['role'],
            'subunidade' => $_SESSION['usuario_sub'],
            'csrf_token' => $csrf_token,
        ]);
    } else {
        send_error('Login ou senha incorretos.');
    }
} catch (Exception $e) {
    send_error('Erro interno do servidor.', 'login.php - ' . $e->getMessage());
}
?>