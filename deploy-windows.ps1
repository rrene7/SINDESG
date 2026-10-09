[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Destination,

    [string]$PhpExe = "C:\xampp\php\php.exe",

    [string]$DatabaseConfigSource = "",

    [string]$LocalConfigSource = "",

    [switch]$Production,

    [switch]$Bootstrap,

    [switch]$SkipBackup
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$Source = [System.IO.Path]::GetFullPath($PSScriptRoot)
$Destination = [System.IO.Path]::GetFullPath($Destination)

function Write-Step([string]$Message) {
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Invoke-PHP([string[]]$Arguments, [string]$WorkingDirectory) {
    Push-Location $WorkingDirectory
    try {
        & $PhpExe @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "PHP terminó con código $LASTEXITCODE: $($Arguments -join ' ')"
        }
    }
    finally {
        Pop-Location
    }
}

function Invoke-RobocopyMirror([string]$Name) {
    $from = Join-Path $Source $Name
    $to = Join-Path $Destination $Name

    if (-not (Test-Path $from -PathType Container)) {
        throw "Falta directorio fuente requerido: $from"
    }

    New-Item -ItemType Directory -Force -Path $to | Out-Null

    & robocopy.exe $from $to /MIR /R:2 /W:1 /NFL /NDL /NJH /NJS /NP
    $code = $LASTEXITCODE

    if ($code -ge 8) {
        throw "Robocopy falló para $Name con código $code."
    }
}

if (-not (Test-Path $PhpExe -PathType Leaf)) {
    throw "No se encontró PHP: $PhpExe"
}

if ($Source.TrimEnd('\') -ieq $Destination.TrimEnd('\')) {
    throw "La fuente y el destino no pueden ser la misma carpeta."
}

foreach ($required in @(
    "index.php",
    "app\bootstrap.php",
    "config\app.php",
    "scripts\check.php",
    "scripts\readiness.php",
    "scripts\backup.php"
)) {
    if (-not (Test-Path (Join-Path $Source $required) -PathType Leaf)) {
        throw "Fuente incompleta; falta $required"
    }
}

Write-Step "Validando checkout fuente"
Invoke-PHP -Arguments @(".\scripts\check.php") -WorkingDirectory $Source

$destinationExists = Test-Path $Destination -PathType Container
$existingDatabaseConfig = Join-Path $Destination "config\database.php"
$existingLocalConfig = Join-Path $Destination "config\local.php"

if ($DatabaseConfigSource -ne "") {
    $DatabaseConfigSource = [System.IO.Path]::GetFullPath($DatabaseConfigSource)
    if (-not (Test-Path $DatabaseConfigSource -PathType Leaf)) {
        throw "No existe DatabaseConfigSource: $DatabaseConfigSource"
    }
}
elseif (Test-Path $existingDatabaseConfig -PathType Leaf) {
    $DatabaseConfigSource = $existingDatabaseConfig
}

if ($LocalConfigSource -ne "") {
    $LocalConfigSource = [System.IO.Path]::GetFullPath($LocalConfigSource)
    if (-not (Test-Path $LocalConfigSource -PathType Leaf)) {
        throw "No existe LocalConfigSource: $LocalConfigSource"
    }
}
elseif (Test-Path $existingLocalConfig -PathType Leaf) {
    $LocalConfigSource = $existingLocalConfig
}

if ($Production) {
    if ($DatabaseConfigSource -eq "") {
        throw "Producción requiere config\database.php existente o -DatabaseConfigSource."
    }
    if ($LocalConfigSource -eq "") {
        throw "Producción requiere config\local.php existente o -LocalConfigSource."
    }
}

$preservedDatabaseConfig = $null
$preservedLocalConfig = $null

if ($DatabaseConfigSource -ne "") {
    $preservedDatabaseConfig = Join-Path ([System.IO.Path]::GetTempPath()) ("sindesg-db-" + [guid]::NewGuid().ToString("N") + ".php")
    Copy-Item -Force $DatabaseConfigSource $preservedDatabaseConfig
}

if ($LocalConfigSource -ne "") {
    $preservedLocalConfig = Join-Path ([System.IO.Path]::GetTempPath()) ("sindesg-local-" + [guid]::NewGuid().ToString("N") + ".php")
    Copy-Item -Force $LocalConfigSource $preservedLocalConfig
}

$preDeployBackup = ""

try {
    if ($destinationExists -and -not $SkipBackup) {
        $backupScript = Join-Path $Destination "scripts\backup.php"
        $destinationDb = Join-Path $Destination "config\database.php"

        if ((Test-Path $backupScript -PathType Leaf) -and (Test-Path $destinationDb -PathType Leaf)) {
            Write-Step "Creando respaldo pre_deploy del destino"
            Push-Location $Destination
            try {
                $backupOutput = & $PhpExe ".\scripts\backup.php" "--label=pre_deploy" 2>&1
                $backupOutput | ForEach-Object { Write-Host $_ }

                if ($LASTEXITCODE -ne 0) {
                    throw "El respaldo pre_deploy falló. Despliegue cancelado."
                }

                $backupLine = $backupOutput |
                    Where-Object { $_ -match '^BACKUP_PATH=' } |
                    Select-Object -Last 1

                if (-not $backupLine) {
                    throw "El respaldo pre_deploy no devolvió BACKUP_PATH."
                }

                $preDeployBackup = ($backupLine -replace '^BACKUP_PATH=', '').Trim()
            }
            finally {
                Pop-Location
            }
        }
        elseif (-not $Bootstrap) {
            throw "El destino existe pero no puede crear respaldo pre_deploy. Use -Bootstrap solo para una instalación inicial."
        }
    }

    Write-Step "Sincronizando código versionado"

    New-Item -ItemType Directory -Force -Path $Destination | Out-Null

    foreach ($directory in @("app", "public", "database", "docs", "scripts", "tests")) {
        Invoke-RobocopyMirror $directory
    }

    New-Item -ItemType Directory -Force -Path (Join-Path $Destination "config") | Out-Null

    foreach ($file in @("app.php", "database.example.php", "local.example.php")) {
        $sourceFile = Join-Path $Source ("config\" + $file)
        if (Test-Path $sourceFile -PathType Leaf) {
            Copy-Item -Force $sourceFile (Join-Path $Destination ("config\" + $file))
        }
    }

    foreach ($file in @("index.php", "README.md", ".gitignore")) {
        $sourceFile = Join-Path $Source $file
        if (Test-Path $sourceFile -PathType Leaf) {
            Copy-Item -Force $sourceFile (Join-Path $Destination $file)
        }
    }

    foreach ($runtimeDirectory in @(
        "storage\uploads",
        "storage\logs",
        "storage\cache",
        "storage\backups",
        "storage\deployments"
    )) {
        New-Item -ItemType Directory -Force -Path (Join-Path $Destination $runtimeDirectory) | Out-Null
    }

    if ($null -ne $preservedDatabaseConfig) {
        Copy-Item -Force $preservedDatabaseConfig (Join-Path $Destination "config\database.php")
    }

    if ($null -ne $preservedLocalConfig) {
        Copy-Item -Force $preservedLocalConfig (Join-Path $Destination "config\local.php")
    }

    $gitCommit = ""
    try {
        $gitCommit = (& git.exe -C $Source rev-parse HEAD 2>$null).Trim()
    }
    catch {
        $gitCommit = ""
    }

    $deployment = [ordered]@{
        deployed_at = (Get-Date).ToString("o")
        source = $Source
        destination = $Destination
        git_commit = $gitCommit
        production = [bool]$Production
        bootstrap = [bool]$Bootstrap
        pre_deploy_backup = $preDeployBackup
    }

    $markerName = "deployment_" + (Get-Date -Format "yyyyMMdd_HHmmss") + ".json"
    $markerPath = Join-Path $Destination ("storage\deployments\" + $markerName)
    $deployment | ConvertTo-Json -Depth 4 | Set-Content -Encoding UTF8 $markerPath

    if ($Bootstrap) {
        Write-Host ""
        Write-Host "[OK] Código desplegado en modo bootstrap." -ForegroundColor Green
        Write-Host "[INFO] Configure/restaure la base SINDESG y luego ejecute readiness.php --strict." -ForegroundColor Yellow
        Write-Host "DEPLOYMENT_MARKER=$markerPath"
        exit 0
    }

    Write-Step "Validando destino"
    Invoke-PHP -Arguments @(".\scripts\check.php", "--database") -WorkingDirectory $Destination

    if ($Production) {
        Invoke-PHP -Arguments @(".\scripts\readiness.php", "--strict") -WorkingDirectory $Destination
    }
    else {
        Invoke-PHP -Arguments @(".\scripts\readiness.php") -WorkingDirectory $Destination
    }

    Write-Host ""
    Write-Host "[OK] Despliegue SINDESG completado." -ForegroundColor Green
    if ($preDeployBackup -ne "") {
        Write-Host "PRE_DEPLOY_BACKUP=$preDeployBackup"
    }
    Write-Host "DEPLOYMENT_MARKER=$markerPath"
}
finally {
    if ($null -ne $preservedDatabaseConfig -and (Test-Path $preservedDatabaseConfig)) {
        Remove-Item -Force $preservedDatabaseConfig
    }
    if ($null -ne $preservedLocalConfig -and (Test-Path $preservedLocalConfig)) {
        Remove-Item -Force $preservedLocalConfig
    }
}
