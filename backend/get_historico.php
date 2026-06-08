<?php
// ARQUIVO: backend/get_historico.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 'sargenteacao']);
require 'db_connect.php';

$id = $_GET['id'] ?? 0;

try {
    $sql = "SELECT * FROM tb_alteracoes WHERE militar_id = ? ORDER BY data_fato DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'sucesso', 'dados' => $dados]);
} catch (PDOException $e) {
    send_error("Erro ao buscar histórico de alterações.", $e->getMessage());
}
?>