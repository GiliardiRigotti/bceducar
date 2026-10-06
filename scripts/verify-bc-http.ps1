param(
    [string]$BaseUrl = 'http://127.0.0.1:8080',
    [string]$DemoPassword = 'Teste@2026'
)

$ErrorActionPreference = 'Stop'
$portal = Invoke-WebRequest -Uri "$BaseUrl/pre-matricula-digital" -UseBasicParsing -TimeoutSec 45
if ($portal.StatusCode -ne 200) { throw 'Portal PMD indisponivel.' }
Write-Output 'Portal PMD: HTTP 200'
$assetMatch = [regex]::Match($portal.Content, 'src="([^"]+/assets/[^"]+\.js)"')
if (-not $assetMatch.Success) { throw 'Bundle JavaScript PMD ausente no HTML.' }
$assetUrl = [Uri]::new([Uri]$BaseUrl, $assetMatch.Groups[1].Value)
$asset = Invoke-WebRequest -Uri $assetUrl -UseBasicParsing -TimeoutSec 45
if ($asset.StatusCode -ne 200) { throw 'Bundle JavaScript PMD indisponivel.' }
Write-Output 'Bundle JavaScript PMD: HTTP 200'
$configResponse = Invoke-WebRequest -Uri "$BaseUrl/config/prematricula.js" -UseBasicParsing -TimeoutSec 45
$configJson = $configResponse.Content -replace '^window\.config\s*=\s*', '' -replace ';\s*$', ''
$pmdConfig = $configJson | ConvertFrom-Json
$expectedCity = 'Balne' + [char]0x00e1 + 'rio Cambori' + [char]0x00fa
if ($pmdConfig.city -ne $expectedCity -or [string]::IsNullOrWhiteSpace($pmdConfig.token)) {
    throw 'Configuracao municipal ou token PMD ausente.'
}
Write-Output 'Configuracao PMD: municipio correto e token presente'

foreach ($login in @('admin.seduc', 'op.medici')) {
    $loginPage = Invoke-WebRequest -Uri "$BaseUrl/login" -UseBasicParsing -SessionVariable bcHttpSession -TimeoutSec 45
    $signedIn = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ login = $login; password = $DemoPassword } -WebSession $bcHttpSession -UseBasicParsing -TimeoutSec 45
    $authResponse = Invoke-WebRequest -Uri "$BaseUrl/auth/check" -WebSession $bcHttpSession -UseBasicParsing -TimeoutSec 45
    $account = $authResponse.Content | ConvertFrom-Json
    if ($account.name -notlike "*$login*") { throw "Sessao incorreta para $login." }
    foreach ($path in @('/bc/matriculas', '/bc/matriculas/efetivadas', '/pre-matricula-digital/inscricoes')) {
        $page = Invoke-WebRequest -Uri "$BaseUrl$path" -WebSession $bcHttpSession -UseBasicParsing -TimeoutSec 45
        if ($page.StatusCode -ne 200) { throw "Pagina indisponivel: $path" }
        Write-Output "$login - $path : HTTP 200"
    }
}
Write-Output 'Verificacao HTTP concluida.'
