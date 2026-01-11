<?php
// Simple front controller / router for native PHP (mysqli)
// PHP 7+ compatible

declare(strict_types=1);

require_once __DIR__ . '/../src/config/env.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Response.php';
require_once __DIR__ . '/../src/core/Validator.php';
require_once __DIR__ . '/../src/core/Audit.php';

use Core\Database;
use Core\Response;
use Core\Validator;
use Core\Audit;

// Sessions for login
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Set PHP timezone per config to align date filters
if (!empty(ENV['TIMEZONE'])) { @date_default_timezone_set(ENV['TIMEZONE']); }

// Decide content type lazily. Default JSON for API; HTML for landing.
header('Content-Type: application/json; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// Normalize URI: remove base dir (e.g., /nak_jual/apotik/public) and trailing slash
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = rtrim(str_replace('\\','/', dirname($scriptName)), '/');
if ($baseDir && strpos($reqPath, $baseDir) === 0) {
    $uri = substr($reqPath, strlen($baseDir));
} else {
    $uri = $reqPath;
}
if ($uri === false || $uri === '') { $uri = '/'; }
$uri = rtrim($uri, '/'); if ($uri === '') { $uri = '/'; }

$db = Database::instance();

// Current user from session
$currentUser = null;
if (!empty($_SESSION['uid'])) {
    $currentUser = [
        'id' => (int)$_SESSION['uid'],
        'username' => (string)($_SESSION['username'] ?? ''),
        'nama' => (string)($_SESSION['nama'] ?? ''),
        'role' => (string)($_SESSION['role'] ?? 'apoteker'),
    ];
}

function require_auth() {
    global $currentUser; if (!$currentUser) { Core\Response::json(['error'=>'Unauthorized'],401); }
}
function require_role(array $roles) {
    global $currentUser; if (!$currentUser) { Core\Response::json(['error'=>'Unauthorized'],401); }
    if (!in_array((string)$currentUser['role'], $roles, true)) { Core\Response::json(['error'=>'Forbidden'],403); }
}

// Auth placeholder (basic apikey via header). Extend later with session/JWT.
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (ENV['REQUIRE_API_KEY'] && $apiKey !== ENV['API_KEY']) {
    Response::json(['error' => 'Unauthorized'], 401);
}

// Auth endpoints
if ($uri === '/api/login' && $method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $u = trim((string)($in['username'] ?? ''));
    $p = (string)($in['password'] ?? '');
    if ($u === '' || $p === '') Response::json(['error'=>'Username/password wajib'],422);
    $st = Database::execute('SELECT id,username,nama,role,password_hash FROM users WHERE username=? LIMIT 1','s',[ $u ]);
    $row = $st->get_result()->fetch_assoc();
    if (!$row) Response::json(['error'=>'User tidak ditemukan'],401);
    $md5 = md5($p);
    if (!hash_equals((string)$row['password_hash'], $md5)) Response::json(['error'=>'Password salah'],401);
    $_SESSION['uid'] = (int)$row['id'];
    $_SESSION['username'] = (string)$row['username'];
    $_SESSION['nama'] = (string)$row['nama'];
    $_SESSION['role'] = (string)$row['role'];
    Response::json(['message'=>'OK','user'=>[ 'id'=>(int)$row['id'],'username'=>$row['username'],'nama'=>$row['nama'],'role'=>$row['role'] ]]);
}
if ($uri === '/api/logout' && $method === 'POST') {
    session_destroy(); Response::json(['message'=>'OK']);
}
if ($uri === '/api/me' && $method === 'GET') {
    if (!$currentUser) Response::json(['user'=>null],200);
    Response::json(['user'=>$currentUser]);
}

// Minimal dashboard metrics
if ($uri === '/api/dashboard' && $method === 'GET') {
    require_auth();
    $penj = Database::select('SELECT COUNT(*) cnt, COALESCE(SUM(total),0) total FROM penjualan WHERE DATE(tgl)=CURDATE()');
    $pemb = Database::select('SELECT COALESCE(SUM(total),0) total FROM pembelian WHERE tgl=CURDATE()');
    $low = Database::select('SELECT COUNT(*) low FROM m_obat WHERE stok <= 5');
    // last 7 days sales
    $last7 = Database::select("SELECT DATE(tgl) d, COALESCE(SUM(total),0) total FROM penjualan WHERE tgl >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(tgl) ORDER BY d");
    // last 7 days purchases
    $last7Buy = Database::select("SELECT tgl d, COALESCE(SUM(total),0) total FROM pembelian WHERE tgl >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY tgl ORDER BY tgl");
    // recent penjualan, low stock list, top selling
    $recent = Database::select('SELECT no_nota,total,tgl FROM penjualan ORDER BY id DESC LIMIT 5');
    $lowList = Database::select('SELECT kode,nama,stok FROM m_obat WHERE stok <= 5 ORDER BY stok ASC, nama LIMIT 5');
    $top7 = Database::select('SELECT i.obat_kode as kode, o.nama, SUM(i.qty) qty, SUM(i.qty*i.harga_jual) omzet FROM penjualan p JOIN penjualan_item i ON i.penjualan_id=p.id JOIN m_obat o ON o.kode=i.obat_kode WHERE DATE(p.tgl) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE() GROUP BY i.obat_kode,o.nama ORDER BY qty DESC LIMIT 5');
    Response::json([
        'today_penjualan_count' => (int)($penj[0]['cnt'] ?? 0),
        'today_penjualan_total' => (float)($penj[0]['total'] ?? 0),
        'today_pembelian_total' => (float)($pemb[0]['total'] ?? 0),
        'low_stock_count' => (int)($low[0]['low'] ?? 0),
        'series_last7' => $last7,
        'series_last7_buy' => $last7Buy,
        'recent_penjualan' => $recent,
        'low_stock_items' => $lowList,
        'top_selling_last7' => $top7,
        'user' => $currentUser,
    ]);
}

// Guard mutating API calls (except login)
if (strpos($uri, '/api/') === 0 && in_array($method, ['POST','PUT','PATCH','DELETE'], true) && $uri !== '/api/login') {
    if (!$currentUser) { Response::json(['error'=>'Unauthorized'],401); }
}

// Route definitions (minimal MVP)
// Master data endpoints
if ($uri === '/api/obat' && $method === 'GET') {
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 20;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $limit;
    if ($q !== '') {
        $like = '%'.$q.'%';
        $rows = Database::select('SELECT kode,nama,produsen,harga,golongan,stok,expired_date FROM m_obat WHERE kode LIKE ? OR nama LIKE ? ORDER BY nama LIMIT ? OFFSET ?','ssii',[ $like, $like, $limit, $offset ]);
        Response::json($rows);
    }
    $rows = Database::select('SELECT * FROM m_obat ORDER BY nama LIMIT ? OFFSET ?','ii',[ $limit, $offset ]);
    Response::json($rows);
}

if ($uri === '/api/obat' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $rules = [
        'kode' => 'nullable|max:50',
        'nama' => 'required|max:255',
        'produsen' => 'nullable|max:255',
        'harga' => 'required|numeric|min:0',
        'stok' => 'required|integer|min:0',
        'expired_date' => 'nullable|date',
        'golongan' => 'required|in:NONE,OTC,OBT,OK,Psikotropika,Narkotika'
    ];
    $err = Validator::make($input, $rules);
    if ($err) Response::json(['errors' => $err], 422);

    // Auto-generate kode if empty
    $kode = isset($input['kode']) ? trim((string)$input['kode']) : '';
    if ($kode === '') {
        $attempts = 0; $generated = null;
        while ($attempts++ < 6) {
            $cand = 'OB'.strtoupper(bin2hex(random_bytes(3))); // e.g., OB4F8A1C
            $st = Database::execute('SELECT 1 FROM m_obat WHERE kode=? LIMIT 1', 's', [ $cand ]);
            if (!$st->get_result()->fetch_row()) { $generated = $cand; break; }
        }
        if (!$generated) Response::json(['error'=>'Gagal generate kode obat'], 500);
        $kode = $generated;
    }

    $sql = 'INSERT INTO m_obat (kode,nama,produsen,harga,stok,expired_date,golongan,created_at) VALUES (?,?,?,?,?,?,?,NOW())';
    Database::execute($sql, 'sssdiss', [
        $kode,
        $input['nama'],
        $input['produsen'] ?? null,
        (float)$input['harga'],
        (int)$input['stok'],
        $input['expired_date'] ?? null,
        $input['golongan'],
    ]);
    Audit::log(null, 'CREATE', 'm_obat', (string)$kode, $input);
    Response::json(['message' => 'Created', 'kode' => $kode], 201);
}

if (preg_match('#^/api/obat/([A-Za-z0-9_-]+)$#', $uri, $m)) {
    $kode = $m[1];
    if ($method === 'GET') {
    $stmt = Database::execute('SELECT * FROM m_obat WHERE kode=? LIMIT 1', 's', [$kode]);
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::json(['error' => 'Not found'], 404);
        Response::json($row);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $allowed = ['nama','produsen','harga','stok','expired_date','golongan'];
        $fields = [];$params=[];$types='';
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $fields[] = "$f = ?";
                $params[] = $input[$f];
                $types .= ($f==='harga'?'d':($f==='stok'?'i':'s'));
            }
        }
        if (!$fields) Response::json(['error'=>'No changes'], 400);
        $params[] = $kode; $types.='s';
    $sql = 'UPDATE m_obat SET '.implode(',', $fields).', updated_at=NOW() WHERE kode=?';
        Database::execute($sql, $types, $params);
        Audit::log(null, 'UPDATE', 'm_obat', (string)$kode, $input);
        Response::json(['message' => 'Updated']);
    }
    if ($method === 'DELETE') {
        Database::execute('DELETE FROM m_obat WHERE kode=?', 's', [$kode]);
        Audit::log(null, 'DELETE', 'm_obat', (string)$kode, []);
        Response::json(['message' => 'Deleted']);
    }
}

