> **Descarrega el plugin instal·lable:** [petit-popular-0.3.1.zip](dist/petit-popular-0.3.1.zip). Obre el fitxer i prem el botó de descàrrega de GitHub.

# Petit Popular · 0.3.1 (prototip)

Plugin per a WordPress 6.4+ i PHP 8.0+. Compatible amb activació per web o per xarxa multisite. No modifica WordPress Popular Posts ni importa les seves dades. No requereix cap servei extern.

## Instal·lació de prova

1. Fes servir primer una còpia de proves del multisite, amb còpia de seguretat.
2. A **Plugins → Afegeix un plugin → Puja un plugin**, puja `petit-popular-0.3.1.zip`.
3. Activa'l a la xarxa o a la web que vulguis provar. Les taules es creen en la primera petició de cada web, incloent les webs creades després de l'activació.
4. Entra a **Configuració → Petit Popular** de cadascuna de les webs. Defineix els dos factors i l'estil base.
5. Genera un shortcode amb els selectors i enganxa'l en un bloc Shortcode. Visita una notícia com a lector no editor per començar a recollir dades.

El factor de notícies i el d'esdeveniments es desen per web: Sabadell i Terrassa poden tenir quatre valors diferents. El plugin conserva les visites observades i aplica el factor només en renderitzar els seus rànquings. No altera comptadors d'altres plugins. Els recomptes públics ajustats es mostren com a **visualitzacions**. Per defecte estan ocults. Els administradors poden consultar les visites reals dels continguts principals dels últims set dies.

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
| `post_months` | Antiguitat màxima de publicació de les notícies, entre 1 i 120 mesos; 6 per defecte. Independent del període de visites |
| `category` | Slug exacte; categoria de notícies o `tribe_events_cat`. No inclou automàticament les categories filles |
| `limit` | 1–24 |
| `layout` | `cards`, `list`, `hero`, `vertical` |
| `image_position` | `side` (imatge al costat) o `top` (imatge al damunt), per a `layout="vertical"` |
| `columns` | 1–4; s'adapta a pantalles petites |
| `show_image` | `yes` (amb foto, per defecte) o `no` (sense foto) |
| `rank_size` | Mida dels números, 20–48 px; 32 px per defecte |
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

Comprovacions executades: 26 de dates i factors, 6 de seguiment al navegador simulat i 86 d'integració amb WordPress 6.8 multisite / PHP 8.0.30 (WordPress Playground, SQLite). Totes han passat. S'han comprovat el recompte real, l'endpoint públic, les restriccions d'accés, els quatre renderitzats, el formulari d'administració i la separació entre webs. Aquesta prova no substitueix la verificació amb MySQL/MariaDB, el tema del mitjà i Events Calendar Pro de producció.

`preview.html` permet comparar els quatre dissenys i canviar color, tipografia, arrodoniment i visibilitat de les visualitzacions amb dades fictícies.

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

Les notícies mostren imatge, titular enllaçat i dia de publicació. Els esdeveniments mostren imatge, títol enllaçat i dia d’inici de celebració. S’han retirat categories, resums, nom del mitjà, hores i llocs. La imatge també enllaça al contingut. Es mantenen la posició al rànquing i les visualitzacions opcionals (ocults per defecte). Els shortcodes antics amb `show_excerpt="yes"` continuen funcionant però ja no mostren extractes.

## Panell visual amb previsualització en directe (0.2.0)

A **Configuració → Petit Popular**, tria el contingut i les dates, selecciona un disseny amb les miniatures i ajusta els colors i la tipografia. La vista de la dreta s’actualitza automàticament. Pots provar l’amplada completa o una barra lateral de 340 px.

- **Mostra d’estil:** textos, imatges i xifres fictícies, identificats com a demostració. Permet veure el disseny sense visites acumulades i sense tenir Events Calendar activat. Els filtres temporals no alteren aquesta mostra.
- **Rànquing real:** continguts i visites de la web segons els filtres. Si no hi ha resultats, es mostra el missatge corresponent. No afegeix visites.
- **Copia el codi:** copia el shortcode actual per inserir-lo en un bloc Shortcode. Si el navegador no permet accedir al porta-retalls, selecciona el codi per copiar-lo manualment.
- Els factors i la identitat de la web són a «Configuració de la web i multiplicadors». Cal desar aquests ajustos abans de previsualitzar-los.

La previsualització només és accessible a administradors i valida un nonce de sessió. El marc de previsualització no executa scripts. El tema de producció pot modificar la tipografia i els espais respecte a la previsualització. El procés d’inserció encara utilitza shortcodes; aquesta versió no inclou un bloc Gutenberg propi.

