<?php
// ==============================================================================
// SISMIL 2.0 - Script de Importação de Militares e Fotos em Lote
// Execução: sudo php /var/www/html/sismil/importar_militares.php
// ==============================================================================

if (php_sapi_name() !== 'cli') {
    die("Este script so pode ser executado via linha de comando (CLI).\n");
}

echo "\n========================================================\n";
echo "       SISMIL 2.0 - IMPORTADOR DE MILITARES & FOTOS     \n";
echo "========================================================\n\n";

$csvFile = __DIR__ . '/militares.csv';
$fotosDir = __DIR__ . '/pasta_fotos/';
$uploadsDir = __DIR__ . '/uploads/';

if (!file_exists($csvFile)) {
    die("ERRO: Arquivo 'militares.csv' nao encontrado em: {$csvFile}\n\n");
}

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// 1. Conexao com o Banco de Dados
require_once __DIR__ . '/backend/src/Core/Database.php';
$db = null;
try {
    $db = \Sismil\Core\Database::getInstance();
} catch (Exception $e) {
    // Tenta conexao direta local
    try {
        require_once __DIR__ . '/backend/config.php';
        $user = defined('DB_USER_PROD') ? DB_USER_PROD : 'sismil_app';
        $pass = defined('DB_PASS_PROD') ? DB_PASS_PROD : '';
        $dbname = defined('DB_NAME_PROD') ? DB_NAME_PROD : 'sismil_db';
        $db = new PDO("mysql:host=localhost;dbname={$dbname};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Exception $ex) {
        die("ERRO de conexao com o banco: " . $ex->getMessage() . "\n\n");
    }
}

// 2. Mapeamento de Fotos Disponiveis na pasta_fotos
$fotosMap = [];
if (is_dir($fotosDir)) {
    $arquivos = scandir($fotosDir);
    foreach ($arquivos as $arq) {
        if ($arq === '.' || $arq === '..') continue;
        $caminhoCompleto = $fotosDir . $arq;
        if (is_file($caminhoCompleto)) {
            $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

            $nomeSemExt = pathinfo($arq, PATHINFO_FILENAME);

            // 1. Guarda pelo numero puro da identidade (ex: "0123456789.JPG" -> "0123456789")
            $apenasNumeros = preg_replace('/\D/', '', $nomeSemExt);
            if (!empty($apenasNumeros)) {
                $fotosMap[$apenasNumeros] = $caminhoCompleto;
            }

            // 2. Guarda pelo texto exato sem extensao
            $nomeLimpo = strtoupper(trim($nomeSemExt));
            $fotosMap[$nomeLimpo] = $caminhoCompleto;
        }
    }
    echo "[+] Fotos reconhecidas na pasta_fotos: " . count($fotosMap) . " arquivos.\n";
} else {
    echo "[!] Aviso: Pasta 'pasta_fotos/' nao encontrada. Importando apenas dados da planilha.\n";
}

// 3. Leitura e Deteccao de Delimitador do CSV
$conteudo = file_get_contents($csvFile);
// Converte de ISO-8859-1 para UTF-8 se necessario
if (!mb_check_encoding($conteudo, 'UTF-8')) {
    $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
}

$linhas = explode("\n", str_replace(["\r\n", "\r"], "\n", $conteudo));
$primeiraLinha = trim($linhas[0]);

$delimitador = ';';
if (substr_count($primeiraLinha, "\t") >= substr_count($primeiraLinha, ';') && substr_count($primeiraLinha, "\t") > 0) {
    $delimitador = "\t";
} elseif (substr_count($primeiraLinha, ',') > substr_count($primeiraLinha, ';')) {
    $delimitador = ',';
}

$handle = fopen('php://memory', 'r+');
fwrite($handle, $conteudo);
rewind($handle);

$cabecalho = fgetcsv($handle, 0, $delimitador);
if (!$cabecalho) {
    die("ERRO: Nao foi possivel ler o cabecalho do CSV.\n");
}

// Normaliza cabecalho com suporte exato aos campos informados
$mapaColunas = [];
$sinonimos = [
    'posto_grad'       => ['pg', 'posto', 'posto/grad', 'posto_grad', 'graduacao', 'posto_graduacao', 'posto/graduação', 'p/g', 'grad'],
    'subunidade'       => ['subunidade', 'su', 'cia', 'companhia', 'sub_unidade'],
    'numero'           => ['nr', 'numero', 'nº', 'num', 'numero_militar', 'n'],
    'nome_guerra'      => ['nome de guerra', 'nome_guerra', 'nome guerra', 'guerra', 'nome_de_guerra'],
    'nome_completo'    => ['nome completo', 'nome_completo', 'nome', 'militar', 'nome_militar'],
    'dt_nascimento'    => ['dt nasc', 'dt_nascimento', 'data nascimento', 'nascimento', 'data_nasc', 'dt_nasc', 'data_nascimento'],
    'dt_praca'         => ['dt praca', 'dt praça', 'dt_praca', 'data praca', 'data praça', 'praca', 'praça', 'dt_incorporacao', 'incorporacao'],
    'idt_militar'      => ['idt mil', 'identidade', 'idt', 'idt militar', 'idt_militar', 'identidade militar', 'rg', 'identidade_militar'],
    'cpf'              => ['cpf', 'cic'],
    'nome_pai'         => ['pai', 'nome pai', 'nome_pai', 'filiacao_pai'],
    'nome_mae'         => ['mae', 'mãe', 'nome mae', 'nome mãe', 'nome_mae', 'filiacao_mae'],
    'endereco'         => ['endereco', 'endereço', 'rua', 'logradouro'],
    'cep'              => ['cep'],
    'email'            => ['e-mail', 'email', 'e_mail', 'correio_eletronico'],
    'celular_princ'    => ['telefone', 'tel', 'celular', 'celular_princ', 'contato', 'celular_principal'],
    'pelotao'          => ['pelotao', 'pelotão', 'pel'],
    'secao'            => ['secao', 'seção', 'sec'],
    'qmg'              => ['qmg', 'qualificacao', 'arma'],
    'tipo_sanguineo'   => ['tipo_sanguineo', 'sangue', 'fator rh', 'tipo sanguineo'],
    'cat_cnh'          => ['cnh', 'cat_cnh', 'categoria cnh'],
    'validade_cnh'     => ['validade_cnh', 'validade cnh', 'vencimento cnh']
];

foreach ($cabecalho as $idx => $colOriginal) {
    $colLimpa = strtolower(trim(preg_replace('/[^a-zA-Z0-9_\/]/', ' ', $colOriginal)));
    $colLimpa = preg_replace('/\s+/', ' ', $colLimpa);
    
    foreach ($sinonimos as $campoBanco => $listaSinonimos) {
        if (in_array($colLimpa, $listaSinonimos, true)) {
            $mapaColunas[$campoBanco] = $idx;
            break;
        }
    }
}

echo "[+] Mapeamento de colunas detectado com sucesso:\n";
foreach ($mapaColunas as $campo => $idx) {
    echo "    - {$campo} => Coluna: " . ($cabecalho[$idx] ?? $idx) . "\n";
}
echo "\n";

if (!isset($mapaColunas['nome_guerra']) && !isset($mapaColunas['nome_completo'])) {
    die("ERRO: Nao foi encontrada nenhuma coluna de Nome ou Nome de Guerra no CSV.\n");
}

// Funcao auxiliar para formatar datas (DD/MM/AAAA -> AAAA-MM-DD)
function formatarDataBanco($valor) {
    if (empty($valor)) return null;
    $v = trim($valor);
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $v, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v;
    }
    return null;
}

