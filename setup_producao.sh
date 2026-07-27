#!/bin/bash
# ==============================================================
# SISMIL 2.0 — Script de Configuração de Produção
# Sistema Operacional: Ubuntu / Debian
# Caminho do sistema: /var/www/html/sismil
#
# USO:
#   sudo bash setup_producao.sh
#
# ATENÇÃO: Execute apenas UMA VEZ no servidor de produção.
# Revise as variáveis da seção "CONFIGURAÇÕES" antes de rodar.
# ==============================================================

set -e  # Para o script imediatamente em caso de erro

# --- CORES PARA OUTPUT ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # Sem cor

log_ok()   { echo -e "${GREEN}  [OK]${NC} $1"; }
log_info() { echo -e "${BLUE}  [..] ${NC} $1"; }
log_warn() { echo -e "${YELLOW}  [!]  ${NC} $1"; }
log_err()  { echo -e "${RED}  [ERRO]${NC} $1"; exit 1; }

echo ""
echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}   SISMIL 2.0 — Setup de Produção              ${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# ==============================================================
# CONFIGURAÇÕES — EDITE ANTES DE EXECUTAR
# ==============================================================

SISMIL_DIR="/var/www/html/sismil"           # Caminho do SISMIL na VM
SISMIL_DB_NAME="sismil_prod"                # Nome do banco de dados de produção
SISMIL_DB_USER="sismil_app"                 # Usuário MySQL exclusivo do SISMIL
SISMIL_DB_PASS=""                           # Senha do usuário MySQL (será solicitada se vazia)
SISMIL_SERVER_IP=""                         # IP ou domínio da intranet (ex: 192.168.1.100)

# ==============================================================

# --- VERIFICAÇÃO: deve ser executado como root ---
if [ "$EUID" -ne 0 ]; then
    log_err "Execute este script como root: sudo bash setup_producao.sh"
fi

# --- VERIFICAÇÃO: diretório do SISMIL existe ---
if [ ! -d "$SISMIL_DIR" ]; then
    log_err "Diretório do SISMIL não encontrado em: $SISMIL_DIR"
fi

# --- SOLICITA A SENHA DO BANCO SE NÃO FOI DEFINIDA ---
if [ -z "$SISMIL_DB_PASS" ]; then
    echo -e "${YELLOW}  Digite a senha para o novo usuário MySQL '${SISMIL_DB_USER}':${NC}"
    read -s SISMIL_DB_PASS
    echo ""
    if [ -z "$SISMIL_DB_PASS" ]; then
        log_err "A senha do banco de dados não pode ser vazia."
    fi
fi

# --- SOLICITA O IP/DOMÍNIO DO SERVIDOR SE NÃO FOI DEFINIDO ---
if [ -z "$SISMIL_SERVER_IP" ]; then
    DETECTED_IP=$(hostname -I | awk '{print $1}')
    echo -e "${YELLOW}  IP detectado da VM: ${DETECTED_IP}${NC}"
    echo -e "${YELLOW}  Pressione ENTER para usar esse IP ou digite outro (ex: 192.168.1.50 ou sismil.intra):${NC}"
    read CUSTOM_IP
    if [ -z "$CUSTOM_IP" ]; then
        SISMIL_SERVER_IP="$DETECTED_IP"
    else
        SISMIL_SERVER_IP="$CUSTOM_IP"
    fi
fi

echo ""
echo -e "${BLUE}Configurações que serão aplicadas:${NC}"
echo "  Diretório SISMIL : $SISMIL_DIR"
echo "  Banco de dados   : $SISMIL_DB_NAME"
echo "  Usuário MySQL    : $SISMIL_DB_USER"
echo "  IP/Domínio       : $SISMIL_SERVER_IP"
echo ""
echo -e "${YELLOW}  Confirma? (s/N):${NC}"
read CONFIRM
if [[ "$CONFIRM" != "s" && "$CONFIRM" != "S" ]]; then
    echo "Operação cancelada."
    exit 0
fi

echo ""

# ==============================================================
# PASSO 1 — display_errors = Off no PHP
# ==============================================================
echo -e "${BLUE}[1/5] Configurando PHP (display_errors)...${NC}"

PHP_INI=$(php --ini 2>/dev/null | grep "Loaded Configuration" | awk '{print $NF}')

if [ -z "$PHP_INI" ] || [ ! -f "$PHP_INI" ]; then
    # Tenta caminhos comuns no Debian/Ubuntu
    for f in /etc/php/*/apache2/php.ini /etc/php/*/fpm/php.ini; do
        if [ -f "$f" ]; then PHP_INI="$f"; break; fi
    done
fi

if [ -f "$PHP_INI" ]; then
    # Faz backup do php.ini original
    cp "$PHP_INI" "${PHP_INI}.bak_sismil"
    log_info "Backup do php.ini salvo em ${PHP_INI}.bak_sismil"

    # Aplica as configurações
    sed -i 's/^display_errors\s*=.*/display_errors = Off/' "$PHP_INI"
    sed -i 's/^log_errors\s*=.*/log_errors = On/' "$PHP_INI"

    # Adiciona error_log se não existir
    if ! grep -q "^error_log" "$PHP_INI"; then
        echo "error_log = /var/log/sismil_php_errors.log" >> "$PHP_INI"
    else
        sed -i 's|^error_log\s*=.*|error_log = /var/log/sismil_php_errors.log|' "$PHP_INI"
    fi

    # Cria o arquivo de log com permissão correta
    touch /var/log/sismil_php_errors.log
    chown www-data:www-data /var/log/sismil_php_errors.log
    chmod 640 /var/log/sismil_php_errors.log

    log_ok "PHP configurado: display_errors=Off, log em /var/log/sismil_php_errors.log"
else
    log_warn "php.ini não encontrado automaticamente. Configure manualmente: display_errors = Off"
fi

# ==============================================================
# PASSO 2 — Usuário MySQL exclusivo para o SISMIL
# ==============================================================
echo -e "${BLUE}[2/5] Criando usuário MySQL exclusivo...${NC}"

mysql -u root -e "
    CREATE USER IF NOT EXISTS '${SISMIL_DB_USER}'@'localhost' IDENTIFIED BY '${SISMIL_DB_PASS}';
    GRANT SELECT, INSERT, UPDATE, DELETE ON \`${SISMIL_DB_NAME}\`.* TO '${SISMIL_DB_USER}'@'localhost';
    FLUSH PRIVILEGES;
" 2>/dev/null

if [ $? -eq 0 ]; then
    log_ok "Usuário '${SISMIL_DB_USER}' criado com acesso restrito ao banco '${SISMIL_DB_NAME}'"
else
    log_warn "Não foi possível criar o usuário MySQL automaticamente."
    log_warn "Crie manualmente: CREATE USER '${SISMIL_DB_USER}'@'localhost' IDENTIFIED BY 'sua_senha';"
    log_warn "                  GRANT SELECT,INSERT,UPDATE,DELETE ON ${SISMIL_DB_NAME}.* TO '${SISMIL_DB_USER}'@'localhost';"
fi

# ==============================================================
# PASSO 3 — Gerar config.php de produção
# ==============================================================
echo -e "${BLUE}[3/5] Gerando config.php de produção...${NC}"

CONFIG_FILE="${SISMIL_DIR}/backend/config.php"

# Faz backup do config.php atual se existir
if [ -f "$CONFIG_FILE" ]; then
    cp "$CONFIG_FILE" "${CONFIG_FILE}.bak_sismil"
    log_info "Backup do config.php salvo em ${CONFIG_FILE}.bak_sismil"
fi

cat > "$CONFIG_FILE" << EOF
<?php
// ============================================================
// ARQUIVO DE CONFIGURAÇÃO DE PRODUÇÃO — SISMIL 2.0
// Gerado automaticamente por setup_producao.sh
// NÃO versionar este arquivo no Git (.gitignore)
// ============================================================

// MODO PRODUÇÃO: cookies seguros, erros ocultos
define('APP_ENV_DEV', false);

// --- BANCO DE DADOS (DESENVOLVIMENTO) ---
define('DB_HOST_DEV', 'localhost');
define('DB_NAME_DEV', 'sismil_db');
define('DB_USER_DEV', 'root');
define('DB_PASS_DEV', '');

// --- BANCO DE DADOS (PRODUÇÃO) ---
define('DB_HOST_PROD', 'localhost');
define('DB_NAME_PROD', '${SISMIL_DB_NAME}');
define('DB_USER_PROD', '${SISMIL_DB_USER}');
define('DB_PASS_PROD', '${SISMIL_DB_PASS}');

// --- ORIGENS CORS PERMITIDAS ---
define('ALLOWED_ORIGINS', [
    'http://${SISMIL_SERVER_IP}',
    'https://${SISMIL_SERVER_IP}',
]);
EOF

chmod 640 "$CONFIG_FILE"
chown www-data:www-data "$CONFIG_FILE"
log_ok "config.php de produção gerado em ${CONFIG_FILE}"

# ==============================================================
# PASSO 4 — Permissões das pastas uploads/
# ==============================================================
echo -e "${BLUE}[4/5] Configurando permissões das pastas uploads/...${NC}"

chown -R www-data:www-data "${SISMIL_DIR}/uploads/"
chmod 755 "${SISMIL_DIR}/uploads/"

for subdir in fotos documentos; do
    if [ -d "${SISMIL_DIR}/uploads/${subdir}" ]; then
        chmod 755 "${SISMIL_DIR}/uploads/${subdir}"
        log_ok "Permissões definidas em uploads/${subdir}/"
    fi
done

# Garante que arquivos já existentes não são executáveis
find "${SISMIL_DIR}/uploads/" -type f -exec chmod 644 {} \;
log_ok "Permissões de uploads configuradas (Apache: leitura/escrita; sem execução)"

# ==============================================================
# PASSO 5 — Reinicia o Apache para aplicar tudo
# ==============================================================
echo -e "${BLUE}[5/5] Reiniciando Apache...${NC}"

if systemctl restart apache2 2>/dev/null; then
    log_ok "Apache reiniciado com sucesso"
else
    log_warn "Não foi possível reiniciar o Apache automaticamente. Rode: sudo systemctl restart apache2"
fi

# ==============================================================
# RELATÓRIO FINAL
# ==============================================================
echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}   Setup concluído!                            ${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo -e "${GREEN}  ✅ display_errors = Off${NC}"
echo -e "${GREEN}  ✅ Usuário MySQL exclusivo criado${NC}"
echo -e "${GREEN}  ✅ config.php de produção gerado${NC}"
echo -e "${GREEN}  ✅ Permissões de uploads configuradas${NC}"
echo -e "${GREEN}  ✅ Apache reiniciado${NC}"
echo ""
echo -e "${YELLOW}  ⚠️  PENDENTE (requer certificado SSL do 5º CTA):${NC}"
echo -e "${YELLOW}     Descomentar o bloco HTTPS no arquivo .htaccess${NC}"
echo -e "${YELLOW}     Localização: ${SISMIL_DIR}/.htaccess${NC}"
echo ""
echo -e "${BLUE}  Acesse o sistema em: http://${SISMIL_SERVER_IP}/sismil${NC}"
echo ""
