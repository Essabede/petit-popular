> **Descarrega el plugin instal·lable:** [petit-popular-0.1.3.zip](dist/petit-popular-0.1.3.zip). Obre el fitxer i prem el botó de descàrrega de GitHub.

# Petit Popular · 0.1.3 (prototip)

Plugin per a WordPress 6.4+ i PHP 8.0+. Compatible amb activació per web o per xarxa multisite. No modifica WordPress Popular Posts ni importa les seves dades. No requereix cap servei extern.

## Instal·lació de prova

1. Fes servir primer una còpia de proves del multisite, amb còpia de seguretat.
2. A **Plugins → Afegeix un plugin → Puja un plugin**, puja `petit-popular-0.1.3.zip`.
3. Activa'l a la xarxa o a la web que vulguis provar. Les taules es creen en la primera petició de cada web, incloent les webs creades després de l'activació.
4. Entra a **Configuració → Petit Popular** de cadascuna de les webs. Defineix els dos factors i l'estil base.
5. Genera un shortcode amb els selectors i enganxa'l en un bloc Shortcode. Visita una notícia com a lector no editor per començar a recollir dades.

El factor de notícies i el d'esdeveniments es desen per web: Sabadell i Terrassa poden tenir quatre valors diferents. El plugin conserva les visites observades i aplica el factor només en renderitzar els seus rànquings. No altera comptadors d'altres plugins. Els recomptes públics ajustats es mostren com a **punts de popularitat**. Per defecte estan ocults. Els administradors poden consultar les visites reals dels continguts principals dels últims set dies.

## Exemples

Notícies amb visites dels últims 7 dies:

```text
[petit_popular type="posts" period="7d" title="El més llegit" layout="cards" columns="3" limit="6"]
```

Esdeveniments dels pròxims 14 dies, ordenats per visites dels últims 3 dies:

```text
[petit_popular type="events" period="days" days="3" event_period="next" event_days="14" ongoing="yes" title="L’agenda més popular" layout="hero" show_count="yes"]
```

Notícies d'una categoria, amb estil propi:

```text
[petit_popular category="cultura" period="month" layout="list" font="serif" title_size="24" accent="#175f64" radius="8" gap="16"]
```

Dates de visites i de celebració independents:

```text
[petit_popular type="events" period="custom" from="2026-11-01" to="2026-11-05" event_period="custom" event_from="2026-11-06" event_to="2026-11-20"]
```

## Selectors

| Atribut | Valors |
| --- | --- |
| `type` | `posts`, `events` |
| `period` | `today`, `7d`, `30d`, `week`, `month`, `days`, `custom`, `all` |
| `days` | 1–3660, quan `period="days"` |
| `from`, `to` | AAAA-MM-DD, quan `period="custom"` |
| `event_period` | `today`, `next`, `weekend`, `month`, `custom`, `all` |
| `event_days` | 1–3660, quan `event_period="next"` |
| `event_from`, `event_to` | AAAA-MM-DD, quan `event_period="custom"` |
| `ongoing` | `yes` o `no`: incloure els que han començat abans del primer dia de l'interval i encara continuen |
| `category` | Slug exacte; categoria de notícies o `tribe_events_cat`. No inclou automàticament les categories filles |
| `limit` | 1–24 |
| `layout` | `cards`, `list`, `hero`, `vertical` |
| `image_position` | `side` (imatge al costat) o `top` (imatge al damunt), per a `layout="vertical"` |
| `columns` | 1–4; s'adapta a pantalles petites |
| `show_count` | `yes` o `no`; ocult per defecte |
| `title` | Títol del rànquing |
| `font` | `theme`, `serif`, `sans` |
| `title_size` | 14–48 píxels; el primer destacat utilitza una mida adaptativa |
| `accent`, `radius`, `gap` | Color hexadecimal, radi 0–48 px i separació 0–64 px; si no s'indiquen s'usen els de la web |

Els intervals inclouen els dies inicial i final i segueixen la zona horària de cada web. «Últims 7 dies» inclou avui i els sis dies anteriors: són dies naturals, no una finestra exacta de 168 hores. La setmana comença dilluns. «Aquest mes» per a l'agenda inclou tot el mes, també dies passats; «pròxims X dies» comença avui. Un diumenge, «aquest cap de setmana» inclou el dissabte anterior i el diumenge actual.

Només apareixen continguts publicats sense contrasenya i amb almenys una visita al període seleccionat. No es completa el rànquing amb continguts sense visites. Els empats es resolen per ID descendent. No hi ha rànquings mixtos en aquesta primera versió.

## Recompte i privacitat

