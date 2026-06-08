<?php
// ============================================================
// ARQUIVO: backend/security.php
// Funções centralizadas de segurança do SISMIL 2.0
// ============================================================

// --- CORS ---
// Permite apenas origens configuradas em config.php
function apply_cors() {
    $allowed_origins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : ['http://localhost'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-Csrf-Token");
        header("Vary: Origin");
    }

    // Responde preflight OPTIONS e encerra
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// --- ERRO GENÉRICO (não expõe detalhes internos) ---
function send_error($msg_usuario, $msg_log = null, $code = 200) {
    if ($msg_log) {
        error_log('[SISMIL] ' . $msg_log);
    }
    http_response_code($code);
    echo json_encode(['status' => 'erro', 'msg' => $msg_usuario]);
    exit;
}

// --- VERIFICAÇÃO DE SESSÃO AUTENTICADA ---
function require_login($roles_permitidos = null) {
    if (!isset($_SESSION['usuario_role'])) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'msg' => 'Acesso negado. Por favor, faça login.']);
        exit;
    }
    if ($roles_permitidos !== null) {
        $role = strtolower($_SESSION['usuario_role']);
        if (!in_array($role, array_map('strtolower', $roles_permitidos))) {
            http_response_code(403);
            echo json_encode(['status' => 'erro', 'msg' => 'Permissão insuficiente.']);
            exit;
        }
    }
}

// --- CSRF: GERAÇÃO DE TOKEN ---
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// --- CSRF: VALIDAÇÃO ---
// Verifica o token enviado no header X-Csrf-Token
function validate_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return; // Só valida POST
    $token_enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $token_sessao  = $_SESSION['csrf_token'] ?? '';
    if (empty($token_enviado) || empty($token_sessao) || !hash_equals($token_sessao, $token_enviado)) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'msg' => 'Requisição inválida (CSRF).']);
        exit;
    }
}

// --- RATE LIMITING (baseado em arquivo no sistema de arquivos) ---
// Limita tentativas por IP em uma janela de tempo.
// $id = identificador único (ex: IP + endpoint)
// Retorna true se permitido, false se bloqueado.
function check_rate_limit($id, $max_attempts = 5, $window_seconds = 900) {
    $dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sismil_rl';
    if (!is_dir($dir)) mkdir($dir, 0700, true);

    $file = $dir . DIRECTORY_SEPARATOR . 'rl_' . md5($id) . '.json';
    $now  = time();

    $data = ['attempts' => 0, 'reset_at' => $now + $window_seconds, 'blocked' => false];
    if (file_exists($file)) {
        $stored = json_decode(file_get_contents($file), true);
        if ($stored && $now < $stored['reset_at']) {
            $data = $stored;
        }
    }

    $data['attempts']++;
    if ($data['attempts'] > $max_attempts) {
        $data['blocked'] = true;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return false;
    }

    file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

// --- RATE LIMITING: RESETAR (em caso de sucesso) ---
function reset_rate_limit($id) {
    $dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sismil_rl';
    $file = $dir . DIRECTORY_SEPARATOR . 'rl_' . md5($id) . '.json';
    if (file_exists($file)) unlink($file);
}

// --- SAFE OUTPUT: escapa para HTML (evita XSS) ---
function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
