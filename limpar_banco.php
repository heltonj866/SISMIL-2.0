<?php
// Script para limpar os militares inseridos incorretamente
if (php_sapi_name() !== 'cli') {
    die("Apenas via CLI.\n");
}

require_once __DIR__ . '/backend/src/Core/Database.php';
try {
    $db = \Sismil\Core\Database::getInstance();
    $db->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE tb_militares; SET FOREIGN_KEY_CHECKS = 1;");
    
    // Limpa fotos temporarias geradas no uploads
    $uploads = __DIR__ . '/uploads/';
    $fotos = glob($uploads . 'foto_*');
    if ($fotos) {
        foreach ($fotos as $f) {
            if (is_file($f)) unlink($f);
        }
    }

    echo "\n========================================================\n";
    echo " [+] SUCESSO: Tabela tb_militares limpa com sucesso!\n";
    echo " [+] Fotos temporarias removidas da pasta uploads.\n";
    echo "========================================================\n\n";
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
