<?php
// ============================================================
// ARQUIVO DE CONFIGURAÇÃO DO BANCO DE DADOS
// ============================================================
// Este é um EXEMPLO. Copie este arquivo para config.php
// e preencha com suas credenciais REAIS.
// O arquivo config.php está no .gitignore e NUNCA deve
// ser comitado no repositório.
// ============================================================

// --- AMBIENTE ---
// true  = Desenvolvimento local (XAMPP)
// false = Servidor de produção do Batalhão
define('APP_ENV_DEV', true);

// --- CONFIGURAÇÕES DE BANCO (DESENVOLVIMENTO) ---
define('DB_HOST_DEV', 'localhost');
define('DB_NAME_DEV', 'sismil_db');
define('DB_USER_DEV', 'root');
define('DB_PASS_DEV', '');

// --- CONFIGURAÇÕES DE BANCO (PRODUÇÃO) ---
define('DB_HOST_PROD', 'localhost');
define('DB_NAME_PROD', 'sismil_prod');
define('DB_USER_PROD', 'usuario_banco_aqui');
define('DB_PASS_PROD', 'senha_banco_aqui');

// --- ORIGENS CORS PERMITIDAS ---
// Adicione aqui os domínios autorizados a acessar o sistema
define('ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:5173',
    'http://127.0.0.1',
    // Adicione o domínio de produção, ex:
    // 'http://intranet.2becnst.eb.mil.br',
]);
