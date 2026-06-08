<?php
// ARQUIVO: backend/toggle_homolog.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 's2']);
validate_csrf();
require 'db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);
$id = $input['id'] ?? null;

if (!$id) { 
    echo json_encode(['status' => 'erro', 'msg' => 'ID inválido']); 
    exit; 
}

try {
    // Inverte de forma segura (0 vira 1, 1 vira 0, NULL vira 1)
    $sql = "UPDATE tb_militares SET homologado = IF(homologado = 1, 0, 1) WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    echo json_encode(['status' => 'sucesso']);
} catch (PDOException $e) {
    send_error("Erro ao alterar homologação do militar.", $e->getMessage());
}
?>