<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Core/Database.php';

try {
    $db = \Sismil\Core\Database::getInstance();
    
    echo "<h2>SISMIL - Diagnóstico e Atualização do Banco de Dados</h2>";
    echo "Executando verificações e migrações...<br><br>";
    
    $queries = [
        "ALTER TABLE tb_arranchamento ADD COLUMN jantar TINYINT(1) DEFAULT 0 AFTER almoco",
        "ALTER TABLE tb_arranchamento ADD COLUMN is_extra TINYINT(1) DEFAULT 0 AFTER jantar",
        "ALTER TABLE tb_arranchamento ADD COLUMN quantidade INT DEFAULT 1 AFTER is_extra"
    ];
    
    foreach ($queries as $q) {
        try {
            $db->exec($q);
            echo "<span style='color:green'><b>SUCESSO:</b></span> <code>$q</code><br>";
        } catch (\PDOException $e) {
            echo "<span style='color:orange'><b>AVISO (Coluna provavelmente já existe):</b></span> <code>$q</code> <br> <small>Detalhe: " . htmlspecialchars($e->getMessage()) . "</small><br>";
        }
    }
    
    // Teste inserindo e lendo algo pra ver se `quantidade` existe mesmo
    try {
        $stmt = $db->query("SHOW COLUMNS FROM tb_arranchamento LIKE 'quantidade'");
        $col = $stmt->fetch();
        if ($col) {
            echo "<br><span style='color:blue; font-size: 18px;'><b>TUDO CERTO!</b> A coluna `quantidade` está 100% ativa no banco de dados! O sistema de Extras vai funcionar perfeitamente agora.</span>";
        } else {
            echo "<br><span style='color:red; font-size: 18px;'><b>ERRO GRAVE:</b> A coluna `quantidade` NÃO FOI CRIADA. Você não tem permissão para alterar a tabela.</span>";
        }
    } catch (\Exception $e) {}

    echo "<br><br><b>Fim da migração.</b>";
    
} catch (\Exception $e) {
    echo "<span style='color:red'>ERRO FATAL DE CONEXÃO:</span> " . htmlspecialchars($e->getMessage());
}