$inseridos = 0;
$atualizados = 0;
$fotosVinculadas = 0;
$erros = 0;
$linhaNum = 1;

while (($dadosLinha = fgetcsv($handle, 0, $delimitador)) !== false) {
    $linhaNum++;
    if (empty(array_filter($dadosLinha))) continue;

    $getVal = function($campo) use ($mapaColunas, $dadosLinha) {
        if (isset($mapaColunas[$campo]) && isset($dadosLinha[$mapaColunas[$campo]])) {
            return trim($dadosLinha[$mapaColunas[$campo]]);
        }
        return '';
    };

    $postoGrad      = strtoupper($getVal('posto_grad'));
    $nomeGuerra     = strtoupper($getVal('nome_guerra'));
    $nomeCompleto   = strtoupper($getVal('nome_completo'));
    $numero         = $getVal('numero');
    $idtMilitar     = preg_replace('/\s+/', '', $getVal('idt_militar'));
    $cpf            = preg_replace('/\D/', '', $getVal('cpf'));
    $subunidade     = strtoupper($getVal('subunidade'));
    $pelotao        = strtoupper($getVal('pelotao'));
    $secao          = strtoupper($getVal('secao'));
    $qmg            = strtoupper($getVal('qmg'));
    $dtNascimento   = formatarDataBanco($getVal('dt_nascimento'));
    $dtPraca        = formatarDataBanco($getVal('dt_praca'));
    $nomePai        = strtoupper($getVal('nome_pai'));
    $nomeMae        = strtoupper($getVal('nome_mae'));
    $endereco       = $getVal('endereco');
    $cep            = preg_replace('/\D/', '', $getVal('cep'));
    $email          = strtolower($getVal('email'));
    $celularPrinc   = $getVal('celular_princ');
    $tipoSanguineo  = strtoupper($getVal('tipo_sanguineo'));
    $catCnh         = strtoupper($getVal('cat_cnh'));
    $validadeCnh    = formatarDataBanco($getVal('validade_cnh'));

    if (empty($nomeGuerra) && !empty($nomeCompleto)) {
        $partes = explode(' ', $nomeCompleto);
        $nomeGuerra = end($partes);
    }
    if (empty($nomeCompleto) && !empty($nomeGuerra)) {
        $nomeCompleto = $nomeGuerra;
    }

    if (empty($postoGrad)) $postoGrad = 'SD EP';

    // 4. Busca da Foto correspondente por Identidade (ex: 0123456789.JPG)
    $fotoPath = null;
    $idtNumeros = preg_replace('/\D/', '', $idtMilitar);
    $cpfNumeros = preg_replace('/\D/', '', $cpf);

    $origemFoto = null;
    if (!empty($idtNumeros) && isset($fotosMap[$idtNumeros])) {
        $origemFoto = $fotosMap[$idtNumeros];
    } elseif (!empty($idtMilitar) && isset($fotosMap[strtoupper($idtMilitar)])) {
        $origemFoto = $fotosMap[strtoupper($idtMilitar)];
    } elseif (!empty($cpfNumeros) && isset($fotosMap[$cpfNumeros])) {
        $origemFoto = $fotosMap[$cpfNumeros];
    } elseif (!empty($nomeGuerra) && isset($fotosMap[$nomeGuerra])) {
        $origemFoto = $fotosMap[$nomeGuerra];
    }

    if ($origemFoto && file_exists($origemFoto)) {
        $ext = strtolower(pathinfo($origemFoto, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $novoNomeFoto = 'foto_' . bin2hex(random_bytes(6)) . '.' . ($ext === 'png' ? 'png' : 'jpg');
            $destino = $uploadsDir . $novoNomeFoto;
            if (copy($origemFoto, $destino)) {
                chmod($destino, 0644);
                $fotoPath = $novoNomeFoto;
                $fotosVinculadas++;
            }
        }
    }

    try {
        // 5. Verifica se o militar ja existe (por IDT, CPF ou Nome+Posto)
        $militarId = null;
        if (!empty($idtMilitar)) {
            $stmt = $db->prepare("SELECT id, foto_path FROM tb_militares WHERE idt_militar = ? LIMIT 1");
            $stmt->execute([$idtMilitar]);
            $res = $stmt->fetch();
            if ($res) { $militarId = (int)$res['id']; if(!$fotoPath) $fotoPath = $res['foto_path']; }
        }
        if (!$militarId && !empty($cpf)) {
            $stmt = $db->prepare("SELECT id, foto_path FROM tb_militares WHERE cpf = ? LIMIT 1");
            $stmt->execute([$cpf]);
            $res = $stmt->fetch();
            if ($res) { $militarId = (int)$res['id']; if(!$fotoPath) $fotoPath = $res['foto_path']; }
        }
        if (!$militarId && !empty($nomeGuerra) && !empty($postoGrad) && !empty($subunidade)) {
            $stmt = $db->prepare("SELECT id, foto_path FROM tb_militares WHERE nome_guerra = ? AND posto_grad = ? AND subunidade = ? LIMIT 1");
            $stmt->execute([$nomeGuerra, $postoGrad, $subunidade]);
            $res = $stmt->fetch();
            if ($res) { $militarId = (int)$res['id']; if(!$fotoPath) $fotoPath = $res['foto_path']; }
        }

        if ($militarId) {
            // UPDATE
            $sql = "UPDATE tb_militares SET 
                posto_grad = COALESCE(NULLIF(:posto, ''), posto_grad),
                numero = COALESCE(NULLIF(:numero, ''), numero),
                nome_guerra = COALESCE(NULLIF(:nome_guerra, ''), nome_guerra),
                nome_completo = COALESCE(NULLIF(:nome_completo, ''), nome_completo),
                subunidade = COALESCE(NULLIF(:subunidade, ''), subunidade),
                pelotao = COALESCE(NULLIF(:pelotao, ''), pelotao),
                secao = COALESCE(NULLIF(:secao, ''), secao),
                qmg = COALESCE(NULLIF(:qmg, ''), qmg),
                dt_nascimento = COALESCE(:dt_nascimento, dt_nascimento),
                dt_praca = COALESCE(:dt_praca, dt_praca),
                nome_pai = COALESCE(NULLIF(:nome_pai, ''), nome_pai),
                nome_mae = COALESCE(NULLIF(:nome_mae, ''), nome_mae),
                email = COALESCE(NULLIF(:email, ''), email),
                celular_princ = COALESCE(NULLIF(:celular_princ, ''), celular_princ),
                endereco = COALESCE(NULLIF(:endereco, ''), endereco),
                cep = COALESCE(NULLIF(:cep, ''), cep),
                tipo_sanguineo = COALESCE(NULLIF(:tipo_sanguineo, ''), tipo_sanguineo),
                cat_cnh = COALESCE(NULLIF(:cat_cnh, ''), cat_cnh),
                validade_cnh = COALESCE(:validade_cnh, validade_cnh),
                foto_path = COALESCE(:foto_path, foto_path),
                status_ativo = 1
            WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':posto'          => $postoGrad,
                ':numero'         => $numero,
                ':nome_guerra'    => $nomeGuerra,
                ':nome_completo'  => $nomeCompleto,
                ':subunidade'     => $subunidade,
                ':pelotao'        => $pelotao,
                ':secao'          => $secao,
                ':qmg'            => $qmg,
                ':dt_nascimento'  => $dtNascimento,
                ':dt_praca'       => $dtPraca,
                ':nome_pai'       => $nomePai,
                ':nome_mae'       => $nomeMae,
                ':email'          => $email,
                ':celular_princ'  => $celularPrinc,
                ':endereco'       => $endereco,
                ':cep'            => $cep,
                ':tipo_sanguineo' => $tipoSanguineo,
                ':cat_cnh'        => $catCnh,
                ':validade_cnh'   => $validadeCnh,
                ':foto_path'      => $fotoPath,
                ':id'             => $militarId
            ]);
            $atualizados++;
            echo " [ATUALIZADO] {$postoGrad} {$nomeGuerra} (ID {$militarId})" . ($fotoPath ? " [📸 FOTO VINCULADA]" : "") . "\n";
        } else {
            // INSERT
            $sql = "INSERT INTO tb_militares (
                cpf, posto_grad, numero, nome_guerra, subunidade, pelotao, secao, nome_completo,
                nome_pai, nome_mae, email, qmg, dt_nascimento, tipo_sanguineo, dt_praca, idt_militar,
                celular_princ, cep, endereco, cat_cnh, validade_cnh, foto_path, status_ativo
            ) VALUES (
                :cpf, :posto_grad, :numero, :nome_guerra, :subunidade, :pelotao, :secao, :nome_completo,
                :nome_pai, :nome_mae, :email, :qmg, :dt_nascimento, :tipo_sanguineo, :dt_praca, :idt_militar,
                :celular_princ, :cep, :endereco, :cat_cnh, :validade_cnh, :foto_path, 1
            )";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':cpf'            => $cpf ?: null,
                ':posto_grad'     => $postoGrad,
                ':numero'         => $numero ?: null,
                ':nome_guerra'    => $nomeGuerra,
                ':subunidade'     => $subunidade ?: null,
                ':pelotao'        => $pelotao ?: null,
                ':secao'          => $secao ?: null,
                ':nome_completo'  => $nomeCompleto,
                ':nome_pai'       => $nomePai ?: null,
                ':nome_mae'       => $nomeMae ?: null,
                ':email'          => $email ?: null,
                ':qmg'            => $qmg ?: null,
                ':dt_nascimento'  => $dtNascimento,
                ':tipo_sanguineo' => $tipoSanguineo ?: null,
                ':dt_praca'       => $dtPraca,
                ':idt_militar'    => $idtMilitar ?: null,
                ':celular_princ'  => $celularPrinc ?: null,
                ':cep'            => $cep ?: null,
                ':endereco'       => $endereco ?: null,
                ':cat_cnh'        => $catCnh ?: null,
                ':validade_cnh'   => $validadeCnh,
                ':foto_path'      => $fotoPath
            ]);
            $inseridos++;
            echo " [INSERIDO]   {$postoGrad} {$nomeGuerra}" . ($fotoPath ? " [📸 FOTO VINCULADA]" : "") . "\n";
        }
    } catch (Exception $e) {
        $erros++;
        echo " [ERRO Linha {$linhaNum}] {$postoGrad} {$nomeGuerra}: " . $e->getMessage() . "\n";
    }
}

fclose($handle);

echo "\n========================================================\n";
echo "              RELATORIO DE IMPORTACAO                   \n";
echo "========================================================\n";
echo " [+] Novos Militares Inseridos : {$inseridos}\n";
echo " [+] Militares Atualizados     : {$atualizados}\n";
echo " [+] Fotos Vinculadas          : {$fotosVinculadas}\n";
if ($erros > 0) {
    echo " [-] Erros encontrados         : {$erros}\n";
}
echo "========================================================\n\n";
