<?php
// ARQUIVO: backend/get_veiculos.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(); // Qualquer usuário autenticado
require 'db_connect.php';

$militar_id = $_GET['militar_id'] ?? '';
if (empty($militar_id)) {
    echo json_encode(['status' => 'erro', 'msg' => 'Militar ID não informado.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tb_veiculos WHERE militar_id = ? ORDER BY id DESC");
    $stmt->execute([$militar_id]);
    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove a observação do S2 para usuários comuns
    if ($_SESSION['usuario_role'] === 'user') {
        foreach ($veiculos as &$v) {
            unset($v['observacao_s2']);
        }
        unset($v);
    }

    echo json_encode(['status' => 'sucesso', 'dados' => $veiculos]);
} catch (Exception $e) {
    send_error("Erro ao carregar veículos.", $e->getMessage());
}
?>