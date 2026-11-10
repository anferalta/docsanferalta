param([string]$BaseUrl = 'https://anferalta.local')
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Net.Http
$base = [uri]$BaseUrl
if ($base.Scheme -ne 'https') { throw 'A validacao exige HTTPS com certificado confiavel.' }
$handler = [System.Net.Http.HttpClientHandler]::new()
$handler.AllowAutoRedirect = $false
$handler.UseProxy = $false
# Development CA certificates have no revocation endpoints. Certificate chain,
# hostname and expiration are still checked by the platform, without a bypass callback.
$handler.CheckCertificateRevocationList = $false
$client = [System.Net.Http.HttpClient]::new($handler)
$client.Timeout = [timespan]::FromSeconds(15)
$results = [System.Collections.Generic.List[object]]::new()
try {
    $cases = @(
        @{Path='/login'; Allowed=@(200)},
        @{Path='/.env'; Allowed=@(403,404)},
        @{Path='/.git/config'; Allowed=@(403,404)},
        @{Path='/vendor/autoload.php'; Allowed=@(403,404)},
        @{Path='/storage/documentos/'; Allowed=@(403,404)},
        @{Path='/backups/'; Allowed=@(403,404)},
        @{Path='/secrets/smtp-password.txt'; Allowed=@(403,404)},
        @{Path='/anferaltadocs/.env'; Allowed=@(403,404)},
        @{Path='/teste_sessao.php'; Allowed=@(403,404)},
        @{Path='/testar_email.php'; Allowed=@(403,404)},
        @{Path='/test'; Allowed=@(404)},
        @{Path='/public/documentos/1'; Allowed=@(302)},
        @{Path='/admin/backups/bd/criar'; Allowed=@(404)},
        @{Path='/assets/css/site.css'; Allowed=@(200)}
    )
    foreach ($case in $cases) {
        $response = $client.GetAsync([uri]::new($base,$case.Path)).GetAwaiter().GetResult()
        try {
            $status = [int]$response.StatusCode
            $ok = $status -in $case.Allowed
            if ($case.Path -eq '/login') {
                $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
                $cookies = if ($response.Headers.Contains('Set-Cookie')) { $response.Headers.GetValues('Set-Cookie') -join ';' } else { '' }
                $ok = $ok -and $body.Contains('name="password"') -and ($cookies -match '(?i)secure') -and ($cookies -match '(?i)httponly') -and ($cookies -match '(?i)samesite=lax')
                $ok = $ok -and $response.Headers.Contains('X-Content-Type-Options') -and $response.Headers.Contains('X-Frame-Options')
            }
            if ($case.Path -eq '/public/documentos/1') { $ok = $ok -and ([string]$response.Headers.Location -eq '/login') }
            $results.Add([pscustomobject]@{Path=$case.Path;Status=$status;Passed=$ok})
        } finally { $response.Dispose() }
    }
    $form = [System.Net.Http.FormUrlEncodedContent]::new([System.Collections.Generic.Dictionary[string,string]]::new())
    $response = $client.PostAsync([uri]::new($base,'/login'),$form).GetAwaiter().GetResult()
    $results.Add([pscustomobject]@{Path='POST /login without CSRF';Status=[int]$response.StatusCode;Passed=([int]$response.StatusCode -eq 303)})
    $response.Dispose(); $form.Dispose()
    $http = [uri]::new('http://' + $base.Authority + '/login')
    $response = $client.GetAsync($http).GetAwaiter().GetResult()
    $results.Add([pscustomobject]@{Path='HTTP to HTTPS';Status=[int]$response.StatusCode;Passed=([int]$response.StatusCode -in 301,308 -and ([string]$response.Headers.Location).StartsWith('https://'))})
    $response.Dispose()
} finally { $client.Dispose(); $handler.Dispose() }
$results | Format-Table -AutoSize
$failed = @($results | Where-Object { -not $_.Passed })
if ($failed.Count) { throw ('Falhas na validacao do servidor: ' + $failed.Count) }
Write-Output ('HTTPS, cookies, protecoes e rotas: ' + $results.Count + ' verificacoes passaram.')
