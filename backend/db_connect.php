<?php
// ARQUIVO: backend/db_connect.php
// Carrega configurações do arquivo local (gitignored)
$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'erro', 'msg' => 'Arquivo config.php não encontrado. Copie config.example.php para config.php e preencha as credenciais.']);
    exit;
}
require_once $config_file;

$em_desenvolvimento = defined('APP_ENV_DEV') ? APP_ENV_DEV : true;

if ($em_desenvolvimento) {
    $host = DB_HOST_DEV;
    $db   = DB_NAME_DEV;
    $user = DB_USER_DEV;
    $pass = DB_PASS_DEV;
} else {
    $host = DB_HOST_PROD;
    $db   = DB_NAME_PROD;
    $user = DB_USER_PROD;
    $pass = DB_PASS_PROD;
}

$charset = 'utf8mb4';
$dsn     = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    // Log completo apenas no servidor; usuário recebe msg genérica
    error_log('[SISMIL] Falha na conexão com o banco: ' . $e->getMessage());
    $msg = $em_desenvolvimento
        ? 'Erro de conexão (dev): ' . $e->getMessage()
        : 'Erro de conexão com o banco de dados. Contate o administrador.';
    echo json_encode(['status' => 'erro', 'msg' => $msg]);
    exit;
}
?>