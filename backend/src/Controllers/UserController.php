<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use Sismil\Services\AuditLogger;
use Sismil\Repositories\UsuarioRepository;
use PDO;
use Exception;

class UserController {
    
    public function get(Request $request) {
        require_login(['admin']);
        try {
            $repo = new UsuarioRepository();
            $users = $repo->findAllSecure();
            Response::json(['status' => 'sucesso', 'data' => $users]);
        } catch (Exception $e) {
            Response::error('Erro ao buscar usuários', 500);
        }
    }

    public function create(Request $request) {
        require_login(['admin']);
        validate_csrf();

        $dados = $request->getBody();
        $idt = trim($dados['new_user_idt'] ?? '');
        $pass = trim($dados['new_user_pass'] ?? '');
        $role = trim($dados['new_user_role'] ?? '');
        
        $militarId = !empty($dados['militar_id']) ? (int)$dados['militar_id'] : null;
        
        // Se não houver militar vinculado, pega os dados manuais
        $nome = strtoupper(trim($dados['nome'] ?? ''));
        $posto = trim($dados['posto_grad'] ?? '');
        $sub = trim($dados['new_user_subunidade'] ?? '');

        if (empty($idt) || empty($pass) || empty($role)) {
            Response::json(['status' => 'erro', 'msg' => 'Preencha os campos obrigatórios.']);
        }
        
        if (empty($militarId) && (empty($nome) || empty($posto))) {
            Response::json(['status' => 'erro', 'msg' => 'Se o usuário não for vinculado a um militar, preencha Nome e Posto.']);
        }

        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("SELECT id FROM tb_usuarios WHERE identidade = ?");
            $stmt->execute([$idt]);
            if ($stmt->fetch()) {
                Response::json(['status' => 'erro', 'msg' => 'Login já existe.']);
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO tb_usuarios (identidade, senha_hash, role, ativo, nome, posto_grad, subunidade, militar_id) VALUES (?, ?, ?, 1, ?, ?, ?, ?)");
            $stmt->execute([$idt, $hash, $role, $nome, $posto, $sub, $militarId]);
            
            AuditLogger::log('USER_CREATE', "Administrador criou o usuário {$idt}");
            Response::json(['status' => 'sucesso']);
        } catch (Exception $e) {
            error_log('[SISMIL] Erro: ' . $e->getMessage());
            Response::json(['status' => 'erro', 'msg' => 'Erro interno']);
        }
    }

    public function update(Request $request) {
        require_login(['admin']);
        validate_csrf();

        $dados = $request->getBody();
        $id = (int)($dados['edit_id'] ?? 0);
        $role = trim($dados['new_user_role'] ?? '');
        $pass = trim($dados['new_user_pass'] ?? '');
        $ativo = (int)($dados['ativo'] ?? 1);
        
        $militarId = !empty($dados['militar_id']) ? (int)$dados['militar_id'] : null;
        
        // Se não houver militar vinculado, pega os dados manuais
        $nome = strtoupper(trim($dados['nome'] ?? ''));
        $posto = trim($dados['posto_grad'] ?? '');
        $sub = trim($dados['new_user_subunidade'] ?? '');

        if ($id <= 0 || empty($role)) {
            Response::json(['status' => 'erro', 'msg' => 'Dados inválidos.']);
        }
        
        if (empty($militarId) && (empty($nome) || empty($posto))) {
            Response::json(['status' => 'erro', 'msg' => 'Se o usuário não for vinculado a um militar, preencha Nome e Posto.']);
        }

        try {
            $pdo = Database::getInstance();
            
            if (!empty($pass)) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE tb_usuarios SET role=?, senha_hash=?, ativo=?, nome=?, posto_grad=?, subunidade=?, militar_id=? WHERE id=?");
                $stmt->execute([$role, $hash, $ativo, $nome, $posto, $sub, $militarId, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE tb_usuarios SET role=?, ativo=?, nome=?, posto_grad=?, subunidade=?, militar_id=? WHERE id=?");
                $stmt->execute([$role, $ativo, $nome, $posto, $sub, $militarId, $id]);
            }
            
            AuditLogger::log('USER_UPDATE', "Administrador atualizou o usuário ID {$id}");
            Response::json(['status' => 'sucesso']);
        } catch (Exception $e) {
            error_log('[SISMIL] Erro: ' . $e->getMessage());
            Response::json(['status' => 'erro', 'msg' => 'Erro interno']);
        }
    }

    public function delete(Request $request) {
        require_login(['admin']);
        validate_csrf();

        $id = (int)($request->getBody('id_user') ?? 0);
        if ($id <= 0) Response::json(['status' => 'erro', 'msg' => 'ID inválido']);

        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("DELETE FROM tb_usuarios WHERE id=?");
            $stmt->execute([$id]);
            AuditLogger::log('USER_DELETE', "Administrador deletou o usuário ID {$id}");
            Response::json(['status' => 'sucesso']);
        } catch (Exception $e) {
            Response::json(['status' => 'erro', 'msg' => 'Erro interno']);
        }
    }
}
