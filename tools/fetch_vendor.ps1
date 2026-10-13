# fetch_vendor.ps1 — download the front-end libraries into app/assets/vendor/
#
# Why this exists: on this machine `curl` returns HTTP 200 with an EMPTY body for
# CDN hosts (jsdelivr/unpkg/cdnjs), so assets silently download as 0 bytes.
# PowerShell's Invoke-WebRequest works. Run this from anywhere:
#
#     powershell -NoProfile -ExecutionPolicy Bypass -File tools\fetch_vendor.ps1
#
# Bootstrap and Chart.js are vendored locally so the app runs with no internet -
# important for a classroom/demo environment.

$ErrorActionPreference = 'Stop'

$root = Join-Path $PSScriptRoot '..\app\assets\vendor'
$root = [System.IO.Path]::GetFullPath($root)

$files = @(
    @{ url = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
       out = 'bootstrap\bootstrap.min.css' },
    @{ url = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
       out = 'bootstrap\bootstrap.bundle.min.js' },
    @{ url = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css'
       out = 'bootstrap-icons\bootstrap-icons.css' },
    @{ url = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2'
       out = 'bootstrap-icons\fonts\bootstrap-icons.woff2' },
    @{ url = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff'
       out = 'bootstrap-icons\fonts\bootstrap-icons.woff' },
    @{ url = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js'
       out = 'chartjs\chart.umd.min.js' }
)

$fail = 0
foreach ($f in $files) {
    $dest = Join-Path $root $f.out
    $dir = Split-Path $dest -Parent
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }

    $ok = $false
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        try {
            Invoke-WebRequest -Uri $f.url -OutFile $dest -UseBasicParsing -TimeoutSec 60
            $size = (Get-Item $dest).Length
            if ($size -gt 0) {
                Write-Output ("OK    {0,-44} {1,10:N0} bytes" -f $f.out, $size)
                $ok = $true
                break
            }
            Write-Output ("RETRY {0} (attempt {1}: 0 bytes)" -f $f.out, $attempt)
        } catch {
            Write-Output ("RETRY {0} (attempt {1}: {2})" -f $f.out, $attempt, $_.Exception.Message)
        }
        Start-Sleep -Seconds 2
    }
    if (-not $ok) {
        Write-Output ("FAIL  {0}  <- {1}" -f $f.out, $f.url)
        $fail++
    }
}

Write-Output ""
if ($fail -eq 0) { Write-Output "All vendor assets downloaded to $root" }
else { Write-Output "$fail asset(s) FAILED - the app will not render correctly" }
exit $fail
