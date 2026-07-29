<?php
/**
 * ARQUIVO: backend/public_search.php
 * Controller para busca pública no terminal da guarda/S2.
 */

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Repositories/MilitarRepository.php';
require_once __DIR__ . '/security.php';

use Sismil\Core\Response;
use Sismil\Repositories\MilitarRepository;

apply_cors();

$termo = trim($_GET['termo'] ?? '');

if (empty($termo)) {
    Response::error('Termo de busca não informado.', 400);
}

try {
    $repo = new MilitarRepository();
    $militares = $repo->searchPublic($termo);

    if (count($militares) > 0) {
        Response::json(['dados' => $militares]);
    } else {
        Response::json(['dados' => []], 'Nenhum militar encontrado.');
    }
} catch (\Exception $e) {
    error_log('[SISMIL] Erro na busca publica: ' . $e->getMessage());
    Response::error('Erro ao processar busca.', 500);
}