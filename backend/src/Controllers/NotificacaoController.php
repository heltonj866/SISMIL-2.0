<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use Sismil\Services\NotificacaoService;
use Sismil\Services\AuditLogger;
use PDO;

class NotificacaoController {

    public function list(Request $request) {
        require_login(['admin', 'sargenteacao']);

        try {
            $apenasNaoLidas = $request->getQuery('nao_lidas') == '1';
            $service = new NotificacaoService();
            $dados = $service->listarNotificacoes($apenasNaoLidas);
            Response::json(['dados' => $dados] + $dados);
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao listar notificações: " . $e->getMessage());
            Response::error('Erro ao carregar notificações.', 500);
        }
    }

    public function marcarLida(Request $request) {
        require_login(['admin', 'sargenteacao']);
        validate_csrf();

        try {
            $id = (int)($request->getBody('id') ?? 0);
            $todas = !empty($request->getBody('todas'));
            $service = new NotificacaoService();
            $service->marcarComoLida($id, $todas);
            Response::json(null, 'Notificação atualizada.');
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao marcar notificação: " . $e->getMessage());
            Response::error('Erro ao atualizar notificação.', 500);
        }
    }

    public function efetivarPreCadastro(Request $request) {
        require_login(['admin', 'sargenteacao']);
        validate_csrf();

        try {
            $notifId = (int)($request->getBody('notificacao_id') ?? 0);
            $dadosBody = $request->getBody('dados') ?? null;

            $pdo = Database::getInstance();
            $dadosMilitar = null;

            if ($notifId > 0) {
                $stmt = $pdo->prepare("SELECT id, dados_json FROM tb_notificacoes WHERE id = ?");
                $stmt->execute([$notifId]);
                $notif = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($notif && !empty($notif['dados_json'])) {
                    $dadosMilitar = json_decode($notif['dados_json'], true);
                }
            } elseif (is_array($dadosBody)) {
                $dadosMilitar = $dadosBody;
            }

            if (!$dadosMilitar || empty($dadosMilitar['nome_completo'])) {
                Response::error('Dados insuficientes para efetivar o pré-cadastro.', 400);
            }

            $nomeCompleto = mb_strtoupper(trim($dadosMilitar['nome_completo']), 'UTF-8');
            $nomeGuerra   = mb_strtoupper(trim($dadosMilitar['nome_guerra'] ?? ''), 'UTF-8');
            if ($nomeGuerra === '') {
                $partes = explode(' ', $nomeCompleto);
                $nomeGuerra = end($partes);
            }
            $cpfVal = !empty($dadosMilitar['cpf']) ? trim((string)$dadosMilitar['cpf']) : null;
            $postoGrad = trim($dadosMilitar['posto_grad'] ?? '') ?: 'Sd EV';
            $numDigitos = !empty($dadosMilitar['numero']) ? preg_replace('/\D/', '', (string)$dadosMilitar['numero']) : '';
            $numeroVal = ($numDigitos !== '') ? (int)$numDigitos : null;

            // 1. Verifica se já não foi cadastrado antes (por nome exato, ou por posto + nome de guerra se único)
            $stmtCheck = $pdo->prepare("SELECT id FROM tb_militares WHERE UPPER(TRIM(nome_completo)) = ?");
            $stmtCheck->execute([$nomeCompleto]);
            $existenteId = (int)$stmtCheck->fetchColumn();

            if ($existenteId <= 0 && $numeroVal !== null) {
                $stmtCheckNum = $pdo->prepare("SELECT id FROM tb_militares WHERE posto_grad = ? AND numero = ?");
                $stmtCheckNum->execute([$postoGrad, $numeroVal]);
                $existenteId = (int)$stmtCheckNum->fetchColumn();
            }

            if ($existenteId > 0) {
                if ($notifId > 0) {
                    $pdo->prepare("UPDATE tb_notificacoes SET lida = 1, militar_id = ? WHERE id = ?")->execute([$existenteId, $notifId]);
                }
                Response::json(['militar_id' => $existenteId, 'ja_existia' => true], 'Militar já constava na base de dados (registro atualizado).');
                return;
            }

            $stmtIns = $pdo->prepare("
                INSERT INTO tb_militares (
                    posto_grad, numero, qmg, grupamento, nome_guerra, nome_completo,
                    cpf, situacao_atual, situacao_desde, subunidade, secao, funcao, status_ativo
                ) VALUES (
                    :posto_grad, :numero, :qmg, :grupamento, :nome_guerra, :nome_completo,
                    :cpf, :situacao_atual, NOW(), :subunidade, :secao, :funcao, 1
                )
            ");
            $stmtIns->execute([
                ':posto_grad'     => $postoGrad,
                ':numero'         => $numeroVal,
                ':qmg'            => !empty($dadosMilitar['qmg']) ? trim((string)$dadosMilitar['qmg']) : null,
                ':grupamento'     => !empty($dadosMilitar['grupamento']) ? trim((string)$dadosMilitar['grupamento']) : null,
                ':nome_guerra'    => $nomeGuerra,
                ':nome_completo'  => $nomeCompleto,
                ':cpf'            => $cpfVal,
                ':situacao_atual' => !empty($dadosMilitar['situacao_atual']) ? trim((string)$dadosMilitar['situacao_atual']) : 'Em Forma',
                ':subunidade'     => !empty($dadosMilitar['subunidade']) ? trim((string)$dadosMilitar['subunidade']) : 'EM',
                ':secao'          => !empty($dadosMilitar['secao']) ? trim((string)$dadosMilitar['secao']) : null,
                ':funcao'         => !empty($dadosMilitar['funcao']) ? trim((string)$dadosMilitar['funcao']) : null,
            ]);

            $novoId = (int)$pdo->lastInsertId();

            if ($notifId > 0) {
                $pdo->prepare("UPDATE tb_notificacoes SET lida = 1, militar_id = ? WHERE id = ?")->execute([$novoId, $notifId]);
            }

            if (!empty($cpfVal)) {
                $stmtUser = $pdo->prepare("
                    INSERT INTO tb_usuarios (identidade, senha_hash, role, ativo, nome, posto_grad, subunidade, militar_id)
                    VALUES (?, '', 'militar', 1, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE militar_id = VALUES(militar_id)
                ");
                $stmtUser->execute([$cpfVal, $nomeGuerra, $postoGrad, $dadosMilitar['subunidade'] ?? 'EM', $novoId]);
            }

            AuditLogger::log('PRE_CADASTRO_EFETIVADO', "Pré-cadastro efetivado via Planilha Geral: {$nomeCompleto} (ID {$novoId}).");

            Response::json(['militar_id' => $novoId], 'Pré-cadastro efetivado com sucesso.');
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao efetivar pré-cadastro: " . $e->getMessage());
            Response::error('Erro ao efetivar pré-cadastro: ' . $e->getMessage(), 500);
        }
    }
}
