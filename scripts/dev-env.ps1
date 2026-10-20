$projectRoot = Split-Path -Parent $PSScriptRoot
$global:WordlePhpDirectory = Join-Path $projectRoot '.tools\php-8.5.11'
$global:WordleNodeDirectory = Join-Path $projectRoot '.tools\node-v24.21.0-win-x64'
$global:WordleComposerDirectory = Join-Path $projectRoot '.tools\composer'

foreach ($requiredPath in @($global:WordlePhpDirectory, $global:WordleNodeDirectory, $global:WordleComposerDirectory)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Missing portable development tool: $requiredPath"
    }
}

$env:Path = "$global:WordlePhpDirectory;$global:WordleNodeDirectory;$global:WordleComposerDirectory;$env:Path"

function global:composer {
    & (Join-Path $global:WordlePhpDirectory 'php.exe') (Join-Path $global:WordleComposerDirectory 'composer.phar') @args
}

Write-Host 'Wordle Hit Today development tools are active.'
