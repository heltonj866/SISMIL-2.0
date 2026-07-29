<?php
namespace Sismil\Core;

/**
 * Classe Request
 * Abstrai a requisição HTTP (GET, POST, JSON) centralizando a sanitização (anti-XSS).
 */
class Request {
    private array $params = [];
    private array $data = [];
    private string $method;
    private string $uri;
    
    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        
        // Sanitiza $_GET
        foreach ($_GET as $key => $value) {
            $this->params[$key] = $this->sanitize($value);
        }
        
        // Lê o corpo da requisição (JSON ou POST)
        if ($this->method === 'POST' || $this->method === 'PUT') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (stripos($contentType, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    foreach ($json as $k => $v) {
                        $this->data[$k] = $this->sanitize($v);
                    }
                }
            } else {
                // Form-data
                foreach ($_POST as $k => $v) {
                    $this->data[$k] = $this->sanitize($v);
                }
            }
        }
    }

    private function sanitize($value) {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        if (is_string($value)) {
            // Anti-XSS básico
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getUri(): string {
        return $this->uri;
    }

    public function getQuery(string $key = null, $default = null) {
        if ($key === null) return $this->params;
        return $this->params[$key] ?? $default;
    }

    public function getBody(string $key = null, $default = null) {
        if ($key === null) return $this->data;
        return $this->data[$key] ?? $default;
    }

    public function getFile(string $key) {
        return $_FILES[$key] ?? null;
    }
}
