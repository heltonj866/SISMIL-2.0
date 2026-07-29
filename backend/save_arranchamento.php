<?php
/**
 * ARQUIVO: backend/save_arranchamento.php
 * Endpoint público/autenticado para registro de refeições (Arranchamento).
 * Implementa limite de requisições por IP (Rate Limiting).
 *
 * @package Sismil\Controllers
 */

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Services/AuditLogger.php';
require_once __DIR__ . '/src/Repositories/ArranchamentoRepository.php';
require_once __DIR__ . '/src/Services/ArranchamentoService.php';
require_once __DIR__ . '/security.php';

use Sismil\Core\Response;
use Sismil\Services\AuditLogger;
use Sismil\Services\ArranchamentoService;

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
    $input = json_decode(file_get_contents('php://input'), true);

    $service = new ArranchamentoService();
    $service->salvarArranchamento($input);
    
    Response::json(null, 'Arranchamento registrado com sucesso.');
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao salvar arranchamento: ' . $e->getMessage());
    Response::error($e->getMessage(), 500);
}
