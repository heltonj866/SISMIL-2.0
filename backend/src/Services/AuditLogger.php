<?php
namespace Sismil\Services;

use Sismil\Core\Database;
use PDOException;

/**
 * Serviço responsável por manter a Trilha de Auditoria (Audit Log).
 * Cumpre o requisito de Segurança da Informação (POSIN-EB) registrando ações imutáveis.
 */
class AuditLogger {
    /**
     * Registra uma ação no banco de dados de auditoria.
     *
     * @param string $acao Tipo de ação (ex: 'LOGIN_SUCCESS', 'CREATE_MILITAR').
     * @param string|null $detalhes Informações extras, geralmente formatadas em JSON.
     * @param int|null $usuarioId ID do usuário que executou a ação (se disponível).
     * @param string|null $usuarioNome Nome ou Identificação do usuário.
     * @return void
     */
    public static function log(string $acao, ?string $detalhes = null, ?int $usuarioId = null, ?string $usuarioNome = null): void {
        try {
            $pdo = Database::getInstance();
            
            // Tenta obter os dados da sessão caso não tenham sido fornecidos explicitamente
            if (session_status() === PHP_SESSION_ACTIVE) {
                if ($usuarioId === null) {
                    $usuarioId = $_SESSION['usuario_id'] ?? null;
                }
                if ($usuarioNome === null) {
                    $usuarioNome = $_SESSION['usuario_nome'] ?? null;
                }
            }

            $ipAddress = self::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent';

            $sql = "INSERT INTO tb_logs_auditoria (usuario_id, usuario_nome, acao, detalhes, ip_address, user_agent) 
                    VALUES (:usuario_id, :usuario_nome, :acao, :detalhes, :ip_address, :user_agent)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':usuario_id'   => $usuarioId,
                ':usuario_nome' => $usuarioNome,
                ':acao'         => $acao,
                ':detalhes'     => $detalhes,
                ':ip_address'   => $ipAddress,
                ':user_agent'   => $userAgent
            ]);

        } catch (\Exception $e) {
            // Em sistemas críticos, se o log falhar, o administrador deve ser notificado.
            // Para não quebrar o sistema, logamos no error_log do PHP.
            error_log("FALHA CRÍTICA DE AUDITORIA: Não foi possível gravar log. Ação: $acao. Erro: " . $e->getMessage());
        }
    }

    /**
     * Obtém o endereço IP real do cliente.
     * @return string Endereço IP.
     */
    private static function getClientIp(): string {
        $ip = 'UNKNOWN';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
}
