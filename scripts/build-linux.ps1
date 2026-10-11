$ErrorActionPreference = 'Stop'

$repositoryRoot = Split-Path -Parent $PSScriptRoot
Push-Location $repositoryRoot

try {
    docker compose -f compose.build.yaml build

    if ($LASTEXITCODE -ne 0) {
        throw 'Could not build the Linux build environment.'
    }

    docker compose -f compose.build.yaml run --rm crm-build

    if ($LASTEXITCODE -ne 0) {
        throw 'EspoCRM Linux build failed.'
    }
}
finally {
    Pop-Location
}
