<?php
/**
 * WELL SAID - zapisy do zajęć grupowych STANAG 6001 (well-said.pl/military-english/zajecia-grupowe/)
 *
 * GET  -> liczba chętnych na każdy zestaw terminów (bez imion i e-maili)
 * POST -> zapisuje zgłoszenie i wysyła powiadomienie na kontakt@well-said.pl
 *
 * Zgłoszenia leżą w pliku wellsaid-grupy/zgloszenia.json POZA folderem strony
 * (obok wellsaid-config.php) i nie trafiają do GitHuba.
 * Ta sama osoba (e-mail) na tym samym poziomie = jedno zgłoszenie; ponowny zapis dopisuje nowe propozycje (bez dublowania).
 */
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

const DAYS  = ['Pn', 'Wt', 'Śr', 'Cz', 'Pt'];
const DAYSF = ['Pn' => 'poniedziałek', 'Wt' => 'wtorek', 'Śr' => 'środa', 'Cz' => 'czwartek', 'Pt' => 'piątek'];
const HOUR_MIN = 10;
const HOUR_MAX = 19;
const SEATS = 4;

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $error, int $code): void { respond(['ok' => false, 'error' => $error], $code); }

$base = dirname(__DIR__, 2);
$dataDir = $base . '/wellsaid-grupy';
$dataFile = $dataDir . '/zgloszenia.json';

function load(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
/** klucz zestawu: dni zawsze w kolejności tygodnia, np. "Pn-18|Cz-18" */
function pairKey(array $p): string {
    $a = $p[0] . '-' . $p[1]; $b = $p[2] . '-' . $p[3];
    return array_search($p[0], DAYS, true) <= array_search($p[2], DAYS, true) ? "$a|$b" : "$b|$a";
}
function summary(array $entries): array {
    $out = ['1' => [], '2' => [], '3' => []];
    foreach ($entries as $en) {
        foreach ($en['pairs'] as $p) {
            $k = pairKey($p);
            $out[(string)$en['level']][$k] = ($out[(string)$en['level']][$k] ?? 0) + 1;
        }
    }
    $res = [];
    foreach ($out as $lvl => $map) {
        arsort($map);
        $res[$lvl] = [];
        foreach ($map as $k => $n) {
            [$a, $b] = explode('|', $k);
            [$d1, $h1] = explode('-', $a); [$d2, $h2] = explode('-', $b);
            $res[$lvl][] = ['a' => [$d1, (int)$h1], 'b' => [$d2, (int)$h2], 'n' => $n];
        }
    }
    return $res;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'GET') {
    respond(['ok' => true, 'seats' => SEATS, 'levels' => summary(load($dataFile))]);
}
if ($method !== 'POST') fail('Niedozwolona metoda.', 405);

$configFile = $base . '/wellsaid-config.php';
if (!is_file($configFile)) { error_log('[WellSaid] Brak pliku konfiguracyjnego'); fail('', 500); }
$cfg = require $configFile;

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) fail('Coś poszło nie tak. Odśwież stronę i spróbuj ponownie.', 400);
$field = static fn(string $k): string => trim((string)($in[$k] ?? ''));

/* ochrona przed spamem: pole-pułapka, czas wypełnienia, limit 5 zgłoszeń na godzinę z jednego IP */
if ($field('website') !== '') respond(['ok' => true, 'seats' => SEATS, 'levels' => summary(load($dataFile))]);
$t = (int)$field('t');
$age = (int)floor((microtime(true) * 1000 - $t) / 1000);
if ($t <= 0 || $age < 3 || $age > 86400) fail('Coś poszło nie tak. Odśwież stronę i spróbuj ponownie.', 400);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rlDir = $cfg['rate_dir'] ?? ($base . '/wellsaid-limits');
if (!is_dir($rlDir)) { @mkdir($rlDir, 0700, true); }
$rlFile = $rlDir . '/g-' . hash('sha256', $ip . 'ws') . '.json';
$now = time();
$hits = is_file($rlFile) ? (json_decode((string)file_get_contents($rlFile), true) ?: []) : [];
$hits = array_values(array_filter($hits, static fn($x) => is_int($x) && $x > $now - 3600));
if (count($hits) >= 5) fail('Wysłano już kilka zgłoszeń. Spróbuj ponownie za godzinę lub napisz na kontakt@well-said.pl.', 429);

/* walidacja */
$oneLine = static fn(string $s): string => trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '') ?? '');
$name  = $oneLine($field('name'));
$email = mb_strtolower($oneLine($field('email')));
$level = (int)$field('level');

if (mb_strlen($name) < 3 || mb_strlen($name) > 120 || count(preg_split('/\s+/u', $name)) < 2) fail('Wpisz imię i nazwisko.', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) fail('Wpisz poprawny adres e-mail.', 422);
if (!in_array($level, [1, 2, 3], true)) fail('Wybierz poziom egzaminu.', 422);
if (empty($in['consent'])) fail('Zaznacz, że zapoznałeś/aś się z polityką prywatności.', 422);

$pairs = []; $seen = [];
foreach ((array)($in['pairs'] ?? []) as $p) {
    if (!is_array($p) || count($p) !== 4) fail('Uzupełnij dni i godziny w każdej propozycji.', 422);
    [$d1, $h1, $d2, $h2] = [(string)$p[0], (int)$p[1], (string)$p[2], (int)$p[3]];
    if (!in_array($d1, DAYS, true) || !in_array($d2, DAYS, true)) fail('Uzupełnij dni w każdej propozycji.', 422);
    if ($h1 < HOUR_MIN || $h1 > HOUR_MAX || $h2 < HOUR_MIN || $h2 > HOUR_MAX) fail('Uzupełnij godziny w każdej propozycji.', 422);
    if ($d1 === $d2) fail('Zajęcia nr 1 i nr 2 muszą być w różne dni.', 422);
    $pp = [$d1, $h1, $d2, $h2];
    $k = pairKey($pp);
    if (isset($seen[$k])) continue;
    $seen[$k] = true; $pairs[] = $pp;
}
if (count($pairs) < 1) fail('Dodaj co najmniej jedną propozycję terminów.', 422);
if (count($pairs) > 6) fail('Możesz dodać maksymalnie 6 propozycji.', 422);

