<?php
/**
 * ARQUIVO: backend/conceder_recompensa.php
 * Endpoint para converter Fatos Observados Positivos (FO+) em Recompensa Automática.
 *
 * @package Sismil\Controllers
 */

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Core/Database.php';
require_once __DIR__ . '/src/Services/AuditLogger.php';
require_once __DIR__ . '/security.php';

use Sismil\Core\Database;
use Sismil\Core\Response;
use Sismil\Services\AuditLogger;

session_start();
apply_cors();
require_login(['admin', 'sargenteacao']);
validate_csrf();

try {
    $pdo = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = filter_var($input['militar_id'] ?? null, FILTER_VALIDATE_INT);
    $autor = $_SESSION['usuario_login'] ?? 'Sistema';

    if (!$id) {
        Response::error('ID do militar inválido.', 400);
    }

    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT id FROM tb_alteracoes WHERE militar_id = ? AND categoria = 'ELOGIO' AND tipo_detalhe = 'FO+' AND consumido = 0 LIMIT 5");
    $stmt->execute([$id]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($ids) < 5) {
        throw new \Exception("Saldo insuficiente. O militar necessita de pelo menos 5 FO+ não consumidos para a conversão.");
    }

    $ids_limpos = array_map('intval', $ids);
    $listaIds = implode(',', $ids_limpos);
    
    $pdo->exec("UPDATE tb_alteracoes SET consumido = 1 WHERE id IN ($listaIds)");
    
    $stmtIns = $pdo->prepare("INSERT INTO tb_alteracoes (militar_id, categoria, tipo_detalhe, data_fato, descricao, qtd_dias, registrado_por) VALUES (?, 'SAUDE', 'Dispensa Recompensa', CURDATE(), 'Recompensa automática (Conversão de 5 FO+).', 1, ?)");
    $stmtIns->execute([$id, $autor]);
    
    $recompensaId = $pdo->lastInsertId();

    $pdo->commit();
    
    AuditLogger::log('RECOMPENSA_CONCEDIDA', "Conversão de 5 FO+ (IDs: {$listaIds}) em Dispensa Recompensa (ID {$recompensaId}) para o Militar ID {$id}.");
    Response::json(null, 'Recompensa concedida com sucesso.');
    
} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[SISMIL] Erro ao conceder recompensa: ' . $e->getMessage());
    Response::error($e->getMessage(), 400);
}