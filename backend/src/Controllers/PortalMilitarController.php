<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use Sismil\Services\SchemaService;
use Sismil\Services\NotificacaoService;
use Sismil\Services\AuditLogger;
use PDO;

class PortalMilitarController {

    private function obterMilitarIdSessao(PDO $pdo): int {
        $militarId = (int)($_SESSION['militar_id'] ?? 0);
        if ($militarId > 0) {
            return $militarId;
        }

        // Caso o usuário tenha logado com CPF mas militar_id não estava vinculado em tb_usuarios, vincula automaticamente
        $username = $_SESSION['usuario_username'] ?? '';
        $cpfClean = preg_replace('/\D/', '', $username);
        if ($cpfClean !== '') {
            $stmt = $pdo->prepare("
                SELECT id FROM tb_militares
                WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = :cpf
                   OR REPLACE(REPLACE(idt_militar, '.', ''), '-', '') = :idt
                LIMIT 1
            ");
            $stmt->execute([':cpf' => $cpfClean, ':idt' => $cpfClean]);
            $foundId = (int)$stmt->fetchColumn();
            if ($foundId > 0) {
                $_SESSION['militar_id'] = $foundId;
                if (!empty($_SESSION['usuario_id'])) {
                    $pdo->prepare("UPDATE tb_usuarios SET militar_id = ? WHERE id = ?")->execute([$foundId, (int)$_SESSION['usuario_id']]);
                }
                return $foundId;
            }
        }
        return 0;
    }

    public function meuPerfil(Request $request) {
        require_login();
        SchemaService::ensureSchema();

        try {
            $pdo = Database::getInstance();
            $militarId = $this->obterMilitarIdSessao($pdo);

            if ($militarId <= 0) {
                Response::error('Sua conta de usuário não está vinculada a um cadastro militar ativo.', 404);
                return;
            }

            $stmtM = $pdo->prepare("SELECT * FROM tb_militares WHERE id = ?");
            $stmtM->execute([$militarId]);
            $militar = $stmtM->fetch(PDO::FETCH_ASSOC);

            if (!$militar) {
                Response::error('Cadastro militar não encontrado.', 404);
                return;
            }

            foreach ($militar as $k => $v) {
                if ($v === null) $militar[$k] = '';
            }
            if (empty($militar['situacao_atual'])) {
                $militar['situacao_atual'] = 'Em Forma';
            }

            // Contagem individual de FO+ (apenas do próprio militar)
            $stmtFo = $pdo->prepare("
                SELECT COUNT(*) FROM tb_alteracoes
                WHERE militar_id = ? AND (UPPER(TRIM(tipo_detalhe)) LIKE '%FO+%' OR UPPER(TRIM(tipo_detalhe)) LIKE '%FO +%')
            ");
            $stmtFo->execute([$militarId]);
            $meuFoPositivo = (int)$stmtFo->fetchColumn();

            // Histórico S1 do próprio militar (apenas lançamentos reais)
            $stmtHist = $pdo->prepare("
                SELECT id, categoria, tipo_detalhe, descricao, data_fato, documento_ref, qtd_dias
                FROM tb_alteracoes
                WHERE militar_id = ? AND categoria IS NOT NULL AND categoria != ''
                ORDER BY data_fato DESC, id DESC
            ");
            $stmtHist->execute([$militarId]);
            $meuHistorico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

            // Veículos do próprio militar (somente leitura)
            $stmtVeic = $pdo->prepare("
                SELECT id, tipo_veiculo, placa, marca, modelo, cor, emissao_crlv, validade_crlv, homologado, observacao_s2
                FROM tb_veiculos
                WHERE militar_id = ?
                ORDER BY id DESC
            ");
            $stmtVeic->execute([$militarId]);
            $meusVeiculos = $stmtVeic->fetchAll(PDO::FETCH_ASSOC);

            // Livro de apresentação individual
            $stmtLivro = $pdo->prepare("
                SELECT id, situacao_anterior, situacao_nova, data_inicio, data_termino, observacao, registrado_por, created_at
                FROM tb_livro_apresentacao
                WHERE militar_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT 20
            ");
            $stmtLivro->execute([$militarId]);
            $meuLivro = $stmtLivro->fetchAll(PDO::FETCH_ASSOC);

            // Arranchamentos futuros do próprio militar
            $stmtArr = $pdo->prepare("
                SELECT id, data_refeicao, cafe, almoco, jantar
                FROM tb_arranchamento
                WHERE data_refeicao >= CURDATE()
                  AND subunidade = ?
                  AND posto_grad = ?
                  AND UPPER(TRIM(nome_guerra)) = UPPER(TRIM(?))
                  AND is_extra = 0
                ORDER BY data_refeicao ASC
                LIMIT 30
            ");
            $stmtArr->execute([
                $militar['subunidade'] ?? '',
                $militar['posto_grad'] ?? '',
                $militar['nome_guerra'] ?? ''
            ]);
            $meusArranchamentos = $stmtArr->fetchAll(PDO::FETCH_ASSOC);

            $payload = [
                'militar'               => $militar,
                'meu_fo_positivo'       => $meuFoPositivo,
                'meu_historico'         => $meuHistorico,
                'meus_veiculos'         => $meusVeiculos,
                'meu_livro'             => $meuLivro,
                'meus_arranchamentos'   => $meusArranchamentos,
                'situacoes_disponiveis' => SchemaService::getSituacoesDisponiveis()
            ];

            Response::json(['dados' => $payload] + $payload);
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao carregar Meu Painel: " . $e->getMessage());
            Response::error('Erro ao carregar dados do perfil.', 500);
        }
    }

    public function salvarDadosBasicos(Request $request) {
        require_login();
        validate_csrf();
        SchemaService::ensureSchema();

        try {
            $pdo = Database::getInstance();
            $militarId = $this->obterMilitarIdSessao($pdo);
            if ($militarId <= 0) {
                Response::error('Cadastro militar não vinculado.', 403);
                return;
            }

            $dados = $request->getBody();

            $stmt = $pdo->prepare("
                UPDATE tb_militares
                SET email          = :email,
                    celular_princ  = :celular_princ,
                    celular_sec    = :celular_sec,
                    nome_resp      = :nome_resp,
                    tel_resp       = :tel_resp,
                    tel_emergencia = :tel_emergencia,
                    cep            = :cep,
                    endereco       = :endereco,
                    num_residencia = :num_residencia,
                    bairro         = :bairro,
                    cidade         = :cidade,
                    estado         = :estado
                WHERE id = :id
            ");

            $stmt->execute([
                ':email'          => strtolower(trim((string)($dados['email'] ?? ''))) ?: null,
                ':celular_princ'  => trim((string)($dados['celular_princ'] ?? '')) ?: null,
                ':celular_sec'    => trim((string)($dados['celular_sec'] ?? '')) ?: null,
                ':nome_resp'      => mb_strtoupper(trim((string)($dados['nome_resp'] ?? '')), 'UTF-8') ?: null,
                ':tel_resp'       => trim((string)($dados['tel_resp'] ?? '')) ?: null,
                ':tel_emergencia' => trim((string)($dados['tel_emergencia'] ?? '')) ?: null,
                ':cep'            => trim((string)($dados['cep'] ?? '')) ?: null,
                ':endereco'       => mb_strtoupper(trim((string)($dados['endereco'] ?? '')), 'UTF-8') ?: null,
                ':num_residencia' => trim((string)($dados['num_residencia'] ?? '')) ?: null,
                ':bairro'         => mb_strtoupper(trim((string)($dados['bairro'] ?? '')), 'UTF-8') ?: null,
                ':cidade'         => mb_strtoupper(trim((string)($dados['cidade'] ?? '')), 'UTF-8') ?: null,
                ':estado'         => mb_strtoupper(substr(trim((string)($dados['estado'] ?? '')), 0, 2), 'UTF-8') ?: null,
                ':id'             => $militarId
            ]);

            AuditLogger::log('PORTAL_DADOS_ATUALIZADOS', "Militar ID {$militarId} atualizou seus próprios dados de contato/endereço.");

            Response::json(null, 'Dados de contato e endereço atualizados com sucesso.');
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao salvar dados básicos no portal: " . $e->getMessage());
            Response::error('Erro ao salvar alterações.', 500);
        }
    }

    public function registrarApresentacao(Request $request) {
        require_login();
        validate_csrf();
        SchemaService::ensureSchema();

        try {
            $pdo = Database::getInstance();
            $militarId = $this->obterMilitarIdSessao($pdo);
            if ($militarId <= 0) {
                Response::error('Cadastro militar não vinculado.', 403);
                return;
            }

            $novaSit     = trim((string)($request->getBody('situacao_nova') ?? ''));
            $dataInicio  = trim((string)($request->getBody('data_inicio') ?? '')) ?: null;
            $dataTermino = trim((string)($request->getBody('data_termino') ?? '')) ?: null;
            $observacao  = trim((string)($request->getBody('observacao') ?? ''));

            $permitidas = SchemaService::getSituacoesDisponiveis();
            if ($novaSit === '' || !in_array($novaSit, $permitidas, true)) {
                Response::error('Selecione uma situação válida da lista.', 400);
                return;
            }

            $stmtM = $pdo->prepare("SELECT id, posto_grad, numero, nome_guerra, subunidade, situacao_atual FROM tb_militares WHERE id = ?");
            $stmtM->execute([$militarId]);
            $m = $stmtM->fetch(PDO::FETCH_ASSOC);
            if (!$m) {
                Response::error('Militar não encontrado.', 404);
                return;
            }

            $sitAnterior = !empty($m['situacao_atual']) ? $m['situacao_atual'] : 'Em Forma';
            $ident = trim($m['posto_grad'] . ' ' . ($m['numero'] ? $m['numero'] . ' ' : '') . $m['nome_guerra']);

            $stmtUp = $pdo->prepare("
                UPDATE tb_militares
                SET situacao_atual = ?, situacao_desde = NOW(), situacao_obs = ?
                WHERE id = ?
            ");
            $stmtUp->execute([$novaSit, $observacao ?: null, $militarId]);

            $stmtLiv = $pdo->prepare("
                INSERT INTO tb_livro_apresentacao (militar_id, situacao_anterior, situacao_nova, data_inicio, data_termino, observacao, registrado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtLiv->execute([$militarId, $sitAnterior, $novaSit, $dataInicio, $dataTermino, $observacao ?: null, $ident]);

            $notifService = new NotificacaoService();
            $notifService->notificarLivroApresentacao($militarId, $ident, $sitAnterior, $novaSit, $observacao);

            AuditLogger::log('PORTAL_APRESENTACAO', "Militar {$ident} (ID {$militarId}) registrou situação '{$novaSit}' no Livro Digital.");

            Response::json(null, 'Apresentação e situação registradas com sucesso.');
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao registrar apresentação no portal: " . $e->getMessage());
            Response::error('Erro ao registrar apresentação.', 500);
        }
    }
}