Per actualitzar, puja el ZIP 0.3.1 des de l’administració de la xarxa i selecciona **Substitueix l’actual per la versió pujada**. No cal desinstal·lar el plugin. Les opcions, els shortcodes i l’històric es conserven.

Validació afegida: 38 comprovacions d’interacció del panell amb JSDOM 26.1.0, a més de les 86 comprovacions WordPress multisite. `tests/builder.mjs` utilitza el formulari generat per les proves WordPress (`tests/admin-fixture.html`, ignorat per Git). Instal·la JSDOM en un directori de proves i defineix `PETIT_TEST_TOOLS` apuntant-hi per executar-lo.

## Filtres de notícies i biblioteca de rànquings (0.3.0)

### Notícies elegibles

A **Configuració de la web i multiplicadors**, selecciona i desa la categoria **Notícies** de cada web. Per defecte es busca el slug `noticies`; si no existeix, es mostra un avís i no s'amplia la cerca a altres entrades. S'inclouen les subcategories de la categoria principal seleccionada. Un filtre `category` addicional restringeix encara més els resultats i no pot evitar aquesta limitació. Els esdeveniments conserven els seus filtres propis.

El camp **Notícies publicades en els últims X mesos** té el valor 6 per defecte i és independent de **Visites rebudes durant**. Per exemple: notícies publicades en els últims sis mesos, ordenades per visites dels últims set dies. Els mesos són naturals, amb el dia de tall inclòs; si el mes de destí és més curt, s'utilitza el seu últim dia. El filtre de publicació també s'aplica als shortcodes antics que no l'especifiquin i als rànquings amb `period="all"`. No s'eliminen estadístiques anteriors.

### Visualitzacions

La sortida pública utilitza **visualitzacions**. Continua aplicant el multiplicador configurat, sense alterar la dada interna real. Es manté l'opció d'ocultar el comptador.

### Desar, recuperar i editar

1. Configura el rànquing i escriu un nom intern, com **Rànquing de la portada**.
2. Prem **Desa el rànquing**. Obtindràs un codi estable, per exemple `[petit_popular id="123"]`. El número l'assigna WordPress; utilitza sempre el que genera el teu panell.
3. Copia aquest codi al lloc corresponent de la web. El nom intern no s'afegeix al titular públic del rànquing.
4. Selecciona'l a **Rànquings desats** i prem **Obre** per modificar-lo. Quan desis els canvis, el mateix codi mostrarà la nova configuració. Buida la memòria cau HTML si la web en fa servir.

El botó **Nou rànquing** inicia una configuració nova. **Elimina** demana confirmació i elimina només la configuració; els codis amb aquell ID deixen de mostrar resultats i les notícies i estadístiques es conserven. La biblioteca és independent per a cada web del multisite. Un ID no es pot traslladar directament entre webs.

Per recuperar un codi antic, obre **Recupera un shortcode anterior**, enganxa'l i prem **Recupera les opcions**. Després posa-hi un nom i desa'l. Per vincular les futures edicions al rànquing desat, substitueix una vegada el codi antic pel nou codi amb ID. Els codis antics continuen funcionant amb els nous filtres, però no es converteixen automàticament en configuracions desades.

Validació de la versió: 77 comprovacions WordPress multisite, 30 d'interacció del panell, 26 de dates i factors i 6 del seguiment de visites. Events Calendar Pro i el tema de producció continuen requerint validació al vostre entorn de proves.

## Sense foto i controls més clars (0.3.1)

- **Fotografia:** tria «Amb foto» o «Sense foto». Sense foto no es genera cap imatge ni espai reservat; el número es mostra al costat del titular. Funciona en tots quatre dissenys i amb notícies o esdeveniments.
- **Color del rànquing:** «Utilitza el color del mitjà» o «Tria un altre color», amb selector visual. No cal escriure codis HEX.
- **Forma de les cantonades:** rectes, lleugerament arrodonides, arrodonides o molt arrodonides.
- **Separació entre elements:** compacta, normal, àmplia o molt àmplia. També pots conservar la configuració general de la web.
- **Mida dels números:** visible (24 px), gran (32 px, per defecte) o molt gran (40 px). Els codis antics i els rànquings desats també adopten la nova mida per defecte.

Els valors numèrics personalitzats dels codis anteriors es conserven i apareixen com una opció personalitzada en recuperar-los. Els colors HEX abreujats s'expandeixen al seu equivalent de sis dígits per al selector visual.

Exemple de llista vertical sense foto:

```text
[petit_popular type="posts" layout="vertical" show_image="no" rank_size="40" limit="5"]
```

Validació actualitzada: 86 comprovacions WordPress multisite i 38 d'interacció del panell.
