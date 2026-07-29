<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use Sismil\Services\AuditLogger;
use PDO;

class AuthController {
    
    public function login(Request $request) {
        $username = $request->getBody('username');
        $password = $request->getBody('password');

        if (!$username || !$password) {
            Response::error('Usuário e senha são obrigatórios', 400);
        }

        $rate_id = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!check_rate_limit($rate_id, 5, 300)) {
            AuditLogger::log('RATE_LIMIT_BLOCK', "Bloqueio temporário de IP por excesso de tentativas falhas de login (User: {$username})");
            Response::error('Muitas tentativas falhas. Tente novamente em 5 minutos.', 429);
        }

        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("
                SELECT u.id, u.identidade as username, u.senha_hash as password_hash, u.role, 
                       u.militar_id, COALESCE(m.nome_guerra, u.nome) as nome_guerra, 
                       COALESCE(m.subunidade, u.subunidade) as subunidade
                FROM tb_usuarios u
                LEFT JOIN tb_militares m ON u.militar_id = m.id
                WHERE u.identidade = ? AND u.ativo = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_username'] = $user['username'];
                $_SESSION['usuario_role'] = $user['role'];
                $_SESSION['usuario_nome'] = $user['nome_guerra'];
                $_SESSION['usuario_sub'] = $user['subunidade'];
                $_SESSION['militar_id'] = $user['militar_id'];
                $_SESSION['logado'] = true;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                AuditLogger::log('LOGIN_SUCCESS', "Usuário {$username} realizou login com sucesso.");

                Response::json([
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'nome' => $user['nome_guerra'],
                    'subunidade' => $user['subunidade'],
                    'csrf_token' => $_SESSION['csrf_token']
                ], 'Login realizado com sucesso.');
            } else {
                AuditLogger::log('LOGIN_FAIL', "Tentativa de login falha para o usuário: {$username}");
                Response::error('Credenciais inválidas ou usuário inativo', 401);
            }
        } catch (\Exception $e) {
            error_log('[SISMIL] Erro no login: ' . $e->getMessage());
            Response::error('Erro interno do servidor', 500);
        }
    }

    public function logout(Request $request) {
        if (isset($_SESSION['usuario_username'])) {
            AuditLogger::log('LOGOUT', "Usuário {$_SESSION['usuario_username']} encerrou a sessão.");
        }
        
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        Response::json(null, 'Sessão encerrada com sucesso.');
    }

    public function check(Request $request) {
        if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
            Response::error('Não autenticado', 401);
        }

        Response::json([
            'id' => $_SESSION['usuario_id'],
            'username' => $_SESSION['usuario_username'],
            'role' => $_SESSION['usuario_role'],
            'nome' => $_SESSION['usuario_nome'],
            'subunidade' => $_SESSION['usuario_sub'],
            'csrf_token' => $_SESSION['csrf_token'] ?? null
        ]);
    }
}
