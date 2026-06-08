<?php
// ARQUIVO: backend/create_user.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
require_login(['admin']); // Apenas admin
validate_csrf();          // Valida token CSRF
require 'db_connect.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $identidade = trim($input['new_user_idt'] ?? '');
    $senha      = $input['new_user_pass'] ?? '';
    $role       = $input['new_user_role'] ?? 'user';
    $subunidade = $input['new_user_subunidade'] ?? null;
    $nome       = $input['nome'] ?? null;
    $posto_grad = $input['posto_grad'] ?? null;

    if (empty($identidade) || empty($senha)) {
        throw new Exception("Preencha Login e Senha.");
    }

    // Verifica Duplicidade
    $check = $pdo->prepare("SELECT id FROM tb_usuarios WHERE identidade = ?");
    $check->execute([$identidade]);
    if ($check->rowCount() > 0) {
        throw new Exception("Login já existe!");
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $sql  = "INSERT INTO tb_usuarios (identidade, senha_hash, role, ativo, subunidade, nome, posto_grad) VALUES (?, ?, ?, 1, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$identidade, $hash, $role, $subunidade, $nome, $posto_grad])) {
        echo json_encode(['status' => 'sucesso', 'msg' => 'Usuário criado com sucesso!']);
    } else {
        throw new Exception("Erro ao criar usuário.");
    }

} catch (Exception $e) {
    send_error($e->getMessage(), 'create_user.php: ' . $e->getMessage());
}
?>