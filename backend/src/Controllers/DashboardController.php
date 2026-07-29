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
            
            $stats = [
                'efetivo' => (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 1")->fetchColumn(),
                'inativos' => (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 0")->fetchColumn(),
                'arranchados_hoje' => (int)$pdo->query("SELECT COUNT(*) FROM tb_arranchamento WHERE data_refeicao = CURDATE()")->fetchColumn(),
                'veiculos' => 0,
                'veiculos_pendentes' => 0
            ];

            $role = $_SESSION['usuario_role'] ?? '';

            if (in_array($role, ['admin', 's2'])) {
                $repoVeiculo = new VeiculoRepository();
                $stats['veiculos'] = $repoVeiculo->getEstatistica('total');
                $stats['veiculos_pendentes'] = $repoVeiculo->getEstatistica('pendentes');
            }

            Response::json($stats);
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao carregar estatísticas: " . $e->getMessage());
            Response::error('Erro ao carregar dashboard', 500);
        }
    }
}
