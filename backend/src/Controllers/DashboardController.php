<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use PDO;

class DashboardController {
    public function stats(Request $request) {
        require_login();

        try {
            $pdo = Database::getInstance();

            // --- Contagens básicas ---
            $militares = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 1")->fetchColumn();
            $inativos  = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 0")->fetchColumn();

            // --- Distribuição por Subunidade ---
            $suStmt     = $pdo->query("SELECT subunidade, COUNT(*) as total FROM tb_militares WHERE status_ativo = 1 GROUP BY subunidade ORDER BY total DESC");
            $efetivo_su = [];
            foreach ($suStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $efetivo_su[$row['subunidade'] ?: 'Sem SU'] = ['total' => (int)$row['total']];
            }

            // --- CNH por Categoria (coluna real: cat_cnh) ---
            $cnhStmt  = $pdo->query("SELECT cat_cnh as cat, COUNT(*) as qtd FROM tb_militares WHERE status_ativo = 1 AND cat_cnh IS NOT NULL AND cat_cnh != '' GROUP BY cat_cnh ORDER BY qtd DESC");
            $cnh_cats = $cnhStmt->fetchAll(PDO::FETCH_ASSOC);
            $com_cnh  = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 1 AND cat_cnh IS NOT NULL AND cat_cnh != ''")->fetchColumn();

            // --- Veículos (inline, sem depender de método que pode não existir) ---
            $veiculos    = 0;
            $pendentes   = 0;
            $homologados = 0;
            $veiculos_pendentes = [];

            $role = $_SESSION['usuario_role'] ?? '';
            if (in_array($role, ['admin', 's2', 'sargenteacao'])) {
                $veiculos    = (int)$pdo->query("SELECT COUNT(*) FROM tb_veiculos")->fetchColumn();
                $pendentes   = (int)$pdo->query("SELECT COUNT(*) FROM tb_veiculos WHERE homologado IS NULL OR homologado = 0")->fetchColumn();
                $homologados = (int)$pdo->query("SELECT COUNT(*) FROM tb_veiculos WHERE homologado = 1")->fetchColumn();

                $pendStmt = $pdo->query(
                    "SELECT v.id, v.placa, v.modelo, m.nome_guerra as militar_nome 
                     FROM tb_veiculos v 
                     JOIN tb_militares m ON v.militar_id = m.id 
                     WHERE v.homologado IS NULL OR v.homologado = 0 
                     LIMIT 10"
                );
                $veiculos_pendentes = $pendStmt ? $pendStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }

            Response::json([
                'militares'          => $militares,
                'inativos'           => $inativos,
                'com_cnh'            => $com_cnh,
                'efetivo_su'         => $efetivo_su,
                'cnh_cats'           => $cnh_cats,
                'veiculos'           => $veiculos,
                'homologados'        => $homologados,
                'pendentes'          => $pendentes,
                'veiculos_pendentes' => $veiculos_pendentes,
            ]);

        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao carregar estatísticas: " . $e->getMessage());
            Response::error('Erro ao carregar dashboard', 500);
        }
    }
}
