# Run from PowerShell 7. No env/config changes in the caller or development checkout.
[CmdletBinding()]
param(
    [switch]$Provision,
    [switch]$KeepAlive,
    [ValidateRange(1, 120)][int]$MaxMinutes = 30,
    [ValidateSet('None', 'Prepare', 'Ready')][string]$InjectFailureAfter = 'None'
)
#Requires -Version 7.0
if (!$Provision) { throw 'Explicit -Provision is required; no database connection was opened.' }

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$repo = (Resolve-Path (Join-Path $backend '..')).Path
$runId = [guid]::NewGuid().ToString('N')
$Scenario = 'ReviewCreate'
$development = $null
$childTerminationFailed = $false
if ($KeepAlive -and !$env:STAYHUB_TV3_AC_BROWSER_PASSWORD) { throw 'KeepAlive requires an owner-supplied ephemeral password in STAYHUB_TV3_AC_BROWSER_PASSWORD.' }
$schema = "stayhub_tv3_ac_test_$runId"
$testUser = 'tv3ac_' + $runId.Substring(0, 16)
$testPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$testAppKey = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$fixturePassword = if ($env:STAYHUB_TV3_AC_BROWSER_PASSWORD) { $env:STAYHUB_TV3_AC_BROWSER_PASSWORD } else { [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24)) }
if ($fixturePassword.Length -lt 24) { throw 'Ephemeral fixture password must contain at least 24 characters.' }
$evidence = Join-Path $repo ".agent-workspace/tv3/rev-create-ui-01a/$runId"
$null = New-Item -ItemType Directory -Path $evidence
$php = (Get-Command php -ErrorAction Stop).Source
$docker = (Get-Command docker -ErrorAction Stop).Source
$container = $null
$manifest = $null
$schemaCreated = $false
$userCreated = $false
$before = $null
$protectedBefore = $null
$failure = $null
$cleanupFailure = $null
$servers = @()

function Protect-Text([string]$Value) {
    return $Value.Replace($testPassword, '[REDACTED]').Replace($testAppKey, '[REDACTED]').Replace($fixturePassword, '[REDACTED]')
}

function Save-Json([string]$Name, $Value) {
    ConvertTo-Json -InputObject $Value -Depth 12 | Set-Content -LiteralPath (Join-Path $evidence $Name) -Encoding utf8
}

function Invoke-Child([string]$Exe, [string[]]$Arguments, [string]$InputText = '', [hashtable]$Environment = @{}, [switch]$AllowFailure, [string]$WorkingDirectory = $repo, [int]$TimeoutSeconds = 180) {
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $Exe
    $start.WorkingDirectory = $WorkingDirectory
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.StandardInputEncoding = [Text.UTF8Encoding]::new($false)
    $start.StandardOutputEncoding = [Text.UTF8Encoding]::new($false)
    $start.StandardErrorEncoding = [Text.UTF8Encoding]::new($false)
    foreach ($argument in $Arguments) { $start.ArgumentList.Add($argument) }
    foreach ($entry in $Environment.GetEnumerator()) { $start.Environment[$entry.Key] = [string]$entry.Value }
    $process = [Diagnostics.Process]::new()
    $process.StartInfo = $start
    $started = $false
    try {
        $started = $process.Start()
        $outTask = $process.StandardOutput.ReadToEndAsync()
        $errTask = $process.StandardError.ReadToEndAsync()
        $process.StandardInput.Write($InputText)
        $process.StandardInput.Close()
        if (!$process.WaitForExit($TimeoutSeconds * 1000)) {
            throw 'Owned child process exceeded its timeout.'
        }
        $result = @{ exit = $process.ExitCode; stdout = $outTask.GetAwaiter().GetResult(); stderr = $errTask.GetAwaiter().GetResult() }
    } finally {
        try {
            if ($started -and !$process.HasExited) {
                $process.Kill($true)
                if (!$process.WaitForExit(10000)) { throw 'Owned child did not exit after termination.' }
            }
        } catch {
            $script:childTerminationFailed = $true
            throw
        } finally {
            $process.Dispose()
        }
    }
    if ($result.exit -ne 0 -and !$AllowFailure) {
        throw "Child process failed (exit $($result.exit)): $(Protect-Text $result.stderr)"
    }
    return $result
}

function Admin-Sql([string]$Sql) {
    # The root secret remains inside the container environment, never in argv or evidence.
    $argsList = @('exec', '-i', $container, 'sh', '-c',
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root --database=information_schema --batch --raw --skip-column-names')
    return (Invoke-Child $docker $argsList ($Sql + "`n")).stdout.Trim()
}

function Hash-Text([string]$Value) {
    return [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($Value)))
}

