<?php
// ARQUIVO: backend/logout.php
header('Content-Type: application/json');
session_start();

// Limpa os dados da sessão no servidor
$_SESSION = [];

// Invalida o cookie PHPSESSID no navegador do usuário (LOW-02)
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false, // Alterar para true com HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

session_destroy();
echo json_encode(['status' => 'sucesso']);
?>