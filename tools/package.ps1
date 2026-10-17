param(
    [string]$PhpExecutable = 'php',
    [string]$ComposerPhar = ''
)
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$releaseRoot = Join-Path $projectRoot 'release'
$packageName = 'global-trade-lines-laravel13'
$stagePath = Join-Path $releaseRoot $packageName
$archivePath = "$stagePath.zip"
if (-not $stagePath.StartsWith($projectRoot + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Invalid package path.' }
if ((Test-Path -LiteralPath $stagePath) -or (Test-Path -LiteralPath $archivePath)) { throw 'Release already exists. Preserve or rename it before building another.' }
New-Item -ItemType Directory -Path $stagePath -Force | Out-Null
foreach ($name in @('app','config','database','docs','public','resources','routes')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $name) -Destination $stagePath -Recurse
}
foreach ($name in @('artisan','composer.json','composer.lock','phpunit.xml','README.md','.env.example','.gitignore','.htaccess')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $name) -Destination $stagePath
}
foreach ($name in @('bootstrap','bootstrap/cache','tests','tests/Feature','tools','storage/app/private','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs')) {
    New-Item -ItemType Directory -Path (Join-Path $stagePath $name) -Force | Out-Null
}
Get-ChildItem -LiteralPath (Join-Path $projectRoot 'bootstrap') -File | Copy-Item -Destination (Join-Path $stagePath 'bootstrap')
Get-ChildItem -LiteralPath (Join-Path $projectRoot 'tests') -File | Copy-Item -Destination (Join-Path $stagePath 'tests')
Get-ChildItem -LiteralPath (Join-Path $projectRoot 'tests/Feature') -File | Copy-Item -Destination (Join-Path $stagePath 'tests/Feature')
foreach ($name in @('php.cmd','package.ps1')) { Copy-Item -LiteralPath (Join-Path $PSScriptRoot $name) -Destination (Join-Path $stagePath 'tools') }
foreach ($name in @('storage','storage/app','storage/app/private','storage/framework','storage/framework/cache','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot "$name/.gitignore") -Destination (Join-Path $stagePath $name)
}
[IO.File]::WriteAllText((Join-Path $stagePath 'bootstrap/cache/.gitignore'),"*`n!.gitignore`n")
Push-Location $stagePath
try {
    if ($ComposerPhar) { & $PhpExecutable $ComposerPhar install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress --no-ansi }
    else { & composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress --no-ansi }
    if ($LASTEXITCODE -ne 0) { throw 'Production dependency installation failed.' }
    & $PhpExecutable artisan --version
    if ($LASTEXITCODE -ne 0) { throw 'Packaged Laravel application did not boot.' }
    & $PhpExecutable artisan view:cache --no-ansi
    if ($LASTEXITCODE -ne 0) { throw 'Packaged Blade compilation failed.' }
    & $PhpExecutable artisan optimize:clear --no-ansi
    if ($LASTEXITCODE -ne 0) { throw 'Package cache cleanup failed.' }
} finally { Pop-Location }
$files = Get-ChildItem -LiteralPath $stagePath -Recurse -Force -File
$forbidden = $files | Where-Object { $_.Name -eq '.env' -or $_.Name -eq 'test-access.json' -or $_.Name -eq 'laravel.log' -or $_.FullName -match '[\\/]vendor[\\/](phpunit|mockery)[\\/]' }
if ($forbidden) { throw 'Private or development files found in release.' }
$manifest = foreach ($file in $files) {
    $relative = $file.FullName.Substring($stagePath.Length + 1).Replace('\','/')
    '{0}  {1}' -f (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLower(),$relative
}
[IO.File]::WriteAllLines((Join-Path $stagePath 'MANIFEST-SHA256.txt'),$manifest)
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [IO.Compression.ZipFile]::Open($archivePath,[IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in (Get-ChildItem -LiteralPath $stagePath -Recurse -Force -File)) {
        $entryName = $packageName + '/' + $file.FullName.Substring($stagePath.Length + 1).Replace('\','/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive,$file.FullName,$entryName,[IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $archive.Dispose() }
foreach ($part in @(@('schema','cms-v1.sql'),@('seed','seed-v1.sql'))) {
    Copy-Item -LiteralPath (Join-Path $projectRoot "database/schema/$($part[1])") -Destination (Join-Path $releaseRoot "$packageName-$($part[0]).sql")
}
$releaseFiles = @($archivePath,(Join-Path $releaseRoot "$packageName-schema.sql"),(Join-Path $releaseRoot "$packageName-seed.sql"))
$checksums = foreach ($file in $releaseFiles) { '{0}  {1}' -f (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToLower(),[IO.Path]::GetFileName($file) }
[IO.File]::WriteAllLines((Join-Path $releaseRoot 'LARAVEL-SHA256SUMS.txt'),$checksums)
Get-Item -LiteralPath $archivePath | Select-Object Name,Length
