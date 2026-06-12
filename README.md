# Mboepi Moodle Deploy

Stack Docker para subir o Moodle com PostgreSQL, Nginx, PHP-FPM, Supervisor e um serviço separado de cron. O projeto foi preparado para deploy em Coolify, mas também funciona com `docker compose` localmente ou em servidor próprio.

## Visão geral

- `moodle`: container principal com Moodle, PHP-FPM, Nginx e Supervisor
- `cron`: container auxiliar que executa `admin/cli/cron.php` em loop
- `postgres`: banco de dados PostgreSQL 16

O build do image já inclui:

- Moodle `MOODLE_502_STABLE`
- extensões PHP necessárias para o Moodle
- locales do sistema para `pt_BR.UTF-8`, `en_US.UTF-8` e `en_AU.UTF-8`

## Estrutura

- [Dockerfile](./Dockerfile)
- [docker-compose.yml](./docker-compose.yml)
- [docker-entrypoint.sh](./docker-entrypoint.sh)
- [moodle-cron.sh](./moodle-cron.sh)
- [supervisord.conf](./supervisord.conf)
- [nginx/default.conf](./nginx/default.conf)
- [php/php.ini](./php/php.ini)

## Requisitos

- Docker e Docker Compose
- Banco PostgreSQL acessível pelo serviço `postgres`
- Variáveis de ambiente definidas para senha e, no primeiro deploy, dados administrativos do Moodle

## Variáveis de ambiente

As variáveis abaixo são esperadas pelo `docker-compose.yml` e pelos scripts de inicialização.

| Variável | Uso |
| --- | --- |
| `POSTGRES_PASSWORD` | Senha do usuário do banco |
| `MOODLE_AUTO_INSTALL` | `true` para instalar automaticamente o Moodle na primeira subida |
| `MOODLE_FULLNAME` | Nome completo do site |
| `MOODLE_SHORTNAME` | Nome curto do site |
| `MOODLE_ADMIN_USER` | Usuário administrador inicial |
| `MOODLE_ADMIN_PASSWORD` | Senha do administrador inicial |
| `MOODLE_ADMIN_EMAIL` | E-mail do administrador inicial |
| `MOODLE_CRON_INTERVAL` | Intervalo do cron em segundos |

O compose já define internamente:

- `MOODLE_URL`
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

O projeto usa volumes bind no host para persistência:

- `/data/mboepi/postgres-data` para o PostgreSQL
- `/data/mboepi/moodledata` para os dados do Moodle

Se for usar outro ambiente, ajuste esses caminhos no [docker-compose.yml](./docker-compose.yml).

## Como funciona a inicialização

O [docker-entrypoint.sh](./docker-entrypoint.sh) faz o seguinte:

1. valida variáveis obrigatórias
2. confere se o código do Moodle existe no container
3. ajusta permissões de `moodledata`
4. cria o `config.php` se ele ainda não existir
5. aguarda o banco ficar acessível
6. instala o Moodle automaticamente se `MOODLE_AUTO_INSTALL=true`
7. executa upgrade pendente dos plugins e do core

O [moodle-cron.sh](./moodle-cron.sh) roda em loop:

1. espera o banco do Moodle estar instalado
2. executa o cron do Moodle como `www-data`
3. corrige permissões de `moodledata`

## Build e execução

### Com Docker Compose

```bash
docker compose up -d --build
```

### Com Coolify

1. Aponte o repositório para um novo projeto no Coolify
2. Configure as variáveis de ambiente exigidas
3. Garanta os volumes persistentes em `/data/mboepi/postgres-data` e `/data/mboepi/moodledata`
4. Faça o deploy

## Primeiro acesso

Na primeira subida com `MOODLE_AUTO_INSTALL=true`, o container principal cria e instala o Moodle automaticamente.

Depois do deploy:

- acesse a URL definida em `MOODLE_URL`
- entre com o usuário administrador inicial definido nas variáveis

## Locales

Este projeto instala e gera locales no build do image para evitar avisos de fallback do Moodle e permitir formatação correta de:

- datas
- números
- moeda
- nomes de meses
- dias da semana
- ordenação

Os locales configurados são:

- `pt_BR.UTF-8`
- `en_US.UTF-8`
- `en_AU.UTF-8`

## Nginx e PHP

- O Nginx atende o Moodle a partir de `/var/www/moodle/public`
- Há fallback para `/var/www/moodle` em rotas PHP específicas
- O PHP usa a configuração em [php/php.ini](./php/php.ini)

## Verificação de saúde

O serviço `moodle` expõe um healthcheck em:

```text
http://localhost/healthz
```

Esse endpoint responde `ok` quando o Nginx e o PHP-FPM estão no ar.

## Problemas comuns

- `config.php` não é criado
  - verifique `MOODLE_URL`, `MOODLE_DBPASS` e, se `MOODLE_AUTO_INSTALL=true`, também `MOODLE_ADMIN_PASSWORD` e `MOODLE_ADMIN_EMAIL`

- Moodle não encontra o código do aplicativo
  - confirme que `/var/www/moodle` não foi sobrescrito por volume vazio

- cron não executa
  - valide se o banco já foi instalado e se `MOODLE_CRON_INTERVAL` está definido

- aviso de locale
  - confirme se a imagem foi rebuildada após a inclusão de `locales` e `locale-gen`

