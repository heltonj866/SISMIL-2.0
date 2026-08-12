<?php
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/src/Core/Database.php';
$db = Sismil\Core\Database::getInstance();
$stmt = $db->query("SELECT * FROM tb_arranchamento WHERE is_extra = 1 OR subunidade = 'EXTRA'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
