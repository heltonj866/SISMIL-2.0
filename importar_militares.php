<?php
// ==============================================================================
// SISMIL 2.0 - Script de Importação de Militares e Fotos em Lote (V2 - Aprimorada)
// Execução: sudo php /var/www/html/sismil/importar_militares.php
// ==============================================================================

if (php_sapi_name() !== 'cli') {
    die("Este script so pode ser executado via linha de comando (CLI).\n");
}

echo "\n========================================================\n";
echo "       SISMIL 2.0 - IMPORTADOR DE MILITARES & FOTOS     \n";
echo "========================================================\n\n";

$csvFile = __DIR__ . '/militares.csv';
$fotosDir = __DIR__ . '/pasta_fotos';
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

// Opcao para limpar tabela antes se o usuario desejar (passando argumento --reset)
$isReset = in_array('--reset', $argv) || in_array('-r', $argv);
if ($isReset) {
    echo "[!] ATENCAO: Limpando tabela de militares antes de importar (--reset ativado)...\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE tb_militares; SET FOREIGN_KEY_CHECKS = 1;");
    echo "[+] Tabela tb_militares limpa com sucesso.\n\n";
}

// 2. Busca Recursiva de Fotos (varre pasta_fotos e qualquer subpasta interna)
$fotosMap = [];
if (is_dir($fotosDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fotosDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $caminhoCompleto = $item->getPathname();
            $ext = strtolower($item->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

            $nomeSemExt = pathinfo($item->getFilename(), PATHINFO_FILENAME);

            // Mapeia por numeros da identidade
            $apenasNumeros = preg_replace('/\D/', '', $nomeSemExt);
            if (!empty($apenasNumeros)) {
                $fotosMap[$apenasNumeros] = $caminhoCompleto;
                // Guarda tambem sem zeros a esquerda se houver
                $semZeros = ltrim($apenasNumeros, '0');
                if (!empty($semZeros)) {
                    $fotosMap[$semZeros] = $caminhoCompleto;
                }
            }

            // Mapeia pelo nome exato em maiusculo
            $nomeLimpo = strtoupper(trim($nomeSemExt));
            $fotosMap[$nomeLimpo] = $caminhoCompleto;
        }
    }
    echo "[+] Fotos encontradas e mapeadas: " . count($fotosMap) . " entradas.\n";
    if (count($fotosMap) > 0) {
        $exemplo = array_slice(array_keys($fotosMap), 0, 3);
        echo "    Exemplos de chaves de foto: " . implode(', ', $exemplo) . "\n";
    }
} else {
    echo "[!] Aviso: Pasta 'pasta_fotos' nao encontrada.\n";
}
echo "\n";

// 3. Leitura e Limpeza do CSV (remove BOM e detecta delimitador)
$conteudo = file_get_contents($csvFile);

// Remove UTF-8 BOM se presente
$conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

// Converte de ISO-8859-1 para UTF-8 se necessario
if (!mb_check_encoding($conteudo, 'UTF-8')) {
    $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
}

$linhas = explode("\n", str_replace(["\r\n", "\r"], "\n", $conteudo));
$primeiraLinha = trim($linhas[0]);

// Detecta delimitador da primeira linha
$contTab = substr_count($primeiraLinha, "\t");
$contPontoVirgula = substr_count($primeiraLinha, ';');
$contVirgula = substr_count($primeiraLinha, ',');

$delimitador = ';';
if ($contTab > $contPontoVirgula && $contTab > $contVirgula) {
    $delimitador = "\t";
} elseif ($contVirgula > $contPontoVirgula && $contVirgula > $contTab) {
    $delimitador = ',';
}

echo "[+] Delimitador detectado: " . ($delimitador === "\t" ? "TAB (Tabulação)" : ($delimitador === ';' ? "Ponto e Vírgula (;)" : "Vírgula (,)")) . "\n";

