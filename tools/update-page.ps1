# Updates only the plugin page on WordPress.org (readme text and tags, banner, icons, screenshots), with no new version.
# Commit and merge your changes first: it uses the committed readme.txt and .wordpress-org/ in this folder.
# The SVN password is read from Windmill by tools/release.sh; it is never shown. Docker Desktop must be running.
# Run:  powershell -File C:\Users\jnich\Projects\Anybook\anybook-wordpress\tools\update-page.ps1
Set-Location (Split-Path -Parent $PSScriptRoot)
$env:VPSMCP_DIR = "C:/Users/jnich/Projects/AiNativeHalo/tools"
& "C:\Program Files\Git\bin\bash.exe" tools/release.sh --page --go-ahead "owner, $(Get-Date -Format yyyy-MM-dd)"
Write-Host ""
Write-Host "Finished with exit code $LASTEXITCODE (0 means the page was updated)."
