<?php
/**
 * ARQUIVO: backend/search.php
 * Controller para pesquisa complexa de militares (Dashboard/Admin).
 */

require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Repositories/MilitarRepository.php';
require_once __DIR__ . '/security.php';

use Sismil\Core\Response;
use Sismil\Repositories\MilitarRepository;

session_start();
apply_cors();
require_login(); 

try {
    $repo = new MilitarRepository();
    
    // Preparação dos filtros baseados no $_GET
    $filtros = [
        'tipo_busca'      => $_GET['tipo_busca'] ?? 'geral',
        'termo'           => trim($_GET['termo'] ?? ''),
        'posto'           => $_GET['posto'] ?? '',
        'qmg'             => $_GET['qmg'] ?? '',
        'subunidade'      => $_GET['subunidade'] ?? '',
        'filtro_cnh'      => $_GET['filtro_cnh'] ?? 'TODAS',
        'mes_aniversario' => $_GET['mes_aniversario'] ?? '',
        'inativos'        => isset($_GET['inativos']) && $_GET['inativos'] == '1',
    ];

    // Filtro sem_foto é restrito
    if (isset($_GET['sem_foto']) && $_GET['sem_foto'] == '1') {
        $role_sessao = strtolower(trim($_SESSION['usuario_role'] ?? ''));
        if (in_array($role_sessao, ['admin', 'sargenteacao'])) {
            $filtros['sem_foto'] = true;
        }
    }

    $resultados = $repo->searchComplex($filtros);

    Response::json(['dados' => $resultados]);
} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao processar busca: ' . $e->getMessage());
    Response::error('Erro ao processar busca. Tente novamente.', 500);
}