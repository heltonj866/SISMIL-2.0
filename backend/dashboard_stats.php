<?php
/**
 * ARQUIVO: backend/dashboard_stats.php
 * Controller de métricas consolidadas para o Dashboard.
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/src/Core/Response.php';
require_once __DIR__ . '/src/Core/Database.php'; // Adicionado temporariamente para as queries ad-hoc
require_once __DIR__ . '/src/Repositories/MilitarRepository.php';
require_once __DIR__ . '/src/Repositories/VeiculoRepository.php';

use Sismil\Core\Response;
use Sismil\Core\Database;
use Sismil\Repositories\MilitarRepository;
use Sismil\Repositories\VeiculoRepository;

session_start();
apply_cors();
require_login(); 

try {
    $pdo = Database::getInstance();
    $repoVeiculos = new VeiculoRepository();

    // Contagens Básicas
    $militares = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 1")->fetchColumn();
    $inativos = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 0")->fetchColumn();
    $veiculos = $repoVeiculos->getEstatistica('total');
    $pendentes = $repoVeiculos->getEstatistica('pendentes');
    $homologados = $repoVeiculos->getEstatistica('homologados');
    $com_cnh = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo=1 AND cat_cnh IS NOT NULL AND cat_cnh != ''")->fetchColumn();

    // Efetivo por Subunidade e Posto
    $sqlEf = "SELECT subunidade, posto_grad, COUNT(*) as qtd
              FROM tb_militares WHERE status_ativo = 1
              GROUP BY subunidade, posto_grad
              ORDER BY subunidade ASC,
                CASE posto_grad
                    WHEN 'Cel' THEN 4 WHEN 'Ten Cel' THEN 5 WHEN 'Maj' THEN 6
                    WHEN 'Cap' THEN 7 WHEN '1º Ten' THEN 8 WHEN '2º Ten' THEN 9
                    WHEN 'Asp' THEN 10 WHEN 'Subten' THEN 11 WHEN 'Sub Ten' THEN 11
                    WHEN '1º Sgt' THEN 12 WHEN '2º Sgt' THEN 13 WHEN '3º Sgt' THEN 14
                    WHEN 'Cb' THEN 15 WHEN 'Sd EP' THEN 16 WHEN 'Sd EV' THEN 17
                    WHEN 'Sd' THEN 18 WHEN 'SC' THEN 99 ELSE 100 END ASC";
    
    $efetivo_raw = $pdo->query($sqlEf)->fetchAll();
    
    $efetivo_su = [];
    foreach ($efetivo_raw as $row) {
        $su = $row['subunidade'] ?: 'Sem SU';
        if (!isset($efetivo_su[$su])) {
            $efetivo_su[$su] = ['total' => 0, 'detalhes' => []];
        }
        $efetivo_su[$su]['total'] += $row['qtd'];
        $efetivo_su[$su]['detalhes'][] = ['posto' => $row['posto_grad'], 'qtd' => $row['qtd']];
    }

    $veiculos_pendentes = $repoVeiculos->getPendentes();

    $sqlCnh = "SELECT cat_cnh as cat, COUNT(*) as qtd
               FROM tb_militares
               WHERE status_ativo=1 AND cat_cnh IS NOT NULL AND cat_cnh != ''
               GROUP BY cat_cnh ORDER BY qtd DESC";
    $cnh_cats = $pdo->query($sqlCnh)->fetchAll();

    Response::json([
        'militares'         => $militares,
        'inativos'          => $inativos,
        'veiculos'          => $veiculos,
        'pendentes'         => $pendentes,
        'homologados'       => $homologados,
        'com_cnh'           => $com_cnh,
        'efetivo_su'        => $efetivo_su,
        'veiculos_pendentes'=> $veiculos_pendentes,
        'cnh_cats'          => $cnh_cats,
    ]);

} catch (\Exception $e) {
    error_log('[SISMIL] Erro ao carregar stats do dashboard: ' . $e->getMessage());
    Response::error('Erro ao carregar estatísticas do dashboard.', 500);
}