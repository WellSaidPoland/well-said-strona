# well-said.pl — strona WellSaid

Strona statyczna (HTML/CSS/JS) + formularz kontaktowy w PHP (PHPMailer, SMTP OVH).

- Gałąź `testy` → beta.well-said.pl (wersja testowa, chroniona hasłem)
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

## Jak wprowadzać zmiany
- Treści zakładek: `_src/pages/*.html`, części wspólne (menu, stopka, <head>): `_src/partials/`
- Po każdej zmianie: `python3 _src/build.py` — generuje gotowe pliki HTML i sitemap.xml
- Strony: / (główna), /military-english/, /general-english/, /business-english/, /opinie/, /faq/, /polityka-prywatnosci/, /regulamin/
