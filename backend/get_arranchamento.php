<?php
header('Content-Type: application/json; charset=utf-8');
require 'db_connect.php';
session_start();

if (!isset($_SESSION['usuario_role']) || !in_array($_SESSION['usuario_role'], ['admin', 'enc_mat'])) {
    echo json_encode(['status' => 'erro', 'msg' => 'Acesso negado.']);
    exit;
}

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
    echo json_encode(['status' => 'erro', 'msg' => $e->getMessage()]);
}
?>
