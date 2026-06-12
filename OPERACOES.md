# Operacoes

Notas de manutencao do ambiente Moodle. Este arquivo fica fora do Git para nao ser enviado ao repositório remoto.

## 1. Limpar dados e volumes

Use quando precisar remover a instalacao atual e subir do zero.

### Parar os servicos

```bash
docker compose down
```

### Limpar os volumes persistidos

Os caminhos usados por esta stack sao:

- `/data/mboepi/postgres-data`
- `/data/mboepi/moodledata`

Para remover o conteudo:

```bash
rm -rf /data/mboepi/postgres-data/*
rm -rf /data/mboepi/moodledata/*
```

Se quiser apagar tambem containers e redes orfaos do projeto:

```bash
docker compose down --remove-orphans
```

### Subir novamente

```bash
docker compose up -d --build
```

## 2. Logs

### Logs em tempo real

```bash
docker compose logs -f moodle
docker compose logs -f cron
docker compose logs -f postgres
```

### Ultimas linhas

```bash
docker compose logs --tail=200 moodle
docker compose logs --tail=200 cron
docker compose logs --tail=200 postgres
```

### Logs no Coolify

- Abra o projeto no Coolify
- Selecione o servico desejado
- Use a aba de logs para acompanhar `moodle`, `cron` ou `postgres`
- Depois de alterar imagem, variaveis ou compose, use a acao de redeploy do proprio Coolify

## 3. Resetar senha do admin

Execute dentro do container `moodle`.

### Com Docker Compose

```bash
docker compose exec moodle sh
php /var/www/moodle/admin/cli/reset_password.php --username=admin --password='NovaSenhaForteAqui'
exit
```

Se o usuario inicial nao for `admin`, troque o valor de `--username`.

### No Coolify

- Abra o servico `moodle`
- Use o terminal do container, se estiver disponivel no ambiente
- Rode o mesmo comando de reset de senha dentro do container

## 4. Backup e restore

### Backup dos volumes

Crie um diretório local para os backups:

```bash
mkdir -p backups
```

#### Backup do PostgreSQL

```bash
docker compose exec -T postgres pg_dump -U moodleuser moodle > backups/moodle.sql
```

#### Backup do moodledata

```bash
tar -czf backups/moodledata.tar.gz -C /data/mboepi moodledata
```

Se quiser usar um nome com data, troque `moodle.sql` e `moodledata.tar.gz` pelos nomes que preferir antes de executar os comandos.

### Restore do PostgreSQL

```bash
docker compose exec -T postgres psql -U moodleuser -d moodle < backups/moodle.sql
```

### Restore do moodledata

```bash
rm -rf /data/mboepi/moodledata/*
tar -xzf backups/moodledata.tar.gz -C /data/mboepi
```

Depois do restore, reinicie os servicos:

```bash
docker compose restart moodle cron
```

## 5. Verificar saude

```bash
curl -fsS http://localhost/healthz
```

Se responder `ok`, Nginx e PHP-FPM estao operando.

## 6. Observacoes

- O Moodle usa `pt_BR.UTF-8` como locale padrao no container.
- O cron roda em um container separado.
- O banco PostgreSQL usa o volume persistente em `/data/mboepi/postgres-data`.
- Os dados do Moodle ficam em `/data/mboepi/moodledata`.
