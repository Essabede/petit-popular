> **Descarrega el plugin instal·lable:** [petit-popular-0.5.1.zip](dist/petit-popular-0.5.1.zip). Obre el fitxer i prem el botó de descàrrega de GitHub.

# Petit Popular · 0.5.1 (prototip)

Plugin per a WordPress 6.4+ i PHP 8.0+. Compatible amb activació per web o per xarxa multisite. No modifica WordPress Popular Posts ni importa les seves dades. No requereix cap servei extern.

## Instal·lació de prova

1. Fes servir primer una còpia de proves del multisite, amb còpia de seguretat.
2. A **Plugins → Afegeix un plugin → Puja un plugin**, puja `petit-popular-0.5.1.zip`.
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
| `layout` | `cards`, `list`, `hero`, `vertical`, `plain` |
| `image_position` | `side` (imatge al costat) o `top` (imatge al damunt), per a `layout="vertical"` |
| `columns` | 1–4; s'adapta a pantalles petites |
| `show_image` | `yes` (amb foto, per defecte) o `no` (sense foto) |
| `rank_size` | Mida dels números, 12–48 px; 32 px per a codis antics i 18 px en crear un rànquing nou al panell |
| `show_count` | `yes` o `no`; ocult per defecte |
| `title` | Títol del rànquing |
| `font` | `theme`, `serif`, `sans` |
| `title_color`, `date_color` | Color hexadecimal del titular i de la data, respectivament; buits per utilitzar els predeterminats |
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

Comprovacions executades: 26 de dates i factors, 6 de seguiment al navegador simulat i 154 d'integració amb WordPress 6.8 multisite / PHP 8.0.30 (WordPress Playground, SQLite). Totes han passat. S'han comprovat el recompte real, l'endpoint públic, les restriccions d'accés, els quatre renderitzats, el formulari d'administració i la separació entre webs. Aquesta prova no substitueix la verificació amb MySQL/MariaDB, el tema del mitjà i Events Calendar Pro de producció.

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

Validació afegida: 69 comprovacions d’interacció del panell amb JSDOM 26.1.0, a més de les 93 comprovacions WordPress multisite. `tests/builder.mjs` utilitza el formulari generat per les proves WordPress (`tests/admin-fixture.html`, ignorat per Git). Instal·la JSDOM en un directori de proves i defineix `PETIT_TEST_TOOLS` apuntant-hi per executar-lo.

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

## Colors de lletra i llistat sense targetes (0.3.2)

Al panell hi ha selectors independents per al **Color dels titulars** i el **Color de les dates**. Tria «Predeterminat» o «Tria un color». Els colors s’apliquen en directe a la previsualització i es desen amb el rànquing. El color del titular també s'aplica al títol general del rànquing si s'ha definit.

El nou disseny **Llistat sense targetes** (`layout="plain"`) mostra files separades per línies, sense fons, ombres ni vores de targeta. Es pot combinar amb foto o sense foto. Manté els números grans, el titular enllaçat i la data. No mostra controls de cantonades ni de columnes perquè no s’hi apliquen. Les targetes i els altres dissenys continuen disponibles.

```text
[petit_popular layout="plain" show_image="no" title_color="#20252d" date_color="#526070" rank_size="32"]
```

Aquesta actualització conserva les opcions i els rànquings desats. Validació: 93 comprovacions WordPress multisite i 42 d’interacció del panell.

## Formularis, agenda i números configurables (0.4.0)

### Dos períodes diferents

El formulari d’esdeveniments pregunta primer **Quan se celebren els esdeveniments?** i després **Quines visualitzacions comptem?**. Un resum mostra la combinació: per exemple, activitats dels pròxims 30 dies ordenades per les visualitzacions dels últims 30 dies. «Nombre de dies a partir d’avui» inclou avui. «Aquest mes complet» inclou dates ja passades del mes. La categoria es tria per nom en un selector.

El resum final de visites dels últims 7 dies no limita la data de celebració. No és necessàriament el mateix rànquing que s’està configurant.

### Filtratge d’esdeveniments i diagnòstic

S’ha substituït la combinació `start_date` / `end_date` per `date_overlaps` inclusiu de l’API pública de The Events Calendar, amb la zona horària de la web. La primera combinació requereix que l’esdeveniment quedi completament dins de l’interval; podia excloure activitats en curs o que acabessin després de la data final. Es recuperen objectes d’esdeveniment en comptes de dependre només del mode de camps IDs. Es continua respectant el que retorna Events Calendar, incloses possibles exclusions del calendari. No es canvia la visibilitat de cap esdeveniment.

