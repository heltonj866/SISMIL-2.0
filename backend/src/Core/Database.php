<?php
namespace Sismil\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Singleton Pattern para conexão com o Banco de Dados via PDO.
 * Garante uma única instância de conexão por requisição.
 */
class Database {
    private static ?PDO $instance = null;

    /**
     * Construtor privado para impedir instanciação direta.
     */
    private function __construct() {}

    /**
     * Impede a clonagem da classe.
     */
    private function __clone() {}

    /**
     * Retorna a instância única da conexão PDO.
     *
     * @return PDO Instância configurada do PDO.
     * @throws Exception Caso ocorra erro na conexão com o banco.
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            // Requer as configurações caso não estejam carregadas
            if (!defined('APP_ENV_DEV')) {
                require_once __DIR__ . '/../../config.php';
            }

            $isDev = defined('APP_ENV_DEV') && APP_ENV_DEV === true;
            $host = $isDev ? DB_HOST_DEV : DB_HOST_PROD;
            $db   = $isDev ? DB_NAME_DEV : DB_NAME_PROD;
            $user = $isDev ? DB_USER_DEV : DB_USER_PROD;
            $pass = $isDev ? DB_PASS_DEV : DB_PASS_PROD;

            $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
            
            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                // Auto-migration silenciosa para adicionar colunas de arranchamento
                try { self::$instance->exec("ALTER TABLE tb_arranchamento ADD COLUMN jantar TINYINT(1) DEFAULT 0 AFTER almoco"); } catch (PDOException $e) {}
                try { self::$instance->exec("ALTER TABLE tb_arranchamento ADD COLUMN is_extra TINYINT(1) DEFAULT 0 AFTER jantar"); } catch (PDOException $e) {}
                try { self::$instance->exec("ALTER TABLE tb_arranchamento ADD COLUMN quantidade INT DEFAULT 1 AFTER is_extra"); } catch (PDOException $e) {}

                // Auto-migration tb_veiculos — colunas opcionais
                try { self::$instance->exec("ALTER TABLE tb_veiculos ADD COLUMN renavam VARCHAR(20) NULL"); } catch (PDOException $e) {}
                try { self::$instance->exec("ALTER TABLE tb_veiculos ADD COLUMN chassi VARCHAR(50) NULL"); } catch (PDOException $e) {}
                try { self::$instance->exec("ALTER TABLE tb_veiculos ADD COLUMN ano_fabricacao YEAR NULL"); } catch (PDOException $e) {}
                try { self::$instance->exec("ALTER TABLE tb_veiculos ADD COLUMN proprietario VARCHAR(150) NULL"); } catch (PDOException $e) {}
                try { self::$instance->exec("ALTER TABLE tb_veiculos ADD COLUMN cpf_proprietario VARCHAR(20) NULL"); } catch (PDOException $e) {}
                try { self::$instance->exec("ALTER TABLE tb_veiculos ADD COLUMN cnh_proprietario VARCHAR(20) NULL"); } catch (PDOException $e) {}
                try { self::$instance->exec("ALTER TABLE tb_veiculos ADD COLUMN validade_crlv DATE NULL"); } catch (PDOException $e) {}
                
            } catch (PDOException $e) {
                // Nunca expõe os detalhes da conexão PDO para o cliente
                error_log("Database Connection Error: " . $e->getMessage());
                throw new Exception("Falha de comunicação com o banco de dados.");
            }
        }
        return self::$instance;
    }
}
