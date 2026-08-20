$ErrorActionPreference = 'Stop'

$scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
php (Join-Path $scriptDirectory 'verify-repository.php')
exit $LASTEXITCODE
