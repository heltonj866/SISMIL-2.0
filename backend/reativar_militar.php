<?php
// ARQUIVO: backend/reativar_militar.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
require_login(['admin', 'sargenteacao']);
validate_csrf();
require 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode(['status' => 'erro', 'msg' => 'ID do militar não informado.']);
    exit;
}

try {
    // Retorna o status_ativo para 1 e limpa a data de desligamento
    $sql = "UPDATE tb_militares SET status_ativo = 1, data_desligamento = NULL WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    echo json_encode(['status' => 'sucesso', 'msg' => 'Militar reativado e integrado ao Efetivo Pronto.']);
} catch (PDOException $e) {
    send_error('Erro ao reativar militar.', 'reativar_militar.php: ' . $e->getMessage());
}
?>