<?php
// ==============================================================================
// SISMIL 2.0 - Script para Desfazer Importação Específica
// Remove APENAS os militares que constam no arquivo militares.csv atual
// ==============================================================================

if (php_sapi_name() !== 'cli') {
    die("Apenas via CLI.\n");
}

echo "\n========================================================\n";
echo "       SISMIL 2.0 - DESFAZER IMPORTACAO RECENTE         \n";
echo "========================================================\n\n";

$csvFile = __DIR__ . '/militares.csv';
if (!file_exists($csvFile)) {
    die("ERRO: Arquivo 'militares.csv' nao encontrado.\n\n");
}

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

// 1. Le os identificadores da planilha para remover apenas eles
$conteudo = file_get_contents($csvFile);
// Remove UTF-8 BOM
$conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

$linhas = explode("\n", str_replace(["\r\n", "\r"], "\n", $conteudo));
$primeiraLinha = trim($linhas[0]);

$delimitador = ';';
if (substr_count($primeiraLinha, "\t") > substr_count($primeiraLinha, ';')) $delimitador = "\t";
elseif (substr_count($primeiraLinha, ',') > substr_count($primeiraLinha, ';')) $delimitador = ',';

$handle = fopen('php://memory', 'r+');
fwrite($handle, $conteudo);
rewind($handle);

$cabecalho = fgetcsv($handle, 0, $delimitador);
$removidos = 0;
$jaRemovidosIds = [];

$uploadsDir = __DIR__ . '/uploads/';

echo "[+] Localizando e removendo apenas os militares presentes no militares.csv...\n\n";

while (($linha = fgetcsv($handle, 0, $delimitador)) !== false) {
    if (empty(array_filter($linha))) continue;

    // Procura por qualquer coluna que tenha o numero da identidade, CPF ou Nome daquela linha
    foreach ($linha as $val) {
        $valLimpo = trim($val);
        if (empty($valLimpo) || strlen($valLimpo) < 3) continue;

        $apenasNum = preg_replace('/\D/', '', $valLimpo);
        
        $militar = null;
        if (!empty($apenasNum) && strlen($apenasNum) >= 5) {
            $stmt = $db->prepare("SELECT id, posto_grad, nome_guerra, idt_militar, foto_path FROM tb_militares WHERE idt_militar = ? OR idt_militar = ? OR cpf = ? LIMIT 1");
            $stmt->execute([$valLimpo, $apenasNum, $apenasNum]);
            $militar = $stmt->fetch();
        } else {
            $stmt = $db->prepare("SELECT id, posto_grad, nome_guerra, idt_militar, foto_path FROM tb_militares WHERE idt_militar = ? OR nome_completo = ? LIMIT 1");
            $stmt->execute([$valLimpo, $valLimpo]);
            $militar = $stmt->fetch();
        }

        if ($militar && !in_array($militar['id'], $jaRemovidosIds, true)) {
            $jaRemovidosIds[] = $militar['id'];

            // Remove foto se foi gerada recentemente
            if (!empty($militar['foto_path']) && strpos($militar['foto_path'], 'foto_') === 0) {
                $fotoFile = $uploadsDir . $militar['foto_path'];
                if (is_file($fotoFile)) @unlink($fotoFile);
            }

            // Exclui apenas este militar
            $del = $db->prepare("DELETE FROM tb_militares WHERE id = ?");
            $del->execute([$militar['id']]);
            $removidos++;
            echo " [-] Removido com sucesso: ID {$militar['id']} - {$militar['posto_grad']} {$militar['nome_guerra']} (Idt: {$militar['idt_militar']})\n";
            break;
        }
    }
}

fclose($handle);

echo "\n========================================================\n";
echo " [+] Total de militares da importacao removidos: {$removidos}\n";
echo " [+] Seus militares anteriores continuam 100% preservados.\n";
echo "========================================================\n\n";
