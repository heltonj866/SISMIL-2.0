<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use PDO;

class AuditController {

    public function list(Request $request) {
        require_login(['admin']);

        $pagina     = max(1, (int)($request->getQuery('pagina') ?? 1));
        $limite     = min(100, max(10, (int)($request->getQuery('limite') ?? 50)));
        $offset     = ($pagina - 1) * $limite;
        $acao       = trim($request->getQuery('acao') ?? '');
        $usuario    = trim($request->getQuery('usuario') ?? '');
        $dataInicio = trim($request->getQuery('data_inicio') ?? '');
        $dataFim    = trim($request->getQuery('data_fim') ?? '');

        try {
            $pdo    = Database::getInstance();
            $where  = ['1=1'];
            $params = [];

            if ($acao !== '') {
                $where[]         = 'acao = :acao';
                $params[':acao'] = $acao;
            }
            if ($usuario !== '') {
                $where[]            = 'usuario_nome LIKE :usuario';
                $params[':usuario'] = "%{$usuario}%";
            }
            if ($dataInicio !== '') {
                $where[]                = 'DATE(created_at) >= :data_inicio';
                $params[':data_inicio'] = $dataInicio;
            }
            if ($dataFim !== '') {
                $where[]             = 'DATE(created_at) <= :data_fim';
                $params[':data_fim'] = $dataFim;
            }

            $whereSQL  = implode(' AND ', $where);
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM tb_logs_auditoria WHERE {$whereSQL}");
            $stmtTotal->execute($params);
            $total = (int)$stmtTotal->fetchColumn();

            $sql  = "SELECT id, usuario_id, usuario_nome, acao, detalhes, ip_address, user_agent, created_at
                     FROM tb_logs_auditoria WHERE {$whereSQL}
                     ORDER BY id DESC LIMIT :limite OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::json([
                'logs'      => $logs,
                'total'     => $total,
                'pagina'    => $pagina,
                'limite'    => $limite,
                'total_pag' => (int)ceil($total / $limite),
            ], 'Logs de auditoria carregados.');

        } catch (\Exception $e) {
            error_log('[SISMIL] Erro ao buscar auditoria: ' . $e->getMessage());
            Response::error('Erro ao carregar logs de auditoria.', 500);
        }
    }

    public function acoes(Request $request) {
        require_login(['admin']);
        try {
            $pdo   = Database::getInstance();
            $stmt  = $pdo->query("SELECT DISTINCT acao FROM tb_logs_auditoria ORDER BY acao ASC");
            $acoes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            Response::json(['acoes' => $acoes]);
        } catch (\Exception $e) {
            Response::error('Erro ao listar tipos de acao.', 500);
        }
    }
}