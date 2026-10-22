<?php
namespace Sismil\Controllers;

use Sismil\Core\Request;
use Sismil\Core\Response;
use Sismil\Core\Database;
use Sismil\Services\SchemaService;
use Sismil\Services\NotificacaoService;
use Sismil\Services\AuditLogger;
use PDO;

class S1PainelController {

    public function resumo(Request $request) {
        require_login(['admin', 'sargenteacao']);
        SchemaService::ensureSchema();

        try {
            $pdo = Database::getInstance();
            $subunidade = trim((string)$request->getQuery('subunidade', ''));
            $dias = (int)$request->getQuery('dias', 0);

            $whereMilitar = "m.status_ativo = 1";
            $params = [];

            if ($subunidade !== '' && $subunidade !== 'Todas') {
                $whereMilitar .= " AND m.subunidade = :su";
                $params[':su'] = $subunidade;
            }

            $whereData = "";
            if ($dias > 0) {
                $whereData = " AND a.data_fato >= DATE_SUB(CURDATE(), INTERVAL " . (int)$dias . " DAY)";
            }

            // 1. Estatísticas de Alterações S1 (apenas lançamentos reais com categoria preenchida)
            $sqlStats = "
                SELECT
                    COUNT(a.id) as total_alteracoes,
                    SUM(CASE WHEN UPPER(TRIM(a.tipo_detalhe)) LIKE '%FO+%' OR UPPER(TRIM(a.tipo_detalhe)) LIKE '%FO +%' THEN 1 ELSE 0 END) as total_fo_positivo,
                    SUM(CASE WHEN UPPER(TRIM(a.tipo_detalhe)) LIKE '%FO-%' OR UPPER(TRIM(a.tipo_detalhe)) LIKE '%FO -%' THEN 1 ELSE 0 END) as total_fo_negativo,
                    SUM(CASE WHEN a.categoria = 'ELOGIO' AND UPPER(TRIM(COALESCE(a.tipo_detalhe, ''))) NOT LIKE '%FO+%' THEN 1 ELSE 0 END) as total_elogios,
                    SUM(CASE WHEN a.categoria = 'DISCIPLINA' AND UPPER(TRIM(COALESCE(a.tipo_detalhe, ''))) NOT LIKE '%FO-%' THEN 1 ELSE 0 END) as total_punicoes,
                    SUM(CASE WHEN a.categoria IN ('SAUDE', 'DISPENSA') THEN 1 ELSE 0 END) as total_dispensas,
                    SUM(CASE WHEN a.categoria = 'FERIAS' THEN 1 ELSE 0 END) as total_ferias
                FROM tb_alteracoes a
                JOIN tb_militares m ON a.militar_id = m.id
                WHERE {$whereMilitar}
                  AND a.categoria IS NOT NULL AND a.categoria != ''
                  {$whereData}
            ";
            $stmtStats = $pdo->prepare($sqlStats);
            $stmtStats->execute($params);
            $statsRow = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];

            $cards = [
                'total_alteracoes'  => (int)($statsRow['total_alteracoes'] ?? 0),
                'total_fo_positivo' => (int)($statsRow['total_fo_positivo'] ?? 0),
                'total_fo_negativo' => (int)($statsRow['total_fo_negativo'] ?? 0),
                'total_elogios'     => (int)($statsRow['total_elogios'] ?? 0),
                'total_punicoes'    => (int)($statsRow['total_punicoes'] ?? 0),
                'total_dispensas'   => (int)($statsRow['total_dispensas'] ?? 0),
                'total_ferias'      => (int)($statsRow['total_ferias'] ?? 0),
            ];

            // 2. Ranking de FO+ (acumulado geral por militar, visível para Sargenteação/Admin)
            $sqlRanking = "
                SELECT
                    m.id, m.posto_grad, m.numero, m.nome_guerra, m.nome_completo,
                    m.subunidade, m.secao, m.grupamento, m.foto_path, COALESCE(m.situacao_atual, 'Em Forma') as situacao_atual,
                    COUNT(a.id) as total_fo_positivo,
                    MAX(a.data_fato) as ultimo_fo_positivo
                FROM tb_militares m
                JOIN tb_alteracoes a ON a.militar_id = m.id
                WHERE {$whereMilitar}
                  AND (UPPER(TRIM(a.tipo_detalhe)) LIKE '%FO+%' OR UPPER(TRIM(a.tipo_detalhe)) LIKE '%FO +%')
                GROUP BY m.id
                ORDER BY total_fo_positivo DESC, ultimo_fo_positivo DESC, m.nome_guerra ASC
                LIMIT 100
            ";
            $stmtRank = $pdo->prepare($sqlRanking);
            $stmtRank->execute($params);
            $rankingFo = $stmtRank->fetchAll(PDO::FETCH_ASSOC);

            // 3. Últimas Alterações Registradas no Histórico S1
            $sqlUltimas = "
                SELECT
                    a.id, a.militar_id, a.categoria, a.tipo_detalhe, a.descricao,
                    a.data_fato, a.documento_ref, a.qtd_dias, a.arquivo_path, a.registrado_por,
                    m.posto_grad, m.numero, m.nome_guerra, m.subunidade
                FROM tb_alteracoes a
                JOIN tb_militares m ON a.militar_id = m.id
                WHERE {$whereMilitar}
                  AND a.categoria IS NOT NULL AND a.categoria != ''
                  {$whereData}
                ORDER BY a.data_fato DESC, a.id DESC
                LIMIT 50
            ";
            $stmtUlt = $pdo->prepare($sqlUltimas);
            $stmtUlt->execute($params);
            $ultimasAlteracoes = $stmtUlt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Resumo do Efetivo por Situação (Livro de Apresentação)
            $sqlSit = "
                SELECT COALESCE(NULLIF(TRIM(m.situacao_atual), ''), 'Em Forma') as situacao, COUNT(*) as total
                FROM tb_militares m
                WHERE {$whereMilitar}
                GROUP BY COALESCE(NULLIF(TRIM(m.situacao_atual), ''), 'Em Forma')
                ORDER BY total DESC, situacao ASC
            ";
            $stmtSit = $pdo->prepare($sqlSit);
            $stmtSit->execute($params);
            $resumoSituacoes = $stmtSit->fetchAll(PDO::FETCH_ASSOC);

            // 5. Registros recentes do Livro de Apresentação Digital
            $sqlLivro = "
                SELECT
                    l.id, l.militar_id, l.situacao_anterior, l.situacao_nova,
                    l.data_inicio, l.data_termino, l.observacao, l.registrado_por, l.created_at,
                    m.posto_grad, m.numero, m.nome_guerra, m.subunidade
                FROM tb_livro_apresentacao l
                JOIN tb_militares m ON l.militar_id = m.id
                WHERE {$whereMilitar}
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT 50
            ";
            $stmtLivro = $pdo->prepare($sqlLivro);
            $stmtLivro->execute($params);
            $livroApresentacao = $stmtLivro->fetchAll(PDO::FETCH_ASSOC);

            // 6. URL salva da planilha
            $stmtCfg = $pdo->prepare("SELECT valor FROM tb_config_sistema WHERE chave = 'planilha_geral_url'");
            $stmtCfg->execute();
            $planilhaUrl = (string)($stmtCfg->fetchColumn() ?: '');

            $stmtSync = $pdo->prepare("SELECT updated_at FROM tb_config_sistema WHERE chave = 'planilha_geral_url'");
            $stmtSync->execute();
            $ultimaSync = (string)($stmtSync->fetchColumn() ?: '');

            $payload = [
                'cards'                 => $cards,
                'ranking_fo_positivo'   => $rankingFo,
                'alteracoes_recentes'   => $ultimasAlteracoes,
                'situacoes_resumo'      => $resumoSituacoes,
                'livro_recente'         => $livroApresentacao,
                'situacoes_disponiveis' => SchemaService::getSituacoesDisponiveis(),
                'planilha_url'          => $planilhaUrl,
                'ultima_sync_planilha'  => $ultimaSync
            ];

            Response::json(['dados' => $payload] + $payload);
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro no resumo S1: " . $e->getMessage());
            Response::error('Erro ao carregar dados do painel S1.', 500);
        }
    }

    public function registrarSituacaoS1(Request $request) {
        require_login(['admin', 'sargenteacao']);
        validate_csrf();
        SchemaService::ensureSchema();

        try {
            $militarId   = (int)($request->getBody('militar_id') ?? 0);
            $novaSit     = trim((string)($request->getBody('situacao_nova') ?? ''));
            $dataInicio  = trim((string)($request->getBody('data_inicio') ?? '')) ?: null;
            $dataTermino = trim((string)($request->getBody('data_termino') ?? '')) ?: null;
            $observacao  = trim((string)($request->getBody('observacao') ?? ''));

            if ($militarId <= 0 || $novaSit === '') {
                Response::error('Militar e nova situação são obrigatórios.', 400);
            }

            $pdo = Database::getInstance();
            $stmtM = $pdo->prepare("SELECT id, posto_grad, numero, nome_guerra, situacao_atual FROM tb_militares WHERE id = ?");
            $stmtM->execute([$militarId]);
            $m = $stmtM->fetch(PDO::FETCH_ASSOC);
            if (!$m) {
                Response::error('Militar não encontrado.', 404);
            }

            $sitAnterior = !empty($m['situacao_atual']) ? $m['situacao_atual'] : 'Em Forma';
            $operador = $_SESSION['usuario_nome'] ?? 'Sargenteação';

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
            $stmtLiv->execute([$militarId, $sitAnterior, $novaSit, $dataInicio, $dataTermino, $observacao ?: null, $operador]);

            AuditLogger::log('LIVRO_APRESENTACAO_S1', "Situação do militar ID {$militarId} alterada de '{$sitAnterior}' para '{$novaSit}'.");

            Response::json(null, 'Situação atualizada e registrada no Livro de Apresentação.');
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao registrar situação S1: " . $e->getMessage());
            Response::error('Erro ao registrar situação.', 500);
        }
    }

    public function sincronizarPlanilha(Request $request) {
        require_login(['admin', 'sargenteacao']);
        validate_csrf();
        SchemaService::ensureSchema();

        try {
            $pdo = Database::getInstance();
            $urlPlanilha = trim((string)($request->getBody('planilha_url') ?? $request->getBody('url_planilha') ?? ''));
            $conteudoRaw = trim((string)($request->getBody('conteudo_colado') ?? $request->getBody('conteudo_planilha') ?? ''));

            if (isset($_FILES['arquivo_planilha']) && $_FILES['arquivo_planilha']['error'] === UPLOAD_ERR_OK) {
                $conteudoRaw = (string)file_get_contents($_FILES['arquivo_planilha']['tmp_name']);
            }

            if ($urlPlanilha !== '') {
                $stmtSaveUrl = $pdo->prepare("
                    INSERT INTO tb_config_sistema (chave, valor) VALUES ('planilha_geral_url', ?)
                    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
                ");
                $stmtSaveUrl->execute([$urlPlanilha]);

                if ($conteudoRaw === '') {
                    $csvUrl = $this->converterLinkGoogleSheetsParaCsv($urlPlanilha);
                    $statusDownload = '';
                    $conteudoRaw = $this->baixarConteudoRemoto($csvUrl, $statusDownload);
                    if ($conteudoRaw === '') {
                        if ($statusDownload === 'PRIVADA') {
                            Response::error('A planilha do Google Drive está com acesso restrito (privada). Mude o compartilhamento no Google Drive para "Qualquer pessoa com o link pode ler" ou utilize a Opção 2 ao lado (copiar e colar as linhas da planilha).', 422);
                        } else {
                            Response::error('Não foi possível baixar a planilha diretamente pelo link (verifique a conexão com a internet da máquina ou utilize a Opção 2: colar os dados / enviar o arquivo CSV).', 422);
                        }
                        return;
                    }
                }
            }

            if ($conteudoRaw === '') {
                Response::error('Informe o link da planilha Google Sheets, envie o arquivo CSV ou cole os dados da planilha.', 400);
                return;
            }

            $linhas = $this->parsePlanilhaTexto($conteudoRaw);
            if (count($linhas) < 2) {
                Response::error('Nenhuma linha de dados válida foi encontrada na planilha.', 400);
                return;
            }

            // Carrega todos os militares do banco para comparação rápida por múltiplos critérios
            $stmtMil = $pdo->query("SELECT id, posto_grad, numero, qmg, grupamento, nome_guerra, nome_completo, situacao_atual, subunidade, secao, funcao FROM tb_militares");
            $militaresBanco = $stmtMil->fetchAll(PDO::FETCH_ASSOC);

            $mapaPorNomeExato = [];
            $mapaPorNomeLimpo = [];
            $mapaPorPostoGuerra = [];
            $mapaPorPostoNumero = [];
            $mapaPorGuerraNumero = [];

            foreach ($militaresBanco as $mb) {
                $ncExato = $this->normalizarChaveNome($mb['nome_completo'] ?? '', false);
                $ncLimpo = $this->normalizarChaveNome($mb['nome_completo'] ?? '', true);
                if ($ncExato !== '') $mapaPorNomeExato[$ncExato] = $mb;
                if ($ncLimpo !== '') $mapaPorNomeLimpo[$ncLimpo] = $mb;

                $posto = $mb['posto_grad'] ?? '';
                $guerra = $this->normalizarChaveNome($mb['nome_guerra'] ?? '', false);
                $numero = trim((string)($mb['numero'] ?? ''));

                if ($posto !== '' && $guerra !== '') {
                    $mapaPorPostoGuerra[$posto . '_' . $guerra][] = $mb;
                }
                if ($posto !== '' && $numero !== '') {
                    $mapaPorPostoNumero[$posto . '_' . $numero][] = $mb;
                }
                $gnKey = $this->normalizarChaveNome($posto . '_' . $numero . '_' . ($mb['nome_guerra'] ?? ''));
                if ($gnKey !== '__') {
                    $mapaPorGuerraNumero[$gnKey] = $mb;
                }
            }

            // Identifica índices das colunas pelo cabeçalho
            // Cabeçalho padrão: Ord | P/Grad | Nr | QM | GPT | NOME DE GUERRA | NOME COMPLETO | SITUAÇÃO | FRAÇÃO | SEÇÃO | FUNÇÃO | ORD.
            $idxMap = [
                'posto_grad'     => 1,
                'numero'         => 2,
                'qmg'            => 3,
                'grupamento'     => 4,
                'nome_guerra'    => 5,
                'nome_completo'  => 6,
                'situacao_atual' => 7,
                'subunidade'     => 8,
                'secao'          => 9,
                'funcao'         => 10
            ];

            $inicioDados = 0;
            foreach ($linhas as $rIdx => $cols) {
                $linhaConcat = mb_strtoupper(implode(' ', $cols), 'UTF-8');
                if (strpos($linhaConcat, 'NOME COMPLETO') !== false || strpos($linhaConcat, 'NOME DE GUERRA') !== false) {
                    $inicioDados = $rIdx + 1;
                    foreach ($cols as $cIdx => $colName) {
                        $cn = $this->normalizarChaveNome($colName);
                        if (in_array($cn, ['PGRAD', 'POSTOGRAD', 'POSTO'])) $idxMap['posto_grad'] = $cIdx;
                        elseif (in_array($cn, ['NR', 'NUMERO', 'NUM'])) $idxMap['numero'] = $cIdx;
                        elseif (in_array($cn, ['QM', 'QMG', 'QMGQMP'])) $idxMap['qmg'] = $cIdx;
                        elseif (in_array($cn, ['GPT', 'GRUPAMENTO'])) $idxMap['grupamento'] = $cIdx;
                        elseif (strpos($cn, 'NOMEDEGUERRA') !== false || $cn === 'GUERRA') $idxMap['nome_guerra'] = $cIdx;
                        elseif (strpos($cn, 'NOMECOMPLETO') !== false || $cn === 'NOME') $idxMap['nome_completo'] = $cIdx;
                        elseif (strpos($cn, 'SITUACAO') !== false) $idxMap['situacao_atual'] = $cIdx;
                        elseif (strpos($cn, 'FRACAO') !== false || strpos($cn, 'SUBUNIDADE') !== false || $cn === 'CIA') $idxMap['subunidade'] = $cIdx;
                        elseif (strpos($cn, 'SECAO') !== false) $idxMap['secao'] = $cIdx;
                        elseif (strpos($cn, 'FUNCAO') !== false) $idxMap['funcao'] = $cIdx;
                    }
                    break;
                }
            }

            $notifService = new NotificacaoService();
            $atualizados = 0;
            $mudancasCia = 0;
            $mudancasSituacao = 0;
            $novosDetectados = [];
            $detalhesMudancas = [];

            $stmtUpdate = $pdo->prepare("
                UPDATE tb_militares
                SET posto_grad = :posto_grad,
                    numero = :numero,
                    qmg = :qmg,
                    grupamento = :grupamento,
                    nome_guerra = :nome_guerra,
                    situacao_atual = :situacao_atual,
                    situacao_desde = CASE WHEN COALESCE(situacao_atual, '') != :situacao_atual2 THEN NOW() ELSE situacao_desde END,
                    subunidade = :subunidade,
                    secao = :secao,
                    funcao = :funcao
                WHERE id = :id
            ");

            $stmtLivro = $pdo->prepare("
                INSERT INTO tb_livro_apresentacao (militar_id, situacao_anterior, situacao_nova, observacao, registrado_por)
                VALUES (?, ?, ?, 'Sincronizado via Planilha Geral', ?)
            ");

            $operador = $_SESSION['usuario_nome'] ?? 'Sincronizador Planilha';

            for ($i = $inicioDados; $i < count($linhas); $i++) {
                $cols = $linhas[$i];
                if (count($cols) < 6) continue;

                $nomeCompleto = mb_strtoupper(trim($cols[$idxMap['nome_completo']] ?? ''), 'UTF-8');
                $nomeGuerra   = mb_strtoupper(trim($cols[$idxMap['nome_guerra']] ?? ''), 'UTF-8');
                if ($nomeCompleto === '' || $nomeCompleto === 'NOME COMPLETO') continue;

                $posto  = $this->normalizarPosto(trim($cols[$idxMap['posto_grad']] ?? ''));
                $numero = trim($cols[$idxMap['numero']] ?? '');
                $qmg    = trim($cols[$idxMap['qmg']] ?? '');
                $gpt    = trim($cols[$idxMap['grupamento']] ?? '');
                $sit    = trim($cols[$idxMap['situacao_atual']] ?? '') ?: 'Em Forma';
                $fracao = $this->normalizarSubunidade(trim($cols[$idxMap['subunidade']] ?? ''));
                $secao  = trim($cols[$idxMap['secao']] ?? '');
                $funcao = trim($cols[$idxMap['funcao']] ?? '');

                $chaveNomeExata = $this->normalizarChaveNome($nomeCompleto, false);
                $chaveNomeLimpa = $this->normalizarChaveNome($nomeCompleto, true);
                $chaveGN        = $this->normalizarChaveNome($posto . '_' . $numero . '_' . $nomeGuerra, false);

                $militarExistente = $mapaPorNomeExato[$chaveNomeExata] ?? ($mapaPorNomeLimpo[$chaveNomeLimpa] ?? null);

                // Se não achou por nome completo, tenta por posto + nome de guerra (quando único) ou posto + número
                if (!$militarExistente && $posto !== '' && $nomeGuerra !== '') {
                    $pgKey = $posto . '_' . $this->normalizarChaveNome($nomeGuerra, false);
                    if (isset($mapaPorPostoGuerra[$pgKey]) && count($mapaPorPostoGuerra[$pgKey]) === 1) {
                        $militarExistente = $mapaPorPostoGuerra[$pgKey][0];
                    }
                }

                if (!$militarExistente && $posto !== '' && $numero !== '') {
                    $pnKey = $posto . '_' . $numero;
                    if (isset($mapaPorPostoNumero[$pnKey]) && count($mapaPorPostoNumero[$pnKey]) === 1) {
                        $militarExistente = $mapaPorPostoNumero[$pnKey][0];
                    }
                }

                if (!$militarExistente) {
                    $militarExistente = $mapaPorGuerraNumero[$chaveGN] ?? null;
                }

                if ($militarExistente) {
                    $novoPosto  = $posto !== '' ? $posto : $militarExistente['posto_grad'];
                    $novoNum    = $numero !== '' ? $numero : $militarExistente['numero'];
                    $novoQmg    = $qmg !== '' ? $qmg : $militarExistente['qmg'];
                    $novoGpt    = $gpt !== '' ? $gpt : $militarExistente['grupamento'];
                    $novoGuerra = $nomeGuerra !== '' ? $nomeGuerra : $militarExistente['nome_guerra'];
                    $novaSit    = $sit !== '' ? $sit : ($militarExistente['situacao_atual'] ?: 'Em Forma');
                    $novaSu     = $fracao !== '' ? $fracao : $militarExistente['subunidade'];
                    $novaSecao  = $secao !== '' ? $secao : $militarExistente['secao'];
                    $novaFuncao = $funcao !== '' ? $funcao : $militarExistente['funcao'];

                    $mudouSu  = ($novaSu !== '' && $novaSu !== ($militarExistente['subunidade'] ?? ''));
                    $mudouSit = ($novaSit !== '' && $novaSit !== ($militarExistente['situacao_atual'] ?: 'Em Forma'));
                    $mudouOutro = (
                        $novoPosto !== ($militarExistente['posto_grad'] ?? '') ||
                        (string)$novoNum !== (string)($militarExistente['numero'] ?? '') ||
                        $novoQmg !== ($militarExistente['qmg'] ?? '') ||
                        $novoGpt !== ($militarExistente['grupamento'] ?? '') ||
                        $novoGuerra !== ($militarExistente['nome_guerra'] ?? '') ||
                        $novaSecao !== ($militarExistente['secao'] ?? '') ||
                        $novaFuncao !== ($militarExistente['funcao'] ?? '')
                    );

                    if ($mudouSu || $mudouSit || $mudouOutro) {
                        $stmtUpdate->execute([
                            ':posto_grad'      => $novoPosto,
                            ':numero'          => $novoNum ?: null,
                            ':qmg'             => $novoQmg ?: null,
                            ':grupamento'      => $novoGpt ?: null,
                            ':nome_guerra'     => $novoGuerra,
                            ':situacao_atual'  => $novaSit,
                            ':situacao_atual2' => $novaSit,
                            ':subunidade'      => $novaSu,
                            ':secao'           => $novaSecao ?: null,
                            ':funcao'          => $novaFuncao ?: null,
                            ':id'              => (int)$militarExistente['id']
                        ]);
                        $atualizados++;

                        $MudancaPartes = [];
                        if ($mudouSu) {
                            $mudancasCia++;
                            $MudancaPartes[] = "Cia: " . ($militarExistente['subunidade'] ?: '---') . " -> {$novaSu}";
                        }
                        if ($mudouSit) {
                            $mudancasSituacao++;
                            $sitAnt = $militarExistente['situacao_atual'] ?: 'Em Forma';
                            $MudancaPartes[] = "Situação: {$sitAnt} -> {$novaSit}";
                            $stmtLivro->execute([(int)$militarExistente['id'], $sitAnt, $novaSit, $operador]);
                        }
                        if (!empty($MudancaPartes) && count($detalhesMudancas) < 40) {
                            $detalhesMudancas[] = [
                                'militar'   => "{$novoPosto} {$novoGuerra}",
                                'alteracao' => implode(' | ', $MudancaPartes)
                            ];
                        }
                    }
                } else {
                    // Militar novo na planilha -> gera notificação e pré-cadastro
                    $dadosNovo = [
                        'posto_grad'     => $posto ?: 'Sd EV',
                        'numero'         => $numero,
                        'qmg'            => $qmg,
                        'grupamento'     => $gpt,
                        'nome_guerra'    => $nomeGuerra,
                        'nome_completo'  => $nomeCompleto,
                        'situacao_atual' => $sit,
                        'subunidade'     => $fracao ?: 'EM',
                        'secao'          => $secao,
                        'funcao'         => $funcao
                    ];
                    $notifService->criarNotificacaoPreCadastro($dadosNovo);
                    $novosDetectados[] = $dadosNovo;
                }
            }

            AuditLogger::log('PLANILHA_SYNC', "Sincronização com Planilha Geral concluída: {$atualizados} atualizado(s), {$mudancasCia} mudança(s) de Cia, " . count($novosDetectados) . " novo(s) detectado(s).");

            $payloadSync = [
                'linhas_lidas'      => max(0, count($linhas) - $inicioDados),
                'atualizados'       => $atualizados,
                'mudancas_cia'      => $mudancasCia,
                'mudancas_situacao' => $mudancasSituacao,
                'novos_na_planilha' => $novosDetectados,
                'novos_detectados'  => $novosDetectados,
                'detalhes_mudancas' => $detalhesMudancas
            ];

            Response::json(['dados' => $payloadSync] + $payloadSync, 'Sincronização concluída com sucesso.');
        } catch (\Exception $e) {
            error_log("[SISMIL] Erro ao sincronizar planilha: " . $e->getMessage());
            Response::error('Erro ao sincronizar planilha: ' . $e->getMessage(), 500);
        }
    }

    private function converterLinkGoogleSheetsParaCsv(string $url): string {
        if (strpos($url, 'export?format=') !== false || strpos($url, 'output=csv') !== false) {
            return $url;
        }
        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $m)) {
            $id = $m[1];
            if (preg_match('/[#&?]gid=([0-9]+)/', $url, $mg)) {
                return "https://docs.google.com/spreadsheets/d/{$id}/export?format=tsv&gid={$mg[1]}";
            }
            // Se não veio gid na URL, descobre automaticamente a aba 'Ef' (Efetivo Geral) na planilha
            $gidEf = $this->descobrirGidAbaEfetivo($id);
            if ($gidEf !== '') {
                return "https://docs.google.com/spreadsheets/d/{$id}/export?format=tsv&gid={$gidEf}";
            }
            return "https://docs.google.com/spreadsheets/d/{$id}/export?format=tsv";
        }
        return $url;
    }

    private function descobrirGidAbaEfetivo(string $spreadsheetId): string {
        if (!function_exists('curl_init')) return '';
        $ch = curl_init("https://docs.google.com/spreadsheets/d/{$spreadsheetId}/htmlview");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, '');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SISMIL/2.0');
        $html = (string)curl_exec($ch);
        curl_close($ch);

        if ($html !== '' && preg_match_all('/items\.push\(\{name:\s*"([^"]+)",\s*pageUrl:.*?gid=(\d+)/s', $html, $matches, PREG_SET_ORDER)) {
            // Prioriza aba chamada "Ef", "Efetivo" ou "Geral"
            foreach ($matches as $item) {
                $nomeAba = mb_strtoupper(trim($item[1]), 'UTF-8');
                if ($nomeAba === 'EF' || strpos($nomeAba, 'EFETIVO') !== false || strpos($nomeAba, 'GERAL') !== false) {
                    return $item[2];
                }
            }
        }
        return '';
    }

    private function baixarConteudoRemoto(string $url, string &$motivoErro = ''): string {
        if (!preg_match('#^https?://#i', $url)) return '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_COOKIEFILE, '');
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SISMIL/2.0');
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            if (stripos($effectiveUrl, 'accounts.google.com') !== false || stripos((string)$res, 'ServiceLogin') !== false || $code === 401 || $code === 403) {
                $motivoErro = 'PRIVADA';
                return '';
            }

            if ($code >= 200 && $code < 300 && is_string($res) && trim($res) !== '' && stripos($res, '<!DOCTYPE html>') === false && stripos($res, '<html') === false) {
                // Se o usuário colou link de outra aba (ex: Mapa) que não tem 'NOME COMPLETO', tenta automaticamente a aba 'Ef'
                if (stripos($res, 'NOME COMPLETO') === false && preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $m)) {
                    $gidEf = $this->descobrirGidAbaEfetivo($m[1]);
                    if ($gidEf !== '' && strpos($url, "gid={$gidEf}") === false) {
                        return $this->baixarConteudoRemoto("https://docs.google.com/spreadsheets/d/{$m[1]}/export?format=tsv&gid={$gidEf}", $motivoErro);
                    }
                }
                return $res;
            }
        }
        return '';
    }

    private function parsePlanilhaTexto(string $raw): array {
        $raw = str_replace(["\r\n", "\r"], "\n", trim($raw));
        $linhasBrutas = explode("\n", $raw);
        if (empty($linhasBrutas)) return [];

        // Detecta delimitador (Tabulação do copiar/colar, ponto-e-vírgula ou vírgula do CSV)
        $primeira = $linhasBrutas[0];
        $delim = ",";
        if (substr_count($primeira, "\t") >= 4) {
            $delim = "\t";
        } elseif (substr_count($primeira, ";") > substr_count($primeira, ",")) {
            $delim = ";";
        }

        $resultado = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $raw);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delim)) !== false) {
            if (!empty(array_filter($row, fn($c) => trim((string)$c) !== ''))) {
                $resultado[] = $row;
            }
        }
        fclose($handle);
        return $resultado;
    }

    private function normalizarChaveNome(string $str, bool $removerPreposicoes = false): string {
        $str = mb_strtoupper(trim($str), 'UTF-8');
        $mapa = [
            'Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
            'Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
            'Ç'=>'C','Ñ'=>'N'
        ];
        $str = strtr($str, $mapa);
        if ($removerPreposicoes) {
            $words = preg_split('/\s+/', preg_replace('/[^A-Z\s]/', '', $str));
            $filtradas = array_filter($words, fn($w) => !in_array($w, ['DE','DA','DO','DOS','DAS','E']) && strlen($w) > 0);
            return implode('', $filtradas);
        }
        return preg_replace('/[^A-Z0-9]/', '', $str);
    }

    private function normalizarPosto(string $posto): string {
        $p = mb_strtoupper(trim($posto), 'UTF-8');
        $mapa = [
            'CEL'     => 'Cel',
            'TC'      => 'TC',
            'TEN CEL' => 'TC',
            'TENCEL'  => 'TC',
            'MAJ'     => 'Maj',
            'CAP'     => 'Cap',
            '1º TEN'  => '1º Ten',
            '1 TEN'   => '1º Ten',
            '1ºTEN'   => '1º Ten',
            '2º TEN'  => '2º Ten',
            '2 TEN'   => '2º Ten',
            '2ºTEN'   => '2º Ten',
            'ASP'     => 'Asp',
            'S TEN'   => 'S Ten',
            'STEN'    => 'S Ten',
            'SUB TEN' => 'S Ten',
            'SUBTEN'  => 'S Ten',
            '1º SGT'  => '1º Sgt',
            '1 SGT'   => '1º Sgt',
            '1ºSGT'   => '1º Sgt',
            '2º SGT'  => '2º Sgt',
            '2 SGT'   => '2º Sgt',
            '2ºSGT'   => '2º Sgt',
            '3º SGT'  => '3º Sgt',
            '3 SGT'   => '3º Sgt',
            '3ºSGT'   => '3º Sgt',
            'ALU'     => 'Alu',
            'AL'      => 'Alu',
            'CB'      => 'Cb',
            'SD EP'   => 'Sd EP',
            'SDEP'    => 'Sd EP',
            'SD EV'   => 'Sd EV',
            'SDEV'    => 'Sd EV',
            'SD'      => 'Sd EP',
            'SC'      => 'SC'
        ];
        return $mapa[$p] ?? trim($posto);
    }

    private function normalizarSubunidade(string $su): string {
        // Quando na planilha está anotado "2ªCia>CEEM" ou "1ªCia>CCAp", a Cia de destino é a que vem após '>'
        if (strpos($su, '>') !== false) {
            $partes = explode('>', $su);
            $su = trim((string)end($partes));
        }
        $s = $this->normalizarChaveNome($su);
        if ($s === '') return '';
        if (strpos($s, '1CIA') !== false) return '1ª Cia E Cnst';
        if (strpos($s, '2CIA') !== false) return '2ª Cia E Cnst';
        if (strpos($s, 'CIACAP') !== false || $s === 'CCAP') return 'Cia C Ap';
        if (strpos($s, 'EQP') !== false || strpos($s, 'MNT') !== false || $s === 'CEEM') return 'Cia E Eqp Mnt';
        if ($s === 'CMDO' || $s === 'COMANDO') return 'Cmdo';
        if ($s === 'EM' || strpos($s, 'ESTADOMAIOR') !== false) return 'EM';
        if (strpos($s, 'PMGU') !== false) return 'PMGu';
        if (strpos($s, 'NPOR') !== false) return 'NPOR';
        if (strpos($s, 'PTTC') !== false) return 'PTTC';
        return trim($su);
    }
}
