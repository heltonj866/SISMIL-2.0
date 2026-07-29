<?php
/**
 * ARQUIVO: backend/get_arranchamento.php
 * Controller de leitura dos arranchamentos por data.
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/src/Core/Response.php';

use Sismil\Core\Response;
use Sismil\Repositories\ArranchamentoRepository;

session_start();
apply_cors();
require_login(['admin', 'enc_mat']);

$data = $_GET['data'] ?? date('Y-m-d');
$role = $_SESSION['usuario_role'];
$sub = $_SESSION['usuario_sub'] ?? '';

try {
    $repo = new ArranchamentoRepository();
    
    if ($role === 'enc_mat' && !empty($sub)) {
        $registros = $repo->getByDataAndSubunidade($data, $sub);
    } else {
        $registros = $repo->getByDataAndSubunidade($data);
    }
    
    $total_cafe = 0;
    $total_almoco = 0;
    
    foreach ($registros as $r) {
        if ($r['cafe']) $total_cafe++;
        if ($r['almoco']) $total_almoco++;
    }
    
    Response::json([
        'data' => $data,
        'totais' => [
            'cafe' => $total_cafe,
            'almoco' => $total_almoco
        ],
        'registros' => $registros
    ]);
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao carregar arranchamento: ' . $e->getMessage());
    Response::error('Erro ao carregar dados de arranchamento.', 500);
}
