# Módulo de transporte escolar

O pacote oficial [portabilis/i-educar-transport-package](https://github.com/portabilis/i-educar-transport-package) está integrado ao Composer do projeto. Ele usa o código em `packages/portabilis/i-educar-transport-package` e registra automaticamente o `TransportServiceProvider`. A versão local validada é o commit `39e75702eaf769a2cecc4419a2fa49f5fd984800`, ramo `2.11`.

Em uma instalação nova, obtenha o pacote antes de executar o Composer:

```powershell
git clone --branch 2.11 https://github.com/portabilis/i-educar-transport-package.git packages/portabilis/i-educar-transport-package
git -C packages/portabilis/i-educar-transport-package checkout 39e75702eaf769a2cecc4419a2fa49f5fd984800
docker compose exec -T php composer install --no-interaction
docker compose exec -T php php artisan package:discover
docker compose exec -T php php artisan migrate --force
```

O PMD também é uma dependência local do projeto; siga [INSTALL-PMD.md](INSTALL-PMD.md) antes do `composer install` em um ambiente limpo. Não use `migrate:fresh` em banco com dados. As migrações de transporte criam tabelas no esquema `modules`, tipos de veículo e menus; não apagam matrículas ou dados anteriores.

Depois de entrar com uma conta administradora global, abra **Transporte escolar** no menu ou acesse `/intranet/educar_transporte_escolar_index.php`. Os cadastros ficam no submenu **Cadastros**: empresas, motoristas, pontos, rotas e veículos. **Movimentações** contém usuários de transporte e cópia de rotas. O pacote também adiciona relatórios.

Os novos menus não concedem acesso automaticamente a tipos de usuário institucionais ou escolares. O administrador deve atribuir apenas os processos necessários em **Usuários → Tipos de usuário** (`/usuarios/tipos`). Um perfil sem essa permissão continua bloqueado pelo sistema legado.

Validação feita nos bancos locais `testing` e de desenvolvimento: migrações aplicadas, provider descoberto, menu criado e páginas de entrada/cadastro abertas por administrador global. As rotas do módulo não alteram o fluxo de pré-matrícula digital.
