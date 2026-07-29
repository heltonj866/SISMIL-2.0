<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Services\MilitarService;
use Sismil\Repositories\MilitarRepository;
use Sismil\Repositories\VeiculoRepository;
use Sismil\Repositories\AlteracaoRepository;
use Sismil\Core\Database;
use Sismil\Core\AuditLogger;

class MilitarController {
    
    public function search(Request $request) {
        require_login();
        
        $filtros = $request->getQuery();
        if (empty($filtros)) {
            $filtros = ['tipo_busca' => 'geral']; // Padrão
        }
        
        try {
            $repo = new MilitarRepository();
            $data = $repo->searchComplex($filtros);
            Response::json($data);
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao buscar militares: " . $e->getMessage());
            Response::error('Erro ao buscar dados.', 500);
        }
    }

    public function getById(Request $request) {
        require_login();
        $id = (int)$request->getQuery('id');
        
        if ($id <= 0) {
            Response::error('ID inválido ou não informado.', 400);
        }
        
        try {
            $repo = new MilitarRepository();
            $dados = $repo->findById($id);
            
            if ($dados) {
                foreach ($dados as $key => $value) {
                    if (is_null($value)) $dados[$key] = "";
                }
                
                if (isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'user') {
                    unset($dados['cpf'], $dados['nome_pai'], $dados['nome_mae'], $dados['celular_sec'], $dados['tel_emergencia']);
                }
                
                Response::json(['dados' => $dados]);
            } else {
                Response::error('Militar não encontrado no banco de dados.', 404);
            }
        } catch (\Exception $e) {
            error_log('[SISMIL] Erro no get_militar: ' . $e->getMessage());
            Response::error('Erro ao buscar dados do militar.', 500);
        }
    }
    
    public function save(Request $request) {
        require_login(['admin', 'sargenteacao']);
        validate_csrf();
        
        try {
            $service = new MilitarService();
            // A superglobal $_POST (e $_FILES) já foi manipulada em Request, 
            // mas o UploadService ainda espera $_FILES. 
            // Por hora passaremos $_POST para manter retrocompatibilidade com UploadService.
            $id = $service->salvarMilitar($_POST);
            
            Response::json(['id' => $id], "Militar salvo com sucesso");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao salvar militar: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
    
    public function desligar(Request $request) {
        require_login(['admin', 'sargenteacao']);
        validate_csrf();
        
        try {
            $id = (int)$request->getBody('id');
            $motivo = $request->getBody('motivo_desligamento', 'Não informado');
            $data_desligamento = $request->getBody('data_desligamento', date('Y-m-d'));
            
            $service = new MilitarService();
            $service->desligarMilitar($id, $motivo, $data_desligamento);
            
            Response::json(null, "Militar desligado com sucesso. Status alterado para inativo.");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao desligar militar: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    public function delete(Request $request) {
        require_login(['admin']);
        validate_csrf();
        
        try {
            $id = (int)$request->getBody('id');
            if ($id <= 0) Response::error('ID inválido', 400);

            $repo = new MilitarRepository();
            $repo->delete($id);

            \Sismil\Services\AuditLogger::log('MILITAR_EXCLUSAO', "Militar ID {$id} excluído permanentemente do banco.");
            Response::json(null, "Militar excluído com sucesso.");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao excluir militar: " . $e->getMessage());
            Response::error('Erro interno ao excluir militar.', 500);
        }
    }
    
    // Antigo get_historico.php
    public function getHistorico(Request $request) {
        require_login();
        $id = (int)$request->getQuery('id');
        if (!$id) Response::error("ID não informado", 400);
        
        try {
            $repo = new AlteracaoRepository();
            $data = $repo->getByMilitarId($id);
            Response::json($data);
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao buscar histórico: " . $e->getMessage());
            Response::error('Erro ao buscar histórico.', 500);
        }
    }

    public function reativar(Request $request) {
        require_login(['admin', 'sargenteacao']);
        validate_csrf();
        
        $dados = $request->getBody();
        $id = (int)($dados['id'] ?? 0);
        
        if ($id <= 0) {
            Response::json(['status' => 'erro', 'msg' => 'ID inválido.']);
            return;
        }
        
        try {
            $pdo = \Sismil\Core\Database::getInstance();
            $stmt = $pdo->prepare("UPDATE tb_militares SET status_ativo = 1, data_desligamento = NULL WHERE id = ?");
            if ($stmt->execute([$id])) {
                \Sismil\Services\AuditLogger::log('REATIVAR_MILITAR', "Militar ID {$id} reativado e reintegrado ao Efetivo Pronto.");
                Response::json(['status' => 'sucesso', 'msg' => 'Militar reativado e integrado ao Efetivo Pronto.']);
            } else {
                throw new \Exception("Erro ao atualizar o status de reativação.");
            }
        } catch (\Exception $e) {
            error_log('[SISMIL] Erro ao reativar militar: ' . $e->getMessage());
            Response::json(['status' => 'erro', 'msg' => 'Erro ao reativar militar.']);
        }
    }
}
