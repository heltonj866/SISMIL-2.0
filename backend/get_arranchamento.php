<?php
// ARQUIVO: backend/get_arranchamento.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 'enc_mat']);
require 'db_connect.php';

$data = $_GET['data'] ?? date('Y-m-d');
$role = $_SESSION['usuario_role'];
$sub = $_SESSION['usuario_sub'] ?? '';

try {
    if ($role === 'enc_mat' && !empty($sub)) {
        $stmt = $pdo->prepare("SELECT * FROM tb_arranchamento WHERE data_refeicao = ? AND subunidade = ? ORDER BY id ASC");
        $stmt->execute([$data, $sub]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tb_arranchamento WHERE data_refeicao = ? ORDER BY id ASC");
        $stmt->execute([$data]);
    }
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcula totais
    $total_cafe = 0;
    $total_almoco = 0;
    
    foreach ($registros as $r) {
        if ($r['cafe']) $total_cafe++;
        if ($r['almoco']) $total_almoco++;
    }
    
    echo json_encode([
        'status' => 'sucesso',
        'data' => $data,
        'totais' => [
            'cafe' => $total_cafe,
            'almoco' => $total_almoco
        ],
        'registros' => $registros
    ]);
} catch (Exception $e) {
    send_error("Erro ao carregar dados de arranchamento.", $e->getMessage());
}
?>
