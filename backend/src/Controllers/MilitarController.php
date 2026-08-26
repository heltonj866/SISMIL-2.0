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
        $isPublic = $request->getQuery('publico') == '1';
        if (!$isPublic) {
            require_login();
        }
        
        $filtros = $request->getQuery();
        if (empty($filtros)) {
            $filtros = ['tipo_busca' => 'geral'];
        }
        
        try {
            $repo = new MilitarRepository();
            
            if ($isPublic && !empty($filtros['termo'])) {
                $data = $repo->searchPublic($filtros['termo']);
            } else {
                $data = $repo->searchComplex($filtros);
            }
            
            Response::json(['dados' => $data], 'Busca realizada com sucesso.');
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
            $id = $service->salvarMilitar($_POST);
            
            Response::json(['id' => $id], "Militar salvo com sucesso");
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao salvar militar: " . $e->getMessage());
            $raw = $e->getMessage();
            if (strpos($raw, '1062') !== false || strpos($raw, 'Duplicate entry') !== false) {
                if (stripos($raw, 'cpf') !== false) {
                    $msg = 'Já existe um militar cadastrado com este CPF.';
                } elseif (stripos($raw, 'idt_militar') !== false) {
                    $msg = 'Já existe um militar cadastrado com esta Identidade Militar.';
                } else {
                    $msg = 'Já existe um cadastro com estes dados únicos no sistema.';
                }
                Response::error($msg, 409);
            }
            Response::error($e->getMessage(), 400);
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
            Response::json(['dados' => $data]);
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
            Response::error('ID inválido.', 400);
            return;
        }
        
        try {
            $pdo = \Sismil\Core\Database::getInstance();
            $stmt = $pdo->prepare("UPDATE tb_militares SET status_ativo = 1, data_desligamento = NULL WHERE id = ?");
            if ($stmt->execute([$id])) {
                \Sismil\Services\AuditLogger::log('REATIVAR_MILITAR', "Militar ID {$id} reativado e reintegrado ao Efetivo Pronto.");
                Response::json(null, 'Militar reativado e integrado ao Efetivo Pronto.');
            } else {
                throw new \Exception("Erro ao atualizar o status de reativação.");
            }
        } catch (\Exception $e) {
            error_log('[SISMIL] Erro ao reativar militar: ' . $e->getMessage());
            Response::error('Erro ao reativar militar.', 500);
        }
    }
    public function saveAlteracao(Request $request) {
        require_login(['admin', 'sargenteacao']);
        validate_csrf();
        
        try {
            $arquivoPath = null;
            if (isset($_FILES['s1_file']) && $_FILES['s1_file']['error'] === UPLOAD_ERR_OK) {
                $uploadService = new \Sismil\Services\UploadService();
                $arquivoPath = $uploadService->validarEProcessarUpload(
                    's1_file',
                    ['pdf', 'jpg', 'jpeg', 'png'],
                    ['application/pdf', 'image/jpeg', 'image/png'],
                    __DIR__ . '/../../../uploads/s1/',
                    's1_'
                );
                // Salvar apenas "s1/nome_do_arquivo.ext"
                if ($arquivoPath) {
                    $arquivoPath = 's1/' . $arquivoPath;
                }
            }

            $repo = new AlteracaoRepository();
            $repo->save($_POST, $arquivoPath);
            \Sismil\Services\AuditLogger::log('SAVE_ALTERACAO', 'Alteração de histórico salva. Tipo: ' . ($_POST['s1_tipo'] ?? ''));
            Response::json(null, 'Alteração salva com sucesso.');
        } catch (\Exception $e) {
            error_log('[SISMIL] Erro ao salvar alteração: ' . $e->getMessage());
            Response::error('Erro ao salvar alteração: ' . $e->getMessage(), 500);
        }
    }

    public function deleteAlteracao(Request $request) {
        require_login(['admin', 'sargenteacao']);
        validate_csrf();
        
        try {
            $id = (int)$request->getBody('id');
            if ($id <= 0) Response::error('ID inválido', 400);
            
            $repo = new AlteracaoRepository();
            $repo->delete($id);
            \Sismil\Services\AuditLogger::log('DELETE_ALTERACAO', "Alteração ID {$id} excluída.");
            Response::json(null, 'Alteração excluída com sucesso.');
        } catch (\Exception $e) {
            error_log('[SISMIL] Erro ao excluir alteração: ' . $e->getMessage());
            Response::error('Erro ao excluir alteração.', 500);
        }
    }

    public function getCep(Request $request) {
        $cep = preg_replace('/\D/', '', $request->getQuery('cep', ''));
        if (strlen($cep) !== 8) {
            Response::error('CEP deve conter 8 dígitos.', 400);
        }
        
        $jsonStr = null;

        if (function_exists('curl_init')) {
            $ch = curl_init("https://viacep.com.br/ws/{$cep}/json/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SISMIL/2.0');
            $jsonStr = curl_exec($ch);
            curl_close($ch);
        }
        
        if (!$jsonStr && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => "User-Agent: SISMIL/2.0\r\n"]]);
            $jsonStr = @file_get_contents("https://viacep.com.br/ws/{$cep}/json/", false, $ctx);
        }
        
        if ($jsonStr) {
            $data = json_decode($jsonStr, true);
            if (is_array($data) && !isset($data['erro'])) {
                Response::json(['dados' => $data]);
                return;
            }
        }
        Response::error('CEP não encontrado ou servidor da OM sem acesso à internet.', 404);
    }
}
