param(
    [Parameter(Mandatory = $true)]
    [string] $BackupDirectory
)

$ErrorActionPreference = 'Stop'
$receiptRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../../storage/payment_receipts')).Path
$backupRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../backups')).Path
$backupDir = (Resolve-Path -LiteralPath $BackupDirectory).Path

if (-not $backupDir.StartsWith($backupRoot + [System.IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Backup directory is outside database/backups.'
}

$manifest = Get-Content -LiteralPath (Join-Path $backupDir 'manifest.json') -Raw | ConvertFrom-Json
$expected = @($manifest.receipts)
$current = @(Get-ChildItem -LiteralPath $receiptRoot -File | Where-Object { $_.Name -ne '.gitignore' })
if ($expected.Count -ne $current.Count) {
    throw 'Receipt count changed after the verified backup.'
}

$targets = @()
foreach ($entry in $expected) {
    $name = [string] $entry.file
    if ([string]::IsNullOrWhiteSpace($name) -or $name -ne [System.IO.Path]::GetFileName($name)) {
        throw 'Backup manifest contains an invalid receipt filename.'
    }
    $target = [System.IO.Path]::GetFullPath((Join-Path $receiptRoot $name))
    if (-not $target.StartsWith($receiptRoot + [System.IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Receipt path is outside private storage.'
    }
    $backupCopy = Join-Path (Join-Path $backupDir 'payment_receipts') $name
    if (-not (Test-Path -LiteralPath $target -PathType Leaf) -or -not (Test-Path -LiteralPath $backupCopy -PathType Leaf)) {
        throw "Missing receipt or backup copy: $name"
    }
    $expectedHash = [string] $entry.sha256
    if ((Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash -ne $expectedHash -or
        (Get-FileHash -LiteralPath $backupCopy -Algorithm SHA256).Hash -ne $expectedHash) {
        throw "Receipt hash changed: $name"
    }
    $targets += $target
}

if ((@($targets | Sort-Object -Unique)).Count -ne $targets.Count) {
    throw 'Backup manifest contains duplicate receipt filenames.'
}

foreach ($target in $targets) {
    Remove-Item -LiteralPath $target -ErrorAction Stop
}

Write-Output "Removed $($targets.Count) backed-up receipt files."
