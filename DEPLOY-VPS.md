# Implantação em VPS (produção)

Guia para executar o BC Educar (i-Educar + Pré-Matrícula Digital + extensão BC + transporte) em uma VPS Linux com `docker-compose.prod.yml`. O `docker-compose.yml` continua sendo o ambiente de desenvolvimento.

## Dimensionamento

| | Homologação | Produção (rede municipal) |
| --- | --- | --- |
| CPU | 2–4 vCPU | 4–8 vCPU |
| RAM | 8 GB | 16 GB |
| Disco | 80 GB SSD | 200–300 GB NVMe |
| Sistema | Ubuntu 24.04 LTS | Ubuntu 24.04 LTS |

- A memória é consumida principalmente por PostgreSQL, PHP-FPM, Horizon e Java (relatórios Jasper). Ajuste `PG_SHARED_BUFFERS`/`PG_EFFECTIVE_CACHE_SIZE` no `.env` conforme a RAM.
- O disco cresce com os documentos enviados pelos responsáveis (`storage/app`). Estimativa: 10 mil inscrições × ~8 documentos × ~1 MB ≈ 80–100 GB por ano letivo. Defina a política de retenção (`BC_DOCUMENT_RETENTION_*`).
- Pico de uso no período de inscrições: dimensione pela produção e reduza depois, se necessário.

## Diferenças em relação ao ambiente local

- Apenas o Caddy publica portas (80/443) e emite o certificado HTTPS. PostgreSQL e Redis não ficam expostos; o Redis exige senha.
- Imagem PHP sem Xdebug e com OPcache (`docker/php/Dockerfile.prod`).
- Todos os serviços com `restart: unless-stopped` e rotação de logs.
- `scheduler` executa `artisan schedule:work`, que já agenda as rotinas `bc:*`; não há o laço `bc-deadlines`.
- Fila, cache e sessão em Redis (`QUEUE_CONNECTION=redis`, processada pelo Horizon).
- Sem Mailpit; use SMTP institucional ou provedor transacional.

## Preparação do servidor

```bash
# Docker Engine e plugin compose: https://docs.docker.com/engine/install/ubuntu/
sudo apt install -y git python3 ufw fail2ban
sudo ufw allow OpenSSH && sudo ufw allow 80/tcp && sudo ufw allow 443 && sudo ufw enable
```

Use acesso SSH apenas por chave. Aponte o registro DNS do domínio para o IP da VPS antes de subir o Caddy.

## Instalação

```bash
sudo mkdir -p /opt/bceducar && sudo chown "$USER" /opt/bceducar
git clone https://github.com/meioUx/bceducar.git /opt/bceducar
cd /opt/bceducar
cp .env.production.example .env
# edite o .env: domínio, senhas, SMTP, tiles do mapa; HOST_UID/HOST_GID = id -u / id -g
scripts/install-vps.sh
```

Use `APP_ENV=homologation` para um servidor de homologação: o script então executa `bc:pmd-configure` (município e mapa de Balneário Camboriú), que o projeto bloqueia em produção. Em produção, ajuste essas chaves `prematricula.*` em Configurações.

O script obtém PMD e transporte nas revisões fixadas, aplica `patches/pmd`, instala dependências sem pacotes de desenvolvimento, compila a interface PMD, executa as migrações na mesma ordem de `scripts/install-pmd.ps1` e sobe os serviços. Também serve para atualizações: rode novamente após `git pull`.

Depois da instalação, siga a configuração funcional de [INSTALL-PMD.md](INSTALL-PMD.md) (município, processos, coordenadas das escolas) e de [INSTALL-TRANSPORTE.md](INSTALL-TRANSPORTE.md). Não execute seeders de demonstração em produção.

## Backup

```bash
sudo crontab -e
0 2 * * * RCLONE_REMOTE=remoto:bceducar /opt/bceducar/scripts/backup-vps.sh >> /var/log/bceducar-backup.log 2>&1
```

O script gera `pg_dump` e um pacote de `storage/app`. Mantenha uma cópia fora da VPS (`RCLONE_REMOTE`) e teste a restauração periodicamente:

```bash
docker compose -f docker-compose.prod.yml exec -T postgres pg_restore -U ieducar -d ieducar --clean < database.dump
```

## Operação

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f fpm horizon scheduler
docker compose -f docker-compose.prod.yml exec fpm php artisan bc:check-readiness
```
