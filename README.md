# 💊 FarmaVida – Sistema de Farmácia Online

Sistema de e-commerce completo para farmácias e drogarias, desenvolvido em PHP com MySQL.

---

## 🚀 Instalação Rápida

### 1. Requisitos
- PHP 7.4+ ou 8.x
- MySQL 5.7+ / MariaDB 10.3+
- Apache/Nginx com mod_rewrite

### 2. Banco de Dados
```sql
-- No phpMyAdmin ou MySQL CLI, execute na ordem:
SOURCE /caminho/para/farmavida/sql/database.sql;
SOURCE /caminho/para/farmavida/sql/adicionar_estoque.sql;
SOURCE /caminho/para/farmavida/sql/adicionar_mercadopago.sql;
SOURCE /caminho/para/farmavida/sql/adicionar_reset_senha.sql;
```

### 3. Configuração do banco
Edite `config.php` ou defina variáveis de ambiente:
```
DB_HOST=localhost
DB_PORT=3306
DB_USER=seu_usuario
DB_PASS=sua_senha_forte
DB_NAME=farmavida
```

### 4. E-mail (recuperação de senha e notificações)
Edite `mailer.php` ou defina variáveis de ambiente:
```
MAIL_SMTP_HOST=smtp.gmail.com
MAIL_SMTP_PORT=587
MAIL_SMTP_USER=seu@email.com
MAIL_SMTP_PASS=senha_de_app_gmail
MAIL_FROM=no-reply@farmavida.com.br
MAIL_FROM_NAME=FarmaVida
```

### 5. Mercado Pago (pagamento online)
Edite `mercadopago_config.php` ou defina variáveis de ambiente:
```
MP_ACCESS_TOKEN=TEST-...     (sandbox) ou APP_USR-... (produção)
MP_PUBLIC_KEY=TEST-...
MP_AMBIENTE=sandbox          (mude para 'production' em produção)
```

### 6. Permissões
```bash
chmod 755 uploads/
chmod 755 logs/
```

---

## 🔑 Primeiro Acesso

Após executar o `database.sql`, um usuário administrador é criado automaticamente.

> ⚠️ **Altere a senha imediatamente após o primeiro login.**
> Por segurança, as credenciais padrão não são documentadas aqui.
> Consulte o arquivo `sql/database.sql` para ver o hash inicial e redefina via painel.

---

## 🗂️ Estrutura de Arquivos

```
farmavida/
├── config.php                  # Banco de dados
├── helpers.php                 # Funções auxiliares + CSRF
├── mailer.php                  # E-mails transacionais
├── style.css                   # Tema visual
├── index.php                   # Catálogo de produtos
├── login.php                   # Login com rate limiting
├── cadastro.php                # Cadastro de clientes
├── logout.php                  # Logout
├── esqueci_senha.php           # Recuperação de senha
├── redefinir_senha.php         # Redefinição via token
├── carrinho.php                # Sacola de compras
├── painel_cliente.php          # Painel do cliente
├── painel_dono.php             # Painel administrativo
├── gerenciar_produtos.php      # CRUD de produtos
├── estoque.php                 # Controle de estoque
├── ajax_handler.php            # API AJAX interna
├── imprimir_pedido.php         # Nota fiscal / recibo
├── relatorios.php              # Relatórios de vendas
├── nfe.php                     # Nota Fiscal Eletrônica
├── erp.php                     # API REST para ERP externo
├── criar_preferencia.php       # Checkout Mercado Pago
├── pagamento_retorno.php       # Retorno pós-pagamento MP
├── pagamento_webhook.php       # Webhook Mercado Pago
├── mercadopago_config.php      # Configuração MP
├── limpar_pedidos_antigos.php  # Limpeza de pedidos
├── uploads/                    # Imagens dos produtos
├── logs/                       # Logs do sistema
│   └── .htaccess               # Bloqueia acesso web
└── sql/
    ├── database.sql                 # Schema + dados iniciais
    ├── adicionar_estoque.sql        # Migração estoque
    ├── adicionar_mercadopago.sql    # Migração MP
    └── adicionar_reset_senha.sql    # Migração recuperação de senha
```

---

## ✨ Funcionalidades

### Para Clientes
- ✅ Cadastro com validação de CPF
- ✅ Login seguro com rate limiting (5 tentativas / 15 min)
- ✅ Recuperação de senha por e-mail
- ✅ Catálogo com filtros por categoria e busca
- ✅ Sacola de compras com atualização em tempo real
- ✅ Pagamento presencial ou online via Mercado Pago
- ✅ Acompanhamento de pedidos em tempo real
- ✅ Notificações por e-mail (boas-vindas, confirmação, status)
- ✅ Edição de dados pessoais

### Para o Farmacêutico (Admin)
- ✅ Painel administrativo responsivo com paginação
- ✅ CRUD de produtos com upload de imagens
- ✅ Controle de estoque com histórico de movimentações
- ✅ Gestão de status dos pedidos via AJAX
- ✅ Emissão de recibo para impressão
- ✅ Relatórios de vendas com gráficos
- ✅ Módulo NF-e (simulado — requer certificado para validade jurídica)
- ✅ API REST para integração com ERP externo (Bling, Omie, etc.)
- ✅ Gestão de webhooks

---

## 🔒 Segurança implementada

| Proteção | Status |
|---|---|
| SQL Injection (prepared statements) | ✅ Todo o sistema |
| CSRF em formulários POST | ✅ Todo o sistema |
| Rate limiting no login | ✅ 5 tentativas / 15 min |
| Senhas com bcrypt | ✅ |
| Validação de MIME type no upload | ✅ |
| Logs protegidos via .htaccess | ✅ |
| Credenciais via variáveis de ambiente | ✅ |
| Token de recuperação de senha com expiração | ✅ 1 hora |

---

## ⚠️ Avisos Legais

- Produtos com prescrição obrigatória devem ser identificados
- A dispensação de medicamentos deve seguir a legislação vigente
- Este sistema é apenas um gerenciador — não substitui o CRF
- NF-e com validade fiscal requer certificado digital A1/A3 e integração SEFAZ
