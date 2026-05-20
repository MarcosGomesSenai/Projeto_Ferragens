# Ferragens Souza

Sistema local de gestao para ferragens e materiais de construcao, evoluido a partir da base Serenity.

## Escopo atual

- Autenticacao com perfis `admin`, `manager`, `operator` e `seller`
- Cadastro de produtos simplificado com codigo de barras, categoria, marca, unidade, precos e estoque minimo
- Cadastro de fornecedores com CNPJ, segmento e condicoes comerciais
- Cadastro de clientes com CPF/CNPJ e crediario basico
- Estoque com movimentacoes auditaveis, inventario e fila de reposicao
- Caixa diario com abertura, sangria, suprimento e fechamento
- PDV de balcao com baixa de estoque e pagamentos
- Vendas, orcamentos e financeiro basico
- Entrada manual de NF de fornecedor
- Relatorios gerenciais: inventario, ABC, giro, margem, perdas e DRE
- Auditoria SQL em `audit_logs`

## Requisitos

- PHP 8.1+
- Composer para instalar a geracao de PDF com mPDF
- MySQL 8.0+ ou MariaDB 10.6+ para ambiente definitivo
- SQLite habilitado no PHP para ambiente local sem MySQL
- Apache com `mod_rewrite`

## Instalação rápida no XAMPP + MySQL

Para instalação nova, execute **somente `schema.sql`**.  
Os arquivos em `database/migrations/` são para atualizar bancos antigos.

Resumo:

1. Coloque a pasta do projeto dentro de `C:\xampp\htdocs\serenity-system`.
2. Inicie **Apache** e **MySQL** no XAMPP.
3. Copie `.env.example` e renomeie a cópia para `.env`.
4. No `.env`, use:

```env
APP_ENV=development
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=ferragens_souza
DB_USER=root
DB_PASS=
DB_SQLITE_PATH=data/database/ferragens_souza.sqlite
```

5. Abra `http://localhost/phpmyadmin`.
6. Importe o arquivo `schema.sql`.
7. Acesse `http://localhost/serenity-system/`.

Conta inicial:

- Email: `admin@ferragensouza.local`
- Senha: `admin123`

Troque a senha no primeiro acesso.

Guia completo: veja `COMO_RODAR_XAMPP.md`.

### Qual SQL executar?

- **Projeto novo / banco zerado:** execute apenas `schema.sql`.
- **Banco antigo já existente:** faça backup e execute as migrations em ordem:
  1. `database/migrations/001_ferragens_souza_core.sql`
  2. `database/migrations/002_ferragens_souza_operational_tables.sql`
  3. `database/migrations/003_cash_register_admin_approval.sql`
  4. `database/migrations/004_simplify_product_fields.sql`
- **Não execute** `database/sqlite_schema.sql` no MySQL. Ele é apenas para SQLite.

## Backup diario

No servidor Linux, agende o backup diario fora do horario da loja:

```bash
0 23 * * * /caminho/do/projeto/scripts/backup_mysql.sh
```

Os arquivos ficam em `backups/` e backups com mais de 30 dias sao removidos automaticamente.

## Estrutura

- [index.php](index.php): roteador principal
- [app/config/config.php](app/config/config.php): constantes da aplicacao
- [app/config/database.php](app/config/database.php): bootstrap do PDO e `.env`
- [schema.sql](schema.sql): schema completo da Ferragens Souza
- [database/migrations/001_ferragens_souza_core.sql](database/migrations/001_ferragens_souza_core.sql): migracao inicial para bases antigas
- [database/migrations/002_ferragens_souza_operational_tables.sql](database/migrations/002_ferragens_souza_operational_tables.sql): tabelas operacionais de PDV, caixa, fiscal, financeiro e orcamentos
- `database/migrations/003_cash_register_admin_approval.sql`: migracao incremental para bases ja existentes, adicionando rastreio de aprovacao administrativa no fechamento de caixa
- `database/migrations/004_simplify_product_fields.sql`: remove campos avancados que foram simplificados no cadastro de produtos

## Observacoes

- A primeira versao nao emite NFC-e.
- Em uma base Serenity antiga, selecione o banco desejado e execute as migracoes em ordem (`001`, `002` e, se a base ja existia antes desta correcao, `003` e `004`) antes de usar os novos modulos.
- O schema foi preparado para leitura futura de XML de NF-e.
- Venda sem estoque exige autorizacao administrativa explicita e fica registrada em auditoria.
- As sessoes agora ficam em `data/sessions`, evitando dependencia de pasta temporaria externa do servidor.
