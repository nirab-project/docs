# Requires Windows PowerShell 5.1 or PowerShell 7. No external tools needed.
[CmdletBinding()]
param(
    [string]$ProjectRoot,
    [string]$OutputRoot
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$stagingPath = $null

function Assert-OutputChild {
    param([string]$Path, [string]$Parent)

    $fullPath = [System.IO.Path]::GetFullPath($Path)
    $prefix = [System.IO.Path]::GetFullPath($Parent).TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar
    if (-not $fullPath.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Output path must stay inside '$Parent': $fullPath"
    }
}

function Add-SourceItem {
    param(
        [System.IO.Compression.ZipArchive]$Archive,
        [System.IO.FileSystemInfo]$Item,
        [string]$RelativePath,
        [string]$ArchiveRoot,
        [string[]]$ExcludedPaths,
        [hashtable]$Stats
    )

    $normalizedPath = $RelativePath.Replace('\', '/')
    foreach ($excludedPath in $ExcludedPaths) {
        if ($normalizedPath -eq $excludedPath -or $normalizedPath.StartsWith($excludedPath + '/', [System.StringComparison]::OrdinalIgnoreCase)) {
            return
        }
    }

    # Stop rather than follow links outside the selected project or omit code.
    if (($Item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
        throw "Linked files/folders are not supported: $($Item.FullName)"
    }

    $entryName = $ArchiveRoot + '/' + $normalizedPath
    if ($Item.PSIsContainer) {
        [void]$Archive.CreateEntry($entryName.TrimEnd('/') + '/')
        foreach ($child in Get-ChildItem -LiteralPath $Item.FullName -Force) {
            Add-SourceItem -Archive $Archive -Item $child -RelativePath ($normalizedPath + '/' + $child.Name) -ArchiveRoot $ArchiveRoot -ExcludedPaths $ExcludedPaths -Stats $Stats
        }
    }
    else {
        [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $Archive, $Item.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal
        )
        $Stats.Files++
        $Stats.Bytes += $Item.Length
    }
}

try {
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
        $ProjectRoot = [System.IO.Path]::GetDirectoryName($MyInvocation.MyCommand.Path)
    }
    $ProjectRoot = (Get-Item -LiteralPath $ProjectRoot -Force).FullName
    if ([string]::IsNullOrWhiteSpace($OutputRoot)) {
        $OutputRoot = Join-Path $ProjectRoot 'NIRAB-SOURCES'
    }
    $OutputRoot = [System.IO.Path]::GetFullPath($OutputRoot)

    $flutterPaths = @(
        'lib',
        'android/app/src/main',
        'android/app/build.gradle',
        'android/app/build.gradle.kts',
        'android/app/google-services.json',
        'android/build.gradle',
        'android/build.gradle.kts',
        'android/settings.gradle',
        'android/settings.gradle.kts',
        'android/gradle.properties',
        'assets/language',
        'assets/json',
        'assets/fonts',
        'assets/font',
        'pubspec.yaml',
        'pubspec.lock',
        'analysis_options.yaml'
    )

    # Only these paths are read; all other project paths stay out of the ZIPs.
    $projects = @(
        @{
            Folder = 'Admin Panel'
            ArchiveRoot = '01-NIRAB-BACKEND-SOURCE'
            Paths = @(
                'app', 'Modules', 'bootstrap', 'config',
                'database/migrations', 'database/seeders',
                'resources/views', 'resources/lang', 'routes',
                '.env.example', 'artisan', 'composer.json', 'composer.lock',
                'modules_statuses.json',
                'public/assets/admin-module/js/nirab-operations.js',
                'public/assets/admin-module/js/nirab-workforce.js'
            )
            Required = @('app', 'Modules', 'config', 'routes', 'database/migrations', 'database/seeders', 'resources/views', 'composer.json', 'artisan', 'public/assets/admin-module/js/nirab-operations.js', 'public/assets/admin-module/js/nirab-workforce.js')
            Excluded = @('bootstrap/cache')
        },
        @{
            Folder = 'User app and web'
            ArchiveRoot = '02-NIRAB-CUSTOMER-SOURCE'
            Paths = $flutterPaths + @('web', 'public', '.firebaserc', '.gitignore')
            Required = @('lib', 'pubspec.yaml', 'android/app/src/main', 'web')
            Excluded = @()
        },
        @{
            Folder = 'Provider app'
            ArchiveRoot = '03-NIRAB-PROVIDER-SOURCE'
            Paths = $flutterPaths
            Required = @('lib', 'pubspec.yaml', 'android/app/src/main')
            Excluded = @()
        },
        @{
            Folder = 'Service man app'
            ArchiveRoot = '04-NIRAB-SERVICEMAN-SOURCE'
            Paths = $flutterPaths
            Required = @('lib', 'pubspec.yaml', 'android/app/src/main')
            Excluded = @()
        }
    )

    # Validate all four sources before creating any output files.
    foreach ($project in $projects) {
        $sourcePath = Join-Path $ProjectRoot $project.Folder
        if (-not (Test-Path -LiteralPath $sourcePath -PathType Container)) {
            throw "Project folder not found: $sourcePath"
        }
        $sourceItem = Get-Item -LiteralPath $sourcePath -Force
        if (($sourceItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw "Linked project folders are not supported: $sourcePath"
        }
        $sourcePrefix = $sourcePath.TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar
        if ($OutputRoot -eq $sourcePath -or $OutputRoot.StartsWith($sourcePrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            throw 'OutputRoot must be outside all four project folders.'
        }
        foreach ($requiredPath in $project.Required) {
            $requiredFullPath = Join-Path $sourcePath $requiredPath
            if (-not (Test-Path -LiteralPath $requiredFullPath)) {
                throw "Required source path not found: $requiredFullPath"
            }
        }
    }

    [void][System.IO.Directory]::CreateDirectory($OutputRoot)
    $runName = Get-Date -Format 'yyyy-MM-dd_HH-mm-ss-fff'
    $finalPath = Join-Path $OutputRoot $runName
    if (Test-Path -LiteralPath $finalPath) {
        $runName += '-' + [System.Guid]::NewGuid().ToString('N').Substring(0, 8)
        $finalPath = Join-Path $OutputRoot $runName
    }
    $stagingPath = Join-Path $OutputRoot ('.building-' + [System.Guid]::NewGuid().ToString('N'))
    Assert-OutputChild -Path $stagingPath -Parent $OutputRoot
    Assert-OutputChild -Path $finalPath -Parent $OutputRoot
    [void][System.IO.Directory]::CreateDirectory($stagingPath)

    $summaries = @()
    $index = 0
    foreach ($project in $projects) {
        $index++
        Write-Host ("[{0}/4] Packing {1} ..." -f $index, $project.Folder)
        $zipPath = Join-Path $stagingPath ($project.ArchiveRoot + '.zip')
        $archive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
        $stats = @{ Files = 0; Bytes = [long]0 }
        try {
            [void]$archive.CreateEntry($project.ArchiveRoot + '/')
            foreach ($relativePath in $project.Paths) {
                $itemPath = Join-Path (Join-Path $ProjectRoot $project.Folder) $relativePath
                if (Test-Path -LiteralPath $itemPath) {
                    $item = Get-Item -LiteralPath $itemPath -Force
                    Add-SourceItem -Archive $archive -Item $item -RelativePath $relativePath -ArchiveRoot $project.ArchiveRoot -ExcludedPaths $project.Excluded -Stats $stats
                }
                else {
                    Write-Verbose "Optional path not present: $itemPath"
                }
            }
        }
        finally {
            $archive.Dispose()
        }
        $summaries += [pscustomobject]@{
            Archive = $project.ArchiveRoot + '.zip'
            Files = $stats.Files
            'Source MB' = [math]::Round($stats.Bytes / 1MB, 2)
            'ZIP MB' = [math]::Round((Get-Item -LiteralPath $zipPath).Length / 1MB, 2)
        }
    }

    # Publish the completed set together; previous runs are kept.
    Assert-OutputChild -Path $stagingPath -Parent $OutputRoot
    Assert-OutputChild -Path $finalPath -Parent $OutputRoot
    Rename-Item -LiteralPath $stagingPath -NewName $runName
    $stagingPath = $null
    Write-Host ''
    $summaries | Format-Table -AutoSize | Out-Host
    Write-Host "Done. Four ZIP files saved in: $finalPath" -ForegroundColor Green
    exit 0
}
catch {
    $failureMessage = $_.Exception.Message
    Write-Verbose $_.ScriptStackTrace
    if ($stagingPath -and (Test-Path -LiteralPath $stagingPath)) {
        try {
            # Delete only the new, verified staging folder created by this run.
            Assert-OutputChild -Path $stagingPath -Parent $OutputRoot
            Remove-Item -LiteralPath $stagingPath -Recurse -Force
        }
        catch {
            Write-Warning "Could not remove incomplete output: $stagingPath"
        }
    }
    Write-Host "Export failed: $failureMessage" -ForegroundColor Red
    exit 1
}
