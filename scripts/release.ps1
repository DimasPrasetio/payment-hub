param(
    [Parameter(Mandatory = $true)]
    [string] $Version
)

$root = Split-Path -Parent $PSScriptRoot

if ($Version -notmatch '^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$') {
    throw "Version must follow Semantic Versioning, for example 1.0.1 or 1.1.0-rc.1"
}

Write-Host "Running production frontend build..."
Push-Location $root
npm run build
Pop-Location

Set-Content -Path (Join-Path $root 'VERSION') -Value $Version -NoNewline

Write-Host "Updated VERSION to $Version"
Write-Host "Next steps:"
Write-Host "1. Update CHANGELOG.md"
Write-Host "2. Commit the release changes, including public/build if it changed"
Write-Host "3. Create the tag: git tag -a v$Version -m \"Release v$Version\""
Write-Host "4. Push branch and tag: git push origin main --follow-tags"