/* zapis (z blokadą pliku) */
if (!is_dir($dataDir)) { @mkdir($dataDir, 0700, true); }
$fh = @fopen($dataFile, 'c+');
if (!$fh) { error_log('[WellSaid] Nie można otworzyć ' . $dataFile); fail('', 500); }
flock($fh, LOCK_EX);
$raw = stream_get_contents($fh);
$entries = json_decode($raw ?: '[]', true) ?: [];
$updated = false; $allPairs = $pairs;
foreach ($entries as $i => $en) {
    if ($en['email'] === $email && (int)$en['level'] === $level) {
        foreach ($en['pairs'] as $old) { if (!isset($seen[pairKey($old)])) { $seen[pairKey($old)] = true; $allPairs[] = $old; } }
        unset($entries[$i]); $updated = true;
    }
}
$allPairs = array_slice($allPairs, 0, 12);
$entries = array_values($entries);
$entries[] = ['t' => date('c'), 'name' => $name, 'email' => $email, 'level' => $level, 'pairs' => $allPairs];
ftruncate($fh, 0); rewind($fh);
fwrite($fh, json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fflush($fh); flock($fh, LOCK_UN); fclose($fh);

$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

$sum = summary($entries);

/* powiadomienie e-mail (błąd wysyłki nie blokuje zapisu) */
$counts = [];
foreach ($sum[(string)$level] as $r) $counts[$r['a'][0] . '-' . $r['a'][1] . '|' . $r['b'][0] . '-' . $r['b'][1]] = $r['n'];
$fmt = static fn(array $p): string => DAYSF[$p[0]] . ' ' . $p[1] . ':00 + ' . DAYSF[$p[2]] . ' ' . $p[3] . ':00';
$lines = []; $full = false;
foreach ($pairs as $p) {
    $n = $counts[pairKey($p)] ?? 1;
    if ($n >= SEATS) $full = true;
    $lines[] = $fmt($p) . '  ->  chętnych: ' . $n . ' / ' . SEATS . ($n >= SEATS ? '  (GRUPA KOMPLETNA)' : '');
}
$date = (new DateTime('now', new DateTimeZone('Europe/Warsaw')))->format('d.m.Y, H:i');
$subject = '[Grupy WellSaid] STANAG ' . $level . ' - ' . $name . ($full ? ' - GRUPA KOMPLETNA' : '') . ($updated ? ' (kolejne głosy)' : '');
$text = "Nowe zgłoszenie do zajęć grupowych na well-said.pl\n\n"
      . "Imię i nazwisko: $name\nE-mail: $email\nPoziom: STANAG $level\nData: $date\n"
      . ($updated ? "Ta osoba zapisała się już wcześniej - nowe propozycje dopisano do jej zgłoszenia.\n" : '')
      . "\nPropozycje terminów:\n- " . implode("\n- ", $lines) . "\n";
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$html = '<!doctype html><html lang="pl"><body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif">'
      . '<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #d9dce3">'
      . '<div style="background:#0c1428;color:#f4f5f7;padding:18px 24px;font-family:Georgia,serif;letter-spacing:.3em;font-size:15px">WELL SAID · ZAJĘCIA GRUPOWE</div>'
      . '<div style="padding:20px 24px;color:#14213d;font-size:15px;line-height:1.7">'
      . '<b>' . $e($name) . '</b> · <a href="mailto:' . $e($email) . '" style="color:#14213d">' . $e($email) . '</a><br>'
      . 'Poziom: <b>STANAG ' . $level . '</b> · ' . $e($date) . ($updated ? '<br><i>Ta osoba zapisała się już wcześniej, nowe propozycje dopisano.</i>' : '')
      . '<ul style="padding-left:18px;margin:16px 0 0">' . implode('', array_map(static fn($l) => '<li>' . $e($l) . '</li>', $lines)) . '</ul>'
      . '</div></div></body></html>';

require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';
try {
    $mail = new PHPMailer(true);
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = 'base64';
    if (($cfg['transport'] ?? 'smtp') === 'smtp') {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'] ?? 'ssl0.ovh.net';
        $mail->Port       = (int)($cfg['smtp_port'] ?? 465);
        $mail->SMTPSecure = $cfg['smtp_secure'] ?? PHPMailer::ENCRYPTION_SMTPS;
        if ($mail->SMTPSecure === '') { $mail->SMTPAutoTLS = false; }
        $mail->SMTPAuth   = $cfg['smtp_auth'] ?? true;
        $mail->Username   = $cfg['smtp_user'];
        $mail->Password   = $cfg['smtp_pass'];
        $mail->Timeout    = 20;
    } else {
        $mail->isMail();
    }
    $mail->setFrom($cfg['from'] ?? $cfg['smtp_user'], 'Zapisy WELL SAID');
    $mail->addAddress($cfg['to'] ?? 'kontakt@well-said.pl', 'WELL SAID');
    $mail->addReplyTo($email, $name);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body    = $html;
    $mail->AltBody = $text;
    $mail->send();
} catch (MailException $ex) {
    error_log('[WellSaid] Grupy - błąd wysyłki: ' . ($mail->ErrorInfo ?? $ex->getMessage()));
}

respond(['ok' => true, 'updated' => $updated, 'seats' => SEATS, 'levels' => $sum]);
