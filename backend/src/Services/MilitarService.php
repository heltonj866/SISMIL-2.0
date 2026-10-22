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
        SchemaService::ensureSchema();
        $this->repo = new MilitarRepository();
        $this->alteracaoRepo = new AlteracaoRepository();
        $this->uploadService = new UploadService();
    }

    public function salvarMilitar(array $dados): int {
        $id = !empty($dados['id']) ? (int)$dados['id'] : (!empty($dados['id_militar']) ? (int)$dados['id_militar'] : 0);
        
        $parametros = [
            ':cpf' => !empty($dados['cpf']) ? $dados['cpf'] : null,
            ':posto_grad' => $dados['posto_grad'] ?? null,
            ':numero' => !empty($dados['numero']) ? $dados['numero'] : null,
            ':nome_guerra' => strtoupper(trim($dados['nome_guerra'] ?? '')),
            ':subunidade' => !empty($dados['subunidade']) ? $dados['subunidade'] : null,
            ':pelotao' => !empty($dados['pelotao']) ? $dados['pelotao'] : null,
            ':secao' => !empty($dados['secao']) ? $dados['secao'] : null,
            ':grupamento' => !empty($dados['grupamento']) ? trim($dados['grupamento']) : null,
            ':funcao' => !empty($dados['funcao']) ? trim($dados['funcao']) : null,
            ':situacao_atual' => !empty($dados['situacao_atual']) ? trim($dados['situacao_atual']) : 'Em Forma',
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

        $militarExistente = null;
        if ($id > 0) {
            $militarExistente = $this->repo->findById($id);
            if (!$militarExistente) {
                throw new Exception("Militar não encontrado para atualização.");
            }
            if (empty($dados['situacao_atual']) && !empty($militarExistente['situacao_atual'])) {
                $parametros[':situacao_atual'] = $militarExistente['situacao_atual'];
            }
            if (!isset($dados['grupamento']) && isset($militarExistente['grupamento'])) {
                $parametros[':grupamento'] = $militarExistente['grupamento'];
            }
            if (!isset($dados['funcao']) && isset($militarExistente['funcao'])) {
                $parametros[':funcao'] = $militarExistente['funcao'];
            }
        }

        $pdo = \Sismil\Core\Database::getInstance();
        if (!empty($parametros[':cpf'])) {
            $stmtCpf = $pdo->prepare("SELECT id, nome_guerra, posto_grad FROM tb_militares WHERE cpf = ? AND id != ?");
            $stmtCpf->execute([$parametros[':cpf'], $id]);
            if ($outro = $stmtCpf->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("O CPF '{$parametros[':cpf']}' já pertence ao militar {$outro['posto_grad']} {$outro['nome_guerra']} (ID #{$outro['id']}).");
            }
        }
        if (!empty($parametros[':idt_militar'])) {
            $stmtIdt = $pdo->prepare("SELECT id, nome_guerra, posto_grad FROM tb_militares WHERE idt_militar = ? AND id != ?");
            $stmtIdt->execute([$parametros[':idt_militar'], $id]);
            if ($outro = $stmtIdt->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("A Identidade '{$parametros[':idt_militar']}' já pertence ao militar {$outro['posto_grad']} {$outro['nome_guerra']} (ID #{$outro['id']}).");
            }
        }

        $dir_uploads = __DIR__ . '/../../../uploads/';
        
        $foto = $this->uploadService->validarEProcessarUpload(
            'foto', 
            ['jpg','jpeg','png'], 
            ['image/jpeg', 'image/png'], 
            $dir_uploads, 
            'foto_'
        );
        $parametros[':foto'] = $foto ?? ($militarExistente['foto_path'] ?? null);

        $pdf_cnh = $this->uploadService->validarEProcessarUpload(
            'pdf_cnh', 
            ['pdf'], 
            ['application/pdf'], 
            $dir_uploads, 
            'cnh_'
        );
        $parametros[':pdf_cnh'] = $pdf_cnh ?? ($militarExistente['pdf_habilitacao'] ?? null);
        
        $pdf_nada_consta = $this->uploadService->validarEProcessarUpload(
            'pdf_nada_consta',
            ['pdf'],
            ['application/pdf'],
            $dir_uploads,
            'nada_consta_'
        );
        $parametros[':pdf_nada_consta'] = $pdf_nada_consta ?? ($militarExistente['pdf_nada_consta'] ?? null);

        if ($id > 0) {
            $parametros[':id'] = $id;
            $this->repo->update($parametros);
            
            $this->alteracaoRepo->registrar(
                $id, 
                $_SESSION['usuario_id'], 
                date('Y-m-d'), 
                'S1', 
                'ATUALIZACAO_CADASTRAL', 
                "Ficha do militar atualizada pelo S1."
            );
            $finalId = $id;
        } else {
            $finalId = $this->repo->insert($parametros);
            
            $this->alteracaoRepo->registrar(
                $finalId, 
                $_SESSION['usuario_id'], 
                date('Y-m-d'), 
                'S1', 
                'INCLUSAO_SISMIL', 
                "Militar cadastrado no sistema."
            );
        }

        // Provisiona/atualiza automaticamente o login do militar (role 'militar') quando possuir CPF
        if (!empty($parametros[':cpf'])) {
            try {
                $stmtU = $pdo->prepare("SELECT id FROM tb_usuarios WHERE militar_id = ? OR identidade = ? LIMIT 1");
                $stmtU->execute([$finalId, $parametros[':cpf']]);
                if (!$stmtU->fetchColumn()) {
                    $stmtInsU = $pdo->prepare("
                        INSERT IGNORE INTO tb_usuarios (identidade, senha_hash, role, ativo, nome, posto_grad, subunidade, militar_id)
                        VALUES (?, '', 'militar', 1, ?, ?, ?, ?)
                    ");
                    $stmtInsU->execute([
                        $parametros[':cpf'],
                        $parametros[':nome_guerra'],
                        $parametros[':posto_grad'],
                        $parametros[':subunidade'],
                        $finalId
                    ]);
                }
            } catch (\Exception $e) {}
        }

        return $finalId;
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

        // Desativa login do militar desligado caso seja apenas perfil 'militar'
        try {
            $pdo = \Sismil\Core\Database::getInstance();
            $pdo->prepare("UPDATE tb_usuarios SET ativo = 0 WHERE militar_id = ? AND role = 'militar'")->execute([$id]);
        } catch (\Exception $e) {}

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