$handle = fopen('php://memory', 'r+');
fwrite($handle, $conteudo);
rewind($handle);

$cabecalho = fgetcsv($handle, 0, $delimitador);
if (!$cabecalho) {
    die("ERRO: Nao foi possivel ler o cabecalho do CSV.\n");
}

function normalizarCabecalho($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $str = str_replace(
        ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','-','_','/','\\','.'],
        ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c',' ',' ',' ',' ',' '],
        $str
    );
    $str = preg_replace('/[^a-z0-9\s]/', '', $str);
    return trim(preg_replace('/\s+/', ' ', $str));
}

// Limpa cada coluna do cabecalho
$mapaColunas = [];
$sinonimos = [
    'posto_grad'       => ['pg', 'posto', 'posto grad', 'graduacao', 'posto graduacao', 'p g', 'grad'],
    'numero'           => ['nr', 'numero', 'n', 'num', 'numero militar'],
    'nome_guerra'      => ['nome de guerra', 'nome guerra', 'guerra'],
    'nome_completo'    => ['nome completo', 'nome', 'militar', 'nome militar'],
    'dt_nascimento'    => ['dt nasc', 'dt nascimento', 'data nascimento', 'nascimento', 'data nasc'],
    'dt_praca'         => ['dt praca', 'data praca', 'praca', 'dt incorporacao', 'incorporacao'],
    'idt_militar'      => ['idt mil', 'identidade', 'idt', 'idt militar', 'identidade militar', 'rg'],
    'cpf'              => ['cpf', 'cic'],
    'nome_pai'         => ['pai', 'nome pai', 'filiacao pai'],
    'nome_mae'         => ['mae', 'nome mae', 'filiacao mae'],
    'endereco'         => ['endereco', 'rua', 'logradouro'],
    'cep'              => ['cep'],
    'email'            => ['email', 'e mail', 'correio eletronico'],
    'celular_princ'    => ['telefone', 'tel', 'celular', 'celular princ', 'contato', 'celular principal'],
    'subunidade'       => ['subunidade', 'su', 'cia', 'companhia', 'sub unidade'],
    'pelotao'          => ['pelotao', 'pel'],
    'secao'            => ['secao', 'sec'],
    'qmg'              => ['qmg', 'qualificacao', 'arma'],
    'tipo_sanguineo'   => ['tipo sanguineo', 'sangue', 'fator rh'],
    'cat_cnh'          => ['cnh', 'cat cnh', 'categoria cnh'],
    'validade_cnh'     => ['validade cnh', 'vencimento cnh']
];

foreach ($cabecalho as $idx => $colOriginal) {
    $colLimpa = normalizarCabecalho($colOriginal);
    
    // Ignora GPT expressamente
    if ($colLimpa === 'gpt') continue;

    foreach ($sinonimos as $campoBanco => $listaSinonimos) {
        if (in_array($colLimpa, $listaSinonimos, true)) {
            $mapaColunas[$campoBanco] = $idx;
            break;
        }
    }
}

echo "[+] Colunas mapeadas com sucesso:\n";
foreach ($mapaColunas as $campo => $idx) {
    echo "    - {$campo} => [Coluna {$idx}] '" . ($cabecalho[$idx] ?? '') . "'\n";
}
echo "\n";