// Pelanggan endpoints (basic)
if ($uri === '/api/pelanggan' && $method === 'GET') {
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 20;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $limit;
    $rows = Database::select('SELECT kode,nama,tgl_lahir,jenis_kelamin,no_hp,alamat FROM m_pelanggan ORDER BY nama LIMIT ? OFFSET ?','ii',[ $limit, $offset ]);
    Response::json($rows);
}

if ($uri === '/api/pelanggan' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $rules = [
        'kode' => 'nullable|max:50',
        'nama' => 'required|max:150',
        'tgl_lahir' => 'nullable|date',
        'jenis_kelamin' => 'nullable|in:L,P',
        'no_hp' => 'nullable|max:30',
        'alamat' => 'nullable|max:255'
    ];
    $err = Validator::make($input, $rules);
    if ($err) Response::json(['errors'=>$err], 422);
    // Auto-generate kode pelanggan if empty: PLXXXXXX (hex)
    $plKode = isset($input['kode']) ? trim((string)$input['kode']) : '';
    if ($plKode === '') {
        $attempts = 0; $generated = null;
        while ($attempts++ < 6) {
            $cand = 'PL'.strtoupper(bin2hex(random_bytes(3))); // e.g., PL4F8A1C
            $st = Database::execute('SELECT 1 FROM m_pelanggan WHERE kode=? LIMIT 1', 's', [ $cand ]);
            if (!$st->get_result()->fetch_row()) { $generated = $cand; break; }
        }
        if (!$generated) Response::json(['error'=>'Gagal generate kode pelanggan'], 500);
        $plKode = $generated;
    }
    Database::execute(
        'INSERT INTO m_pelanggan (kode,nama,tgl_lahir,jenis_kelamin,no_hp,alamat,created_at) VALUES (?,?,?,?,?,?,NOW())',
        'ssssss',
        [ $plKode, $input['nama'], $input['tgl_lahir'] ?? null, $input['jenis_kelamin'] ?? null, $input['no_hp'] ?? null, $input['alamat'] ?? null ]
    );
    Audit::log(null, 'CREATE', 'm_pelanggan', (string)$plKode, $input);
    Response::json(['message' => 'Created', 'kode'=>$plKode], 201);
}

if (preg_match('#^/api/pelanggan/([A-Za-z0-9_-]+)$#', $uri, $m)) {
    $kode = $m[1];
    if ($method === 'GET') {
        $stmt = Database::execute('SELECT * FROM m_pelanggan WHERE kode=? LIMIT 1', 's', [$kode]);
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) Response::json(['error'=>'Not found'], 404);
        Response::json($row);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $allowed = ['nama','tgl_lahir','jenis_kelamin','alergi','kondisi_medis','no_hp','alamat'];
        $fields=[];$params=[];$types='';
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) { $fields[] = "$f=?"; $params[]=$input[$f]; $types.='s'; }
        }
        if (!$fields) Response::json(['error'=>'No changes'], 400);
        $params[]=$kode; $types.='s';
        Database::execute('UPDATE m_pelanggan SET '.implode(',', $fields).', updated_at=NOW() WHERE kode=?', $types, $params);
        Audit::log(null, 'UPDATE', 'm_pelanggan', (string)$kode, $input);
        Response::json(['message'=>'Updated']);
    }
    if ($method === 'DELETE') {
        Database::execute('DELETE FROM m_pelanggan WHERE kode=?', 's', [$kode]);
        Audit::log(null, 'DELETE', 'm_pelanggan', (string)$kode, []);
        Response::json(['message'=>'Deleted']);
    }
}

// Dokter endpoints
if ($uri === '/api/dokter' && $method === 'GET') {
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 20; $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit;
    Response::json(Database::select('SELECT sip,nama,spesialisasi,fasilitas_kesehatan FROM m_dokter ORDER BY nama LIMIT ? OFFSET ?','ii',[ $limit, $offset ]));
}
if ($uri === '/api/dokter' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $rules = [ 'sip'=>'nullable|max:100', 'nama'=>'required|max:150', 'spesialisasi'=>'nullable|max:150', 'fasilitas_kesehatan'=>'nullable|max:150' ];
    $err = Validator::make($input, $rules); if ($err) Response::json(['errors'=>$err], 422);
    // Auto-generate SIP if empty: DRXXXXXX (hex)
    $sip = isset($input['sip']) ? trim((string)$input['sip']) : '';
    if ($sip === '') {
        $attempts = 0; $generated = null;
        while ($attempts++ < 6) {
            $cand = 'DR'.strtoupper(bin2hex(random_bytes(3)));
            $st = Database::execute('SELECT 1 FROM m_dokter WHERE sip=? LIMIT 1','s',[ $cand ]);
            if (!$st->get_result()->fetch_row()) { $generated = $cand; break; }
        }
        if (!$generated) Response::json(['error'=>'Gagal generate SIP dokter'],500);
        $sip = $generated;
    }
    Database::execute('INSERT INTO m_dokter (sip,nama,spesialisasi,fasilitas_kesehatan,created_at) VALUES (?,?,?,?,NOW())','ssss',[ $sip,$input['nama'],$input['spesialisasi']??null,$input['fasilitas_kesehatan']??null ]);
    Audit::log(null, 'CREATE', 'm_dokter', (string)$sip, $input);
    Response::json(['message'=>'Created','sip'=>$sip],201);
}
if (preg_match('#^/api/dokter/([A-Za-z0-9_-]+)$#', $uri, $m)) {
    $sip = $m[1];
    if ($method==='GET') {
        $stmt = Database::execute('SELECT * FROM m_dokter WHERE sip=? LIMIT 1','s',[$sip]);
        $row = $stmt->get_result()->fetch_assoc();
        if(!$row) Response::json(['error'=>'Not found'],404);
        Response::json($row);
    }
    if ($method==='PUT' || $method==='PATCH') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $allowed = ['nama','spesialisasi','fasilitas_kesehatan'];
        $fields=[];$params=[];$types='';
        foreach ($allowed as $f) if (array_key_exists($f,$input)) { $fields[]="$f=?"; $params[]=$input[$f]; $types.='s'; }
        if (!$fields) Response::json(['error'=>'No changes'],400);
        $params[]=$sip; $types.='s';
        Database::execute('UPDATE m_dokter SET '.implode(',', $fields).', updated_at=NOW() WHERE sip=?', $types, $params);
        Audit::log(null, 'UPDATE', 'm_dokter', (string)$sip, $input);
        Response::json(['message'=>'Updated']);
    }
    if ($method==='DELETE') {
        Database::execute('DELETE FROM m_dokter WHERE sip=?','s',[$sip]);
        Audit::log(null, 'DELETE', 'm_dokter', (string)$sip, []);
        Response::json(['message'=>'Deleted']);
    }
}

