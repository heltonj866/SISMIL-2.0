<?php
// Script de Migracao das Colunas de Veiculos
try {
    $pdo = null;
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=sismil_db;charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (Exception $e) {
        require_once __DIR__ . '/config.php';
        $user = defined('DB_USER_PROD') ? DB_USER_PROD : 'sismil_app';
        $pass = defined('DB_PASS_PROD') ? DB_PASS_PROD : '';
        $pdo = new PDO("mysql:host=localhost;dbname=sismil_db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }

    $colunas = [
        "ALTER TABLE tb_veiculos ADD COLUMN proprietario_nome VARCHAR(100) NULL AFTER cor",
        "ALTER TABLE tb_veiculos ADD COLUMN proprietario_parentesco VARCHAR(50) NULL AFTER proprietario_nome",
        "ALTER TABLE tb_veiculos ADD COLUMN pdf_comprovante_vinculo VARCHAR(255) NULL AFTER pdf_veiculo"
    ];

    foreach ($colunas as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Ignora se coluna ja existir
        }
    }

    echo "\n==============================================\n";
    echo " SUCESSO: Banco de dados atualizado com sucesso!\n";
    echo "==============================================\n\n";

} catch (Exception $e) {
    echo "\nERRO: " . $e->getMessage() . "\n\n";
}
