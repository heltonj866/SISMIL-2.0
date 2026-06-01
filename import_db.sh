#!/bin/bash

# Script para automatizar a importação do banco SISMIL no Servidor Linux

echo "========================================="
echo "   IMPORTADOR DO BANCO SISMIL 2.0        "
echo "========================================="

# Verifica se está rodando como root/sudo
if [ "$EUID" -ne 0 ]; then
  echo "❌ POR FAVOR, RODE ESTE SCRIPT COM SUDO!"
  echo "Execute: sudo bash import_db.sh"
  exit 1
fi

DB_NAME="sismil_db"
SQL_FILE="/var/www/html/sismil/sismil_producao.sql"

if [ ! -f "$SQL_FILE" ]; then
  # Tenta procurar no diretório atual se não achar no caminho padrão
  SQL_FILE="sismil_producao.sql"
fi

if [ ! -f "$SQL_FILE" ]; then
  echo "❌ Arquivo sismil_producao.sql não encontrado!"
  echo "Por favor, coloque o arquivo .sql na mesma pasta deste script."
  exit 1
fi

echo "📦 Criando banco de dados $DB_NAME (se não existir)..."
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

echo "📥 Importando o banco de dados... (Isso pode levar alguns segundos)"
mysql $DB_NAME < "$SQL_FILE"

if [ $? -eq 0 ]; then
  echo "✅ BANCO IMPORTADO COM SUCESSO!"
  echo "O banco $DB_NAME foi populado com os dados de $SQL_FILE"
else
  echo "❌ Ocorreu um erro durante a importação."
fi