function formatarDataBanco($valor) {
    if (empty($valor)) return null;
    $v = trim($valor);
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $v, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2})$/', $v, $m)) {
        $ano = (int)$m[3];
        $anoComp = ($ano > 50) ? (1900 + $ano) : (2000 + $ano);
        return sprintf('%04d-%02d-%02d', $anoComp, $m[2], $m[1]);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v;
    }
    if (preg_match('/^\d{4}$/', $v)) {
        return $v . '-01-01';
    }
    if (is_numeric($v) && (int)$v > 20000 && (int)$v < 60000) {
        $timestamp = ((int)$v - 25569) * 86400;
        return gmdate('Y-m-d', $timestamp);
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
    $idtMilitar     = trim($getVal('idt_militar'));
    
    // Tratamento de CPF com zeros a esquerda cortados pelo Excel
    $cpfRaw         = preg_replace('/\D/', '', $getVal('cpf'));
    $cpf            = '';
    $cpfPad         = '';
    if (!empty($cpfRaw)) {
        $cpfPad = str_pad($cpfRaw, 11, '0', STR_PAD_LEFT);
        $cpf = sprintf('%s.%s.%s-%s', substr($cpfPad, 0, 3), substr($cpfPad, 3, 3), substr($cpfPad, 6, 3), substr($cpfPad, 9, 2));
    }

    $subunidade     = strtoupper($getVal('subunidade'));
    $pelotao        = strtoupper($getVal('pelotao'));
    $secao          = strtoupper($getVal('secao'));
    $qmg            = strtoupper($getVal('qmg'));
    $dtNascimento   = formatarDataBanco($getVal('dt_nascimento'));
    $dtPraca        = formatarDataBanco($getVal('dt_praca'));
    $nomePai        = strtoupper($getVal('nome_pai'));
    $nomeMae        = strtoupper($getVal('nome_mae'));
    $endereco       = $getVal('endereco');

    // Tratamento de CEP com zeros a esquerda
    $cepRaw         = preg_replace('/\D/', '', $getVal('cep'));
    $cep            = '';
    if (!empty($cepRaw)) {
        $cepPad = str_pad($cepRaw, 8, '0', STR_PAD_LEFT);
        $cep = sprintf('%s-%s', substr($cepPad, 0, 5), substr($cepPad, 5, 3));
    }

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

    // 4. Busca da Foto correspondente
    $fotoPath = null;
    $idtLimpa = preg_replace('/\D/', '', $idtMilitar);
    $idtSemZero = ltrim($idtLimpa, '0');

    $origemFoto = null;
    if (!empty($idtLimpa) && isset($fotosMap[$idtLimpa])) {
        $origemFoto = $fotosMap[$idtLimpa];
    } elseif (!empty($idtSemZero) && isset($fotosMap[$idtSemZero])) {
        $origemFoto = $fotosMap[$idtSemZero];
    } elseif (!empty($idtMilitar) && isset($fotosMap[strtoupper($idtMilitar)])) {
        $origemFoto = $fotosMap[strtoupper($idtMilitar)];
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
        // 5. Verifica se ja existe por IDENTIDADE, CPF ou NOME DE GUERRA
        $militarId = null;
        if (!empty($idtMilitar) && strlen($idtMilitar) >= 3) {
            $stmt = $db->prepare("SELECT id, foto_path FROM tb_militares WHERE idt_militar = ? LIMIT 1");
            $stmt->execute([$idtMilitar]);
            $res = $stmt->fetch();
            if ($res) {
                $militarId = (int)$res['id'];
                if (!$fotoPath) $fotoPath = $res['foto_path'];
            }
        }
        if (!$militarId && !empty($cpfRaw)) {
            $stmt = $db->prepare("SELECT id, foto_path FROM tb_militares WHERE cpf = ? OR cpf = ? OR cpf = ? LIMIT 1");
            $stmt->execute([$cpf, $cpfRaw, $cpfPad]);
            $res = $stmt->fetch();
            if ($res) {
                $militarId = (int)$res['id'];
                if (!$fotoPath) $fotoPath = $res['foto_path'];
            }
        }
        if (!$militarId && !empty($nomeGuerra) && !empty($postoGrad)) {
            $stmt = $db->prepare("SELECT id, foto_path FROM tb_militares WHERE nome_guerra = ? AND posto_grad = ? LIMIT 1");
            $stmt->execute([$nomeGuerra, $postoGrad]);
            $res = $stmt->fetch();
            if ($res) {
                $militarId = (int)$res['id'];
                if (!$fotoPath) $fotoPath = $res['foto_path'];
            }
        }

        if ($militarId) {
            // UPDATE: Atualiza os dados que faltavam (Praça, Mãe, Email, Pai, Endereço, CPF corrigido com 11 digitos)
            $sql = "UPDATE tb_militares SET 
                cpf = COALESCE(NULLIF(:cpf, ''), cpf),
                posto_grad = COALESCE(NULLIF(:posto, ''), posto_grad),
                numero = COALESCE(NULLIF(:numero, ''), numero),
                nome_guerra = COALESCE(NULLIF(:nome_guerra, ''), nome_guerra),
                nome_completo = COALESCE(NULLIF(:nome_completo, ''), nome_completo),
                dt_nascimento = COALESCE(:dt_nascimento, dt_nascimento),
                dt_praca = COALESCE(:dt_praca, dt_praca),
                nome_pai = COALESCE(NULLIF(:nome_pai, ''), nome_pai),
                nome_mae = COALESCE(NULLIF(:nome_mae, ''), nome_mae),
                email = COALESCE(NULLIF(:email, ''), email),
                celular_princ = COALESCE(NULLIF(:celular_princ, ''), celular_princ),
                endereco = COALESCE(NULLIF(:endereco, ''), endereco),
                cep = COALESCE(NULLIF(:cep, ''), cep),
                foto_path = COALESCE(:foto_path, foto_path),
                status_ativo = 1
            WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':cpf'            => $cpf,
                ':posto'          => $postoGrad,
                ':numero'         => $numero,
                ':nome_guerra'    => $nomeGuerra,
                ':nome_completo'  => $nomeCompleto,
                ':dt_nascimento'  => $dtNascimento,
                ':dt_praca'       => $dtPraca,
                ':nome_pai'       => $nomePai,
                ':nome_mae'       => $nomeMae,
                ':email'          => $email,
                ':celular_princ'  => $celularPrinc,
                ':endereco'       => $endereco,
                ':cep'            => $cep,
                ':foto_path'      => $fotoPath,
                ':id'             => $militarId
            ]);
            $atualizados++;
            echo " [ATUALIZADO] {$postoGrad} {$nomeGuerra} (Idt: {$idtMilitar} | CPF: {$cpf})" . ($fotoPath ? " [📸 FOTO OK]" : "") . "\n";
        } else {
            // INSERT
            $sql = "INSERT INTO tb_militares (
                cpf, posto_grad, numero, nome_guerra, nome_completo,
                nome_pai, nome_mae, email, dt_nascimento, dt_praca, idt_militar,
                celular_princ, cep, endereco, foto_path, status_ativo
            ) VALUES (
                :cpf, :posto_grad, :numero, :nome_guerra, :nome_completo,
                :nome_pai, :nome_mae, :email, :dt_nascimento, :dt_praca, :idt_militar,
                :celular_princ, :cep, :endereco, :foto_path, 1
            )";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':cpf'            => $cpf ?: null,
                ':posto_grad'     => $postoGrad,
                ':numero'         => $numero ?: null,
                ':nome_guerra'    => $nomeGuerra,
                ':nome_completo'  => $nomeCompleto,
                ':nome_pai'       => $nomePai ?: null,
                ':nome_mae'       => $nomeMae ?: null,
                ':email'          => $email ?: null,
                ':dt_nascimento'  => $dtNascimento,
                ':dt_praca'       => $dtPraca,
                ':idt_militar'    => $idtMilitar ?: null,
                ':celular_princ'  => $celularPrinc ?: null,
                ':cep'            => $cep ?: null,
                ':endereco'       => $endereco ?: null,
                ':foto_path'      => $fotoPath
            ]);
            $inseridos++;
            echo " [INSERIDO]   {$postoGrad} {$nomeGuerra} (Idt: {$idtMilitar})" . ($fotoPath ? " [📸 FOTO OK]" : "") . "\n";
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
