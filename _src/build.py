#!/usr/bin/env python3
"""
WELL SAID — generator strony.
Składa gotowe pliki HTML z części wspólnych (_src/partials) i treści zakładek (_src/pages).

Użycie (z folderu repozytorium):  python3 _src/build.py

Każdy plik w _src/pages zaczyna się od komentarza <!--meta {...}--> z danymi strony:
  path         adres strony, np. "/military-english/"
  title        tytuł w karcie przeglądarki i w Google
  description  opis w Google
  og_title / og_description   (opcjonalnie) tekst przy udostępnianiu linku
  head         (opcjonalnie) nazwy części z _src/partials dołączanych w <head>, rozdzielone przecinkami
  body_class   (opcjonalnie) klasa dla <body>
  preload      (opcjonalnie) obraz ładowany priorytetowo
  sitemap      (opcjonalnie) false = pomiń w mapie strony
"""
import json, os, re, hashlib, datetime
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, '_src')
part = lambda n: open(os.path.join(SRC, 'partials', n + '.html'), encoding='utf-8').read()

css = open(os.path.join(ROOT, 'assets/css/style.css'), 'rb').read() + open(os.path.join(ROOT, 'assets/js/main.js'), 'rb').read()
version = hashlib.md5(css).hexdigest()[:8]

head, header, footer = part('head'), part('header'), part('footer')
urls = []
for fn in sorted(os.listdir(os.path.join(SRC, 'pages'))):
    if not fn.endswith('.html'):
        continue
    raw = open(os.path.join(SRC, 'pages', fn), encoding='utf-8').read()
    m = re.match(r'\s*<!--meta\s*(\{.*?\})\s*-->\s*', raw, re.S)
    meta = json.loads(m.group(1)); body = raw[m.end():]
    esc = lambda s: s.replace('&', '&amp;').replace('"', '&quot;')
    vals = {
        'title': esc(meta['title']), 'description': esc(meta['description']), 'path': meta['path'],
        'og_title': esc(meta.get('og_title', meta['title'])), 'og_description': esc(meta.get('og_description', meta['description'])),
        'body_class': meta.get('body_class', ''), 'version': version,
        'preload_tag': ('<link rel="preload" href="%s" as="image">\n' % meta['preload']) if meta.get('preload') else '',
        'head_extra': ''.join(part(h.strip()) for h in meta.get('head', '').split(',') if h.strip()),
    }
    out = re.sub(r'\{\{(\w+)\}\}', lambda mm: vals[mm.group(1)], head)
    out += header + '\n<main id="tresc">\n' + body + '\n</main>\n\n' + footer
    out += '\n<script src="/assets/js/main.js?v=%s" defer></script>\n</body>\n</html>\n' % version
    dest = os.path.join(ROOT, meta['path'].strip('/'), 'index.html') if meta['path'] != '/' else os.path.join(ROOT, 'index.html')
    if meta['path'].endswith('.html'):
        dest = os.path.join(ROOT, meta['path'].lstrip('/'))
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    open(dest, 'w', encoding='utf-8').write(out)
    if meta.get('sitemap', True):
        urls.append((meta['path'], meta.get('priority', '0.8')))
    print('zbudowano', meta['path'])

today = datetime.date.today().isoformat()
sm = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
sm += ''.join('  <url><loc>https://well-said.pl%s</loc><lastmod>%s</lastmod><priority>%s</priority></url>\n' % (u, today, p) for u, p in urls)
sm += '</urlset>\n'
open(os.path.join(ROOT, 'sitemap.xml'), 'w').write(sm)
print('mapa strony:', len(urls), 'adresów')
