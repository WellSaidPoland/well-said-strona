<?php
/**
 * WELL SAID — obsługa formularza kontaktowego (well-said.pl)
 *
 * Wysyła wiadomość z formularza na kontakt@well-said.pl przez serwer SMTP OVH.
 * Temat zawsze zaczyna się od „[Formularz WellSaid]” (reguła w Outlooku).
 *
 * HASŁO DO SKRZYNKI NIE JEST W TYM PLIKU.
 * Leży w pliku wellsaid-config.php na serwerze, POZA folderem strony
 * (dwa poziomy wyżej niż ten plik), i nie trafia do GitHuba.
 * Wzór pliku: api/config.example.php
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(bool $ok, string $error = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'Niedozwolona metoda.', 405);
}

/* ---------- konfiguracja ---------- */
$configFile = dirname(__DIR__, 2) . '/wellsaid-config.php';
if (!is_file($configFile)) {
    error_log('[WellSaid] Brak pliku konfiguracyjnego: ' . $configFile);
    respond(false, '', 500);
}
$cfg = require $configFile;

/* ---------- ochrona przed spamem ---------- */
$field = static fn(string $k): string => trim((string)($_POST[$k] ?? ''));

// 1) pole-pułapka: człowiek go nie widzi, bot je wypełnia
if ($field('website') !== '') {
    respond(true); // udajemy sukces, nic nie wysyłamy
}

// 2) zbyt szybkie wysłanie (bot) albo formularz sprzed doby
$t = (int)$field('t');
$ageSec = (int)floor((microtime(true) * 1000 - $t) / 1000);
if ($t <= 0 || $ageSec < 3 || $ageSec > 86400) {
    respond(false, 'Coś poszło nie tak. Odśwież stronę i spróbuj ponownie.', 400);
}

// 3) limit: maks. 5 wiadomości na godzinę z jednego adresu IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rlDir = $cfg['rate_dir'] ?? (dirname(__DIR__, 2) . '/wellsaid-limits');
if (!is_dir($rlDir)) { @mkdir($rlDir, 0700, true); }
$rlFile = $rlDir . '/' . hash('sha256', $ip . 'ws') . '.json';
$now = time();
$hits = is_file($rlFile) ? (json_decode((string)file_get_contents($rlFile), true) ?: []) : [];
$hits = array_values(array_filter($hits, static fn($x) => is_int($x) && $x > $now - 3600));
if (count($hits) >= 5) {
    respond(false, 'Wysłano już kilka wiadomości. Spróbuj ponownie za godzinę lub napisz na kontakt@well-said.pl.', 429);
}

/* ---------- walidacja danych ---------- */
$clean = static fn(string $s): string => trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '');
$oneLine = static fn(string $s): string => trim(preg_replace('/\s+/u', ' ', $s) ?? '');

$name    = $oneLine($clean($field('name')));
$email   = $oneLine($clean($field('email')));
$course  = $oneLine($clean($field('course')));
$message = $clean($field('message'));
$avail   = $oneLine($clean($field('availability')));

$courses = ['Business English', 'STANAG 6001 / Military English', 'General English', 'Jeszcze nie wiem, doradź mi'];

if (mb_strlen($name) < 3 || mb_strlen($name) > 120 || count(preg_split('/\s+/u', $name)) < 2) {
    respond(false, 'Wpisz imię i nazwisko.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
    respond(false, 'Wpisz poprawny adres e-mail.', 422);
}
if (!in_array($course, $courses, true)) {
    respond(false, 'Wybierz jedną z opcji.', 422);
}
if ($message === '' || mb_strlen($message) > 1000) {
    respond(false, 'Napisz kilka słów o sobie i swoich celach (maks. 1000 znaków).', 422);
}
if (mb_strlen($avail) > 1500) {
    $avail = mb_substr($avail, 0, 1500) . '…';
}

/* ---------- treść maila ---------- */
$courseShort = [
    'Business English' => 'Business English',
    'STANAG 6001 / Military English' => 'Military English',
    'General English' => 'General English',
    'Jeszcze nie wiem, doradź mi' => 'Doradź mi',
][$course];

$subject = '[Formularz WellSaid] ' . $courseShort . ' — ' . $name;
$date = (new DateTime('now', new DateTimeZone('Europe/Warsaw')))->format('d.m.Y, H:i');
$availTxt = $avail !== '' ? $avail : '— (nie wskazano)';

$text = "Nowa wiadomość z formularza na well-said.pl\n\n"
      . "Imię i nazwisko: $name\n"
      . "E-mail: $email\n"
      . "Kurs: $course\n"
      . "Terminy: $availTxt\n"
      . "Data wysłania: $date\n\n"
      . "Wiadomość:\n$message\n\n"
      . "— Aby odpowiedzieć, kliknij „Odpowiedz” — mail trafi prosto do nadawcy.\n";

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$row = static fn(string $k, string $v): string =>
    '<tr><td style="padding:8px 16px 8px 0;color:#6f6b67;font-size:13px;letter-spacing:.08em;vertical-align:top;white-space:nowrap">' . $k . '</td>'
  . '<td style="padding:8px 0;color:#14213d;font-size:15px">' . $v . '</td></tr>';

$html = '<!doctype html><html lang="pl"><body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif">'
      . '<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #d9dce3">'
      . '<div style="background:#0c1428;color:#f4f5f7;padding:18px 24px;font-family:Georgia,serif;letter-spacing:.3em;font-size:15px">WELL SAID · FORMULARZ</div>'
      . '<div style="padding:20px 24px"><table role="presentation" style="border-collapse:collapse">'
      . $row('IMIĘ I NAZWISKO', $e($name))
      . $row('E-MAIL', '<a href="mailto:' . $e($email) . '" style="color:#14213d">' . $e($email) . '</a>')
      . $row('KURS', $e($course))
      . $row('TERMINY', $e($availTxt))
      . $row('WYSŁANO', $e($date))
      . '</table>'
      . '<div style="margin-top:18px;padding-top:16px;border-top:1px solid #e7e9ee;color:#3c3938;font-size:15px;line-height:1.6;white-space:pre-wrap">' . $e($message) . '</div>'
      . '<p style="margin-top:22px;color:#7d8699;font-size:12px">Kliknij „Odpowiedz” — odpowiedź trafi prosto do nadawcy.</p>'
      . '</div></div></body></html>';

/* ---------- wysyłka (PHPMailer + SMTP OVH) ---------- */
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
        $mail->isMail(); // tylko awaryjnie / do testów
    }

    $mail->setFrom($cfg['from'] ?? $cfg['smtp_user'], 'Formularz WELL SAID');
    $mail->addAddress($cfg['to'] ?? 'kontakt@well-said.pl', 'WELL SAID');
    $mail->addReplyTo($email, $name);

    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body    = $html;
    $mail->AltBody = $text;

    $mail->send();
} catch (MailException $ex) {
    error_log('[WellSaid] Błąd wysyłki: ' . ($mail->ErrorInfo ?? $ex->getMessage()));
    respond(false, '', 502);
}

$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

respond(true);
