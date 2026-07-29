<?php
/**
 * ARQUIVO: backend/save_arranchamento.php
 * Endpoint público/autenticado para registro de refeições (Arranchamento).
 * Implementa limite de requisições por IP (Rate Limiting).
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

// O endpoint pode ser consumido publicamente sem sessão ativa, 
// então não exigimos require_login(), mas iniciamos a sessão para o AuditLogger
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
apply_cors();

$rate_id = 'arranchamento_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rate_id, 20, 3600)) {
    AuditLogger::log('RATE_LIMIT_BLOCK', "Abuso no arranchamento detectado e bloqueado.");
    Response::error('Limite de solicitações excedido. Tente novamente em 1 hora.', 429);
}

try {
    $pdo = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);

    $subunidade  = trim($input['subunidade'] ?? '');
    $posto_grad  = trim($input['posto_grad'] ?? '');
    $numero      = trim($input['numero'] ?? '');
    $nome_guerra = trim($input['nome_guerra'] ?? '');
    $refeicoes   = $input['refeicoes'] ?? [];

    if (empty($subunidade) || empty($posto_grad) || empty($nome_guerra) || empty($refeicoes)) {
        Response::error('Dados incompletos. Preencha todos os campos e selecione pelo menos uma refeição.', 400);
    }

    $pdo->beginTransaction();
    
    $stmtCheck = $pdo->prepare("SELECT id FROM tb_arranchamento WHERE data_refeicao = ? AND subunidade = ? AND posto_grad = ? AND nome_guerra = ?");
    $stmtUpdate = $pdo->prepare("UPDATE tb_arranchamento SET cafe = ?, almoco = ?, numero = ? WHERE id = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO tb_arranchamento (data_refeicao, subunidade, posto_grad, numero, nome_guerra, cafe, almoco) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $datasArranchadas = [];

    foreach ($refeicoes as $ref) {
        $data   = $ref['data'];
        $cafe   = $ref['cafe'];
        $almoco = $ref['almoco'];
        
        $datasArranchadas[] = $data;

        $stmtCheck->execute([$data, $subunidade, $posto_grad, $nome_guerra]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $stmtUpdate->execute([$cafe, $almoco, $numero, $row['id']]);
        } else {
            $stmtInsert->execute([$data, $subunidade, $posto_grad, $numero, $nome_guerra, $cafe, $almoco]);
        }
    }
    
    $pdo->commit();
    
    $diasList = implode(', ', $datasArranchadas);
    AuditLogger::log('ARRANCHAMENTO_REALIZADO', "Arranchamento submetido por {$posto_grad} {$nome_guerra} para os dias: {$diasList}.");
    
    Response::json(null, 'Arranchamento registrado com sucesso.');
} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[SISMIL] Erro ao salvar arranchamento: ' . $e->getMessage());
    Response::error('Erro ao salvar o arranchamento. Tente novamente.', 500);
}
