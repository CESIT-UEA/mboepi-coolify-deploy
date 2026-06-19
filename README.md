# Mboepi Moodle Deploy

Stack Docker para subir o Moodle com PostgreSQL, Nginx, PHP-FPM, Supervisor e um servico separado de cron. O projeto foi preparado para deploy em Coolify, mas tambem funciona com `docker compose` localmente ou em servidor proprio.

## Visao geral

- `moodle`: container principal com Moodle, PHP-FPM, Nginx e Supervisor
- `cron`: container auxiliar que executa `admin/cli/cron.php` em loop
- `postgres`: banco de dados PostgreSQL 16

O build da image ja inclui:

- Moodle `MOODLE_502_STABLE`
- extensoes PHP necessarias para o Moodle
- locales do sistema para `pt_BR.UTF-8`, `en_US.UTF-8` e `en_AU.UTF-8`

## Estrutura

- [Dockerfile](./Dockerfile)
- [docker-compose.yml](./docker-compose.yml)
- [docker-compose.override.yml](./docker-compose.override.yml)
- [.env](./.env)
- [docker-entrypoint.sh](./docker-entrypoint.sh)
- [moodle-cron.sh](./moodle-cron.sh)
- [supervisord.conf](./supervisord.conf)
- [nginx/default.conf](./nginx/default.conf)
- [php/php.ini](./php/php.ini)

## Requisitos

- Docker e Docker Compose
- Banco PostgreSQL acessivel pelo servico `postgres`
- Variaveis de ambiente definidas para senha e, no primeiro deploy, dados administrativos do Moodle

## Variaveis de ambiente

As variaveis abaixo sao esperadas pelo `docker-compose.yml` e pelos scripts de inicializacao.

| Variavel | Uso |
| --- | --- |
| `POSTGRES_PASSWORD` | Senha do usuario do banco |
| `MOODLE_AUTO_INSTALL` | `true` para instalar automaticamente o Moodle na primeira subida |
| `MOODLE_FULLNAME` | Nome completo do site |
| `MOODLE_SHORTNAME` | Nome curto do site |
| `MOODLE_ADMIN_USER` | Usuario administrador inicial |
| `MOODLE_ADMIN_PASSWORD` | Senha do administrador inicial |
| `MOODLE_ADMIN_EMAIL` | E-mail do administrador inicial |
| `MOODLE_CRON_INTERVAL` | Intervalo do cron em segundos |

O compose ja define internamente:

- `MOODLE_URL`
- `MOODLE_SSLPROXY`
- `MOODLE_REVERSEPROXY`
- `MOODLE_DBTYPE`
- `MOODLE_DBHOST`
- `MOODLE_DBNAME`
- `MOODLE_DBUSER`
- `MOODLE_DBPORT`
- `MOODLE_DATAROOT`
- `TZ`
- `LANG`
- `LANGUAGE`
- `LC_ALL`

## Volumes

O projeto usa volumes bind no host para persistencia:

- `/data/mboepi/postgres-data` para o PostgreSQL
- `/data/mboepi/moodledata` para os dados do Moodle

Se for usar outro ambiente, ajuste esses caminhos no [docker-compose.yml](./docker-compose.yml).

## Como funciona a inicializacao

O [docker-entrypoint.sh](./docker-entrypoint.sh) faz o seguinte:

1. valida variaveis obrigatorias
2. confere se o codigo do Moodle existe no container
3. ajusta permissoes de `moodledata`
4. cria o `config.php` se ele ainda nao existir
5. aguarda o banco ficar acessivel
6. instala o Moodle automaticamente se `MOODLE_AUTO_INSTALL=true`
7. executa upgrade pendente dos plugins e do core

O [moodle-cron.sh](./moodle-cron.sh) roda em loop:

1. espera o banco do Moodle estar instalado
2. executa o cron do Moodle como `www-data`
3. corrige permissoes de `moodledata`