function Development-Fingerprint {
    $tables = @( (Admin-Sql "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$development' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;") -split "`r?`n" | Where-Object { $_ })
    if ($tables.Count -eq 0) { throw 'Cannot establish development database fingerprint.' }
    $counts = [ordered]@{}
    foreach ($table in $tables) {
        if ($table -notmatch '^[a-zA-Z0-9_]+$') { throw 'Unexpected table name in development fingerprint.' }
        $counts[$table] = Admin-Sql ('SELECT COUNT(*) FROM `' + $development + '`.`' + $table + '`;')
    }
    # Raw dump stays in memory only. Persist only SHA256 and row counts, never records.
    $dump = Invoke-Child $docker @('exec', $container, 'sh', '-c',
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump --user=root --single-transaction --skip-comments --compact --skip-add-locks --skip-disable-keys --hex-blob --order-by-primary --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"')
    return @{ tables = $counts; sha256 = Hash-Text $dump.stdout }
}

function Assert-AdminIdentity {
    $observed = (Admin-Sql 'SELECT DATABASE(), VERSION(), @@server_uuid, CURRENT_USER();') -split "`t"
    if ($observed.Count -ne 4 -or $observed[0] -ne 'information_schema' -or
        $observed[1] -ne $manifest.version -or $observed[2] -ne $manifest.server_uuid -or $observed[3] -ne $manifest.admin_account) {
        throw 'Administrative connection instance changed; refuse writes or cleanup.'
    }
}

function Protected-Fingerprint {
    $paths = @((Invoke-Child 'git' @('ls-files')).stdout -split "`r?`n" | Where-Object { $_ })
    # Protect all nonignored untracked work, including the accepted UC48 work and TV3 matrix.
    $paths += @((Invoke-Child 'git' @('ls-files', '--others', '--exclude-standard')).stdout -split "`r?`n" | Where-Object { $_ })
    $paths += @('.env', 'backend/.env', 'frontend/.env')
    $hashes = [ordered]@{}
    foreach ($path in ($paths | Sort-Object -Unique)) {
        $absolute = Join-Path $repo $path
        $hashes[$path] = if (Test-Path -LiteralPath $absolute -PathType Leaf) { (Get-FileHash -LiteralPath $absolute -Algorithm SHA256).Hash } else { 'ABSENT' }
    }
    return $hashes
}

try {
    if (!$Provision) { throw 'Explicit -Provision is required; no database connection was opened.' }
    if (Test-Path -LiteralPath (Join-Path $backend 'bootstrap/cache/config.php')) { throw 'Refuse cached Laravel configuration.' }
    if ($env:APP_CONFIG_CACHE -or $env:APP_BASE_PATH -or (Test-Path -LiteralPath (Join-Path $repo '.env.testing'))) { throw 'Refuse alternate cache/env/base path.' }
    $protectedBefore = Protected-Fingerprint
    Save-Json 'protected-before.json' $protectedBefore
    $phpProbe = Invoke-Child $php @('-r', 'if (PHP_VERSION_ID < 80200 || !extension_loaded("pdo_mysql")) {exit(1);} echo PHP_VERSION;')
    $ids = (Invoke-Child $docker @('compose', 'ps', '-q', 'mysql')).stdout.Trim()
    if ($ids -notmatch '^[a-f0-9]{12,64}$') { throw 'Expected exactly one existing Compose MySQL container; none created or started by runner.' }
    $container = $ids
    $info = @((Invoke-Child $docker @('inspect', $container)).stdout | ConvertFrom-Json)[0]
    if ($info.Config.Image -ne 'mysql:8.4' -or !$info.State.Running -or $info.State.Health.Status -ne 'healthy') {
        throw 'Expected healthy existing mysql:8.4 container.'
    }
    if ($info.Config.Labels.'com.docker.compose.service' -ne 'mysql' -or
        [IO.Path]::GetFullPath($info.Config.Labels.'com.docker.compose.project.working_dir') -ne $repo) {
        throw 'Compose container does not belong to this checkout.'
    }
    $bindings = @($info.NetworkSettings.Ports.'3306/tcp')
    if ($bindings.Count -ne 1 -or $bindings[0].HostIp -ne '127.0.0.1') { throw 'Expected one loopback TCP binding.' }
    $port = [string]$bindings[0].HostPort
    # Parse selectors only. Root credentials never leave memory or enter evidence.
    $selectorProbe = @'
require 'backend/vendor/autoload.php';
$v = Dotenv\Dotenv::parse(file_get_contents('.env'));
if (!empty($v['APP_CONFIG_CACHE']) || !empty($v['DB_URL']) || !empty($v['DB_SOCKET'])) {exit(2);}
$keys = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE'];
foreach ($keys as $key) {
    if (getenv($key) !== false && getenv($key) !== ($v[$key] ?? null)) {exit(3);}
}
echo json_encode(array_intersect_key($v, array_flip($keys)), JSON_THROW_ON_ERROR);
'@
    $selectors = (Invoke-Child $php @('-r', $selectorProbe)).stdout | ConvertFrom-Json
    $developmentEntry = @($info.Config.Env | Where-Object { $_.StartsWith('MYSQL_DATABASE=') })
    if ($developmentEntry.Count -ne 1) { throw 'Missing unambiguous Compose development schema.' }
    $development = $developmentEntry[0].Substring('MYSQL_DATABASE='.Length)
    if ($development -notmatch '^[a-zA-Z0-9_]{1,64}$' -or $development.StartsWith('stayhub_tv3_ac_test_') -or
        $selectors.DB_CONNECTION -ne 'mysql' -or $selectors.DB_HOST -ne '127.0.0.1' -or
        [string]$selectors.DB_PORT -ne $port -or $selectors.DB_DATABASE -cne $development) {
        throw 'Root development target does not match the observed Compose instance.'
    }
    $identity = (Admin-Sql 'SELECT DATABASE(), VERSION(), @@server_uuid, CURRENT_USER();') -split "`t"
    if ($identity.Count -ne 4 -or $identity[0] -ne 'information_schema' -or $identity[1] -notmatch '^8\.4\.' -or $identity[2] -notmatch '^[a-f0-9-]{36}$' -or $identity[3] -ne 'root@localhost') {
        throw 'Unexpected administrative connection identity.'
    }
    function Free-Port {
        $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
        $listener.Start()
        try { return $listener.LocalEndpoint.Port } finally { $listener.Stop() }
    }
    $backendPort = Free-Port
    do { $frontendPort = Free-Port } while ($frontendPort -eq $backendPort)
    $manifest = @{ scenario = $Scenario; backend_url = "http://localhost:$backendPort"; frontend_url = "http://localhost:$frontendPort"; run_id = $runId; database = $schema; username = $testUser; host = '127.0.0.1'; port = $port;
        development_database = $development; admin_account = $identity[3]; server_uuid = $identity[2]; version = $identity[1]; container = $container; php = $phpProbe.stdout;
        head = (Invoke-Child 'git' @('rev-parse', 'HEAD')).stdout.Trim(); created_utc = [DateTime]::UtcNow.ToString('o') }
    Save-Json 'manifest.json' $manifest
    $before = Development-Fingerprint
    Save-Json 'development-before.json' $before
    if ((Admin-Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$schema';") -ne '0' -or
        (Admin-Sql "SELECT COUNT(*) FROM mysql.user WHERE User='$testUser';") -ne '0') {
        throw 'Owned names already exist; refuse to adopt or overwrite resources.'
    }
    Assert-AdminIdentity
    $null = Admin-Sql ('CREATE DATABASE `' + $schema + '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    $schemaCreated = $true
    Assert-AdminIdentity
    $null = Admin-Sql "CREATE USER '$testUser'@'%' IDENTIFIED BY '$testPassword';"
    $userCreated = $true
    $literalGrantSchema = $schema.Replace('_', '\_')
    Assert-AdminIdentity
    $null = Admin-Sql ('GRANT ALL PRIVILEGES ON `' + $literalGrantSchema + '`.* TO ' + "'$testUser'@'%';")
    $envMap = @{ APP_ENV = 'local'; APP_KEY = $testAppKey;
        DB_CONNECTION = 'mysql'; DB_HOST = '127.0.0.1'; DB_PORT = $port; DB_DATABASE = $schema;
        DB_USERNAME = $testUser; DB_PASSWORD = $testPassword; DB_URL = ''; DB_SOCKET = '';
        DB_CHARSET = 'utf8mb4'; DB_COLLATION = 'utf8mb4_unicode_ci'; MYSQL_ATTR_SSL_CA = '';
        BCRYPT_ROUNDS = '4'; MAIL_MAILER = 'array'; BROADCAST_CONNECTION = 'null';
        CACHE_STORE = 'array'; SESSION_DRIVER = 'database'; QUEUE_CONNECTION = 'sync'; LOG_CHANNEL = 'null';
        STAYHUB_TV3_AC_BROWSER_OPT_IN = '1'; STAYHUB_TV3_AC_OPT_IN = '1'; STAYHUB_TV3_AC_MANIFEST = (Join-Path $evidence 'manifest.json') }
    $envMap.STAYHUB_TV3_AC_REVIEW_CREATE_BROWSER_OPT_IN = '1'
    $envMap.APP_URL = $manifest.backend_url
    $envMap.APP_DEBUG = 'false'
    $envMap.STAYHUB_TV3_AC_BROWSER_PASSWORD = $fixturePassword
    $prepared = Invoke-Child $php @('tests/Support/ReviewCreateBrowserServer.php', 'prepare') '' $envMap -WorkingDirectory $backend
    $fixture = $prepared.stdout | ConvertFrom-Json -AsHashtable
    $prepared.stdout | Set-Content -LiteralPath (Join-Path $evidence 'fixture.json') -Encoding utf8
    if ($InjectFailureAfter -eq 'Prepare') { throw 'REV_CREATE_UI_EXPECTED_FAILURE_AFTER_PREPARE' }
    $envMap.Remove('STAYHUB_TV3_AC_BROWSER_PASSWORD')
    function Start-Owned([string]$Exe, [string[]]$Arguments, [hashtable]$Environment, [string]$Directory) {
        $start = [Diagnostics.ProcessStartInfo]::new()
        $start.FileName = $Exe; $start.WorkingDirectory = $Directory
        $start.UseShellExecute = $false; $start.CreateNoWindow = $true
        $start.RedirectStandardOutput = $true; $start.RedirectStandardError = $true
        $start.StandardOutputEncoding = [Text.UTF8Encoding]::new($false)
        $start.StandardErrorEncoding = [Text.UTF8Encoding]::new($false)
        foreach ($arg in $Arguments) { $start.ArgumentList.Add($arg) }
        foreach ($entry in $Environment.GetEnumerator()) { $start.Environment[$entry.Key] = [string]$entry.Value }
        # Do not forward the fixture login password to long-running servers.
        $start.Environment.Remove('STAYHUB_TV3_AC_BROWSER_PASSWORD') | Out-Null
        $process = [Diagnostics.Process]::new(); $process.StartInfo = $start
        $null = $process.Start()
        return @{ process = $process; output = $process.StandardOutput.ReadToEndAsync(); error = $process.StandardError.ReadToEndAsync() }
    }
    $servers += Start-Owned $php @('-S', "127.0.0.1:$backendPort", '-t', 'public', 'tests/Support/ReviewCreateBrowserServer.php') $envMap $backend
    $frontendEnv = @{ VITE_BACKEND_URL = $manifest.backend_url; VITE_ENABLE_BOOKING_MOCKS = 'false'; VITE_ENABLE_PROPERTY_MOCKS = 'false' }
    # Runtime-only virtual entry, generated inside the ignored run directory.
    $qaConfig = Join-Path $evidence 'qa-vite.config.mjs'
    $plugin = @'
import base from __BASE_CONFIG__;
import fs from 'node:fs';
const fixture = JSON.parse(fs.readFileSync(__FIXTURE_PATH__, 'utf8').replace(/^\uFEFF/, ''));
const cases = Object.fromEntries(Object.entries(fixture.fixture.bookings).filter(([,b])=>b.family==='UI'));
const entryId = __FRONTEND_ROOT__.replace(/\\/g,'/') + '/__tv3_qa_virtual__/review-create.jsx';
const entry = `
import React, {useState,useEffect} from 'react';
import {createRoot} from 'react-dom/client';
import {BrowserRouter} from 'react-router-dom';
import {ReviewCreateModal} from '/src/components/reviews/ReviewCreateModal.jsx';
import CustomerReviewsPage from '/src/pages/customer/CustomerReviewsPage.jsx';
import {reviewService} from '/src/services/reviewService.js';
import {authService} from '/src/services/authService.js';
import '/src/index.css';
const cases=${JSON.stringify(cases)},actors=${JSON.stringify(fixture.fixture.actors)};
function QA(){
 const [actor,setActor]=useState('C_A'),[password,setPassword]=useState(''),[user,setUser]=useState(null);
 const [selected,setSelected]=useState('UI-MAIN'),[open,setOpen]=useState(false),[mock,setMock]=useState(false);
 const [result,setResult]=useState(null),[message,setMessage]=useState(''),[busy,setBusy]=useState(false);
 const [view,setView]=useState(false),[refresh,setRefresh]=useState(0);
 useEffect(()=>{authService.restoreSession().then(setUser).catch(()=>setUser(null));},[]);
 const spec=cases[selected],booking={id:spec.id,status:spec.status,booking_code:spec.code,room:{name:spec.room_name,property:{name:spec.property_name}}};
 async function login(e){e.preventDefault();setBusy(true);try{setUser(await authService.login({email:actors[actor].email,password,expectedRole:actors[actor].role}));setPassword('');setMessage('Đã đăng nhập QA');}catch(e){setMessage(e.message||'Đăng nhập thất bại');}finally{setBusy(false);}}
 async function logout(){setBusy(true);try{await authService.logout();setUser(null);setMessage('Đã đăng xuất QA');}catch(e){setMessage(e.message);}finally{setBusy(false);}}
 async function readBack(){try{setResult(await reviewService.getCustomerReviewForBooking(spec.id));}catch(e){setMessage(e.message);}}
 return <main className="mx-auto max-w-5xl space-y-5 p-6">
 <h1 className="text-2xl font-bold">TV3 QA — Tạo Review UC16/60</h1>
 <p role="note">Điểm vào QA tổng hợp; Booking lưu thật trong schema cô lập. Modal/service/API production nguyên trạng. Chưa kiểm tích hợp trang Booking TV2.</p>
 <form onSubmit={login} className="flex flex-wrap gap-3"><label>Actor <select value={actor} onChange={e=>setActor(e.target.value)}>{Object.keys(actors).map(a=><option key={a}>{a}</option>)}</select></label><label>Mật khẩu tạm <input type="password" autoComplete="off" value={password} onChange={e=>setPassword(e.target.value)}/></label><button disabled={busy}>Đăng nhập QA</button></form>
 <p>Phiên hiện tại: {user?user.name:'Khách chưa đăng nhập'}</p><button disabled={busy} onClick={logout}>Đăng xuất QA</button><p role="status">{message}</p>
 <label>Fixture <select disabled={open} value={selected} onChange={e=>{setSelected(e.target.value);setResult(null);}}>{Object.entries(cases).map(([k,b])=><option key={k} value={k}>{k} — {b.status}</option>)}</select></label>
 <p>{spec.property_name} · {spec.room_name} · Booking #{spec.id}. Case không đủ điều kiện được mở có chủ đích để kiểm Backend UC60; không mô phỏng eligibility trang Booking.</p>
 <label><input type="checkbox" checked={mock} disabled={open} onChange={e=>setMock(e.target.checked)}/> Truyền isMock=true để kiểm chặn gửi; không tạo ID mẫu</label>
 <div className="flex flex-wrap gap-4"><button onClick={()=>setOpen(true)}>Mở modal production</button><button onClick={readBack}>Đọc lại qua Review service</button><button onClick={()=>{setRefresh(x=>x+1);setView(x=>!x);}}>Ẩn/hiện Đánh giá của tôi</button><a href="/customer/reviews">Mở trang production</a></div>
 <pre className="overflow-auto whitespace-pre-wrap">{result?JSON.stringify(result,null,2):''}</pre>
 <ReviewCreateModal key={selected} isOpen={open} onClose={()=>setOpen(false)} booking={booking} isMock={mock} onSuccess={r=>{setResult(r);setRefresh(x=>x+1);}} onConflict={readBack}/>
 {view&&<CustomerReviewsPage key={refresh}/>}</main>;
}
createRoot(document.getElementById('root')).render(<BrowserRouter><QA/></BrowserRouter>);
`;
export default {...base,root:__FRONTEND_ROOT__,cacheDir:__CACHE_PATH__,plugins:[...base.plugins,{
 name:'tv3-review-create-qa',enforce:'pre',
 resolveId(id){if(id==='virtual:tv3-review-create.jsx'||id===entryId)return entryId;},
 load(id){if(id===entryId)return entry;},
 configureServer(server){server.middlewares.use(async(req,res,next)=>{
  if(req.url?.split('?')[0]!=='/__tv3_qa__/review-create')return next();
  try{const html=await server.transformIndexHtml('/__tv3_qa__/review-create','<!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TV3 Review QA</title></head><body><div id="root"></div><script type="module" src="/@id/virtual:tv3-review-create.jsx"></script></body></html>');res.statusCode=200;res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);}catch(e){next(e);}
 });}
}]};
'@
    $plugin = $plugin.Replace('__BASE_CONFIG__', (([uri](Join-Path $repo 'frontend/vite.config.js')).AbsoluteUri | ConvertTo-Json -Compress))
    $plugin = $plugin.Replace('__FIXTURE_PATH__', ((Join-Path $evidence 'fixture.json') | ConvertTo-Json -Compress))
    $plugin = $plugin.Replace('__FRONTEND_ROOT__', ((Join-Path $repo 'frontend') | ConvertTo-Json -Compress))
    $plugin = $plugin.Replace('__CACHE_PATH__', ((Join-Path $evidence 'vite-cache') | ConvertTo-Json -Compress))
    [IO.File]::WriteAllText($qaConfig, $plugin, [Text.UTF8Encoding]::new($false))
    $servers += Start-Owned (Get-Command node).Source @('node_modules/vite/bin/vite.js', '--config', $qaConfig, '--host', '127.0.0.1', '--port', [string]$frontendPort, '--strictPort') $frontendEnv (Join-Path $repo 'frontend')
    $session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $headers = @{ Accept = 'application/json'; Origin = $manifest.frontend_url; Referer = $manifest.frontend_url + '/' }
    $ready = $false
    for ($i = 0; $i -lt 50; $i++) {
        foreach ($server in $servers) { if ($server.process.HasExited) { throw 'Owned server exited before readiness.' } }
        try {
            $csrf = Invoke-WebRequest ($manifest.backend_url + '/sanctum/csrf-cookie') -WebSession $session -Headers $headers -SkipHttpErrorCheck -TimeoutSec 2
            $front = Invoke-WebRequest $manifest.frontend_url -SkipHttpErrorCheck -TimeoutSec 2
            if ($csrf.StatusCode -eq 204 -and $front.StatusCode -eq 200) { $ready = $true; break }
        } catch { }
        Start-Sleep -Milliseconds 200
    }
    if (!$ready) { throw 'Owned server readiness timed out.' }

    if ($InjectFailureAfter -eq 'Ready') { throw 'REV_CREATE_UI_EXPECTED_FAILURE_AFTER_READY' }
    function Server-Command([string]$Mode, [string]$InputText = '') {
        $result = Invoke-Child $php @('tests/Support/ReviewCreateBrowserServer.php', $Mode) $InputText $envMap -WorkingDirectory $backend
        try { return $result.stdout | ConvertFrom-Json -AsHashtable }
        catch {
            Save-Json 'command-error.json' @{mode = $Mode; output = (Protect-Text $result.stdout); error = (Protect-Text $result.stderr)}
            throw
        }
    }
    function Assert-Status($Response, [int]$Expected, [string]$Name) {
        if ([int]$Response.StatusCode -ne $Expected) { throw "$Name expected HTTP $Expected, observed $($Response.StatusCode)." }
        $script:probes.Add(@{ name = $Name; status = [int]$Response.StatusCode })
        Save-Json 'http-probes.json' @($script:probes)
    }
    function Request-QA([string]$Method, [string]$Path, $Jar, [hashtable]$Headers, [string]$Body = '') {
        # PowerShell sessions retain request headers; absence must mean absence on the wire.
        $Jar.Headers.Remove('X-XSRF-TOKEN') | Out-Null
        $argsMap = @{ Uri = $manifest.backend_url + $Path; Method = $Method; WebSession = $Jar; Headers = $Headers;
            SkipHttpErrorCheck = $true; TimeoutSec = 10 }
        if ($Method -eq 'POST') { $argsMap.ContentType = 'application/json'; $argsMap.Body = $Body }
        return Invoke-WebRequest @argsMap
    }
    function Fresh-Xsrf($Jar, [hashtable]$Headers) {
        $cookie = $Jar.Cookies.GetCookies([uri]$manifest.backend_url)['XSRF-TOKEN']
        if (!$cookie) { throw 'Missing XSRF cookie.' }
        $Headers['X-XSRF-TOKEN'] = [uri]::UnescapeDataString($cookie.Value)
    }
    $script:probes = [Collections.Generic.List[object]]::new()
    $beforeBusiness = Server-Command 'audit-probes'
    Save-Json 'business-before.json' $beforeBusiness
    foreach ($path in @('/src/components/reviews/ReviewCreateModal.jsx', '/src/pages/customer/CustomerReviewsPage.jsx', '/src/services/reviewService.js', '/src/api/reviewApi.js', '/src/mappers/reviewMapper.js', '/__tv3_qa__/review-create', '/@id/virtual:tv3-review-create.jsx')) {
        $source = Invoke-WebRequest ($manifest.frontend_url + $path) -SkipHttpErrorCheck -TimeoutSec 15
        if ($source.StatusCode -ne 200) { Save-Json 'entry-error.json' @{ path=$path; status=$source.StatusCode; content=$source.Content }; throw "Vite QA module unavailable: $path" }
        if ($path -like '*virtual:*') {
            [IO.File]::WriteAllText((Join-Path $evidence 'entry-transformed.js'), $source.Content, [Text.UTF8Encoding]::new($false))
            if ($source.Content -notmatch 'ReviewCreateModal' -or $source.Content -notmatch '/src/services/reviewService.js' -or $source.Content -match '<ReviewCreateModal') { throw 'QA entry was not transformed to executable React module.' }
        }
    }
    Save-Json 'entry-verification.json' @{ served = $true; transformed = $true; manual_browser = $false; production_parent = $false }
    $oracleResults = [Collections.Generic.List[object]]::new()
    $validBody = '{"rating":5,"comment":"Phòng sạch sẽ - MAIN"}'
    function Compare-Review($Response, [int]$BookingId, [string]$Name, $LiteralPayload, [switch]$List) {
        [IO.File]::WriteAllText((Join-Path $evidence ('response-' + $oracleResults.Count + '.json')), $Response.Content, [Text.UTF8Encoding]::new($false))
        $key = if ($List) {'list_response'} else {'response'}
        $payload = '{"' + $key + '":' + $Response.Content + ',"booking_id":' + [string]$BookingId + ',"literal_payload":' + ($LiteralPayload | ConvertTo-Json -Compress) + '}'
        $comparison = Server-Command 'verify' $payload
        if (!$comparison.matched) { throw 'Independent Review oracle did not match.' }
        $oracleResults.Add(@{name=$Name; matched=$true; booking_id=$BookingId})
    }
    foreach ($actor in @('C_A','C_B','H_A','H_B','ADMIN')) {
        $spec = $fixture.fixture.actors[$actor]
        $jar = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
        $actorHeaders = @{Accept='application/json'; Origin=$manifest.frontend_url; Referer=$manifest.frontend_url + '/'}
        $mainId = [int]$fixture.fixture.bookings['P-MAIN'].id
        Assert-Status (Request-QA 'GET' '/sanctum/csrf-cookie' $jar $actorHeaders) 204 "$actor csrf-cookie"
        Fresh-Xsrf $jar $actorHeaders
        Assert-Status (Request-QA 'POST' "/api/customer/bookings/$mainId/review" $jar $actorHeaders $validBody) 401 "$actor guest create"
        $payload = @{email=$spec.email; password=$fixturePassword; expected_role=$spec.role} | ConvertTo-Json -Compress
        if ($actor -eq 'C_A') {
            $actorHeaders.Remove('X-XSRF-TOKEN')
            Assert-Status (Request-QA 'POST' '/login' $jar $actorHeaders $payload) 419 'missing XSRF login'
        }
        Fresh-Xsrf $jar $actorHeaders
        $login = Request-QA 'POST' '/login' $jar $actorHeaders $payload
        $payload = $null
        Assert-Status $login 200 "$actor login"
        if (($login.Content | ConvertFrom-Json).user.id -ne $spec.id) { throw 'Login actor differs.' }
        if (!$jar.Cookies.GetCookies([uri]$manifest.backend_url)['tv3ac_ui_' + $runId + '_session']) { throw 'Owned session cookie missing.' }
        Fresh-Xsrf $jar $actorHeaders
        Assert-Status (Request-QA 'GET' '/api/me' $jar $actorHeaders) 200 "$actor persisted session"
        $sessionAudit = Server-Command 'audit-probes'
        if (@($sessionAudit.session_users | Where-Object {$_.user_id -eq $spec.id -and $_.count -gt 0}).Count -ne 1) { throw 'Database session missing.' }
        if ($actor -eq 'C_A') {
            $valid = $actorHeaders['X-XSRF-TOKEN']; $actorHeaders.Remove('X-XSRF-TOKEN')
            Assert-Status (Request-QA 'POST' "/api/customer/bookings/$mainId/review" $jar $actorHeaders $validBody) 419 'missing XSRF create'
            $actorHeaders['X-XSRF-TOKEN'] = 'wrong-xsrf-token'
            Assert-Status (Request-QA 'POST' "/api/customer/bookings/$mainId/review" $jar $actorHeaders $validBody) 419 'wrong XSRF create'
            $actorHeaders['X-XSRF-TOKEN'] = $valid
            foreach ($kind in @('DUPLICATE','FOREIGN','PENDING','CONFIRMED','CHECKED_IN','CANCELLED','REJECTED')) {
                $id = [int]$fixture.fixture.bookings["P-$kind"].id
                $status = if ($kind -eq 'DUPLICATE') {409} elseif ($kind -eq 'FOREIGN') {404} else {422}
                Assert-Status (Request-QA 'POST' "/api/customer/bookings/$id/review" $jar $actorHeaders $validBody) $status $kind
            }
            Assert-Status (Request-QA 'POST' ('/api/customer/bookings/' + $fixture.fixture.missing_id + '/review') $jar $actorHeaders $validBody) 404 'missing Booking'
            foreach ($invalid in @('{"rating":0,"comment":"abcde"}', '{"rating":6,"comment":"abcde"}', '{"rating":5,"comment":"abcd"}', '{"rating":5,"comment":null}')) {
                Assert-Status (Request-QA 'POST' "/api/customer/bookings/$mainId/review" $jar $actorHeaders $invalid) 422 'invalid input'
            }
            foreach ($kind in @('MAIN','MIN','MAX','CROSS_HOST','DOUBLE','UNKNOWN','HTML')) {
                $id = [int]$fixture.fixture.bookings["P-$kind"].id
                $comment = if ($kind -eq 'MIN') {'abcde'} elseif ($kind -eq 'MAX') {'Đ' * 1000} elseif ($kind -eq 'HTML') {'<script>alert("QA")</script> Phòng sạch.'} else {"Phòng sạch sẽ - $kind"}
                $literal = @{rating=$(if ($kind -eq 'MIN') {1} else {5}); comment=$comment}
                $postPayload = @{rating=$literal.rating; comment=$comment; customer_id=999999; property_id=999999}
                if ($kind -eq 'MAIN') { $postPayload.comment = '  ' + $comment + '  ' }
                $post = Request-QA 'POST' "/api/customer/bookings/$id/review" $jar $actorHeaders ($postPayload | ConvertTo-Json -Compress)
                if ($post.Headers['Access-Control-Allow-Origin'] -ne $manifest.frontend_url -or $post.Headers['Access-Control-Allow-Credentials'] -ne 'true') { throw 'Creation CORS headers differ.' }
                if ($kind -eq 'UNKNOWN') {
                    Assert-Status $post 500 'synthetic after-commit response failure'
                    if ($post.Headers['X-TV3-QA-Fault'] -ne 'after-commit-response') { throw 'Missing QA fault attribution.' }
                } else {
                    Assert-Status $post 201 "create P-$kind"
                    Compare-Review $post $id "POST P-$kind" $literal
                }
                $list = Request-QA 'GET' '/api/customer/reviews?per_page=50' $jar $actorHeaders
                Assert-Status $list 200 "customer readback P-$kind"
                Compare-Review $list $id "GET P-$kind" $literal -List
            }
            $doubleId = [int]$fixture.fixture.bookings['P-DOUBLE'].id
            Assert-Status (Request-QA 'POST' "/api/customer/bookings/$doubleId/review" $jar $actorHeaders $validBody) 409 'sequential duplicate, not browser double click'
            $unknownId = [int]$fixture.fixture.bookings['P-UNKNOWN'].id
            Assert-Status (Request-QA 'POST' "/api/customer/bookings/$unknownId/review" $jar $actorHeaders $validBody) 409 'replay after response failure does not duplicate'
            Assert-Status (Request-QA 'POST' '/register' $jar $actorHeaders '{}') 403 'harness rejects unrelated mutation'
            $uiId = [int]$fixture.fixture.bookings['UI-MAIN'].id
            Assert-Status (Request-QA 'POST' "/api/customer/bookings/$uiId/review" $jar $actorHeaders $validBody) 403 'probe cannot consume UI fixture'
        } elseif ($actor -eq 'C_B') {
            Assert-Status (Request-QA 'POST' "/api/customer/bookings/$mainId/review" $jar $actorHeaders $validBody) 404 'other Customer cannot review'
            $list = Request-QA 'GET' '/api/customer/reviews?per_page=50' $jar $actorHeaders
            Assert-Status $list 200 'other Customer own list'
            if (@(($list.Content | ConvertFrom-Json).data | Where-Object {$_.booking_id -eq $mainId}).Count -ne 0) { throw 'Customer list leaks created Review.' }
        } else {
            Assert-Status (Request-QA 'POST' "/api/customer/bookings/$mainId/review" $jar $actorHeaders $validBody) 403 "$actor role denied"
        }
        Fresh-Xsrf $jar $actorHeaders
        Assert-Status (Request-QA 'POST' '/logout' $jar $actorHeaders '{}') 200 "$actor logout"
        Assert-Status (Request-QA 'GET' '/api/me' $jar $actorHeaders) 401 "$actor guest after logout"
    }
    Save-Json 'oracle.json' @($oracleResults)
    $afterBusiness = Server-Command 'audit-probes'
    Save-Json 'business-after.json' $afterBusiness
    Save-Json 'sessions-after.json' @{authenticated_sessions = @($afterBusiness.session_users).Count}
    if (@($afterBusiness.session_users).Count -ne 0) { throw 'Authenticated sessions remain after HTTP probes.' }
    if ($afterBusiness.delta.new_reviews -ne 7) { throw 'Probe inventory mismatch.' }
    $seal = Server-Command 'seal'
    if (!$seal.sealed) { throw 'Browser checkpoint not sealed.' }
    Save-Json 'browser-checkpoint.json' $seal
    Save-Json 'ready.json' @{ run_id = $runId; scenario = $Scenario; backend_url = $manifest.backend_url; frontend_url = $manifest.frontend_url;
        qa_entry = ($manifest.frontend_url + '/__tv3_qa__/review-create'); processes = @($servers | ForEach-Object { $_.process.Id }); stop_file = (Join-Path $evidence 'stop.request'); mode = 'real HTTP cookie jar; no browser UI observation' }
    if ($KeepAlive) {
        Write-Output "REV_CREATE_UI browser harness ready: $evidence"
        $deadline = [DateTime]::UtcNow.AddMinutes($MaxMinutes)
        while (!(Test-Path -LiteralPath (Join-Path $evidence 'stop.request'))) {
            if ([DateTime]::UtcNow -ge $deadline) { throw 'Browser QA lease expired; owned cleanup required.' }
            foreach ($server in $servers) { if ($server.process.HasExited) { throw 'Owned QA server exited unexpectedly.' } }
            Start-Sleep -Milliseconds 500
        }
        $browserAfter = Server-Command 'audit-browser'
        Save-Json 'browser-business-after.json' $browserAfter
        Save-Json 'browser-sessions-after.json' @{ authenticated_sessions = @($browserAfter.session_users).Count }
        if (!$browserAfter.delta.matched) { throw 'Browser mutation audit failed.' }
        if (@($browserAfter.session_users).Count -ne 0) { throw 'Authenticated browser session remained at stop; logout before stopping the run.' }
    }
} catch {
    $failure = Protect-Text $_.Exception.Message
} finally {
    try {
        $stopped = @()
        foreach ($server in $servers) {
            $pidOwned = $server.process.Id
            if (!$server.process.HasExited) { $server.process.Kill($true); $server.process.WaitForExit(10000) | Out-Null }
            $stopped += @{ pid = $pidOwned; exited = $server.process.HasExited }
            $serverIndex = $stopped.Count
            (Protect-Text $server.output.GetAwaiter().GetResult()) | Set-Content -LiteralPath (Join-Path $evidence ("server-$serverIndex-stdout.txt"))
            (Protect-Text $server.error.GetAwaiter().GetResult()) | Set-Content -LiteralPath (Join-Path $evidence ("server-$serverIndex-stderr.txt"))
            $server.process.Dispose()
        }
        Save-Json 'process-cleanup.json' $stopped
        if (@($stopped | Where-Object { !$_.exited }).Count -gt 0) { throw 'Owned process remained after termination.' }
        $portChecks = @()
        if ($null -ne $manifest) {
            foreach ($origin in @($manifest.backend_url, $manifest.frontend_url)) {
                $released = @([Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners() | Where-Object { $_.Port -eq ([uri]$origin).Port }).Count -eq 0
                $portChecks += @{ port = ([uri]$origin).Port; released = $released }
            }
        }
        Save-Json 'port-cleanup.json' $portChecks
        if (@($portChecks | Where-Object { !$_.released }).Count -gt 0) { throw 'QA listener port was not released.' }
        if ($schemaCreated -or $userCreated) {
            if ($childTerminationFailed) { throw 'Owned child termination unconfirmed; refuse resource cleanup.' }
            if ($schema -cne "stayhub_tv3_ac_test_$runId" -or $testUser -cne ('tv3ac_' + $runId.Substring(0, 16))) { throw 'Ownership mismatch; refuse cleanup.' }
            Assert-AdminIdentity
            $connections = Admin-Sql "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='$testUser';"
            if ($connections -ne '0') { throw 'Owned user still has active connections; refuse drop and preserve evidence.' }
            if ($schemaCreated) { $null = Admin-Sql ('DROP DATABASE `' + $schema + '`;') }
            if ($userCreated) { $null = Admin-Sql "DROP USER '$testUser'@'%';" }
            $schemaRemaining = Admin-Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$schema';"
            $userRemaining = Admin-Sql "SELECT COUNT(*) FROM mysql.user WHERE User='$testUser';"
            $connectionsRemaining = Admin-Sql "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='$testUser';"
            Save-Json 'cleanup.json' @{ schema_remaining = $schemaRemaining; user_remaining = $userRemaining; connections_before_drop = $connections; connections_remaining = $connectionsRemaining }
            if ($schemaRemaining -ne '0' -or $userRemaining -ne '0' -or $connectionsRemaining -ne '0') { throw 'Owned resources remain after cleanup.' }
        }
        if ($null -ne $before) {
            $after = Development-Fingerprint
            Save-Json 'development-after.json' $after
            if ($before.sha256 -ne $after.sha256 -or
                (($before.tables | ConvertTo-Json -Compress) -cne ($after.tables | ConvertTo-Json -Compress))) {
                throw 'Development fingerprint changed; do not restore or overwrite development data.'
            }
        }
    } catch { $cleanupFailure = Protect-Text $_.Exception.Message }
    if ($null -ne $protectedBefore) {
        try {
            $protectedAfter = Protected-Fingerprint
            Save-Json 'protected-after.json' $protectedAfter
            if (($protectedBefore | ConvertTo-Json -Compress) -cne ($protectedAfter | ConvertTo-Json -Compress)) {
                throw 'Protected source/config fingerprints changed; no files restored automatically.'
            }
        } catch { $cleanupFailure = "$cleanupFailure $($_.Exception.Message)".Trim() }
    }
    foreach ($artifact in (Get-ChildItem -LiteralPath $evidence -File)) {
        $artifactText = [IO.File]::ReadAllText($artifact.FullName)
        if ($artifactText.Contains($testPassword) -or $artifactText.Contains($testAppKey) -or $artifactText.Contains($fixturePassword)) {
            [IO.File]::WriteAllText($artifact.FullName, (Protect-Text $artifactText))
            $cleanupFailure = "$cleanupFailure Secret detected and redacted.".Trim()
        }
    }
    Save-Json 'result.json' @{ run_id = $runId; scenario = $Scenario; injected_failure_after = $InjectFailureAfter; failure = $failure; cleanup_failure = $cleanupFailure;
        passed = ($null -eq $failure -and $null -eq $cleanupFailure); finished_utc = [DateTime]::UtcNow.ToString('o') }
    $testPassword = $null; $testAppKey = $null; $fixturePassword = $null
}
Write-Output "REV_CREATE_UI evidence: $evidence"
if ($failure -or $cleanupFailure) { throw "REV_CREATE_UI failed: $failure Cleanup: $cleanupFailure" }
Write-Output 'REV_CREATE_UI browser environment and cleanup verified.'
