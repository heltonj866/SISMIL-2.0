<?php
// ARQUIVO: backend/get_users.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin']);
require 'db_connect.php';

try {
    $sql = "SELECT * FROM tb_usuarios";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach($users as $u) {
        $data[] = [
            'id' => $u['id'],
            'identidade' => $u['identidade'],
            'role' => $u['role'],
            'ativo' => isset($u['ativo']) ? $u['ativo'] : 1,
            'posto_grad' => isset($u['posto_grad']) ? $u['posto_grad'] : '',
            'nome_guerra' => isset($u['nome_guerra']) ? $u['nome_guerra'] : 'Usuário',
            'subunidade' => isset($u['subunidade']) ? $u['subunidade'] : '---'
        ];
    }

    echo json_encode(['status' => 'sucesso', 'data' => $data]);

} catch (Exception $e) {
    send_error("Erro ao buscar lista de usuários.", $e->getMessage());
}
?>