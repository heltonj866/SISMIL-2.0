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
            throw new \InvalidArgumentException('Dados incompletos. Preencha todos os campos e selecione pelo menos uma refeição.');
        }

        // Valida e normaliza cada dia informado (data real YYYY-MM-DD, refeições 0/1)
        if (!is_array($refeicoes)) {
            throw new \InvalidArgumentException('Formato de refeições inválido.');
        }
        $refeicoesValidas = [];
        foreach ($refeicoes as $ref) {
            $dataRef = is_array($ref) ? trim((string)($ref['data'] ?? '')) : '';
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dataRef, $mt) || !checkdate((int)$mt[2], (int)$mt[3], (int)$mt[1])) {
                throw new \InvalidArgumentException('Data de refeição inválida.');
            }
            $refeicoesValidas[] = [
                'data'   => $dataRef,
                'cafe'   => !empty($ref['cafe']) ? 1 : 0,
                'almoco' => !empty($ref['almoco']) ? 1 : 0,
            ];
        }

        $datasArranchadas = $this->repo->salvarLote($subunidade, $posto_grad, $numero, $nome_guerra, $refeicoesValidas);

        $diasList = implode(', ', $datasArranchadas);
        AuditLogger::log('ARRANCHAMENTO_REALIZADO', "Arranchamento submetido por {$posto_grad} {$nome_guerra} para os dias: {$diasList}.");
    }
}
