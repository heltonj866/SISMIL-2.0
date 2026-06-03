<?php
// ARQUIVO: backend/get_veiculos.php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['usuario_role'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'erro', 'msg' => 'Acesso negado. Por favor, faça login.']);
    exit;
}

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
    echo json_encode(['status' => 'erro', 'msg' => 'Erro BD: ' . $e->getMessage()]);
}
?>