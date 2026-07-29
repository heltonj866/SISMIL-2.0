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

            // --- Efetivo por Subunidade e Posto (Detalhado) ---
            $sqlEf = "SELECT subunidade, posto_grad, COUNT(*) as qtd
                      FROM tb_militares WHERE status_ativo = 1
                      GROUP BY subunidade, posto_grad
                      ORDER BY subunidade ASC,
                        CASE posto_grad
                            WHEN 'Gen Ex' THEN 1 WHEN 'Gen Div' THEN 2 WHEN 'Gen Bda' THEN 3 WHEN 'Cel' THEN 4
                            WHEN 'TC' THEN 5 WHEN 'Ten Cel' THEN 5 WHEN 'Maj' THEN 6 WHEN 'Cap' THEN 7
                            WHEN '1º Ten' THEN 8 WHEN '2º Ten' THEN 9 WHEN 'Asp' THEN 10
                            WHEN 'Subten' THEN 11 WHEN 'Sub Ten' THEN 11 WHEN 'S Ten' THEN 11
                            WHEN '1º Sgt' THEN 12 WHEN '2º Sgt' THEN 13 WHEN '3º Sgt' THEN 14
                            WHEN 'Alu' THEN 15 WHEN 'Cb' THEN 16 WHEN 'Sd EP' THEN 17 WHEN 'Sd EV' THEN 18
                            WHEN 'Sd' THEN 18 WHEN 'SC' THEN 99 ELSE 100 END ASC";
            
            $efetivo_raw = $pdo->query($sqlEf)->fetchAll(PDO::FETCH_ASSOC);
            
            $efetivo_su = [];
            foreach ($efetivo_raw as $row) {
                $su = $row['subunidade'] ?: 'Sem SU';
                if (!isset($efetivo_su[$su])) {
                    $efetivo_su[$su] = ['total' => 0, 'detalhes' => []];
                }
                $efetivo_su[$su]['total'] += (int)$row['qtd'];
                $efetivo_su[$su]['detalhes'][] = [
                    'posto' => $row['posto_grad'] ?: 'Outro',
                    'qtd'   => (int)$row['qtd']
                ];
            }

            // --- CNH por Categoria (coluna real: cat_cnh) ---
            $cnhStmt  = $pdo->query("SELECT cat_cnh as cat, COUNT(*) as qtd FROM tb_militares WHERE status_ativo = 1 AND cat_cnh IS NOT NULL AND cat_cnh != '' GROUP BY cat_cnh ORDER BY qtd DESC");
            $cnh_cats = $cnhStmt->fetchAll(PDO::FETCH_ASSOC);
            $com_cnh  = (int)$pdo->query("SELECT COUNT(*) FROM tb_militares WHERE status_ativo = 1 AND cat_cnh IS NOT NULL AND cat_cnh != ''")->fetchColumn();

            // --- Veículos ---
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
