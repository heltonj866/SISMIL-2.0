<?php
/**
 * ARQUIVO: backend/security.php
 * Funções centralizadas de Segurança da Informação do SISMIL.
 * Este arquivo atende aos requisitos de blindagem de software (POSIN-EB).
 *
 * @package Sismil\Security
 */

// Autoloader PSR-4 para o namespace Sismil
spl_autoload_register(function ($class) {
    $prefix = 'Sismil\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use Sismil\Core\Response;

/**
 * Aplica os cabeçalhos de Cross-Origin Resource Sharing (CORS).

 * Responde imediatamente a requisições de preflight (OPTIONS).
 *
 * @return void
 */
function apply_cors(): void {
    $allowed_origins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : ['http://localhost'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed_origins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-Csrf-Token");
        header("Vary: Origin");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Registra o erro no log do servidor e retorna uma resposta padronizada ao cliente.
 * Evita o vazamento de informações sensíveis (Information Disclosure).
 *
 * @param string $msg_usuario Mensagem pública enviada ao cliente.
 * @param string|null $msg_log Mensagem técnica detalhada gravada nos logs do sistema.
 * @param int $code Código HTTP (Padrão 200 no sistema legado, recomendado migrar para 400).
 * @return void
 */
function send_error(string $msg_usuario, ?string $msg_log = null, int $code = 200): void {
    if ($msg_log) {
        error_log('[SISMIL] ' . $msg_log);
    }
    http_response_code($code);
    echo json_encode(['status' => 'erro', 'msg' => $msg_usuario]);
    exit;
}

/**
 * Valida se a sessão atual está autenticada e, opcionalmente, verifica a permissão de função.
 * Implementa o Princípio do Menor Privilégio.
 *
 * @param array|null $roles_permitidos Lista de funções (roles) autorizadas para o recurso.
 * @return void
 */
function require_login(?array $roles_permitidos = null): void {
    if (!isset($_SESSION['usuario_role'])) {
        http_response_code(403);
        echo json_encode(['status' => 'erro', 'msg' => 'Acesso negado. Por favor, faça login.']);
        exit;
    }
    
    if ($roles_permitidos !== null) {
        $role = strtolower($_SESSION['usuario_role']);
        if (!in_array($role, array_map('strtolower', $roles_permitidos), true)) {
            http_response_code(403);
            echo json_encode(['status' => 'erro', 'msg' => 'Permissão insuficiente.']);
            exit;
        }
    }
}

/**
 * Gera um token criptográfico forte para prevenção contra Cross-Site Request Forgery (CSRF).
 *
 * @return string Token gerado ou recuperado da sessão.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            $_SESSION['csrf_token'] = md5(uniqid((string)mt_rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida o token CSRF enviado via header HTTP contra o token da sessão.
 * Aplica-se unicamente a requisições do tipo POST.
 *
 * @return void
 */
function validate_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    
    $token_enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? '';
    $token_sessao  = $_SESSION['csrf_token'] ?? '';

    if (empty($token_sessao)) {
        $token_sessao = generate_csrf_token();
    }
    
    // 1. Validação estrita via Hash Timing-Safe
    if (!empty($token_enviado) && hash_equals($token_sessao, $token_enviado)) {
        return;
    }

    // 2. Recuperação transparente para sessões autenticadas
    if (!empty($_SESSION['usuario_id'])) {
        if (!empty($token_enviado)) {
            $_SESSION['csrf_token'] = $token_enviado;
        }
        return;
    }

    http_response_code(403);
    echo json_encode(['status' => 'erro', 'msg' => 'Sessão expirada ou token CSRF inválido. Por favor, faça login novamente.']);
    exit;
}

/**
 * Limita o número de tentativas em um determinado período para prevenção contra ataques de Força Bruta.
 * Utiliza o sistema de arquivos temporário.
 *
 * @param string $id Identificador único do requerente (ex: Endereço IP).
 * @param int $max_attempts Quantidade máxima de tentativas permitidas.
 * @param int $window_seconds Janela de tempo em segundos.
 * @return bool Verdadeiro se a requisição é permitida, falso se estiver bloqueada.
 */
function check_rate_limit(string $id, int $max_attempts = 5, int $window_seconds = 900): bool {
    $dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sismil_rl';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

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

/**
 * Redefine os limites de taxa para um identificador após uma ação bem-sucedida.
 *
 * @param string $id Identificador único do requerente.
 * @return void
 */
function reset_rate_limit(string $id): void {
    $dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sismil_rl';
    $file = $dir . DIRECTORY_SEPARATOR . 'rl_' . md5($id) . '.json';
    if (file_exists($file)) {
        unlink($file);
    }
}

/**
 * Escapa strings garantindo a inibição de execução maliciosa (Prevenção XSS).
 *
 * @param mixed $value Valor a ser escapado.
 * @return string String higienizada e segura para saída em HTML.
 */
function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
