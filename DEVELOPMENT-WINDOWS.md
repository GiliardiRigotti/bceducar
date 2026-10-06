# Desenvolvimento local no Windows

Este guia complementa o [guia de instalação](INSTALL.md) com comandos para PowerShell. Use uma instalação local de desenvolvimento.

## Pré-requisitos

Instale Docker Desktop com suporte a containers Linux e inicie o aplicativo. Verifique no PowerShell:

```powershell
docker version
docker compose version
```

O Docker fornece PHP, Composer, PostgreSQL e Redis definidos em `docker-compose.yml`. Node/npm, sozinhos, não executam o sistema.

## Primeira instalação

Na pasta do repositório, crie a configuração sem substituir uma configuração existente:

```powershell
if (-not (Test-Path -LiteralPath .env)) {
    Copy-Item -LiteralPath .env.example -Destination .env
}
```

Para usar a porta 8080, ajuste estas variáveis no arquivo `.env`:

```dotenv
APP_URL=http://localhost:8080
DOCKER_NGINX_PORT=8080
```

Inicie os serviços e execute a instalação. Prossiga para o comando seguinte apenas se o anterior terminar com sucesso:

```powershell
docker compose up -d --build
docker compose exec php composer new-install
docker compose exec php php artisan db:seed
```

Abra http://localhost:8080. O acesso inicial documentado pelo projeto é usuário `admin` e senha `123456789`; altere a senha após entrar.

## Uso diário

```powershell
docker compose up -d
docker compose ps
docker compose logs --tail=100 fpm nginx horizon
```

Para parar os serviços preservando o volume do banco:

```powershell
docker compose stop
```

## Validação de mudanças PHP

Consulte a seção de testes em [INSTALL.md](INSTALL.md) para preparar um banco `testing` separado. Depois de preparar esse banco:

```powershell
docker compose exec php vendor/bin/pest
```

## Estado inicial deste workspace

- Origem: https://github.com/portabilis/i-educar.git
- Branch de origem: `2.12`.
- Commit inicial: `cd1da687c`.
- Branch de trabalho: `melhorias/bceducar`.
- Na verificação inicial, PHP, Composer e Docker não estavam disponíveis no PATH; a aplicação e os testes ainda não foram executados.

## Preparação realizada

Docker Desktop foi instalado. A inicialização do motor falhou com `Virtual Machine Platform not enabled`; a habilitação WSL foi executada como administrador e o Windows solicitou reinicialização. A configuração passou em `docker compose config --quiet`.

O arquivo `.env` local aponta para http://localhost:8080. As portas de Nginx, PostgreSQL e Redis foram vinculadas a 127.0.0.1. O Dockerfile inclui Java 8 e fontes para os relatórios; a imagem ainda precisa ser construída e validada.

Os fontes dos cinco módulos foram clonados em `packages/portabilis`: Relatórios, Biblioteca, Transporte, Educacenso e Pré-Matrícula Digital. Eles ainda não foram ativados, e suas migrações não foram executadas.

A Pré-Matrícula Digital foi obtida com checkout esparso, sem a pasta de testes incompatível com nomes de arquivos Windows. Sua dependência `dex/frontier ^0.16.0` conflita com `^0.18.0` exigida pelo i-Educar 2.12. Resolver e validar a combinação de versões antes de ativar esse módulo. O funcionamento completo também depende das chaves Google Maps e Froala descritas no README do pacote.

Após reiniciar o Windows, iniciar Docker Desktop e confirmar `docker info`. Continuar com a resolução das dependências, construção dos containers, instalação, ativação dos módulos, migrações e validação do login e dos relatórios.

## Integração da Pré-Matrícula Digital preparada

Consulte [INSTALL-PMD.md](INSTALL-PMD.md). O instalador `scripts/install-pmd.ps1` fixa a revisão do módulo e ajusta sua restrição de Frontier para `^0.18.0`. A interface foi compilada e publicada localmente. A configuração inicial de Plug and Play habilita somente PMD; os outros quatro módulos continuam ignorados.

A preparação passou duas vezes, e a configuração Compose foi validada. A compatibilidade PHP e os fluxos funcionais ainda dependem da instalação completa: nesta tentativa o Docker Desktop retornou `Docker Desktop is unable to start`.

A instalação completa foi autorizada e tentada, mas interrompeu antes da instalação PHP e das migrações: o motor Docker continua indisponível. O Windows registra reinicialização pendente, `VirtualizationFirmwareEnabled=False`, e o WSL informa indisponibilidade de WSL2. Reinicie o computador; se a virtualização continuar indisponível, confira sua habilitação no BIOS/UEFI (ou virtualização aninhada, caso este Windows seja uma máquina virtual).

## Execução validada em 02/10/2026

O motor Docker está funcionando e os seis serviços locais estão ativos. A imagem PHP foi construída; as dependências do host foram instaladas pelo lockfile; a chave, os links públicos, todas as migrações do host e os seeders iniciais foram executados.

O comando do Horizon em docker-compose.yml passou a usar `php /var/www/ieducar/artisan horizon`, evitando o erro `php\r` causado pelo checkout com quebras de linha Windows.

A tela de login respondeu HTTP 200. O acesso inicial admin / 123456789 foi validado por uma sessão HTTP e abriu /intranet/educar_index.php com HTTP 200. O endereço para teste é http://localhost:8080.

A integração PHP do PMD continua pendente: a etapa Composer Plug and Play de new-install falhou porque o resolvedor bloqueou league/commonmark 2.10.1, fixado pelo lockfile do host, por avisos de segurança. As proteções do Composer foram preservadas. A instalação do host foi concluída executando separadamente permissões (como root dentro do container), key:generate, storage:link, migrate e db:seed. Os assets preparados do PMD não indicam backend funcional.

## SMTP de captura local

O overlay opcional docker-compose.mail.yml configura o transporte de e-mail local e Mailpit em http://localhost:8025. Para iniciar, testar e retornar à configuração normal, consulte [validação SMTP](docs/matricula-digital/COMUNICACAO-SMTP-LOCAL-2026-10-05.md). O script de validação exige banco testing e reverte alterações transacionais.
