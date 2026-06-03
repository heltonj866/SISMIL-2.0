<?php
// ARQUIVO: backend/toggle_homolog_veiculo.php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['usuario_role']) || !in_array(strtolower($_SESSION['usuario_role']), ['admin', 's2'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'erro', 'msg' => 'Acesso negado.']);
    exit;
}

require 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$status = $data['status'] ?? null;
$obs = $data['observacao'] ?? null;

if (!$id) {
    echo json_encode(['status' => 'erro', 'msg' => 'ID inválido']);
    exit;
}

try {
    // Atualiza o status de homologação e salva a observação da S2
    $stmt = $pdo->prepare("UPDATE tb_veiculos SET homologado = ?, observacao_s2 = ? WHERE id = ?");
    $stmt->execute([$status, $obs, $id]);
    
    echo json_encode(['status' => 'sucesso']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'erro', 'msg' => $e->getMessage()]);
}
?>