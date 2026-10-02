$ErrorActionPreference = 'Stop'
$sources = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'sources.json') -Raw | ConvertFrom-Json
foreach ($source in $sources) {
    $target = Join-Path $PSScriptRoot $source.file
    $temporary = "$target.download"
    $url = 'https://dynamoindia.com/media/scooters/' + $source.source
    & curl.exe --fail --location --silent --show-error --retry 2 --connect-timeout 15 --max-time 90 --output $temporary $url
    if ($LASTEXITCODE -ne 0) { throw "Download failed: $url" }
    $bytes = [IO.File]::ReadAllBytes($temporary)
    $signature = [byte[]](137, 80, 78, 71, 13, 10, 26, 10)
    if ($bytes.Length -lt 24) { throw "Invalid PNG: $url" }
    for ($i = 0; $i -lt 8; $i++) {
        if ($bytes[$i] -ne $signature[$i]) { throw "Invalid PNG: $url" }
    }
    Move-Item -LiteralPath $temporary -Destination $target -Force
    Write-Output "Downloaded $($source.file) ($($bytes.Length) bytes)"
}
