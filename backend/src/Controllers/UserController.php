<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use Sismil\Services\AuditLogger;
use PDO;
use Exception;

class UserController {
    
    public function get(Request $request) {
        require_login(['admin']);
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query("SELECT id, username as identidade, role, status_ativo as ativo, nome_guerra as nome, posto_grad, subunidade FROM tb_usuarios ORDER BY id ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $nome = strtoupper(trim($dados['nome'] ?? ''));
        $posto = trim($dados['posto_grad'] ?? '');
        $sub = trim($dados['new_user_subunidade'] ?? '');

        if (empty($idt) || empty($pass) || empty($role) || empty($nome) || empty($posto)) {
            Response::json(['status' => 'erro', 'msg' => 'Preencha os campos obrigatórios.']);
        }

        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("SELECT id FROM tb_usuarios WHERE username = ?");
            $stmt->execute([$idt]);
            if ($stmt->fetch()) {
                Response::json(['status' => 'erro', 'msg' => 'Login já existe.']);
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO tb_usuarios (username, password_hash, role, status_ativo, nome_guerra, posto_grad, subunidade) VALUES (?, ?, ?, 1, ?, ?, ?)");
            $stmt->execute([$idt, $hash, $role, $nome, $posto, $sub]);
            
            AuditLogger::log('USER_CREATE', "Administrador criou o usuário {$idt}");
            Response::json(['status' => 'sucesso']);
        } catch (Exception $e) {
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
        $nome = strtoupper(trim($dados['nome'] ?? ''));
        $posto = trim($dados['posto_grad'] ?? '');
        $sub = trim($dados['new_user_subunidade'] ?? '');

        if ($id <= 0 || empty($role) || empty($nome) || empty($posto)) {
            Response::json(['status' => 'erro', 'msg' => 'Dados inválidos.']);
        }

        try {
            $pdo = Database::getInstance();
            
            if (!empty($pass)) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE tb_usuarios SET role=?, password_hash=?, status_ativo=?, nome_guerra=?, posto_grad=?, subunidade=? WHERE id=?");
                $stmt->execute([$role, $hash, $ativo, $nome, $posto, $sub, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE tb_usuarios SET role=?, status_ativo=?, nome_guerra=?, posto_grad=?, subunidade=? WHERE id=?");
                $stmt->execute([$role, $ativo, $nome, $posto, $sub, $id]);
            }
            
            AuditLogger::log('USER_UPDATE', "Administrador atualizou o usuário ID {$id}");
            Response::json(['status' => 'sucesso']);
        } catch (Exception $e) {
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
