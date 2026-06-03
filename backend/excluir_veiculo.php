<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['usuario_role']) || !in_array(strtolower($_SESSION['usuario_role']), ['admin', 'sargenteacao'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'erro', 'msg' => 'Acesso negado.']);
    exit;
}

require 'db_connect.php';
$data = json_decode(file_get_contents("php://input"), true);
$pdo->prepare("DELETE FROM tb_veiculos WHERE id = ?")->execute([$data['id']]);
echo json_encode(['status' => 'sucesso']);
?>