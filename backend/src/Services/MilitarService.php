<?php
namespace Sismil\Services;

use Sismil\Repositories\MilitarRepository;
use Sismil\Repositories\AlteracaoRepository;
use Exception;

class MilitarService {
    private MilitarRepository $repo;
    private AlteracaoRepository $alteracaoRepo;
    private UploadService $uploadService;

    public function __construct() {
        $this->repo = new MilitarRepository();
        $this->alteracaoRepo = new AlteracaoRepository();
        $this->uploadService = new UploadService();
    }

    public function salvarMilitar(array $dados): int {
        $id = !empty($dados['id']) ? (int)$dados['id'] : 0;
        
        $parametros = [
            ':cpf' => !empty($dados['cpf']) ? $dados['cpf'] : null,
            ':posto_grad' => $dados['posto_grad'] ?? null,
            ':numero' => !empty($dados['numero']) ? $dados['numero'] : null,
            ':nome_guerra' => strtoupper(trim($dados['nome_guerra'] ?? '')),
            ':subunidade' => !empty($dados['subunidade']) ? $dados['subunidade'] : null,
            ':pelotao' => !empty($dados['pelotao']) ? $dados['pelotao'] : null,
            ':secao' => !empty($dados['secao']) ? $dados['secao'] : null,
            ':nome_completo' => strtoupper(trim($dados['nome_completo'] ?? '')),
            ':nome_pai' => strtoupper(trim($dados['nome_pai'] ?? '')),
            ':nome_mae' => strtoupper(trim($dados['nome_mae'] ?? '')),
            ':qmg' => !empty($dados['qmg']) ? $dados['qmg'] : null,
            ':dt_nascimento' => !empty($dados['dt_nascimento']) ? $dados['dt_nascimento'] : null,
            ':tipo_sanguineo' => !empty($dados['tipo_sanguineo']) ? $dados['tipo_sanguineo'] : null,
            ':dt_praca' => !empty($dados['dt_praca']) ? $dados['dt_praca'] : null,
            ':idt_militar' => !empty($dados['idt_militar']) ? $dados['idt_militar'] : null,
            ':email' => strtolower(trim($dados['email'] ?? '')),
            ':celular_princ' => !empty($dados['celular_princ']) ? $dados['celular_princ'] : null,
            ':celular_sec' => !empty($dados['celular_sec']) ? $dados['celular_sec'] : null,
            ':nome_resp' => strtoupper(trim($dados['nome_resp'] ?? '')),
            ':tel_resp' => !empty($dados['tel_resp']) ? $dados['tel_resp'] : null,
            ':tel_emergencia' => !empty($dados['tel_emergencia']) ? $dados['tel_emergencia'] : null,
            ':cep' => !empty($dados['cep']) ? $dados['cep'] : null,
            ':endereco' => strtoupper(trim($dados['endereco'] ?? '')),
            ':num_residencia' => !empty($dados['num_residencia']) ? $dados['num_residencia'] : null,
            ':bairro' => strtoupper(trim($dados['bairro'] ?? '')),
            ':cidade' => strtoupper(trim($dados['cidade'] ?? '')),
            ':estado' => !empty($dados['estado']) ? $dados['estado'] : null,
            ':cat_cnh' => !empty($dados['cat_cnh']) ? $dados['cat_cnh'] : null,
            ':validade_cnh' => !empty($dados['validade_cnh']) ? $dados['validade_cnh'] : null,
        ];

        // Se for update, pegamos os dados atuais para não sobrescrever arquivos se não enviados
        $militarExistente = null;
        if ($id > 0) {
            $militarExistente = $this->repo->findById($id);
            if (!$militarExistente) {
                throw new Exception("Militar não encontrado para atualização.");
            }
        }

        $dir_uploads = __DIR__ . '/../../../uploads/';
        
        // Upload da Foto
        $foto = $this->uploadService->validarEProcessarUpload(
            'foto', 
            ['jpg','jpeg','png'], 
            ['image/jpeg', 'image/png'], 
            $dir_uploads, 
            'foto_'
        );
        $parametros[':foto'] = $foto ?? ($militarExistente['foto_path'] ?? null);

        // Upload da CNH
        $pdf_cnh = $this->uploadService->validarEProcessarUpload(
            'pdf_cnh', 
            ['pdf'], 
            ['application/pdf'], 
            $dir_uploads, 
            'cnh_'
        );
        $parametros[':pdf_cnh'] = $pdf_cnh ?? ($militarExistente['pdf_habilitacao'] ?? null);
        
        // A ficha de nada consta não entra na edição comum (salvarMilitar), mas garantimos que não apague
        $parametros[':pdf_nada_consta'] = $militarExistente['pdf_nada_consta'] ?? null;

        if ($id > 0) {
            $parametros[':id'] = $id;
            $this->repo->update($parametros);
            
            // Registra histórico S1
            $this->alteracaoRepo->registrar(
                $id, 
                $_SESSION['usuario_id'], 
                date('Y-m-d'), 
                'S1', 
                'ATUALIZACAO_CADASTRAL', 
                "Ficha do militar atualizada pelo S1."
            );
            return $id;
        } else {
            $novoId = $this->repo->insert($parametros);
            
            $this->alteracaoRepo->registrar(
                $novoId, 
                $_SESSION['usuario_id'], 
                date('Y-m-d'), 
                'S1', 
                'INCLUSAO_SISMIL', 
                "Militar cadastrado no sistema."
            );
            return $novoId;
        }
    }

    public function desligarMilitar(int $id, string $motivo, string $dataDesligamento): void {
        if ($id <= 0) throw new Exception("ID inválido");

        $militar = $this->repo->findById($id);
        if (!$militar) throw new Exception("Militar não encontrado");

        $dir_uploads = __DIR__ . '/../../../uploads/';
        
        $pdfNadaConsta = $this->uploadService->validarEProcessarUpload(
            'pdf_nada_consta', 
            ['pdf'], 
            ['application/pdf'], 
            $dir_uploads, 
            'nada_consta_'
        );

        $this->repo->updateStatus($id, 0, $pdfNadaConsta);

        $descricao = "Desligamento: {$motivo}";
        if ($pdfNadaConsta) {
            $descricao .= " (Ficha anexada)";
        }

        $this->alteracaoRepo->registrar(
            $id,
            $_SESSION['usuario_id'],
            $dataDesligamento,
            'S1',
            'DESLIGAMENTO',
            $descricao
        );
    }
}