En seleccionar **Rànquing real**, l’apartat **Per què alguns esdeveniments amb visites no apareixen?** compara fins a 10 activitats amb visites dins del període de recompte: mostra el títol, les dates de celebració i si queden fora de les dates, si s’han exclòs els esdeveniments ja iniciats o si Events Calendar no les retorna. El diagnòstic és privat per a administradors.

El cas de prova d’un taller únic del 12/11/2026, amb 26 visites recents i interval de celebració del 09/11 al 08/12, apareix al rànquing. **Això és una prova amb un substitut de l’API basat en el contracte documentat, no una verificació de la instal·lació de producció ni de la llicència Pro.** El motiu exacte de l’absència del taller a la web real s’ha de comprovar amb el nou diagnòstic.

Referències consultades al repositori oficial: `src/Tribe/Query.php` i `src/Tribe/Repositories/Event.php`, incloent `filter_by_date_overlaps` i els àlies de dates.

### Previsualització amb continguts reals sense visites

**Continguts reals per provar el disseny** mostra notícies o activitats publicades que compleixen les dates i la categoria encara que no tinguin visites. No és un rànquing de popularitat i no mostra xifres de visualitzacions. La vista **Rànquing real** continua utilitzant només visites registrades. No es canvia automàticament d’una vista a l’altra ni s’inventen recomptes.

### Números amb menys pes visual

Per als rànquings nous, el panell proposa número sol de 18 px i gruix normal. Es pot triar no mostrar-lo, número sol, cercle o etiqueta; canviar-ne la mida i el gruix; i configurar el color del número, el fons i el contorn, incloent fons transparent i absència de contorn. Els colors són dins d’**Colors dels números** per no sobrecarregar el formulari. Els codis i rànquings antics mantenen cercle, 32 px i gruix marcat fins que es modifiquen.

Atributs: `rank_style="none|number|circle|badge"`, `rank_size="12..48"`, `rank_weight="400|500|600|700|800|850"`, `rank_color="#hex"`, `rank_background="#hex|transparent"`, `rank_border="#hex|none"`. Deixar un color buit conserva el predeterminat.

Validació: 107 comprovacions WordPress multisite (incloent el contracte d’esdeveniments), 50 d’interacció del panell, 26 de dates i factors, 6 de seguiment. `tests/browser-smoke.mjs` comprova el formulari i la previsualització en Chromium amb els continguts de prova exportats per WordPress i una resposta HTTP simulada; no es connecta a cap web de producció.

## Color en passar-hi per sobre i llistats més compactes (0.4.1)

El selector **Color del titular en passar-hi per sobre o enfocar-lo amb el teclat** permet personalitzar el color dels enllaços del titular. Tria un color amb el selector visual o deixa «Predeterminat» per conservar el comportament anterior. S’aplica també amb focus de teclat. Atribut: `hover_color="#hex"`.

**Espai dels llistats sense foto** ofereix «Ampli (com fins ara)», «Compacte» i «Molt compacte». Només afecta els dissenys de files (`plain`, `list`, `vertical`) sense fotografia. En els modes compactes es redueixen els marges i l’espai reservat al número; data i visualitzacions comparteixen línia quan hi caben, sense caixa de fons al comptador. Si no hi caben, passen de línia. La mida del titular continua sent la seleccionada. Atribut: `density="comfortable|compact|tight"`.

Els rànquings i els codis existents mantenen el mode ampli. Els rànquings nous al panell proposen el mode compacte quan es tria un llistat sense foto. El control general de separació queda ocult en els modes compactes per evitar dues opcions que controlin el mateix espai.

Prova real de layout en Chromium, amb columna de 300 px i contingut de prova idèntic: alçada de contingut 689,8 px en ampli, 313,8 px en compacte i 257,0 px en molt compacte. S’ha verificat l’alineació de data/comptador i el color tant amb `:hover` com amb `:focus-visible`. Aquestes dimensions són de la prova; el resultat depèn dels titulars, tipografia i configuració de cada web.

Validació de la versió: 113 comprovacions WordPress multisite, 55 d’interacció del panell i la prova de navegador `tests/density-browser.mjs`.

## HTML del rànquing per a Mailchimp (0.5.0)

A **4. Exporta el rànquing a Mailchimp** pots escollir un disseny del plugin independentment del que utilitzes a la web. El valor inicial és **Targetes horitzontals**, amb dues columnes; també permet una o tres columnes, llistat lateral, vertical, primer destacat i llistat sense targetes. «El mateix disseny que a la web» reutilitza el disseny seleccionat al formulari.

### Ús