- Es compta una càrrega visible de pàgina amb JavaScript, incloent recàrregues. No són usuaris únics.
- S'exclouen els usuaris amb permís `edit_posts`, previsualitzacions, entrades amb contrasenya i bots identificables per user-agent. No és una protecció completa contra trànsit automatitzat o manipulació.
- El servidor valida un nonce lligat al contingut. L'endpoint retorna només si s'ha comptat, mai les visites reals ni el factor.
- No es desen IP, cookies ni identificadors de visitant. La taula desa només ID de contingut, dia i visites.
- L'històric comença amb el plugin. Canviar el factor no modifica les dades originals.
- Les dades es conserven indefinidament. Desactivar o esborrar el plugin no les elimina automàticament en aquest prototip.
- El recompte és aproximat i no serveix per facturació o mesurament auditat.

## Memòria cau

L'script funciona en pàgines servides des de memòria cau, però el nonce incrustat caduca. Configura una caducitat de la memòria cau HTML inferior a 12 hores i exclou les peticions POST a `admin-ajax.php` de qualsevol memòria cau. Després d'activar o canviar factors, buida la memòria cau de les pàgines amb rànquings. Els rànquings en pàgines ja desades no s'actualitzen fins a renovar-les.

## Events Calendar Pro

S'utilitza el tipus `tribe_events` i l'API pública `tribe_get_events()` de The Events Calendar per filtrar dates; es mostra només la data de celebració amb les seves funcions públiques. No es llegeixen directament les taules internes del proveïdor.

Cal validar amb la versió instal·lada de **Events Calendar Pro** les recurrències, els esdeveniments de diversos dies, els fusos horaris i els possibles conflictes de tema o memòria cau. El recompte s'associa a l'ID del contingut, no a cada ocurrència individual. No s'inclou ni es distribueix Events Calendar Pro. El prototip encara no és una validació de compatibilitat amb el multisite de producció.

## Proves

- `php tests/dates.php`: intervals, dates invàlides, canvi d'hora i factors.
- `tests/wordpress.php`: integració amb WordPress. **Només en un WordPress efímer amb dades fictícies**: crea entrades, canvia opcions i crea una segona web en multisite. El fitxer assumeix WordPress a `/wordpress`.
- `node --check assets/track.js` i `node --check assets/builder.js`.

El ZIP instal·lable exclou els tests.

## Validació d'aquesta versió

Comprovacions executades: 22 de dates i factors, 6 de seguiment al navegador simulat i 41 d'integració amb WordPress 6.8 multisite / PHP 8.0.30 (WordPress Playground, SQLite). Totes han passat. S'han comprovat el recompte real, l'endpoint públic, les restriccions d'accés, els quatre renderitzats, el formulari d'administració i la separació entre webs. Aquesta prova no substitueix la verificació amb MySQL/MariaDB, el tema del mitjà i Events Calendar Pro de producció.

`preview.html` permet comparar els quatre dissenys i canviar color, tipografia, arrodoniment i visibilitat dels punts amb dades fictícies.

## Rànquing vertical

Una sola columna, també en escriptoris i en barres laterals estretes. Els elements apareixen un sota l’altre en ordre de popularitat. El selector `columns` no afecta aquest disseny.

```text
[petit_popular type="posts" period="7d" layout="vertical" image_position="side" limit="5" show_excerpt="no" title_size="18"]
```

Per mostrar targetes apilades amb la imatge al damunt, utilitza `image_position="top"`. A la mostra visual es pot seleccionar una amplada de barra lateral de 340 px.

## Identitat corporativa (0.1.3)

Font: Manual de marca de Cèl·lula Acció Creativa facilitat per l’usuari; logotips a les pàgines 3–4 i colors a la pàgina 5. Petit Sabadell utilitza verd `#2E9C87` i Petit Terrassa corall `#FC634F` com a accent distintiu. La paleta complementària compartida és groc `#F5BA40`, rosa `#E3708F` i turquesa `#21A6BD`. En els dos últims colors del manual, els camps RGB i CMYK són inconsistents; s'han utilitzat els HEX explícits. No s'ha redistribuït el PDF.

A Configuració → Petit Popular, tria la identitat de cada web. Els colors personalitzats desats anteriorment es conserven fins que seleccionis un preset. El shortcode admet `brand="sabadell"` o `brand="terrassa"`; un `accent` explícit té preferència. Els rètols mantenen text fosc sobre blanc i el color corporatiu es fa servir en subratllats, contorns i detalls.

El manual especifica Nunito (pàgina 6). Pots seleccionar `font="nunito"` si el tema ja la proporciona; el plugin no descarrega fonts externes i utilitza Arial com a alternativa.

## Targetes orientades al clic (0.1.3)

Les notícies mostren imatge, titular enllaçat i dia de publicació. Els esdeveniments mostren imatge, títol enllaçat i dia d’inici de celebració. S’han retirat categories, resums, nom del mitjà, hores i llocs. La imatge també enllaça al contingut. Es mantenen la posició al rànquing i els punts opcionals (ocults per defecte). Els shortcodes antics amb `show_excerpt="yes"` continuen funcionant però ja no mostren extractes.
