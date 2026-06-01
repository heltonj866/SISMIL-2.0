<?php
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';

try {
    $id = $_POST['id_militar'] ?? null;
    $homologado = $_POST['homologado'] ?? null;
    
    if (!$id) throw new Exception("ID inválido");
    
    $sql = "UPDATE tb_militares SET homologado = :homologado WHERE id = :id";
    $pdo->prepare($sql)->execute([':homologado' => $homologado, ':id' => $id]);
    
    echo json_encode(['status' => 'sucesso']);
} catch (Exception $e) { 
    echo json_encode(['status' => 'erro', 'msg' => $e->getMessage()]); 
}
?>