1. Recupera un rànquing desat o configura els filtres i les opcions del rànquing que vols enviar. No cal desar els canvis per exportar les opcions actuals.
2. Tria el disseny del correu i, si és horitzontal, el nombre de columnes. Aquest selector no modifica el disseny desat de la web.
3. Tria **Color de fons al correu**. El fons de la pàgina web no es pot deduir del rànquing. «Utilitza el color del rànquing» facilita conservar lletres blanques sobre un fons corporatiu.
4. Prem **Genera l’HTML del moment**. S’obre una previsualització específica del correu, amb la data de generació.
5. Prem **Copia l’HTML per a Mailchimp** i enganxa’l en un bloc **Codi / Code** del constructor de la campanya. El botó copia el fragment de taules HTML, no el shortcode de WordPress.
6. També pots **Descarregar l’HTML** per obrir-lo al navegador o utilitzar-lo com a fitxer. El fitxer descarregat inclou una estructura de document UTF-8; el fragment copiat al porta-retalls és adequat per al bloc Codi.

### Dades del moment

L’exportació sempre consulta el **rànquing real**, encara que la previsualització de la web estigui en mode de mostra o de continguts sense visites. Si no hi ha resultats amb visites dins dels filtres, s’explica l’error i no s’exporten dades fictícies.

Es congelen titulars, dates, enllaços, imatges seleccionades i visualitzacions ajustades pel factor configurat. Es respecten les opcions de foto, números i recompte visible. Les notícies mostren el dia de publicació i els esdeveniments el dia de celebració. No es carreguen resums. Les fotografies continuen referenciant les URL públiques del servidor WordPress; el plugin no les puja a Mailchimp.

L’HTML no s’actualitza després de copiar-lo ni d’enviar la newsletter. Per renovar les dades cal generar-lo de nou. Si canvies qualsevol opció al formulari, el panell invalida l’HTML anterior per evitar copiar una versió desfasada.

### Adaptació al correu

Es fan servir taules i estils en línia, en un bloc de fins a 600 px. No hi ha scripts, CSS Grid, Flexbox, classes de WordPress ni dependència del plugin en el correu enviat. Els titulars i les fotos contenen enllaços absoluts a la web. La tipografia del correu es tria en un selector independent, amb alternatives estàndard; el fragment no carrega fonts web. L’efecte hover i alguns acabats com cantonades poden variar entre lectors.

La previsualització del navegador no substitueix una **tramesa de prova a Mailchimp** i la revisió en els lectors de correu habituals. No s’ha enviat cap campanya ni s’ha accedit a un compte Mailchimp en aquestes proves. El plugin no necessita credencials de Mailchimp ni cap connexió a la seva API per generar el codi.

### Validació

149 comprovacions WordPress multisite i 67 d’interacció del panell. S’han comprovat els cinc dissenys, la instantània de dades, els factors, les dates d’activitat, l’absència de dades fictícies, les imatges amb URL absoluta, els permisos i el nonce, la còpia, la descàrrega i la invalidació d’exportacions antigues.

`tests/email-browser.mjs` revisa en Chromium l’HTML generat per WordPress dins del diàleg del panell: amplada de 600 px, dues columnes i contingut real de prova. La petició del panell es simula amb aquesta resposta de prova; no es connecta a cap WordPress de producció ni a Mailchimp.

## Marges i tipografia del correu (0.5.1)

Tots els dissenys exportats inclouen **18 píxels d’espai interior a la dreta i a l’esquerra**. L’amplada màxima exterior continua sent 600 px; el contingut ocupa 564 px. S’utilitza padding de cel·la, compatible amb el disseny de taules dels correus, i s’han recalculat les columnes i les imatges perquè no desbordin. Si el bloc Codi de Mailchimp ja afegeix espai lateral, ajusta’l per evitar sumar dos marges.

El selector **Tipus de lletra del rànquing al correu** és independent de la tipografia de la web. Permet reutilitzar la del rànquing web o triar Arial, Helvetica, Verdana, Tahoma, Trebuchet MS, Georgia, Times New Roman, Nunito, Open Sans o Lato. S’aplica al títol del bloc, els titulars, les dates, les visualitzacions i els números. Canviar-lo invalida l’exportació anterior i cal regenerar l’HTML. No modifica el rànquing desat de la web.

Nunito, Open Sans i Lato tenen Arial com a alternativa. El codi no descarrega fonts: el lector de correu o la plantilla de Mailchimp ha de disposar de la font específica; en cas contrari s’utilitza l’alternativa. Cal revisar una tramesa de prova en els lectors habituals.

Validació: 154 comprovacions WordPress multisite i 69 d’interacció del panell. En Chromium s’han mesurat 18 px exactes a cada costat del contingut, amb dues columnes dins dels 600 px, i s’ha verificat la família Georgia seleccionada per al correu.
