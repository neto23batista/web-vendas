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
-- No phpMyAdmin ou MySQL CLI, execute:
SOURCE /caminho/para/farmavida/sql/database.sql;
```

### 3. Configuração
Edite o arquivo `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_NAME', 'farmavida');
```

### 4. Permissões
```bash
chmod 755 uploads/
```

---

## 🔑 Credenciais de Acesso

| Tipo | E-mail | Senha |
|------|--------|-------|
| Administrador | admin@farmavida.com | admin123 |

---

## 🗂️ Estrutura de Arquivos

```
farmavida/
├── config.php              # Configuração do banco de dados
├── helpers.php             # Funções auxiliares
├── style.css               # Tema visual farmácia
├── index.php               # Loja / Catálogo de produtos
├── login.php               # Login de usuários
├── cadastro.php            # Cadastro de clientes
├── logout.php              # Logout
├── carrinho.php            # Sacola de compras
├── painel_cliente.php      # Painel do cliente
├── painel_dono.php         # Painel admin / farmacêutico
├── gerenciar_produtos.php  # CRUD de produtos
├── ajax_handler.php        # API interna (AJAX)
├── imprimir_pedido.php     # Nota fiscal / recibo
├── limpar_pedidos_antigos.php  # Limpeza automática
├── uploads/                # Imagens dos produtos
└── sql/
    └── database.sql        # Schema + dados iniciais
```

---

## 🏷️ Categorias de Produtos

| Categoria | Descrição |
|-----------|-----------|
| Medicamentos | Medicamentos com e sem tarja |
| Genéricos | Versões genéricas de medicamentos |
| Vitaminas | Suplementos vitamínicos e minerais |
| Higiene Pessoal | Produtos de higiene e cuidado pessoal |
| Dermocosméticos | Cosméticos indicados por dermatologistas |
| Infantil | Produtos para bebês e crianças |
| Bem-Estar | Suplementos para qualidade de vida |
| Primeiros Socorros | Curativos, antissépticos e kit emergência |
| Ortopedia | Bengalas, joelheiras, palmilhas |

---

## ✨ Funcionalidades

### Para Clientes
- ✅ Cadastro e login seguro
- ✅ Catálogo com filtros por categoria e busca
- ✅ Sacola de compras com atualização em tempo real
- ✅ Acompanhamento de pedidos
- ✅ Edição de dados pessoais

### Para o Farmacêutico (Admin)
- ✅ Painel administrativo responsivo
- ✅ Gestão completa de produtos (CRUD)
- ✅ Upload de imagens dos produtos
- ✅ Controle de status dos pedidos (Aguardando → Separando → Pronto → Entregue)
- ✅ Atualização automática de pedidos (AJAX)
- ✅ Emissão de nota/recibo para impressão
- ✅ Limpeza de pedidos finalizados
- ✅ Dashboard com estatísticas em tempo real

---

## 🎨 Tema Visual

- **Cores**: Verde farmácia (#00875a) + Azul (#0052cc)
- **Fontes**: DM Sans (corpo) + Sora (títulos)
- **Design**: Limpo, profissional e moderno
- **Responsivo**: Mobile-first, funciona em qualquer tela

---

## ⚠️ Avisos Legais

- Produtos com prescrição obrigatória devem ser identificados
- A dispensação de medicamentos deve seguir a legislação vigente
- Este sistema é apenas um gerenciador — não substitui o CRF
