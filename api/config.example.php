<?php
/**
 * WZÓR pliku konfiguracyjnego formularza — WELL SAID
 *
 * 1. Skopiuj ten plik i nazwij kopię:  wellsaid-config.php
 * 2. Wpisz hasło do skrzynki kontakt@well-said.pl w 'smtp_pass'.
 * 3. Wgraj plik na serwer OVH do folderu GŁÓWNEGO konta hostingu
 *    (tam, gdzie widać foldery „www” i „beta”), NIE do folderu strony.
 * Ten plik (z hasłem) nigdy nie trafia do GitHuba.
 */
return [
    'transport' => 'smtp',
    'smtp_host' => 'ssl0.ovh.net',
    'smtp_port' => 465,
    'smtp_user' => 'kontakt@well-said.pl',
    'smtp_pass' => 'TUTAJ-WPISZ-HASLO-DO-SKRZYNKI',
    'from'      => 'kontakt@well-said.pl',
    'to'        => 'kontakt@well-said.pl',
];
