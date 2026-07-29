<?php
namespace Sismil\Core;

/**
 * Classe utilitária para padronização de respostas JSON (Padrão ePING/REST).
 * Garante que todas as saídas da API sigam a mesma estrutura semântica.
 * Mantém retrocompatibilidade com o frontend (status => 'sucesso'/'erro', msg).
 */
class Response {
    /**
     * Envia uma resposta JSON de sucesso (HTTP 200/201).
     *
     * @param mixed $data Dados a serem enviados (opcional).
     * @param string $message Mensagem de sucesso (opcional).
     * @param int $statusCode Código HTTP (padrão 200).
     * @return void
     */
    public static function json($data = null, string $message = 'Sucesso', int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'status'    => 'sucesso', // Retrocompatibilidade frontend
            'success'   => true,      // Novo padrão
            'msg'       => $message,  // Retrocompatibilidade
            'message'   => $message,  // Novo padrão
            'timestamp' => date('c')
        ];
        
        if ($data !== null) {
            // Mescla os dados no array de resposta para garantir que chaves como 'dados'
            // fiquem na raiz do JSON (retrocompatibilidade com frontend antigo).
            if (is_array($data)) {
                $response = array_merge($response, $data);
            }
            $response['data'] = $data; // Mantém a nova padronização também
        }

        echo json_encode($response);
        exit;
    }

    /**
     * Envia uma resposta JSON de erro (HTTP 4xx/5xx).
     *
     * @param string $message Mensagem de erro a ser exibida para o cliente.
     * @param int $statusCode Código HTTP (padrão 400).
     * @param mixed $details Detalhes adicionais do erro (opcional).
     * @return void
     */
    public static function error(string $message, int $statusCode = 400, $details = null): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            'status'    => 'erro',   // Retrocompatibilidade
            'success'   => false,    // Novo padrão
            'msg'       => $message, // Retrocompatibilidade
            'message'   => $message, // Novo padrão
            'error'     => $details ?? $message,
            'timestamp' => date('c')
        ]);
        exit;
    }
}
