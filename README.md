# well-said.pl — strona WellSaid

Strona statyczna (HTML/CSS/JS) + formularz kontaktowy w PHP (PHPMailer, SMTP OVH).

- Gałąź `testy` → nowa.well-said.pl (wersja testowa, chroniona hasłem)
- Gałąź `main` → well-said.pl (produkcja)
- Publikacja: integracja Git w hostingu OVH (webhook GitHub)
- Formularz: wiadomości na kontakt@well-said.pl, stały temat „[Formularz WellSaid] …” (reguła w Outlooku)
- Hasło SMTP: plik konfiguracyjny POZA repozytorium, na serwerze

Marka: navy #14213D, off-white #F4F5F7, warm dark #3C3938; czcionki Cormorant Garamond + Lato.

## Struktura
- `index.html` — Strona główna (hero, O mnie, Metoda, Kontakt)
- `assets/css/style.css` — wygląd (kolory marki w `:root`), `assets/css/fonts.css` — czcionki na własnym serwerze
- `assets/js/main.js` — menu, animacje, formularz, kalendarz
- `api/kontakt.php` — wysyłka formularza (PHPMailer w `api/lib/`), wzór konfiguracji: `api/config.example.php`
- Plik z hasłem `wellsaid-config.php` leży na serwerze w katalogu głównym konta OVH (obok `www` i `nowa`)
