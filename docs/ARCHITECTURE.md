# Arquitetura

## Camadas

- `src/Core`
- `src/Infrastructure`
- `src/Application`
- `src/Presentation/Web`

## Rotas migradas

- `public/index.php`
- `public/login.php`
- `public/cadastro.php`
- `public/logout.php`

## Estratégia

- as URLs continuam as mesmas
- o legado em `app/pages` segue ativo nos módulos ainda não migrados
- as novas rotas usam controller, service, repository e view separados
