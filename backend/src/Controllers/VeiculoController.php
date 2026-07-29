<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Services\VeiculoService;
use Sismil\Repositories\VeiculoRepository;

class VeiculoController {
    
    public function getByMilitar(Request $request) {
        require_login();
        $id = (int)$request->getQuery('militar_id');
        if (!$id) Response::error("ID não informado", 400);
        
        try {
            $repo = new VeiculoRepository();
            $data = $repo->getByMilitarId($id);
            Response::json(['dados' => $data], 'Veículos carregados com sucesso.');
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao buscar veículos: " . $e->getMessage());
            Response::error('Erro ao buscar dados.', 500);
        }
    }

    
    public function save(Request $request) {
        require_login(['admin', 's2', 'sargenteacao']);
        validate_csrf();
        
        try {
            $service = new VeiculoService();
            $service->salvarVeiculo($_POST);
            Response::json(null, "Veículo salvo com sucesso.");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro no save_veiculo: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
    
    public function homologar(Request $request) {
        require_login(['admin', 's2']);
        validate_csrf();
        
        try {
            $id = (int)$request->getBody('id');
            $homologado = (int)$request->getBody('homologado');
            $obs = $request->getBody('observacao_s2', '');
            
            $service = new VeiculoService();
            $service->homologarVeiculo($id, $homologado, $obs);
            
            Response::json(null, "Status do veículo atualizado com sucesso.");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao homologar veículo: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
    
    public function delete(Request $request) {
        require_login(['admin', 's2', 'sargenteacao']);
        validate_csrf();
        
        try {
            $id = (int)$request->getBody('id');
            $service = new VeiculoService();
            $service->excluirVeiculo($id);
            
            Response::json(null, "Veículo excluído com sucesso.");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao excluir veículo: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
}
