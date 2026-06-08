<?php
// ARQUIVO: backend/homologar_veiculo.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 's2']);
validate_csrf();
require 'db_connect.php';

try {
    $id = $_POST['id_militar'] ?? null;
    $homologado = $_POST['homologado'] ?? null;
    
    if (!$id) throw new Exception("ID inválido");
    
    $sql = "UPDATE tb_militares SET homologado = :homologado WHERE id = :id";
    $pdo->prepare($sql)->execute([':homologado' => $homologado, ':id' => $id]);
    
    echo json_encode(['status' => 'sucesso']);
} catch (Exception $e) { 
    send_error("Erro ao homologar militar.", $e->getMessage()); 
}
?>
