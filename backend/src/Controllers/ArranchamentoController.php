<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Services\ArranchamentoService;
use Sismil\Repositories\ArranchamentoRepository;

class ArranchamentoController {
    
    public function getByData(Request $request) {
        require_login(['admin', 'enc_mat']);
        $data = $request->getQuery('data', date('Y-m-d'));
        
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
            $total_jantar = 0;
            foreach ($registros as $r) {
                $qtd = isset($r['quantidade']) ? (int)$r['quantidade'] : 1;
                if ($qtd < 1) $qtd = 1;
                if ($r['cafe']) $total_cafe += $qtd;
                if ($r['almoco']) $total_almoco += $qtd;
                if ($r['jantar']) $total_jantar += $qtd;
            }
            
            Response::json([
                'status' => 'sucesso',
                'data' => $data,
                'totais' => [
                    'cafe' => $total_cafe,
                    'almoco' => $total_almoco,
                    'jantar' => $total_jantar
                ],
                'registros' => $registros
            ]);
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao buscar arranchamento: " . $e->getMessage());
            Response::error('Erro ao buscar dados.', 500);
        }
    }
    
    public function save(Request $request) {
        // O arranchamento pode ser público. 
        // O rate limit já deve ser validado na Rota ou no Core, mas podemos deixar no Controller.
        $rate_id = 'arranchamento_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!check_rate_limit($rate_id, 20, 3600)) {
            Response::error('Limite de solicitações excedido. Tente novamente em 1 hora.', 429);
        }

        try {
            $input = $request->getBody(); // JSON body
            $service = new ArranchamentoService();
            $service->salvarArranchamento($input);
            Response::json(null, "Arranchamento registrado com sucesso.");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao salvar arranchamento: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
    
    public function saveExtra(Request $request) {
        require_login(['admin', 'enc_mat']);
        
        try {
            $input = $request->getBody();
            $repo = new ArranchamentoRepository();
            
            $role = $_SESSION['usuario_role'] ?? '';
            $user_sub = $_SESSION['usuario_sub'] ?? '';
            
            if (!empty($input['is_batch']) && is_array($input['items'])) {
                foreach ($input['items'] as $item) {
                    if ($role === 'enc_mat' && !empty($user_sub)) {
                        $item['subunidade'] = $user_sub;
                    }
                    if (!empty($item['quantidade']) && (int)$item['quantidade'] > 0) {
                        $repo->salvarExtra($item);
                    }
                }
            } else {
                if (empty($input['data']) || empty($input['nome_guerra']) || empty($input['posto_grad'])) {
                    Response::error('Dados incompletos.', 400);
                }
                if ($role === 'enc_mat' && !empty($user_sub)) {
                    $input['subunidade'] = $user_sub;
                }
                $repo->salvarExtra($input);
            }
            
            Response::json(null, "Arranchamento atualizado/salvo com sucesso.");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao salvar arranchamento extra: " . $e->getMessage());
            Response::error('Erro interno do servidor.', 500);
        }
    }
}