// Health check
if ($uri === '/api/health') {
    Response::json(['status' => 'ok', 'time' => date('c')]);
}

// Simple landing redirect to app UI
if ($uri === '/' || $uri === '/index.php') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=./app/login.html" /></head><body>Memuat UI...</body></html>';
    exit;
}

// Supplier endpoints
if ($uri === '/api/supplier' && $method === 'GET') {
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 20; $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit;
    Response::json(Database::select('SELECT kode,nama,sertifikasi,alamat,kontak FROM m_supplier ORDER BY nama LIMIT ? OFFSET ?','ii',[ $limit, $offset ]));
}
if ($uri === '/api/supplier' && $method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $rules = [ 'kode'=>'nullable|max:50','nama'=>'required|max:200','sertifikasi'=>'nullable|max:200','alamat'=>'nullable','kontak'=>'nullable|max:100' ];
    $err = Validator::make($in,$rules); if($err) Response::json(['errors'=>$err],422);
    $kode = isset($in['kode']) ? trim((string)$in['kode']) : '';
    if ($kode === '') {
        $attempts = 0; $gen = null;
        while ($attempts++ < 6) {
            $cand = 'SP'.date('ymdHis').($attempts>1 ? strtoupper(bin2hex(random_bytes(1))) : '');
            $st = Database::execute('SELECT 1 FROM m_supplier WHERE kode=? LIMIT 1','s',[ $cand ]);
            if (!$st->get_result()->fetch_row()) { $gen = $cand; break; }
            usleep(100000);
        }
        if (!$gen) Response::json(['error'=>'Gagal generate kode supplier'],500);
        $kode = $gen;
    }
    Database::execute('INSERT INTO m_supplier (kode,nama,sertifikasi,alamat,kontak,created_at) VALUES (?,?,?,?,?,NOW())','sssss',[ $kode,$in['nama'],$in['sertifikasi']??null,$in['alamat']??null,$in['kontak']??null ]);
    Audit::log(null,'CREATE','m_supplier',$kode,$in);
    Response::json(['message'=>'Created','kode'=>$kode],201);
}
if (preg_match('#^/api/supplier/([A-Za-z0-9_-]+)$#',$uri,$m)) {
    $kode=$m[1];
    if($method==='GET'){ $st=Database::execute('SELECT * FROM m_supplier WHERE kode=?','s',[$kode]); $row=$st->get_result()->fetch_assoc(); if(!$row) Response::json(['error'=>'Not found'],404); Response::json($row);}    
    if($method==='PUT' || $method==='PATCH'){
        $in=json_decode(file_get_contents('php://input'),true)?:[]; $fields=[];$params=[];$types=''; foreach(['nama','sertifikasi','alamat','kontak'] as $f){ if(array_key_exists($f,$in)){ $fields[]="$f=?"; $params[]=$in[$f]; $types.='s'; }} if(!$fields) Response::json(['error'=>'No changes'],400); $params[]=$kode; $types.='s'; Database::execute('UPDATE m_supplier SET '.implode(',', $fields).', updated_at=NOW() WHERE kode=?',$types,$params); Audit::log(null,'UPDATE','m_supplier',$kode,$in); Response::json(['message'=>'Updated']);
    }
    if($method==='DELETE'){ Database::execute('DELETE FROM m_supplier WHERE kode=?','s',[$kode]); Audit::log(null,'DELETE','m_supplier',$kode,[]); Response::json(['message'=>'Deleted']); }
}

// Karyawan endpoints
if ($uri === '/api/karyawan' && $method === 'GET') { Response::json(Database::select('SELECT kode,nama,jabatan,level_akses FROM m_karyawan ORDER BY nama LIMIT 100')); }
if ($uri === '/api/karyawan' && $method === 'GET') {
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 20; $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit;
    Response::json(Database::select('SELECT kode,nama,jabatan,level_akses FROM m_karyawan ORDER BY nama LIMIT ? OFFSET ?','ii',[ $limit, $offset ]));
}
if ($uri === '/api/karyawan' && $method === 'POST') {
    $in=json_decode(file_get_contents('php://input'), true)?:[]; $rules=[ 'kode'=>'nullable|max:50','nama'=>'required|max:150','jabatan'=>'required|max:100','level_akses'=>'required|in:farmasi,kasir,gudang,admin' ]; $err=Validator::make($in,$rules); if($err) Response::json(['errors'=>$err],422);
    $kode = isset($in['kode']) ? trim((string)$in['kode']) : '';
    if ($kode === '') {
        $attempts = 0; $gen = null;
        while ($attempts++ < 6) {
            $cand = 'KY'.date('ymdHis').($attempts>1 ? strtoupper(bin2hex(random_bytes(1))) : '');
            $st = Database::execute('SELECT 1 FROM m_karyawan WHERE kode=? LIMIT 1','s',[ $cand ]);
            if (!$st->get_result()->fetch_row()) { $gen = $cand; break; }
            usleep(100000);
        }
        if (!$gen) Response::json(['error'=>'Gagal generate kode karyawan'],500);
        $kode = $gen;
    }
    Database::execute('INSERT INTO m_karyawan (kode,nama,jabatan,level_akses,created_at) VALUES (?,?,?,?,NOW())','ssss',[ $kode,$in['nama'],$in['jabatan'],$in['level_akses'] ]);
    Audit::log(null,'CREATE','m_karyawan',$kode,$in); Response::json(['message'=>'Created','kode'=>$kode],201);
}
if (preg_match('#^/api/karyawan/([A-Za-z0-9_-]+)$#',$uri,$m)) {
    $kode=$m[1]; if($method==='GET'){ $st=Database::execute('SELECT * FROM m_karyawan WHERE kode=?','s',[$kode]); $row=$st->get_result()->fetch_assoc(); if(!$row) Response::json(['error'=>'Not found'],404); Response::json($row);} if($method==='PUT' || $method==='PATCH'){ $in=json_decode(file_get_contents('php://input'),true)?:[]; $fields=[];$params=[];$types=''; foreach(['nama','jabatan','level_akses'] as $f){ if(array_key_exists($f,$in)){ $fields[]="$f=?"; $params[]=$in[$f]; $types.='s'; } } if(!$fields) Response::json(['error'=>'No changes'],400); $params[]=$kode; $types.='s'; Database::execute('UPDATE m_karyawan SET '.implode(',', $fields).', updated_at=NOW() WHERE kode=?',$types,$params); Audit::log(null,'UPDATE','m_karyawan',$kode,$in); Response::json(['message'=>'Updated']); } if($method==='DELETE'){ Database::execute('DELETE FROM m_karyawan WHERE kode=?','s',[$kode]); Audit::log(null,'DELETE','m_karyawan',$kode,[]); Response::json(['message'=>'Deleted']); }
}

