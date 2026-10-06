param([switch]$PrepareOnly)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
$packagePath = Join-Path $projectRoot 'packages/portabilis/pre-matricula-digital'
$revision = 'b73af875578ffbbb7ac1157e1aff64bca518d45f'
$utf8 = [Text.UTF8Encoding]::new($false)

function Invoke-Checked {
    param([string]$Executable, [string[]]$Arguments)
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Falha em $Executable $($Arguments -join ' ') (codigo $LASTEXITCODE)."
    }
}

if (-not (Test-Path -LiteralPath $packagePath)) {
    # O checkout esparso exclui testes com nomes incompatíveis com Windows.
    Invoke-Checked git @('clone', '--no-checkout', 'https://github.com/portabilis/pre-matricula-digital.git', $packagePath)
    Invoke-Checked git @('-C', $packagePath, 'sparse-checkout', 'init', '--no-cone')
    @('/*', '!/tests/') | & git -C $packagePath sparse-checkout set --no-cone --stdin
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao configurar checkout esparso.' }
    # Git validates names even outside the sparse checkout. Upstream tests contain a Windows-invalid directory.
    # Only this pinned checkout is affected; those tests remain excluded from the worktree.
    if ($env:OS -eq 'Windows_NT') {
        Invoke-Checked git @('-C', $packagePath, 'config', 'core.protectNTFS', 'false')
    }
    Invoke-Checked git @('-C', $packagePath, 'checkout', $revision)
}

$installedRevision = & git -C $packagePath rev-parse HEAD
if ($LASTEXITCODE -ne 0 -or $installedRevision -ne $revision) {
    throw "Revisao do PMD diferente da revisao preparada: $revision. Revise a compatibilidade antes de continuar."
}

# Restore the complete BC delta before dependency installation and frontend compilation.
Invoke-Checked python @((Join-Path $PSScriptRoot 'apply-pmd-customizations.py'), '--package', $packagePath)

$manifestPath = Join-Path $packagePath 'composer.json'
$manifest = [IO.File]::ReadAllText($manifestPath)
$frontier = ($manifest | ConvertFrom-Json).require.'dex/frontier'
if ($frontier -eq '^0.16.0') {
    $manifest = $manifest.Replace('"dex/frontier": "^0.16.0"', '"dex/frontier": "^0.18.0"')
    [IO.File]::WriteAllText($manifestPath, $manifest, $utf8)
} elseif ($frontier -ne '^0.18.0') {
    throw 'Dependencia Frontier inesperada; revise o manifesto do PMD.'
}

if (-not (Test-Path -LiteralPath (Join-Path $packagePath '.env'))) {
    Copy-Item -LiteralPath (Join-Path $packagePath '.env.example') -Destination (Join-Path $packagePath '.env')
}

$envPath = Join-Path $projectRoot '.env'
if (-not (Test-Path -LiteralPath $envPath)) {
    Copy-Item -LiteralPath (Join-Path $projectRoot '.env.example') -Destination $envPath
}
$environment = [IO.File]::ReadAllText($envPath)
foreach ($entry in @{
    FRONTIER_ENDPOINT = '/pre-matricula-digital'
    FRONTIER_VIEWS_PATH = 'packages/portabilis/pre-matricula-digital/dist'
}.GetEnumerator()) {
    $pattern = '(?m)^' + $entry.Key + '=(.*)\r?$'
    if ($environment -match $pattern) {
        if ($Matches[1].Trim().Trim('"', "'") -ne $entry.Value) {
            throw "Configuracao $($entry.Key) existente difere da configuracao PMD. Ajuste manualmente."
        }
    } else {
        $environment += "`n$($entry.Key)=$($entry.Value)`n"
    }
}
if ($environment -match '(?m)^FRONTIER_PROXY_HOST=\S+') {
    throw 'FRONTIER_PROXY_HOST esta configurado. Remova o proxy de desenvolvimento para usar o build publicado.'
}
[IO.File]::WriteAllText($envPath, $environment, $utf8)

