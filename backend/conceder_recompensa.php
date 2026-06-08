<?php
// ARQUIVO: backend/conceder_recompensa.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/security.php';
session_start();
apply_cors();
require_login(['admin', 'sargenteacao']);
validate_csrf();
require 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['militar_id'] ?? 0;
// Adaptação: Usa usuario_idt como autor
$autor = $_SESSION['usuario_idt'] ?? 'Sistema';

try {
    $pdo->beginTransaction();
    
    // Verifica Saldo
    $stmt = $pdo->prepare("SELECT id FROM tb_alteracoes WHERE militar_id = ? AND categoria = 'ELOGIO' AND tipo_detalhe = 'FO+' AND consumido = 0 LIMIT 5");
    $stmt->execute([$id]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($ids) < 5) throw new Exception("Saldo insuficiente (Mínimo 5 FO+).");

    // Consome
    $listaIds = implode(',', $ids);
    // Para segurança absoluta contra injeção SQL caso $ids venha a ter strings, 
    // embora fetchAll(PDO::FETCH_COLUMN) de ids (inteiros do banco) seja seguro, 
    // mapeamos os IDs para garantir que são apenas números inteiros.
    $ids_limpos = array_map('intval', $ids);
    $listaIds = implode(',', $ids_limpos);
    $pdo->exec("UPDATE tb_alteracoes SET consumido = 1 WHERE id IN ($listaIds)");
    
    // Gera Dispensa
    $stmtIns = $pdo->prepare("INSERT INTO tb_alteracoes (militar_id, categoria, tipo_detalhe, data_fato, descricao, qtd_dias, registrado_por) VALUES (?, 'SAUDE', 'Dispensa Recompensa', CURDATE(), 'Recompensa automática (5 FO+ atingidos).', 1, ?)");
    $stmtIns->execute([$id, $autor]);

    $pdo->commit();
    echo json_encode(['status' => 'sucesso']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    send_error("Erro ao processar a concessão de recompensa.", $e->getMessage());
}
?>