// Formula racik endpoints
if ($uri === '/api/formula' && $method === 'GET') { $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 20; $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit; Response::json(Database::select('SELECT kode,nama,dokter_sip,standar FROM m_formula_racik ORDER BY nama LIMIT ? OFFSET ?','ii',[ $limit, $offset ])); }
if ($uri === '/api/formula' && $method === 'POST') {
    $in=json_decode(file_get_contents('php://input'), true)?:[]; $rules=[ 'kode'=>'nullable|max:50','nama'=>'required|max:150','dokter_sip'=>'nullable|max:100','komposisi'=>'nullable','petunjuk'=>'nullable','standar'=>'nullable' ]; $err=Validator::make($in,$rules); if($err) Response::json(['errors'=>$err],422);
    $kode = isset($in['kode']) ? trim((string)$in['kode']) : '';
    if ($kode === '') {
        $attempts = 0; $gen = null;
        while ($attempts++ < 6) {
            $cand = 'FR'.date('ymdHis').($attempts>1 ? strtoupper(bin2hex(random_bytes(1))) : '');
            $st = Database::execute('SELECT 1 FROM m_formula_racik WHERE kode=? LIMIT 1','s',[ $cand ]);
            if (!$st->get_result()->fetch_row()) { $gen = $cand; break; }
            usleep(100000);
        }
        if (!$gen) Response::json(['error'=>'Gagal generate kode formula'],500);
        $kode = $gen;
    }
    $komp = isset($in['komposisi']) ? json_encode($in['komposisi'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null; Database::execute('INSERT INTO m_formula_racik (kode,nama,dokter_sip,komposisi,petunjuk,standar,created_at) VALUES (?,?,?,?,?,?,NOW())','sssssi',[ $kode,$in['nama'],$in['dokter_sip']??null,$komp,$in['petunjuk']??null, !empty($in['standar'])?1:0 ]); Audit::log(null,'CREATE','m_formula_racik',$kode,$in); Response::json(['message'=>'Created','kode'=>$kode],201);
}
if (preg_match('#^/api/formula/([A-Za-z0-9_-]+)$#',$uri,$m)) {
    $kode=$m[1]; if($method==='GET'){ $st=Database::execute('SELECT * FROM m_formula_racik WHERE kode=?','s',[$kode]); $row=$st->get_result()->fetch_assoc(); if(!$row) Response::json(['error'=>'Not found'],404); Response::json($row);} if($method==='PUT' || $method==='PATCH'){ $in=json_decode(file_get_contents('php://input'),true)?:[]; $fields=[];$params=[];$types=''; foreach(['nama','dokter_sip','petunjuk','standar'] as $f){ if(array_key_exists($f,$in)){ $fields[]="$f=?"; $params[]=( $f==='standar' ? (!empty($in[$f])?1:0) : $in[$f]); $types.= ($f==='standar'?'i':'s'); } } if(array_key_exists('komposisi',$in)){ $fields[]='komposisi=?'; $params[]= json_encode($in['komposisi'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $types.='s'; } if(!$fields) Response::json(['error'=>'No changes'],400); $params[]=$kode; $types.='s'; Database::execute('UPDATE m_formula_racik SET '.implode(',', $fields).', updated_at=NOW() WHERE kode=?',$types,$params); Audit::log(null,'UPDATE','m_formula_racik',$kode,$in); Response::json(['message'=>'Updated']); } if($method==='DELETE'){ Database::execute('DELETE FROM m_formula_racik WHERE kode=?','s',[$kode]); Audit::log(null,'DELETE','m_formula_racik',$kode,[]); Response::json(['message'=>'Deleted']); }
}

// Interaksi obat endpoints (normalize pair order)
if ($uri === '/api/interaksi' && $method === 'GET') { $limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 50; $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit; Response::json(Database::select('SELECT * FROM interaksi_obat ORDER BY id DESC LIMIT ? OFFSET ?','ii',[ $limit, $offset ])); }
if ($uri === '/api/interaksi' && $method === 'POST') {
    $in=json_decode(file_get_contents('php://input'), true)?:[]; $rules=[ 'obat1_kode'=>'required','obat2_kode'=>'required','tingkat'=>'required|in:minor,moderate,major,contraindicated','keterangan'=>'nullable' ]; $err=Validator::make($in,$rules); if($err) Response::json(['errors'=>$err],422); $a=(string)$in['obat1_kode']; $b=(string)$in['obat2_kode']; if ($a===$b) Response::json(['errors'=>['pair'=>'Obat tidak boleh sama']],422); $p = [$a,$b]; sort($p, SORT_STRING); Database::execute('INSERT INTO interaksi_obat (obat1_kode,obat2_kode,tingkat,keterangan,created_at) VALUES (?,?,?,?,NOW())','ssss',[ $p[0],$p[1],$in['tingkat'],$in['keterangan']??null ]); Audit::log(null,'CREATE','interaksi_obat',$p[0].'+'.$p[1],$in); Response::json(['message'=>'Created'],201);
}
// Interaksi detail routes (edit/delete)
if (preg_match('#^/api/interaksi/(\d+)$#',$uri,$m)) {
    $id = (int)$m[1];
    if ($method === 'GET') { $st=Database::execute('SELECT * FROM interaksi_obat WHERE id=?','i',[ $id ]); $row=$st->get_result()->fetch_assoc(); if(!$row) Response::json(['error'=>'Not found'],404); Response::json($row); }
    if ($method === 'PUT' || $method==='PATCH') {
        $in=json_decode(file_get_contents('php://input'), true)?:[]; $fields=[];$params=[];$types='';
        if (isset($in['tingkat'])) { $fields[]='tingkat=?'; $params[]=$in['tingkat']; $types.='s'; }
        if (array_key_exists('keterangan',$in)) { $fields[]='keterangan=?'; $params[]= $in['keterangan']; $types.='s'; }
        if (!$fields) Response::json(['error'=>'No changes'],400); $params[]=$id; $types.='i';
        Database::execute('UPDATE interaksi_obat SET '.implode(',', $fields).' WHERE id=?',$types,$params);
        Audit::log(null,'UPDATE','interaksi_obat',(string)$id,$in); Response::json(['message'=>'Updated']);
    }
    if ($method === 'DELETE') { Database::execute('DELETE FROM interaksi_obat WHERE id=?','i',[ $id ]); Audit::log(null,'DELETE','interaksi_obat',(string)$id,[]); Response::json(['message'=>'Deleted']); }
}

// Reports
if ($uri === '/api/reports/penjualan' && $method === 'GET') {
    require_auth();
    $from = isset($_GET['from']) ? (string)$_GET['from'] : date('Y-m-01');
    $to = isset($_GET['to']) ? (string)$_GET['to'] : date('Y-m-d');
    $sum = Database::select('SELECT COUNT(*) trx, COALESCE(SUM(total),0) total FROM penjualan WHERE DATE(tgl) BETWEEN ? AND ?','ss',[ $from, $to ]);
    $items = Database::select('SELECT DATE(tgl) as tgl, COUNT(*) trx, COALESCE(SUM(total),0) total FROM penjualan WHERE DATE(tgl) BETWEEN ? AND ? GROUP BY DATE(tgl) ORDER BY tgl','ss',[ $from, $to ]);
    Response::json(['summary'=>$sum ? $sum[0] : ['trx'=>0,'total'=>0],'rows'=>$items,'from'=>$from,'to'=>$to]);
}
if ($uri === '/api/reports/obat-terlaris' && $method === 'GET') {
    require_auth();
    $from = isset($_GET['from']) ? (string)$_GET['from'] : date('Y-m-01');
    $to = isset($_GET['to']) ? (string)$_GET['to'] : date('Y-m-d');
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
    $rows = Database::select('SELECT i.obat_kode, o.nama, SUM(i.qty) qty, SUM(i.qty*i.harga_jual) omzet FROM penjualan p JOIN penjualan_item i ON i.penjualan_id=p.id JOIN m_obat o ON o.kode=i.obat_kode WHERE DATE(p.tgl) BETWEEN ? AND ? GROUP BY i.obat_kode,o.nama ORDER BY qty DESC LIMIT ?','ssi',[ $from, $to, $limit ]);
    Response::json($rows);
}
if ($uri === '/api/reports/jenis' && $method === 'GET') {
    require_auth();
    $from = isset($_GET['from']) ? (string)$_GET['from'] : date('Y-m-01');
    $to = isset($_GET['to']) ? (string)$_GET['to'] : date('Y-m-d');
    $rows = Database::select("SELECT jenis, COUNT(*) trx, COALESCE(SUM(total),0) total FROM penjualan WHERE DATE(tgl) BETWEEN ? AND ? GROUP BY jenis ORDER BY jenis","ss",[ $from, $to ]);
    Response::json($rows);
}
if ($uri === '/api/reports/stok-obat' && $method === 'GET') {
    require_auth();
    $rows = Database::select('SELECT kode,nama,golongan,stok,expired_date FROM m_obat ORDER BY nama');
    Response::json($rows);
}
if ($uri === '/api/reports/pelanggan' && $method === 'GET') {
    require_auth();
    $rows = Database::select('SELECT kode,nama,tgl_lahir,jenis_kelamin,no_hp,alamat FROM m_pelanggan ORDER BY nama');
    Response::json($rows);
}

// Pembelian (MVP)
if ($uri === '/api/pembelian' && $method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $rules = [ 'no_faktur'=>'nullable|max:100','tgl'=>'nullable|date','supplier_kode'=>'required','pajak'=>'nullable|numeric','items'=>'required' ];
    $err = Validator::make($in,$rules); if($err) Response::json(['errors'=>$err],422);
    $items = is_array($in['items']??null)?$in['items']:null; if(!$items || !count($items)) Response::json(['errors'=>['items'=>['Minimal 1 item']]],422);
    $tgl = !empty($in['tgl']) ? $in['tgl'] : date('Y-m-d');
    // Validate supplier exists to avoid FK failure
    $stSup = Database::execute('SELECT 1 FROM m_supplier WHERE kode=? LIMIT 1','s',[ $in['supplier_kode'] ]);
    if (!$stSup->get_result()->fetch_row()) {
        Response::json(['errors'=>['supplier_kode'=>['Supplier tidak ditemukan']]],422);
    }
    // Validate each item and obat existence, sanitize values
    $kodeSet = [];
    foreach ($items as $idx=>$it) {
        foreach (['obat_kode','batch_no','qty','harga_beli'] as $req) { if (!isset($it[$req]) || $it[$req]==='' || $it[$req]===null) Response::json(['errors'=>["items.$idx"=>["$req wajib"]]],422); }
        $items[$idx]['qty'] = (int)$it['qty']; if ($items[$idx]['qty']<=0) Response::json(['errors'=>["items.$idx"=>['qty minimal 1']]],422);
        $items[$idx]['harga_beli'] = (float)$it['harga_beli']; if ($items[$idx]['harga_beli']<0) Response::json(['errors'=>["items.$idx"=>['harga_beli tidak boleh negatif']]],422);
        // Default expired_date to +1 year from tgl if kosong
        $exp = isset($it['expired_date']) && trim((string)$it['expired_date'])!=='' ? (string)$it['expired_date'] : null;
        if ($exp === null) { $exp = date('Y-m-d', strtotime($tgl.' +1 year')); }
        $items[$idx]['expired_date'] = $exp;
        $kodeSet[$it['obat_kode']] = true;
    }
    if ($kodeSet) {
        $klist = array_keys($kodeSet); $place = implode(',', array_fill(0, count($klist), '?')); $types = str_repeat('s', count($klist));
        $stOb = Database::execute('SELECT kode FROM m_obat WHERE kode IN ('.$place.')', $types, $klist);
        $found = []; $res=$stOb->get_result(); while($r=$res->fetch_assoc()){ $found[$r['kode']]=true; }
        foreach ($items as $idx=>$it) { if (empty($found[$it['obat_kode']])) Response::json(['errors'=>["items.$idx"=>['Obat tidak ditemukan']]],422); }
    }
    // Auto-generate no_faktur if empty: PB + timestamp, ensure uniqueness with retries
    $noFak = isset($in['no_faktur']) ? trim((string)$in['no_faktur']) : '';
    if ($noFak === '') {
        $attempts = 0; $gen = null;
        while ($attempts++ < 6) {
            $cand = 'PB'.date('YmdHis').($attempts>1 ? strtoupper(bin2hex(random_bytes(1))) : '');
            $st = Database::execute('SELECT 1 FROM pembelian WHERE no_faktur=? LIMIT 1','s',[ $cand ]);
            if (!$st->get_result()->fetch_row()) { $gen = $cand; break; }
            usleep(100000); // 100ms
        }
        if (!$gen) Response::json(['error'=>'Gagal generate no_faktur'],500);
        $noFak = $gen;
    }
    Database::execute('INSERT INTO pembelian (no_faktur,tgl,supplier_kode,pajak,total,created_at) VALUES (?,?,?,?,0,NOW())','sssd',[ $noFak,$tgl,$in['supplier_kode'], (float)($in['pajak']??0) ]);
    $st = Database::execute('SELECT id FROM pembelian WHERE no_faktur=?','s',[ $noFak ]); $beli = $st->get_result()->fetch_assoc(); $beliId = (int)$beli['id'];
    $grand = 0.0;
    foreach ($items as $it) {
        // items sudah tervalidasi di atas dan expired_date sudah diisi
        $qty = (int)$it['qty']; $hb = (float)$it['harga_beli']; $grand += $qty*$hb;
        Database::execute('INSERT INTO pembelian_item (pembelian_id,obat_kode,batch_no,expired_date,qty,harga_beli,cold_chain,suhu_min,suhu_max,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())','isssididd',[ $beliId,$it['obat_kode'],$it['batch_no'],$it['expired_date'],$qty,$hb, !empty($it['cold_chain'])?1:0, isset($it['suhu_min'])?(float)$it['suhu_min']:null, isset($it['suhu_max'])?(float)$it['suhu_max']:null ]);
        // Update stok total
        Database::execute('UPDATE m_obat SET stok = stok + ?, expired_date = IF(expired_date IS NULL OR expired_date > ?, ?, expired_date), updated_at=NOW() WHERE kode=?','isss',[ $qty, $it['expired_date'], $it['expired_date'], $it['obat_kode'] ]);
        // Stok kartu
        $saldo = Database::select('SELECT saldo FROM stok_kartu WHERE obat_kode=? ORDER BY id DESC LIMIT 1','s',[ $it['obat_kode'] ]);
        $prev = $saldo && isset($saldo[0]['saldo']) ? (int)$saldo[0]['saldo'] : 0; $newSaldo = $prev + $qty;
    Database::execute('INSERT INTO stok_kartu (tgl,obat_kode,batch_no,ref_type,ref_id,qty_in,qty_out,saldo,keterangan,created_at) VALUES (NOW(),?,?,?,?,?,?,?, ?, NOW())','sssiiiis',[ $it['obat_kode'],$it['batch_no'],'BELI',$beliId,$qty,0,$newSaldo,'Pembelian' ]);
    }
    Database::execute('UPDATE pembelian SET total=? WHERE id=?','di',[ $grand, $beliId ]);
    // Hutang supplier (AP): jika bayar < total+pajak, catat saldo hutang
    $bayar = isset($in['bayar']) ? (float)$in['bayar'] : 0.0;
    $totalTagihan = $grand + (float)($in['pajak']??0);
    $saldo = max(0.0, $totalTagihan - $bayar);
    if ($saldo > 0.0) {
        Database::execute('INSERT INTO hutang_supplier (supplier_kode,pembelian_id,saldo,created_at) VALUES (?,?,?,NOW())','sid',[ $in['supplier_kode'], $beliId, $saldo ]);
    }
    Audit::log(null,'CREATE','pembelian',(string)$beliId,$in);
    Response::json(['message'=>'Created','id'=>$beliId,'no_faktur'=>$noFak,'total'=>$grand],201);
}

if ($uri === '/api/pembelian' && $method === 'GET') {
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 20; $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit;
    $rows = Database::select('SELECT p.id,p.no_faktur,p.tgl,p.supplier_kode,p.pajak,p.total, COALESCE(h.saldo,0) AS saldo, CASE WHEN COALESCE(h.saldo,0)>0 THEN "HUTANG" ELSE "LUNAS" END AS status FROM pembelian p LEFT JOIN hutang_supplier h ON h.pembelian_id=p.id ORDER BY p.id DESC LIMIT ? OFFSET ?','ii',[ $limit, $offset ]);
    Response::json($rows);
}

// Detail pembelian untuk cetak per nota
if ($uri === '/api/pembelian/detail' && $method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $no = isset($_GET['no_faktur']) ? trim((string)$_GET['no_faktur']) : '';
    if ($id<=0 && $no==='') Response::json(['error'=>'Bad request'],400);
    if ($id>0) {
        $st = Database::execute('SELECT * FROM pembelian WHERE id=? LIMIT 1','i',[ $id ]);
    } else {
        $st = Database::execute('SELECT * FROM pembelian WHERE no_faktur=? LIMIT 1','s',[ $no ]);
    }
    $h = $st->get_result()->fetch_assoc();
    if (!$h) Response::json(['error'=>'Not found'],404);
    $items = Database::select('SELECT obat_kode,batch_no,expired_date,qty,harga_beli FROM pembelian_item WHERE pembelian_id=?','i',[ (int)$h['id'] ]);
    Response::json(['header'=>$h,'items'=>$items]);
}

// Laporan pembelian: periode dan/atau per supplier
if ($uri === '/api/report/pembelian' && $method === 'GET') {
    $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
    $sup = isset($_GET['supplier_kode']) ? trim((string)$_GET['supplier_kode']) : '';
    $where = [];$params=[];$types='';
    if ($from !== '' && $to !== '') { $where[] = 'p.tgl BETWEEN ? AND ?'; $params[]=$from; $params[]=$to; $types.='ss'; }
    if ($sup !== '') { $where[] = 'p.supplier_kode = ?'; $params[]=$sup; $types.='s'; }
    $sql = 'SELECT p.id,p.no_faktur,p.tgl,p.supplier_kode,p.pajak,p.total, i.obat_kode,i.batch_no,i.qty,i.harga_beli FROM pembelian p JOIN pembelian_item i ON i.pembelian_id=p.id';
    if ($where) $sql .= ' WHERE '.implode(' AND ',$where);
    $sql .= ' ORDER BY p.tgl ASC, p.id ASC';
    $rows = Database::select($sql, $types, $params);
    Response::json($rows);
}

// Hapus pembelian (rollback stok jika memungkinkan)
if (preg_match('#^/api/pembelian/(\d+)$#', $uri, $m)) {
    $pid = (int)$m[1];
    if ($method === 'DELETE') {
        // Ambil item pembelian
        $items = Database::select('SELECT obat_kode,batch_no,qty FROM pembelian_item WHERE pembelian_id=?','i',[ $pid ]);
        if (!$items) Response::json(['error'=>'Not found'],404);
        // Cek stok cukup untuk rollback
        foreach ($items as $it) {
            $st = Database::execute('SELECT stok FROM m_obat WHERE kode=? LIMIT 1','s',[ $it['obat_kode'] ]);
            $row = $st->get_result()->fetch_assoc();
            $stok = $row ? (int)$row['stok'] : 0;
            if ($stok < (int)$it['qty']) {
                Response::json(['error'=>'Tidak bisa hapus: stok sudah terpakai','detail'=>['obat_kode'=>$it['obat_kode'],'stok'=>$stok,'butuh_rollback'=>$it['qty']]], 422);
            }
        }
        // Rollback stok dan kartu stok
        foreach ($items as $it) {
            $qty = (int)$it['qty'];
            Database::execute('UPDATE m_obat SET stok = stok - ?, updated_at=NOW() WHERE kode=?','is',[ $qty, $it['obat_kode'] ]);
            // saldo terakhir
            $saldo = Database::select('SELECT saldo FROM stok_kartu WHERE obat_kode=? ORDER BY id DESC LIMIT 1','s',[ $it['obat_kode'] ]);
            $prev = $saldo && isset($saldo[0]['saldo']) ? (int)$saldo[0]['saldo'] : 0; $newSaldo = $prev - $qty;
            Database::execute('INSERT INTO stok_kartu (tgl,obat_kode,batch_no,ref_type,ref_id,qty_in,qty_out,saldo,keterangan,created_at) VALUES (NOW(),?,?,?,?,?,?,?, ?, NOW())','sssiiiis',[ $it['obat_kode'],$it['batch_no'],'BATAL_BELI',$pid,0,$qty,$newSaldo,'Pembatalan Pembelian' ]);
        }
        Database::execute('DELETE FROM pembelian_item WHERE pembelian_id=?','i',[ $pid ]);
        // Hapus hutang supplier dan pembayaran jika ada
        $hs = Database::select('SELECT id FROM hutang_supplier WHERE pembelian_id=?','i',[ $pid ]);
        if ($hs){
            $hid = (int)$hs[0]['id'];
            // ensure payment table exists before delete
            @Database::execute('CREATE TABLE IF NOT EXISTS hutang_supplier_bayar (id INT AUTO_INCREMENT PRIMARY KEY, hutang_id INT NOT NULL, tgl DATE NOT NULL, nominal DOUBLE NOT NULL, metode VARCHAR(50) NULL, keterangan VARCHAR(255) NULL, created_at DATETIME NOT NULL)');
            Database::execute('DELETE FROM hutang_supplier_bayar WHERE hutang_id=?','i',[ $hid ]);
            Database::execute('DELETE FROM hutang_supplier WHERE id=?','i',[ $hid ]);
        }
        Database::execute('DELETE FROM pembelian WHERE id=?','i',[ $pid ]);
        Audit::log(null,'DELETE','pembelian',(string)$pid,[]);
        Response::json(['message'=>'Deleted']);
    }
}

// Kartu stok per obat
if ($uri === '/api/stok-kartu' && $method === 'GET') {
    $kode = isset($_GET['obat_kode']) ? trim((string)$_GET['obat_kode']) : '';
    if ($kode==='') Response::json(['error'=>'obat_kode wajib'],400);
    $limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 200; $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit;
    $head = Database::select('SELECT kode,nama,stok,expired_date FROM m_obat WHERE kode=?','s',[ $kode ]);
    if (!$head) Response::json(['error'=>'Obat tidak ditemukan'],404);
    $rows = Database::select('SELECT id,tgl,obat_kode,batch_no,ref_type,ref_id,qty_in,qty_out,saldo,keterangan,created_at FROM stok_kartu WHERE obat_kode=? ORDER BY id ASC LIMIT ? OFFSET ?','sii',[ $kode, $limit, $offset ]);
    Response::json(['obat'=>$head[0],'rows'=>$rows]);
}

// Hutang Supplier - list outstanding and history
if ($uri === '/api/hutang-supplier' && $method === 'GET') {
    $sup = isset($_GET['supplier_kode']) ? trim((string)$_GET['supplier_kode']) : '';
    $onlyOpen = isset($_GET['status']) ? (trim((string)$_GET['status'])==='open') : true;
    $where=[]; $types=''; $params=[];
    if ($sup!==''){ $where[]='h.supplier_kode=?'; $types.='s'; $params[]=$sup; }
    if ($onlyOpen){ $where[]='h.saldo > 0'; }
    $sql = 'SELECT h.id,h.supplier_kode,p.no_faktur,p.tgl,(p.total+p.pajak) AS tagihan,h.saldo FROM hutang_supplier h JOIN pembelian p ON p.id=h.pembelian_id';
    if ($where) $sql .= ' WHERE '.implode(' AND ',$where);
    $sql .= ' ORDER BY p.tgl ASC, h.id ASC';
    $rows = Database::select($sql, $types, $params);
    Response::json($rows);
}

// Hutang Supplier - detail by pembelian_id or hutang_id
if ($uri === '/api/hutang-supplier/detail' && $method === 'GET') {
    $pid = isset($_GET['pembelian_id']) ? (int)$_GET['pembelian_id'] : 0;
    $hid = isset($_GET['hutang_id']) ? (int)$_GET['hutang_id'] : 0;
    $hs = null;
    if ($hid>0) $hs = Database::select('SELECT * FROM hutang_supplier WHERE id=?','i',[ $hid ]);
    else if ($pid>0) $hs = Database::select('SELECT * FROM hutang_supplier WHERE pembelian_id=?','i',[ $pid ]);
    if (!$hs) Response::json(['error'=>'Not found'],404);
    $h = $hs[0];
    // payments
    @Database::execute('CREATE TABLE IF NOT EXISTS hutang_supplier_bayar (id INT AUTO_INCREMENT PRIMARY KEY, hutang_id INT NOT NULL, tgl DATE NOT NULL, nominal DOUBLE NOT NULL, metode VARCHAR(50) NULL, keterangan VARCHAR(255) NULL, created_at DATETIME NOT NULL)');
    $pays = Database::select('SELECT id,tgl,nominal,metode,keterangan,created_at FROM hutang_supplier_bayar WHERE hutang_id=? ORDER BY id ASC','i',[ (int)$h['id'] ]);
    Response::json(['hutang'=>$h,'pembayaran'=>$pays]);
}

// Hutang Supplier - pay
if (preg_match('#^/api/hutang-supplier/(\d+)/pay$#', $uri, $m) && $method === 'POST') {
    $hid = (int)$m[1];
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $nom = isset($in['nominal']) ? (float)$in['nominal'] : 0.0;
    $tgl = isset($in['tgl']) ? (string)$in['tgl'] : date('Y-m-d');
    $metode = isset($in['metode']) ? trim((string)$in['metode']) : null;
    $ket = isset($in['keterangan']) ? trim((string)$in['keterangan']) : null;
    if ($nom <= 0) Response::json(['errors'=>['nominal'=>['Harus > 0']]],422);
    $hs = Database::select('SELECT h.id,h.supplier_kode,h.saldo,p.id as pembelian_id,(p.total+p.pajak) as tagihan FROM hutang_supplier h JOIN pembelian p ON p.id=h.pembelian_id WHERE h.id=?','i',[ $hid ]);
    if (!$hs) Response::json(['error'=>'Not found'],404);
    $saldo = (float)$hs[0]['saldo'];
    if ($nom > $saldo) Response::json(['errors'=>['nominal'=>['Lebih besar dari saldo hutang']]],422);
    // Ensure payment table exists
    @Database::execute('CREATE TABLE IF NOT EXISTS hutang_supplier_bayar (id INT AUTO_INCREMENT PRIMARY KEY, hutang_id INT NOT NULL, tgl DATE NOT NULL, nominal DOUBLE NOT NULL, metode VARCHAR(50) NULL, keterangan VARCHAR(255) NULL, created_at DATETIME NOT NULL)');
    Database::execute('INSERT INTO hutang_supplier_bayar (hutang_id,tgl,nominal,metode,keterangan,created_at) VALUES (?,?,?,?,?,NOW())','isdss',[ $hid, $tgl, $nom, $metode, $ket ]);
    Database::execute('UPDATE hutang_supplier SET saldo = saldo - ? WHERE id=?','di',[ $nom, $hid ]);
    $row = Database::select('SELECT saldo FROM hutang_supplier WHERE id=?','i',[ $hid ]);
    Response::json(['message'=>'Paid','saldo'=>$row? (float)$row[0]['saldo'] : 0.0]);
}

// Penjualan (MVP) with safety checks
if ($uri === '/api/penjualan' && $method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $rules = [ 'no_nota'=>'nullable|max:100','tgl'=>'nullable','jenis'=>'required|in:NONE,OTC,Resep,Racik','pelanggan_kode'=>'nullable','dokter_sip'=>'nullable','items'=>'required' ];
    $err = Validator::make($in,$rules); if($err) Response::json(['errors'=>$err],422);
    $items = is_array($in['items']??null)?$in['items']:null; if(!$items || !count($items)) Response::json(['errors'=>['items'=>['Minimal 1 item']]],422);
    $jenis = $in['jenis'];
    if (in_array($jenis, ['Resep','Racik'], true) && empty($in['dokter_sip'])) Response::json(['errors'=>['dokter_sip'=>['Wajib untuk resep/racik']]],422);

    // Fetch patient allergy
    $alergi = null; if (!empty($in['pelanggan_kode'])) { $st=Database::execute('SELECT alergi FROM m_pelanggan WHERE kode=?','s',[ $in['pelanggan_kode'] ]); $row=$st->get_result()->fetch_assoc(); $alergi = $row ? (string)$row['alergi'] : null; }

    // Load all obat details
    $kodeList = array_map(function($it){ return $it['obat_kode']; }, $items);
    $place = implode(',', array_fill(0, count($kodeList), '?'));
    $types = str_repeat('s', count($kodeList));
    $st = Database::execute('SELECT kode,nama,stok,expired_date,golongan FROM m_obat WHERE kode IN ('.$place.')', $types, $kodeList);
    $map = []; $res = $st->get_result(); while($r=$res->fetch_assoc()){ $map[$r['kode']]=$r; }

    $errors = [];
    $narkoFlag = 0;
    $today = date('Y-m-d');
    foreach ($items as $idx=>$it) {
        $kode = $it['obat_kode'] ?? '';
        $qty = (int)($it['qty'] ?? 0);
        if (!$kode || $qty<=0) { $errors["items.$idx"][] = 'obat_kode/qty tidak valid'; continue; }
        if (!isset($map[$kode])) { $errors["items.$idx"][] = 'Obat tidak ditemukan'; continue; }
        $o = $map[$kode];
        if ((int)$o['stok'] < $qty) { $errors["items.$idx"][] = 'Stok tidak cukup'; }
        if (!empty($o['expired_date']) && $o['expired_date'] < $today) { $errors["items.$idx"][] = 'Obat kadaluarsa'; }
        if ($alergi && (stripos($alergi, $kode)!==false || stripos($alergi, $o['nama'])!==false)) { $errors["items.$idx"][] = 'Alergi pasien terdeteksi'; }
        if (in_array($o['golongan'], ['Psikotropika','Narkotika'], true)) $narkoFlag = 1;
    }
    // Interaksi obat pairwise
    for ($i=0; $i<count($kodeList); $i++) {
        for ($j=$i+1; $j<count($kodeList); $j++) {
            $a=$kodeList[$i]; $b=$kodeList[$j]; $p=[$a,$b]; sort($p,SORT_STRING);
            $st2 = Database::execute('SELECT tingkat FROM interaksi_obat WHERE obat1_kode=? AND obat2_kode=? LIMIT 1','ss',$p);
            $row2 = $st2->get_result()->fetch_assoc();
            if ($row2) {
                $t = $row2['tingkat'];
                if ($t==='contraindicated' || $t==='major') { $errors['interaksi'][] = "Interaksi $a-$b: $t"; }
            }
        }
    }
    if ($errors) Response::json(['errors'=>$errors], 422);

    // Generate nota; add small random suffix to avoid collisions on high-frequency saves
    $no = !empty($in['no_nota']) ? $in['no_nota'] : ('PJ'.date('YmdHis').substr((string)mt_rand(100,999),-3));
    $tgl = !empty($in['tgl']) ? $in['tgl'] : date('Y-m-d H:i:s');
    $createdBy = !empty($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
    Database::execute('INSERT INTO penjualan (no_nota,tgl,jenis,pelanggan_kode,dokter_sip,narkotika_psikotropika,total,created_by,created_at) VALUES (?,?,?,?,?,?,0,?,NOW())','sssssii',[ $no, $tgl, $jenis, $in['pelanggan_kode']??null, $in['dokter_sip']??null, $narkoFlag, $createdBy ]);
    $stx = Database::execute('SELECT id FROM penjualan WHERE no_nota=?','s',[ $no ]); $rowx = $stx->get_result()->fetch_assoc(); $jualId = (int)$rowx['id'];
    $grand = 0.0;
    foreach ($items as $it) {
        $kode=$it['obat_kode']; $qty=(int)$it['qty']; $harga = isset($it['harga_jual']) ? (float)$it['harga_jual'] : (float)$map[$kode]['harga'];
    Database::execute('INSERT INTO penjualan_item (penjualan_id,obat_kode,batch_no,qty,harga_jual,dosis,etiket,created_at) VALUES (?,?,?,?,?,?,?,NOW())','issidss',[ $jualId,$kode,$it['batch_no']??null,$qty,$harga,$it['dosis']??null,$it['etiket']??null ]);
        $grand += $qty*$harga;
        // Update stok total
        Database::execute('UPDATE m_obat SET stok = stok - ? WHERE kode=?','is',[ $qty, $kode ]);
        // Stok kartu
        $saldo = Database::select('SELECT saldo FROM stok_kartu WHERE obat_kode=? ORDER BY id DESC LIMIT 1','s',[ $kode ]);
        $prev = $saldo && isset($saldo[0]['saldo']) ? (int)$saldo[0]['saldo'] : 0; $newSaldo = $prev - $qty;
    Database::execute('INSERT INTO stok_kartu (tgl,obat_kode,batch_no,ref_type,ref_id,qty_in,qty_out,saldo,keterangan,created_at) VALUES (NOW(),?,?,?,?,?,?,?, ?, NOW())','sssiiiis',[ $kode,$it['batch_no']??null,'JUAL',$jualId,0,$qty,$newSaldo,'Penjualan' ]);
    }
    Database::execute('UPDATE penjualan SET total=? WHERE id=?','di',[ $grand, $jualId ]);
    Audit::log(null,'CREATE','penjualan',(string)$jualId,$in);
    Response::json(['message'=>'Created','id'=>$jualId,'no_nota'=>$no,'items_count'=>count($items),'total'=>$grand],201);
}

if ($uri === '/api/penjualan' && $method === 'GET') {
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 20; $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit;
    $tgl = isset($_GET['tgl']) ? trim((string)$_GET['tgl']) : '';
    $tglFrom = isset($_GET['tgl_from']) ? trim((string)$_GET['tgl_from']) : '';
    $tglTo = isset($_GET['tgl_to']) ? trim((string)$_GET['tgl_to']) : '';
    $sql = 'SELECT id,no_nota,tgl,jenis,pelanggan_kode,dokter_sip,narkotika_psikotropika,total FROM penjualan';
    $conds = [];$params=[];$types='';
    if ($tgl !== '') { $conds[] = 'DATE(tgl) = ?'; $params[] = $tgl; $types .= 's'; }
    elseif ($tglFrom !== '' && $tglTo !== '') { $conds[] = 'DATE(tgl) BETWEEN ? AND ?'; $params[]=$tglFrom; $params[]=$tglTo; $types.='ss'; }
    elseif ($tglFrom !== '') { $conds[] = 'DATE(tgl) >= ?'; $params[]=$tglFrom; $types.='s'; }
    elseif ($tglTo !== '') { $conds[] = 'DATE(tgl) <= ?'; $params[]=$tglTo; $types.='s'; }
    if ($conds) { $sql .= ' WHERE '.implode(' AND ', $conds); }
    $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';
    $types .= 'ii'; $params[] = $limit; $params[] = $offset;
    $rows = Database::select($sql, $types, $params);
    Response::json($rows);
}

if (preg_match('#^/api/penjualan/(\d+)$#', $uri, $m)) {
    $pid = (int)$m[1];
    if ($method === 'GET') {
        $st = Database::execute('SELECT id,no_nota,tgl,jenis,pelanggan_kode,dokter_sip,narkotika_psikotropika,total FROM penjualan WHERE id=? LIMIT 1','i',[ $pid ]);
        $hdr = $st->get_result()->fetch_assoc();
        if (!$hdr) Response::json(['error'=>'Not found'],404);
        $it = Database::select('SELECT i.id,i.obat_kode,o.nama AS obat_nama,i.batch_no,i.qty,i.harga_jual,i.dosis,i.etiket FROM penjualan_item i JOIN m_obat o ON o.kode=i.obat_kode WHERE i.penjualan_id=? ORDER BY i.id','i',[ $pid ]);
        Response::json([ 'header'=>$hdr, 'items'=>$it ]);
    }
}

if ($uri === '/api/expired' && $method === 'GET') {

    // default 30 hari
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    $days = max(1, min(365, $days)); // safety

    $sql = "
        SELECT 
            a.obat_kode,
            a.batch_no,
            b.nama,
            a.expired_date,
            DATEDIFF(a.expired_date, CURDATE()) AS sisa_hari
        FROM pembelian_item a
        JOIN m_obat b ON a.obat_kode = b.kode
        WHERE a.expired_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY a.expired_date ASC
    ";

    $rows = Database::select($sql, 'i', [$days]);
    Response::json($rows);
}

Response::json(['error' => 'Route not found', 'route' => $uri], 404);
