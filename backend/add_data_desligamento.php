<?php
require 'db_connect.php';
try {
    $pdo->exec("ALTER TABLE tb_militares ADD COLUMN data_desligamento DATE DEFAULT NULL");
    echo "Coluna data_desligamento adicionada com sucesso!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Coluna já existe.";
    } else {
        echo "Erro: " . $e->getMessage();
    }
}
?>
