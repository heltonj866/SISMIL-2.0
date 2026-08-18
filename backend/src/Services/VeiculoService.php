<?php
namespace Sismil\Services;

use Sismil\Repositories\VeiculoRepository;
use Sismil\Repositories\AlteracaoRepository;
use Exception;

class VeiculoService {
    private VeiculoRepository $repo;
    private AlteracaoRepository $alteracaoRepo;
    private UploadService $uploadService;

    public function __construct() {
        $this->repo = new VeiculoRepository();
        $this->alteracaoRepo = new AlteracaoRepository();
        $this->uploadService = new UploadService();
    }

    public function salvarVeiculo(array $dados): void {
        $militar_id = !empty($dados['militar_id']) ? (int)$dados['militar_id'] : 0;
        $id = !empty($dados['id']) ? (int)$dados['id'] : 0;

        if ($militar_id <= 0) {
            throw new Exception("Militar não informado.");
        }

        $proprietarioNome = !empty($dados['proprietario_nome']) ? strtoupper(trim($dados['proprietario_nome'])) : null;
        $proprietarioParentesco = !empty($dados['proprietario_parentesco']) ? strtoupper(trim($dados['proprietario_parentesco'])) : null;

        $parametros = [
            ':militar_id'             => $militar_id,
            ':tipo_veiculo'           => $dados['tipo_veiculo'] ?? 'Carro',
            ':marca'                  => strtoupper(trim($dados['marca']        ?? '')),
            ':modelo'                 => strtoupper(trim($dados['modelo']       ?? '')),
            ':cor'                    => strtoupper(trim($dados['cor']          ?? '')),
            ':proprietario_nome'      => $proprietarioNome,
            ':proprietario_parentesco'=> $proprietarioParentesco,
            ':placa'                  => strtoupper(trim($dados['placa']        ?? '')),
            ':emissao_crlv'           => !empty($dados['emissao_crlv']) ? $dados['emissao_crlv'] : null,
        ];

        $veiculoExistente = null;
        if ($id > 0) {
            $veiculoExistente = $this->repo->findById($id);
            if (!$veiculoExistente) {
                throw new Exception("Veículo não encontrado para atualização.");
            }
        }

        $dir_uploads = __DIR__ . '/../../../uploads/';
        
        // Upload CRLV (PDF ou Imagem)
        $pdf_veiculo = $this->uploadService->validarEProcessarUpload(
            'pdf_veiculo',
            ['pdf', 'jpg', 'jpeg', 'png'],
            ['application/pdf', 'image/jpeg', 'image/png'],
            $dir_uploads,
            'crlv_'
        );
        $parametros[':pdf_veiculo'] = $pdf_veiculo ?? ($veiculoExistente['pdf_veiculo'] ?? null);

        // Upload Comprovante de Vínculo / Documento do Proprietário (Opcional)
        $pdf_vinculo = $this->uploadService->validarEProcessarUpload(
            'pdf_comprovante_vinculo',
            ['pdf', 'jpg', 'jpeg', 'png'],
            ['application/pdf', 'image/jpeg', 'image/png'],
            $dir_uploads,
            'vinculo_'
        );

        if ($id > 0) {
            $parametros[':id'] = $id;
            $parametros[':pdf_comprovante_vinculo'] = $pdf_vinculo ?? ($veiculoExistente['pdf_comprovante_vinculo'] ?? null);
            // Ao salvar alterações no veículo, reseta a observação antiga do S2 e volta status para PENDENTE (0)
            $parametros[':homologado'] = 0;
            $parametros[':observacao_s2'] = null;

            $this->repo->update($parametros);
            
            $this->alteracaoRepo->registrar(
                $militar_id, 
                $_SESSION['usuario_id'], 
                date('Y-m-d'), 
                'S2', 
                'ATUALIZACAO_VEICULO', 
                "Veículo placa " . $parametros[':placa'] . " atualizado pela Sargenteação (retornado para reavaliação do S2)."
            );
        } else {
            $parametros[':pdf_comprovante_vinculo'] = $pdf_vinculo;
            $this->repo->insert($parametros);
            
            $this->alteracaoRepo->registrar(
                $militar_id, 
                $_SESSION['usuario_id'], 
                date('Y-m-d'), 
                'S2', 
                'CADASTRO_VEICULO', 
                "Novo veículo cadastrado: placa " . $parametros[':placa'] . " (Aguardando homologação da S2)"
            );
        }
    }

    public function homologarVeiculo(int $id, int $homologado, string $obs): void {
        if ($id <= 0) throw new Exception("ID inválido");

        $veiculo = $this->repo->findById($id);
        if (!$veiculo) throw new Exception("Veículo não encontrado.");

        $this->repo->updateHomologacao($id, $homologado, $obs);

        $statusStr = $homologado == 1 ? "HOMOLOGADO" : "PENDENTE";
        $descricao = "S2 avaliou veículo {$veiculo['placa']} como {$statusStr}.";
        if (!empty($obs)) {
            $descricao .= " Obs: {$obs}";
        }

        $this->alteracaoRepo->registrar(
            $veiculo['militar_id'],
            $_SESSION['usuario_id'],
            date('Y-m-d'),
            'S2',
            'HOMOLOGACAO',
            $descricao
        );
    }

    public function excluirVeiculo(int $id): void {
        if ($id <= 0) throw new Exception("ID inválido");

        $veiculo = $this->repo->findById($id);
        if (!$veiculo) throw new Exception("Veículo não encontrado.");

        $this->repo->delete($id);

        $this->alteracaoRepo->registrar(
            $veiculo['militar_id'],
            $_SESSION['usuario_id'],
            date('Y-m-d'),
            'S2',
            'EXCLUSAO_VEICULO',
            "Veículo excluído (Placa: {$veiculo['placa']})"
        );
    }
}
