<?php
// ARQUIVO: backend/update_user.php
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';
session_start();

if (!isset($_SESSION['usuario_role']) || $_SESSION['usuario_role'] !== 'admin') {
    echo json_encode(['status' => 'erro', 'msg' => 'Acesso negado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['edit_id'] ?? '';
$role = $input['new_user_role'] ?? 'user';
$nova_senha = $input['new_user_pass'] ?? '';
$ativo = isset($input['ativo']) ? (int)$input['ativo'] : null;
$subunidade = $input['new_user_subunidade'] ?? null;
$nome = $input['nome'] ?? null;
$posto_grad = $input['posto_grad'] ?? null;

if (empty($id)) {
    echo json_encode(['status' => 'erro', 'msg' => 'ID do usuário não informado.']);
    exit;
}

try {
    if (!empty($nova_senha)) {
        $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        if ($ativo !== null) {
            $sql = "UPDATE tb_usuarios SET role = ?, senha_hash = ?, ativo = ?, subunidade = ?, nome = ?, posto_grad = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$role, $hash, $ativo, $subunidade, $nome, $posto_grad, $id]);
        } else {
            $sql = "UPDATE tb_usuarios SET role = ?, senha_hash = ?, subunidade = ?, nome = ?, posto_grad = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$role, $hash, $subunidade, $nome, $posto_grad, $id]);
        }
    } else {
        if ($ativo !== null) {
            $sql = "UPDATE tb_usuarios SET role = ?, ativo = ?, subunidade = ?, nome = ?, posto_grad = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$role, $ativo, $subunidade, $nome, $posto_grad, $id]);
        } else {
            $sql = "UPDATE tb_usuarios SET role = ?, subunidade = ?, nome = ?, posto_grad = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$role, $subunidade, $nome, $posto_grad, $id]);
        }
    }
    echo json_encode(['status' => 'sucesso', 'msg' => 'Atualizado com sucesso!']);
} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'msg' => 'Erro: ' . $e->getMessage()]);
}
?>