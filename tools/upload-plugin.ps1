# Uploads the Anybook WordPress plugin (version in readme.txt) to plugins.svn.wordpress.org/anybook.
# The SVN password is read from Windmill by tools/release.sh; it is never shown. Docker Desktop must be running.
# Run:  powershell -File C:\Users\jnich\Projects\Anybook\anybook-wordpress\tools\upload-plugin.ps1
Set-Location (Split-Path -Parent $PSScriptRoot)
# Windows Python needs a Windows-style path to find the Windmill helper (vpsmcp.py).
$env:VPSMCP_DIR = "C:/Users/jnich/Projects/AiNativeHalo/tools"
& "C:\Program Files\Git\bin\bash.exe" tools/release.sh --submit --go-ahead "owner, 2026-11-10"
Write-Host ""
Write-Host "Finished with exit code $LASTEXITCODE (0 means uploaded)."
