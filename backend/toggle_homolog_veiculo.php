<?php
// ARQUIVO: backend/toggle_homolog_veiculo.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 's2']); // Apenas admin ou s2
validate_csrf();
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
    send_error("Erro ao alterar homologação do veículo.", $e->getMessage());
}
?>