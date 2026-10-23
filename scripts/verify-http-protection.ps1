# Production HTTP publication acceptance: run from an authorized client workstation.
# Read only: performs HTTP GET requests; never changes the server or database.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$baseUri = $null
if (-not [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref]$baseUri) -or
    $baseUri.Scheme -cne 'https' -or
    $baseUri.UserInfo -ne '' -or
    $baseUri.Query -ne '' -or
    $baseUri.Fragment -ne '') {
    throw 'BaseUrl debe ser una URL HTTPS absoluta, sin credenciales ni parámetros. Ejemplo: https://sindesg.institucion/SINDESG'
}

$base = $BaseUrl.TrimEnd('/')

function Get-HttpStatus([string]$Url) {
    try {
        $response = Invoke-WebRequest -Uri $Url -Method Get -UseBasicParsing -TimeoutSec 20 -MaximumRedirection 0 -ErrorAction Stop
        return [int]$response.StatusCode
    } catch {
        if ($null -ne $_.Exception.Response) {
            return [int]$_.Exception.Response.StatusCode
        }
        throw "No se pudo consultar el servidor con TLS válido: $Url"
    }
}

# La raíz HTTP de Linux es .../public; en XAMPP es el directorio del proyecto.
# Aceptar uno de esos formatos sin relajar ninguna comprobación de seguridad.
$assetCandidates = @(
    "$base/assets/css/app.css",
    "$base/public/assets/css/app.css"
)
$validAssets = @()
foreach ($candidate in $assetCandidates) {
    if ((Get-HttpStatus -Url $candidate) -eq 200) {
        $validAssets += $candidate
    }
}
if ($validAssets.Count -ne 1) {
    throw "Debe existir exactamente una ruta pública válida para CSS. Revisar DocumentRoot y exposición de /public: $($validAssets.Count)"
}
Write-Host "[OK] CSS público funciona sobre HTTPS: $($validAssets[0])" -ForegroundColor Green

$privatePaths = @(
    'config/database.example.php',
    'config/app.php',
    'docs/DESPLIEGUE_INSTITUCIONAL.md',
    'scripts/readiness.php',
    'app/bootstrap.php',
    'database/schema/001_bootstrap.sql'
)
foreach ($path in $privatePaths) {
    $url = "$base/$path"
    $code = Get-HttpStatus -Url $url
    if ($code -notin @(403,404)) {
        throw "RIESGO: recurso privado responde HTTP $code y NO está bloqueado: $url"
    }
    Write-Host "[OK] Acceso bloqueado (HTTP $code): $path" -ForegroundColor Green
}

Write-Host '[OK] Protección HTTP comprobada desde esta estación.' -ForegroundColor Green
Write-Host 'Aún se requieren pruebas de roles, concurrencia, respaldos y recuperación.' -ForegroundColor Yellow