# Ativa apenas PMD quando ainda não existe configuração de módulos.
$pluggedPath = Join-Path $projectRoot 'packages/composer.json'
if (-not (Test-Path -LiteralPath $pluggedPath)) {
    $otherModules = @(Get-ChildItem -Path (Join-Path $projectRoot 'packages/*/*/composer.json') |
        Where-Object { $_.FullName -ne $manifestPath } |
        ForEach-Object { ([IO.File]::ReadAllText($_.FullName) | ConvertFrom-Json).name })
    $plugged = @{ extra = @{ 'composer-plug-and-play' = @{ ignore = $otherModules } } }
    [IO.File]::WriteAllText($pluggedPath, ($plugged | ConvertTo-Json -Depth 5) + "`n", $utf8)
} else {
    $plugged = [IO.File]::ReadAllText($pluggedPath) | ConvertFrom-Json
    if ($plugged.extra.'composer-plug-and-play'.ignore -contains 'portabilis/pre-matricula-digital') {
        throw 'PMD consta na lista de modulos ignorados em packages/composer.json.'
    }
}

if ($PrepareOnly) {
    Write-Host 'Fontes e configuracao PMD preparados. Backend e migracoes ainda nao executados.'
    return
}

$dockerServer = & docker info --format '{{.ServerVersion}}'
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace(($dockerServer -join ''))) {
    throw 'Motor Docker indisponivel. Confirme WSL2, virtualizacao e reinicializacao pendente antes de continuar.'
}
Write-Host "Docker Server: $dockerServer"
Invoke-Checked docker @('compose', 'up', '-d', '--build')
Invoke-Checked docker @('compose', 'exec', '-T', 'php', 'composer', 'install', '--no-interaction')
Invoke-Checked docker @('compose', 'exec', '-T', 'php', 'composer', 'plug-and-play:update', '--no-interaction')
Invoke-Checked docker @('compose', '-f', 'docker-compose.yml', '-f', 'docker-compose.pmd.yml', 'run', '--rm', '--no-deps', 'pmd-build')
Invoke-Checked docker @('compose', 'exec', '-T', 'php', 'php', 'artisan', 'config:clear')
# Host views must exist before historical PMD migrations reference them.
$hostMigrations = @('compose', 'exec', '-T', 'php', 'php', 'artisan', 'migrate', '--force', '--path=database/migrations')
foreach ($directory in @('addressing', 'audit', 'data', 'educacenso', 'exporter', 'legacy', 'misc', 'report', 'table', 'view')) {
    $hostMigrations += "--path=database/migrations/$directory"
}
Invoke-Checked docker $hostMigrations
Invoke-Checked docker @('compose', 'exec', '-T', 'php', 'php', 'artisan', 'migrate', '--force')
Invoke-Checked docker @('compose', 'exec', '-T', 'php', 'php', 'artisan', 'migrate', '--force', '--path=database/migrations/pmd')
Invoke-Checked docker @('compose', 'exec', '-T', 'php', 'php', 'artisan', 'bc:pmd-configure')
Invoke-Checked docker @('compose', 'exec', '-T', 'php', 'php', 'artisan', 'vendor:publish', '--tag=pmd', '--force')
Invoke-Checked docker @('compose', 'restart', 'fpm', 'horizon')
Invoke-Checked docker @('compose', 'exec', '-T', 'php', 'php', 'artisan', 'route:list', '--path=pre-matricula-digital')
Invoke-Checked docker @('compose', 'exec', '-T', 'php', 'php', 'artisan', 'lighthouse:validate-schema')
Write-Host 'PMD instalado. Configure municipio, processos, tiles e Froala antes de abrir inscricoes. A busca residencial automatica requer geocoder privado ou municipal; a selecao no mapa funciona sem chave Google.'
