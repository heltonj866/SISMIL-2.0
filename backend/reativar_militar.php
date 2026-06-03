<?php
// ARQUIVO: backend/reativar_militar.php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['usuario_role']) || !in_array(strtolower($_SESSION['usuario_role']), ['admin', 'sargenteacao'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'erro', 'msg' => 'Acesso negado.']);
    exit;
}

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
    echo json_encode(['status' => 'erro', 'msg' => 'Erro no banco: ' . $e->getMessage()]);
}
?>