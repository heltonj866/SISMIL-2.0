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
            \Sismil\Services\AuditLogger::log('VEICULO_SALVAR', 'Veículo placa ' . mb_substr(preg_replace('/[\x00-\x1F]/', '', (string)($_POST['placa'] ?? '')), 0, 10) . ' salvo (militar ID ' . (int)($_POST['militar_id'] ?? 0) . ').');
            Response::json(null, "Veículo salvo com sucesso.");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro no save_veiculo: " . $e->getMessage());
            $raw = $e->getMessage();
            if (strpos($raw, '1062') !== false || strpos($raw, 'Duplicate entry') !== false) {
                Response::error('Já existe um veículo cadastrado com esta placa.', 409);
            }
            Response::error('Erro ao salvar veículo: ' . $e->getMessage(), 500);
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
            
            \Sismil\Services\AuditLogger::log('VEICULO_HOMOLOGACAO', 'Veículo ID ' . $id . ' marcado como ' . ($homologado ? 'HOMOLOGADO' : 'NÃO homologado') . '.');
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
            
            \Sismil\Services\AuditLogger::log('VEICULO_EXCLUSAO', 'Veículo ID ' . $id . ' excluído.');
            Response::json(null, "Veículo excluído com sucesso.");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao excluir veículo: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
}