## Build e execucao

### Com Docker Compose

```bash
docker compose up -d --build
```

### No Windows 11

Este repositorio ja inclui um override e um `.env` prontos para uso local.

Arquivos usados:

- [docker-compose.override.yml](./docker-compose.override.yml)
- [.env](./.env)

O override:

- troca os volumes para pastas locais em `C:/mboepi`
- publica o Moodle em `http://localhost:8080`
- ajusta `MOODLE_URL` e `MOODLE_DOMAIN` para `localhost`
- desativa `MOODLE_SSLPROXY` para evitar cookies `Secure` em HTTP local
- ativa `MOODLE_REVERSEPROXY` para o Moodle respeitar a porta externa `8080`

Passo a passo:

1. Instale o Docker Desktop
2. Habilite o WSL2 no Docker Desktop
3. Abra o PowerShell na raiz do projeto
4. Execute:

```powershell
docker compose up -d --build
```

5. Acompanhe a primeira subida com:

```powershell
docker compose logs -f moodle
```

6. Acesse:

- `http://localhost:8080`
- `http://localhost:8080/healthz`

7. Use o usuario e a senha definidos em [.env](./.env)

Para reiniciar do zero:

```powershell
docker compose down
```

Depois, se quiser reinstalar tudo, apague:

- `C:/mboepi/postgres-data`
- `C:/mboepi/moodledata`

E suba novamente:

```powershell
docker compose up -d --build
```

### Com Coolify

1. Aponte o repositorio para um novo projeto no Coolify
2. Configure as variaveis de ambiente exigidas
3. Garanta os volumes persistentes em `/data/mboepi/postgres-data` e `/data/mboepi/moodledata`
4. Faca o deploy

## Primeiro acesso

Na primeira subida com `MOODLE_AUTO_INSTALL=true`, o container principal cria e instala o Moodle automaticamente.

Depois do deploy:

- acesse a URL definida em `MOODLE_URL`
- entre com o usuario administrador inicial definido nas variaveis

## Locales

Este projeto instala e gera locales no build do image para evitar avisos de fallback do Moodle e permitir formatacao correta de:

- datas
- numeros
- moeda
- nomes de meses
- dias da semana
- ordenacao

Os locales configurados sao:

- `pt_BR.UTF-8`
- `en_US.UTF-8`
- `en_AU.UTF-8`

## Nginx e PHP

- O Nginx atende o Moodle a partir de `/var/www/moodle/public`
- Ha fallback para `/var/www/moodle` em rotas PHP especificas
- O PHP usa a configuracao em [php/php.ini](./php/php.ini)

## Verificacao de saude

O servico `moodle` expoe um healthcheck em:

```text
http://localhost/healthz
```

Esse endpoint responde `ok` quando o Nginx e o PHP-FPM estao no ar.

## Problemas comuns

- `config.php` nao e criado
  - verifique `MOODLE_URL`, `MOODLE_DBPASS` e, se `MOODLE_AUTO_INSTALL=true`, tambem `MOODLE_ADMIN_PASSWORD` e `MOODLE_ADMIN_EMAIL`

- loop de redirecionamento `303` em `http://localhost:8080`
  - confirme que `MOODLE_SSLPROXY=false` no [docker-compose.override.yml](./docker-compose.override.yml)
  - confirme que `MOODLE_REVERSEPROXY=true` no [docker-compose.override.yml](./docker-compose.override.yml)
  - se a instalacao ja foi criada com HTTPS, ajuste `cookiesecure` para `0` no banco local

- Moodle nao encontra o codigo do aplicativo
  - confirme que `/var/www/moodle` nao foi sobrescrito por volume vazio

- cron nao executa
  - valide se o banco ja foi instalado e se `MOODLE_CRON_INTERVAL` esta definido

- aviso de locale
  - confirme se a imagem foi rebuildada apos a inclusao de `locales` e `locale-gen`
