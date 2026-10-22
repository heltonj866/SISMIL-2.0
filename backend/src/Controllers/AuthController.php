<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use Sismil\Services\AuditLogger;
use Sismil\Services\SchemaService;
use PDO;

class AuthController {
    
    public function login(Request $request) {
        $username = trim((string)$request->getBody('username'));
        $password = (string)$request->getBody('password');

        if (!$username || !$password) {
            Response::error('Usuário e senha são obrigatórios', 400);
        }

        $rate_id = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!check_rate_limit($rate_id, 5, 300)) {
            AuditLogger::log('RATE_LIMIT_BLOCK', "Bloqueio temporário de IP por excesso de tentativas falhas de login (User: {$username})");
            Response::error('Muitas tentativas falhas. Tente novamente em 5 minutos.', 429);
        }

        try {
            SchemaService::ensureSchema();
            $pdo = Database::getInstance();
            $usernameClean = preg_replace('/\D/', '', $username);
            $stmt = $pdo->prepare("
                SELECT u.id, u.identidade as username, u.senha_hash as password_hash, u.role, 
                       u.militar_id, COALESCE(m.nome_guerra, u.nome) as nome_guerra, 
                       COALESCE(m.subunidade, u.subunidade) as subunidade
                FROM tb_usuarios u
                LEFT JOIN tb_militares m ON u.militar_id = m.id
                WHERE (u.identidade = :u1 OR REPLACE(REPLACE(u.identidade, '.', ''), '-', '') = :u2) AND u.ativo = 1
            ");
            $stmt->execute([':u1' => $username, ':u2' => $usernameClean ?: $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Se ainda não possui conta em tb_usuarios ou está com senha vazia (aguardando primeiro acesso)
            if ((!$user || empty($user['password_hash'])) && strlen($usernameClean) === 11) {
                $stmtMil = $pdo->prepare("
                    SELECT id FROM tb_militares
                    WHERE status_ativo = 1 AND REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?
                    LIMIT 1
                ");
                $stmtMil->execute([$usernameClean]);
                if ($stmtMil->fetchColumn()) {
                    Response::error('Primeiro acesso pendente. Clique em "Novo Usuário (Primeiro Acesso)" abaixo para cadastrar sua senha.', 403);
                    return;
                }
            }

            if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
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
                    'militar_id' => $user['militar_id'],
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

    public function primeiroAcesso(Request $request) {
        $cpf = trim((string)($request->getBody('cpf') ?? ''));
        $dtNascimento = trim((string)($request->getBody('dt_nascimento') ?? ''));
        $idtMilitar = trim((string)($request->getBody('idt_militar') ?? ''));
        $senha = (string)($request->getBody('nova_senha') ?? $request->getBody('senha') ?? '');
        $confirmar = (string)($request->getBody('confirmar_senha') ?? $request->getBody('confirmar') ?? '');

        $cpfClean = preg_replace('/\D/', '', $cpf);
        if (strlen($cpfClean) !== 11 || $senha === '') {
            Response::error('Informe um CPF válido de 11 dígitos e a senha desejada.', 400);
            return;
        }

        if (strlen($senha) < 6) {
            Response::error('A senha deve ter no mínimo 6 caracteres.', 400);
            return;
        }

        if ($confirmar !== '' && $senha !== $confirmar) {
            Response::error('A confirmação de senha não confere.', 400);
            return;
        }

        $rate_id = 'primeiro_acesso_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!check_rate_limit($rate_id, 6, 300)) {
            AuditLogger::log('RATE_LIMIT_BLOCK', "Bloqueio de IP no Primeiro Acesso (CPF: {$cpf})");
            Response::error('Muitas tentativas. Aguarde 5 minutos e tente novamente.', 429);
            return;
        }

        try {
            SchemaService::ensureSchema();
            $pdo = Database::getInstance();

            // Localiza o militar ativo pelo CPF
            $stmtMil = $pdo->prepare("
                SELECT id, cpf, posto_grad, nome_guerra, nome_completo, subunidade, dt_nascimento, idt_militar
                FROM tb_militares
                WHERE status_ativo = 1
                  AND REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?
                LIMIT 1
            ");
            $stmtMil->execute([$cpfClean]);
            $militar = $stmtMil->fetch(PDO::FETCH_ASSOC);

            if (!$militar) {
                AuditLogger::log('PRIMEIRO_ACESSO_FALHA', "Tentativa de primeiro acesso com CPF não encontrado: {$cpf}");
                Response::error('Nenhum militar ativo foi localizado com este CPF. Procure a Sargenteação para atualizar seu CPF na ficha.', 404);
                return;
            }

            // Validação de segurança: exige Data de Nascimento (se o militar tiver data cadastrada no banco) ou Identidade Militar
            $dtBanco = (!empty($militar['dt_nascimento']) && $militar['dt_nascimento'] !== '0000-00-00')
                ? substr((string)$militar['dt_nascimento'], 0, 10)
                : '';
            $idtBancoClean = preg_replace('/\D/', '', (string)($militar['idt_militar'] ?? ''));
            $idtInputClean = preg_replace('/\D/', '', $idtMilitar);

        $dtBanco = (!empty($militar['dt_nascimento']) && $militar['dt_nascimento'] !== '0000-00-00')
            ? substr((string)$militar['dt_nascimento'], 0, 10)
            : '';
        $idtBancoClean = preg_replace('/\D/', '', (string)($militar['idt_militar'] ?? ''));
        $idtInputClean = preg_replace('/\D/', '', $idtMilitar);

        // Converte DD/MM/AAAA para AAAA-MM-DD se necessário
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dtNascimento, $mDate)) {
            $dtNascimento = "{$mDate[3]}-{$mDate[2]}-{$mDate[1]}";
        }

        if ($dtBanco !== '') {
            if ($dtNascimento === '' || $dtNascimento !== $dtBanco) {
                AuditLogger::log('PRIMEIRO_ACESSO_FALHA', "Data de nascimento divergente no primeiro acesso para o militar ID {$militar['id']}.");
                Response::error('A Data de Nascimento informada não confere com o seu cadastro militar.', 403);
                return;
            }
        } elseif ($idtBancoClean !== '') {
            if ($idtInputClean === '' || $idtInputClean !== $idtBancoClean) {
                AuditLogger::log('PRIMEIRO_ACESSO_FALHA', "Identidade militar divergente no primeiro acesso para o militar ID {$militar['id']}.");
                Response::error('A Identidade Militar informada não confere com o seu cadastro.', 403);
                return;
            }
        }

            // Verifica se já existe usuário em tb_usuarios
            $cpfFormatado = vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($cpfClean));
            $stmtUser = $pdo->prepare("
                SELECT id, senha_hash, role
                FROM tb_usuarios
                WHERE militar_id = :mid
                   OR identidade = :u1
                   OR REPLACE(REPLACE(identidade, '.', ''), '-', '') = :u2
                LIMIT 1
            ");
            $stmtUser->execute([
                ':mid' => (int)$militar['id'],
                ':u1'  => $cpfFormatado,
                ':u2'  => $cpfClean
            ]);
            $usuarioExistente = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($usuarioExistente && !empty($usuarioExistente['senha_hash'])) {
                Response::error('Este CPF já possui senha cadastrada. Faça login normalmente ou solicite a redefinição de senha na Sargenteação.', 409);
                return;
            }

            $hash = password_hash($senha, PASSWORD_DEFAULT);

            if ($usuarioExistente) {
                $stmtUp = $pdo->prepare("
                    UPDATE tb_usuarios
                    SET identidade = ?, senha_hash = ?, ativo = 1, militar_id = ?, nome = ?, posto_grad = ?, subunidade = ?
                    WHERE id = ?
                ");
                $stmtUp->execute([
                    $cpfFormatado,
                    $hash,
                    (int)$militar['id'],
                    $militar['nome_guerra'],
                    $militar['posto_grad'],
                    $militar['subunidade'],
                    (int)$usuarioExistente['id']
                ]);
                $userId = (int)$usuarioExistente['id'];
                $role = $usuarioExistente['role'] ?: 'militar';
            } else {
                $role = 'militar';
                $stmtIns = $pdo->prepare("
                    INSERT INTO tb_usuarios (identidade, senha_hash, role, ativo, nome, posto_grad, subunidade, militar_id)
                    VALUES (?, ?, 'militar', 1, ?, ?, ?, ?)
                ");
                $stmtIns->execute([
                    $cpfFormatado,
                    $hash,
                    $militar['nome_guerra'],
                    $militar['posto_grad'],
                    $militar['subunidade'],
                    (int)$militar['id']
                ]);
                $userId = (int)$pdo->lastInsertId();
            }

            // Autentica automaticamente após criar a senha
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $userId;
            $_SESSION['usuario_username'] = $cpfFormatado;
            $_SESSION['usuario_role'] = $role;
            $_SESSION['usuario_nome'] = $militar['nome_guerra'];
            $_SESSION['usuario_sub'] = $militar['subunidade'];
            $_SESSION['militar_id'] = (int)$militar['id'];
            $_SESSION['logado'] = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            AuditLogger::log('PRIMEIRO_ACESSO_SUCESSO', "Militar {$militar['posto_grad']} {$militar['nome_guerra']} (ID {$militar['id']}) cadastrou sua senha de primeiro acesso.");

            Response::json([
                'id'         => $userId,
                'username'   => $cpfFormatado,
                'role'       => $role,
                'nome'       => $militar['nome_guerra'],
                'subunidade' => $militar['subunidade'],
                'militar_id' => (int)$militar['id'],
                'csrf_token' => $_SESSION['csrf_token']
            ], 'Senha cadastrada com sucesso! Bem-vindo ao SISMIL.');
        } catch (\Exception $e) {
            error_log('[SISMIL] Erro no primeiro acesso: ' . $e->getMessage());
            Response::error('Erro interno ao processar o primeiro acesso.', 500);
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
            'militar_id' => $_SESSION['militar_id'] ?? null,
            'csrf_token' => $_SESSION['csrf_token'] ?? null
        ]);
    }
}
