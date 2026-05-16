# Como rodar no XAMPP com MySQL

Este projeto usa PHP + MySQL no XAMPP. Para uma instalação nova, execute **somente o arquivo `schema.sql`**. Os arquivos dentro de `database/migrations/` são apenas para bancos antigos que já existiam antes das correções.

## 1. Coloque o projeto no XAMPP

1. Extraia o ZIP.
2. Renomeie a pasta para algo simples, por exemplo:
   `serenity-system`
3. Mova a pasta para:
   `C:\xampp\htdocs\serenity-system`

No final, o arquivo principal deve estar em:
`C:\xampp\htdocs\serenity-system\index.php`

## 2. Ligue os serviços

Abra o XAMPP Control Panel e inicie:

- Apache
- MySQL

## 3. Configure o arquivo `.env`

Na raiz do projeto, copie:

`.env.example`

E renomeie a cópia para:

`.env`

Para XAMPP padrão, deixe assim:

```env
APP_ENV=development
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=ferragens_souza
DB_USER=root
DB_PASS=
DB_SQLITE_PATH=data/database/ferragens_souza.sqlite
```

No XAMPP, normalmente o usuário é `root` e a senha fica vazia.

## 4. Importe o banco correto

Acesse:

`http://localhost/phpmyadmin`

Depois siga uma das opções abaixo.

### Instalação nova

Execute **somente** este arquivo:

`schema.sql`

Não execute `database/sqlite_schema.sql` e não execute as migrations.

O `schema.sql` já cria o banco `ferragens_souza`, cria as tabelas e cadastra o usuário inicial.

### Banco antigo já existente

Se você já tinha uma versão anterior do projeto com banco criado, não use `schema.sql` por cima sem backup.

Faça backup primeiro e execute as migrations nesta ordem:

1. `database/migrations/001_ferragens_souza_core.sql`
2. `database/migrations/002_ferragens_souza_operational_tables.sql`
3. `database/migrations/003_cash_register_admin_approval.sql`
4. `database/migrations/004_simplify_product_fields.sql`

## 5. Acesse o sistema

Abra no navegador:

`http://localhost/serenity-system/`

ou:

`http://localhost/serenity-system/index.php`

Conta inicial:

- Email: `admin@ferragensouza.local`
- Senha: `admin123`

O sistema vai pedir para trocar a senha no primeiro acesso.

## 6. Fluxo básico para testar

1. Faça login como admin.
2. Troque a senha inicial.
3. Cadastre ou confira produtos.
4. Acesse o PDV.
5. Clique em **Abrir Caixa**.
6. Informe o troco inicial.
7. Faça uma venda.

## Erros comuns

### `Unknown database 'ferragens_souza'`

O banco não foi criado/importado. Importe o `schema.sql` no phpMyAdmin.

### `Access denied for user 'root'@'localhost'`

A senha do MySQL no `.env` não bate com a senha configurada no seu XAMPP.

### `could not find driver`

A extensão PDO MySQL do PHP não está habilitada. No XAMPP comum ela já vem ativa, mas confirme em `php.ini` se `pdo_mysql` está habilitado.

### Tela branca

Com `APP_ENV=development`, o erro deve aparecer na tela. Verifique também os logs do Apache no XAMPP.
