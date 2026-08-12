<?php
require_once __DIR__ . '/backend/src/Core/Database.php';

try {
    $db = \Sismil\Core\Database::getInstance();
    
    // Add jantar column
    try {
        $db->exec("ALTER TABLE tb_arranchamento ADD COLUMN jantar TINYINT(1) DEFAULT 0 AFTER almoco");
        echo "Coluna 'jantar' adicionada com sucesso.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Coluna 'jantar' ja existe.\n";
        } else {
            echo "Erro ao adicionar 'jantar': " . $e->getMessage() . "\n";
        }
    }
    
    // Add is_extra column
    try {
        $db->exec("ALTER TABLE tb_arranchamento ADD COLUMN is_extra TINYINT(1) DEFAULT 0 AFTER jantar");
        echo "Coluna 'is_extra' adicionada com sucesso.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Coluna 'is_extra' ja existe.\n";
        } else {
            echo "Erro ao adicionar 'is_extra': " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erro de conexão: " . $e->getMessage() . "\n";
}
