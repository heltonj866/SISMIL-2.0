# SISMIL - Sistema de Gerenciamento Militar

O SISMIL foi recentemente migrado para uma arquitetura moderna utilizando **Vue 3 + Vite** no Frontend, mantendo o robusto backend legado em **PHP**.

## Arquitetura Atual

- **Frontend (SPA)**: Desenvolvido com Vue 3, Pinia (Gerenciamento de Estado) e Vue Router. O código-fonte reside na pasta `frontend/`.
- **Backend (API)**: Arquivos PHP legados isolados na pasta `backend/`. Eles agora funcionam como APIs RESTful que consomem e retornam dados em formato JSON.
- **Banco de Dados**: MySQL gerenciando a base de dados `sismil_db`.
- **Servidor Web**: Apache (XAMPP). O roteamento do SPA é tratado via `.htaccess` na raiz do projeto.

## Como Desenvolver (Frontend)

Para realizar modificações no design ou nas funcionalidades visuais:

1. Acesse o terminal e navegue até a pasta frontend:
   ```bash
   cd frontend
   ```
2. Instale as dependências (caso seja a primeira vez):
   ```bash
   npm install
   ```
3. Inicie o servidor de desenvolvimento:
   ```bash
   npm run dev
   ```
4. O Vite irá iniciar um proxy que encaminha requisições da rota `/backend` para o servidor Apache (`http://localhost/sismil/backend/`).

## Como Fazer o Build (Produção)

Sempre que concluir modificações no Frontend, você precisa compilar os arquivos para produção:

1. Na pasta `frontend/`, rode:
   ```bash
   npm run build
   ```
2. O Vite irá gerar uma pasta chamada `dist/`.
3. Copie o conteúdo de `frontend/dist/` e cole na **raiz do projeto** (`C:\xampp\htdocs\sismil\`), substituindo o arquivo `index.html` e a pasta `assets/`.

> A pasta `backend/` e `uploads/` da raiz NUNCA devem ser excluídas, pois elas contêm os scripts da API e as fotos/pdfs dos militares!

## Estrutura de Pastas na Raiz
- `/backend`: Scripts PHP.
- `/uploads`: Diretório para fotos de perfil e `documentos/` para PDFs de nada consta.
- `/frontend`: Código-fonte Vue 3.
- `/assets`: Gerado pelo build do Vite.
- `.htaccess`: Regras do Apache para o roteamento SPA.
- `index.html`: Arquivo compilado pelo Vite.