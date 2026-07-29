<?php
namespace Sismil\Services;

use Sismil\Repositories\ArranchamentoRepository;
use Exception;

class ArranchamentoService {
    private ArranchamentoRepository $repo;

    public function __construct() {
        $this->repo = new ArranchamentoRepository();
    }

    public function salvarArranchamento(array $dados): void {
        $subunidade  = trim($dados['subunidade'] ?? '');
        $posto_grad  = trim($dados['posto_grad'] ?? '');
        $numero      = trim($dados['numero'] ?? '');
        $nome_guerra = trim($dados['nome_guerra'] ?? '');
        $refeicoes   = $dados['refeicoes'] ?? [];

        if (empty($subunidade) || empty($posto_grad) || empty($nome_guerra) || empty($refeicoes)) {
            throw new Exception('Dados incompletos. Preencha todos os campos e selecione pelo menos uma refeição.');
        }

        $datasArranchadas = $this->repo->salvarLote($subunidade, $posto_grad, $numero, $nome_guerra, $refeicoes);

        $diasList = implode(', ', $datasArranchadas);
        AuditLogger::log('ARRANCHAMENTO_REALIZADO', "Arranchamento submetido por {$posto_grad} {$nome_guerra} para os dias: {$diasList}.");
    }
}
