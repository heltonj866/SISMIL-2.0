<?php
// ARQUIVO: backend/excluir_veiculo.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 'sargenteacao']);
validate_csrf();
require 'db_connect.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    if (!$id) {
        throw new Exception("ID do veículo inválido.");
    }
    
    $stmt = $pdo->prepare("DELETE FROM tb_veiculos WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['status' => 'sucesso']);
} catch (Exception $e) {
    send_error("Erro ao excluir veículo.", $e->getMessage());
}
?>