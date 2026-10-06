# Pré-Matrícula Digital

Integração do [módulo oficial Portábilis](https://github.com/portabilis/pre-matricula-digital) com este i-Educar. Fontes em `packages/portabilis/pre-matricula-digital`, branch upstream `2.12`, revisão fixada `b73af875578ffbbb7ac1157e1aff64bca518d45f`.

## Instalação no Windows

Com Docker Desktop executando containers Linux, na raiz do projeto:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/install-pmd.ps1
```

O instalador prepara os fontes, ajusta a restrição de Frontier de `^0.16.0` para `^0.18.0` (a versão exigida pelo host), instala dependências PHP, compila a interface, executa migrações pendentes, publica os assets e verifica as rotas e o schema GraphQL. Interrompe na primeira falha. Não executa `prematricula:install`, que contém exclusões de tabelas e views; a integração utiliza as migrações oficiais.

O ajuste de Frontier ainda precisa de validação em execução com Laravel 13. Não foi alterada a versão do framework do host, nem ignorados requisitos do Composer. Outros conflitos de dependências, se encontrados, interrompem a instalação.

Na primeira preparação, se `packages/composer.json` não existir, o instalador gera a lista de módulos ignorados para ativar somente PMD. Uma configuração existente é preservada; os módulos já habilitados nela também participam da resolução e das migrações.

Para preparar somente os arquivos, sem Docker ou alterações no banco:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/install-pmd.ps1 -PrepareOnly
```

O `.env` do i-Educar usa:

```dotenv
FRONTIER_ENDPOINT=/pre-matricula-digital
FRONTIER_VIEWS_PATH=packages/portabilis/pre-matricula-digital/dist
```

O acesso local, conforme o `APP_URL` atual, será http://localhost:8080/pre-matricula-digital após concluir a instalação. Esse endereço serve a interface compilada; não precisa de servidor Vite permanente.

## Configuração funcional

Os mapas usam Leaflet e tiles configuráveis, sem chave Google. Configure `MAP_TILE_URL`, `MAP_ATTRIBUTION` e `MAP_MAX_ZOOM` no `.env` do host quando necessário. A localização residencial pode ser confirmada no mapa ou por coordenadas. Para busca automática, configure `GEOCODING_URL` com uma instância privada/municipal compatível com Nominatim; o serviço público não recebe endereços residenciais. Veja [a configuração dos mapas](docs/mapas/MIGRACAO-MAPAS.md). `VITE_FROALA_KEY`, quando necessário para o editor, permanece no `.env` do pacote. Depois de alterar a interface, recompile e publique:

```powershell
docker compose -f docker-compose.yml -f docker-compose.pmd.yml run --rm --no-deps pmd-build
docker compose exec php php artisan vendor:publish --tag=pmd --force
```

Na versão obtida, município, UF, logo, códigos IBGE e centro do mapa são configurações `prematricula.*` na área de configurações do i-Educar, criadas pelas migrações do pacote. Confira esses valores antes de uso: o upstream inclui valores padrão de Içara/SC. Embora o README upstream cite variáveis `PMD_CITY` e outras, esta revisão não as lê no arquivo de configuração do pacote.

Prepare ano letivo aberto, coordenadas das escolas, turmas com vagas e cursos/séries habilitados para importar ao PMD. Configure e publique os processos de inscrição pelo módulo.

## Verificação realizada neste workspace

### Integração de 03/10/2026

O backend PMD foi instalado com Lighthouse 6.71.0 e Frontier 0.18.0. CommonMark foi atualizado para 2.10.3 para resolver o bloqueio de segurança, mantendo as proteções do Composer. `legacy.code` foi restaurado na configuração do host para carregar as migrações de integração do pacote. O instalador agora aplica as migrações do núcleo antes das migrações oficiais PMD, resolvendo a dependência da view `relatorio.view_dados_escola` em instalação limpa.

O PMD é conectado ao workflow documental por serviços do host e vínculo único da solicitação com a inscrição. Operadores são limitados às escolas autorizadas, e o login automático administrativo upstream foi substituído pela sessão existente. Consulte [SEMEACAO-BC.md](SEMEACAO-BC.md) para dados de demonstração, acessos, testes e limites funcionais.

A base estrutural do plano por ondas fica em `database/migrations/pmd`. O instalador a executa após as migrations oficiais do PMD. Em uma instalação já existente, aplique-a com `docker compose exec -T php php artisan migrate --force --path=database/migrations/pmd`. O estado e as pendências de cada onda estão em `docs/matricula-digital/`.

Os registros abaixo descrevem tentativas anteriores à integração de 03/10/2026.

- Dependências frontend instaladas com Yarn 1.22.22 e lockfile preservado.
- Build de produção concluído com base `/vendor/pre-matricula-digital/`, com avisos de dependências upstream e Sass.
- Configuração Compose e preparação do instalador validadas.
- Assets compilados copiados para `public/vendor/pre-matricula-digital`.
- Instalação PHP, migrações, rotas, schema GraphQL, login e inscrições ainda não validados: Docker Desktop retorna `Docker Desktop is unable to start`.
- Após autorização explícita do usuário, o instalador completo foi executado e interrompeu ao iniciar os containers devido ao motor Docker indisponível. Dependências PHP e migrações não chegaram a ser executadas.
- O diagnóstico do Windows confirmou reinicialização pendente e `VirtualizationFirmwareEnabled=False`. O comando `wsl --status` informou que WSL2 não está disponível na configuração atual.

Resolva a inicialização do Docker Desktop antes de executar a etapa completa. O guia [DEVELOPMENT-WINDOWS.md](DEVELOPMENT-WINDOWS.md) registra a pendência de reinicialização após habilitar WSL/Virtual Machine Platform.
