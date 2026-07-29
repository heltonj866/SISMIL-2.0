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

        $parametros = [
            ':militar_id' => $militar_id,
            ':tipo_veiculo' => $dados['tipo_veiculo'],
            ':marca' => strtoupper(trim($dados['marca'])),
            ':modelo' => strtoupper(trim($dados['modelo'])),
            ':cor' => strtoupper(trim($dados['cor'])),
            ':placa' => strtoupper(trim($dados['placa'])),
            ':renavam' => $dados['renavam'] ?: null,
            ':chassi' => strtoupper(trim($dados['chassi'] ?? '')),
            ':ano_fabricacao' => $dados['ano_fabricacao'] ?: null,
            ':proprietario' => strtoupper(trim($dados['proprietario'] ?? '')),
            ':cpf_proprietario' => $dados['cpf_proprietario'] ?: null,
            ':cnh_proprietario' => $dados['cnh_proprietario'] ?: null,
            ':emissao_crlv' => $dados['emissao_crlv'] ?: null,
            ':validade_crlv' => $dados['validade_crlv'] ?: null,
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

        if ($id > 0) {
            $parametros[':id'] = $id;
            $this->repo->update($parametros);
            
            $this->alteracaoRepo->registrar(
                $militar_id, 
                $_SESSION['usuario_id'], 
                date('Y-m-d'), 
                'S2', 
                'ATUALIZACAO_VEICULO', 
                "Atualização de veículo placa " . $parametros[':placa']
            );
        } else {
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
