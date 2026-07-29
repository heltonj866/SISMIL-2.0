<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use Sismil\Repositories\VeiculoRepository;

class DashboardController {
    public function stats(Request $request) {
        require_login();
        
        try {
            $pdo = Database::getInstance();
            
            $militares = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 1")->fetchColumn();
            $inativos  = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 0")->fetchColumn();

            // Distribuição por subunidade
            $suStmt = $pdo->query("SELECT subunidade, COUNT(*) as total FROM tb_militares WHERE status_ativo = 1 GROUP BY subunidade ORDER BY total DESC");
            $efetivo_su_raw = $suStmt->fetchAll(PDO::FETCH_ASSOC);
            $efetivo_su = [];
            foreach ($efetivo_su_raw as $row) {
                $efetivo_su[$row['subunidade'] ?: 'Sem SU'] = ['total' => (int)$row['total']];
            }

            // CNH por categoria
            $cnhStmt = $pdo->query("SELECT categoria_cnh as cat, COUNT(*) as qtd FROM tb_militares WHERE status_ativo = 1 AND categoria_cnh IS NOT NULL AND categoria_cnh != '' GROUP BY categoria_cnh ORDER BY qtd DESC");
            $cnh_cats = $cnhStmt->fetchAll(PDO::FETCH_ASSOC);

            $com_cnh = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 1 AND categoria_cnh IS NOT NULL AND categoria_cnh != ''")->fetchColumn();

            $stats = [
                'militares'  => $militares,
                'inativos'   => $inativos,
                'com_cnh'    => $com_cnh,
                'efetivo_su' => $efetivo_su,
                'cnh_cats'   => $cnh_cats,
                'veiculos'   => 0,
                'homologados'=> 0,
                'pendentes'  => 0,
                'veiculos_pendentes' => []
            ];

            $role = $_SESSION['usuario_role'] ?? '';

            if (in_array($role, ['admin', 's2', 'sargenteacao'])) {
                $repoVeiculo = new VeiculoRepository();
                $stats['veiculos']   = $repoVeiculo->getEstatistica('total');
                $stats['pendentes']  = $repoVeiculo->getEstatistica('pendentes');
                $stats['homologados']= $repoVeiculo->getEstatistica('homologados');
                $pendentesStmt = $pdo->query("SELECT v.id, v.placa, v.modelo, m.nome_guerra as militar_nome FROM tb_veiculos v JOIN tb_militares m ON v.militar_id = m.id WHERE v.homologado IS NULL OR v.homologado = 0 LIMIT 10");
                $stats['veiculos_pendentes'] = $pendentesStmt ? $pendentesStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }

            Response::json($stats);

        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao carregar estatísticas: " . $e->getMessage());
            Response::error('Erro ao carregar dashboard', 500);
        }
    }
}
