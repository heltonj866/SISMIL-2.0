<?php
/**
 * ARQUIVO: backend/get_historico.php
 * Controller de leitura do histórico disciplinar (S1).
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/src/Core/Response.php';

use Sismil\Core\Response;
use Sismil\Repositories\AlteracaoRepository;

session_start();
apply_cors();
require_login(['admin', 'sargenteacao']);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    Response::error('ID inválido.', 400);
}

try {
    $repo = new AlteracaoRepository();
    $dados = $repo->getByMilitarId($id);
    
    Response::json(['dados' => $dados]);
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao buscar historico: ' . $e->getMessage());
    Response::error('Erro ao buscar histórico de alterações.', 500);
}