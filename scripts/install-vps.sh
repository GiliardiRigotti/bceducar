#!/usr/bin/env bash
# Instalação/atualização em VPS Linux com docker-compose.prod.yml.
# Equivalente a scripts/install-pmd.ps1 + INSTALL-TRANSPORTE.md, para produção.
#
# Uso:
#   scripts/install-vps.sh             # prepara fontes, sobe containers, migra e publica
#   scripts/install-vps.sh --prepare   # só prepara os fontes (sem Docker e sem banco)
#
# Interrompe na primeira falha. Nunca executa migrate:fresh nem prematricula:install.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PMD_PATH="packages/portabilis/pre-matricula-digital"
PMD_REVISION="b73af875578ffbbb7ac1157e1aff64bca518d45f"
TRANSPORT_PATH="packages/portabilis/i-educar-transport-package"
TRANSPORT_REVISION="39e75702eaf769a2cecc4419a2fa49f5fd984800"
COMPOSE=(docker compose -f docker-compose.prod.yml)

fail() { echo "ERRO: $*" >&2; exit 1; }
step() { echo; echo "==> $*"; }

for bin in git python3 docker; do
    command -v "$bin" >/dev/null || fail "$bin não encontrado."
done

[[ -f .env ]] || fail "Crie o .env a partir de .env.production.example antes de continuar."
grep -Eq '^[A-Z_]+=.*TROCAR' .env && fail "O .env ainda contém valores TROCAR."
grep -Eq '^APP_ENV=production\s*$' .env || fail "APP_ENV deve ser production."
grep -Eq '^APP_DEBUG=false\s*$' .env || fail "APP_DEBUG deve ser false."

step "Pré-Matrícula Digital na revisão $PMD_REVISION"
if [[ ! -d "$PMD_PATH" ]]; then
    git clone --no-checkout https://github.com/portabilis/pre-matricula-digital.git "$PMD_PATH"
    git -C "$PMD_PATH" sparse-checkout init --no-cone
    printf '/*\n!/tests/\n' | git -C "$PMD_PATH" sparse-checkout set --no-cone --stdin
    git -C "$PMD_PATH" checkout "$PMD_REVISION"
fi
[[ "$(git -C "$PMD_PATH" rev-parse HEAD)" == "$PMD_REVISION" ]] \
    || fail "Revisão do PMD diferente de $PMD_REVISION. Revise a compatibilidade antes de continuar."

# Restaura o delta BC antes do Composer e do build do frontend.
python3 -I scripts/apply-pmd-customizations.py --package "$PMD_PATH"

FRONTIER="$(python3 -I -c 'import json,sys; print(json.load(open(sys.argv[1]))["require"].get("dex/frontier",""))' "$PMD_PATH/composer.json")"
if [[ "$FRONTIER" == "^0.16.0" ]]; then
    sed -i 's#"dex/frontier": "^0.16.0"#"dex/frontier": "^0.18.0"#' "$PMD_PATH/composer.json"
elif [[ "$FRONTIER" != "^0.18.0" ]]; then
    fail "Dependência Frontier inesperada ($FRONTIER); revise o manifesto do PMD."
fi

[[ -f "$PMD_PATH/.env" ]] || cp "$PMD_PATH/.env.example" "$PMD_PATH/.env"

for entry in "FRONTIER_ENDPOINT=/pre-matricula-digital" "FRONTIER_VIEWS_PATH=$PMD_PATH/dist"; do
    key="${entry%%=*}"
    if grep -Eq "^$key=" .env; then
        current="$(grep -E "^$key=" .env | tail -1 | cut -d= -f2- | tr -d "\"' \r")"
        [[ "$current" == "${entry#*=}" ]] || fail "$key existente difere da configuração PMD. Ajuste manualmente."
    else
        printf '\n%s\n' "$entry" >> .env
    fi
done
grep -Eq '^FRONTIER_PROXY_HOST=\S+' .env \
    && fail "FRONTIER_PROXY_HOST está configurado. Remova o proxy de desenvolvimento."

if [[ ! -f packages/composer.json ]]; then
    printf '{\n    "extra": {\n        "composer-plug-and-play": {\n            "ignore": []\n        }\n    }\n}\n' > packages/composer.json
elif grep -q '"portabilis/pre-matricula-digital"' packages/composer.json; then
    fail "PMD consta na lista de módulos ignorados em packages/composer.json."
fi

step "Transporte escolar na revisão $TRANSPORT_REVISION"
if [[ ! -d "$TRANSPORT_PATH" ]]; then
    git clone --branch 2.11 https://github.com/portabilis/i-educar-transport-package.git "$TRANSPORT_PATH"
    git -C "$TRANSPORT_PATH" checkout "$TRANSPORT_REVISION"
fi
[[ "$(git -C "$TRANSPORT_PATH" rev-parse HEAD)" == "$TRANSPORT_REVISION" ]] \
    || fail "Revisão do transporte diferente de $TRANSPORT_REVISION."

if [[ "${1:-}" == "--prepare" ]]; then
    echo "Fontes preparados. Containers, dependências e migrações não foram executados."
    exit 0
fi

step "Containers"
docker info --format '{{.ServerVersion}}' >/dev/null || fail "Docker indisponível."
"${COMPOSE[@]}" up -d --build postgres redis fpm

artisan() { "${COMPOSE[@]}" exec -T fpm php artisan "$@"; }

step "Dependências PHP"
"${COMPOSE[@]}" exec -T fpm composer install --no-interaction --no-dev --optimize-autoloader
"${COMPOSE[@]}" exec -T fpm composer plug-and-play:update --no-interaction

step "Frontend da Pré-Matrícula Digital"
docker compose -f docker-compose.prod.yml -f docker-compose.pmd.yml run --rm --no-deps pmd-build

grep -Eq '^APP_KEY=\S+' .env || artisan key:generate --force
"${COMPOSE[@]}" exec -T fpm composer set-permissions
artisan storage:link || true
artisan config:clear

step "Migrações"
# As views do núcleo precisam existir antes das migrações históricas do PMD.
host_paths=(--path=database/migrations)
for dir in addressing audit data educacenso exporter legacy misc report table view; do
    host_paths+=("--path=database/migrations/$dir")
done
artisan migrate --force "${host_paths[@]}"
artisan migrate --force
artisan migrate --force --path=database/migrations/pmd
artisan bc:pmd-configure
artisan vendor:publish --tag=pmd --force

step "Cache de views"
# config:cache e route:cache não são usados: o código chama env() fora de config/
# (ex.: ASSETS_SECURE em AppServiceProvider), o que deixaria de funcionar com cache.
artisan view:cache

step "Serviços"
"${COMPOSE[@]}" up -d --build
"${COMPOSE[@]}" restart fpm horizon scheduler

step "Verificações"
artisan route:list --path=pre-matricula-digital >/dev/null
artisan lighthouse:validate-schema
artisan bc:check-readiness || echo "Aviso: bc:check-readiness apontou pendências; revise antes de abrir inscrições."

echo
echo "Instalação concluída. Configure município, processos, tiles e SMTP antes de abrir inscrições."
echo "Não rode seeders de demonstração (BalnearioCamboriu*DemoSeeder) em produção."
