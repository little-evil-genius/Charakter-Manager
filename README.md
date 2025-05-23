# Charakter-Manager
Das Charakter-Manager Plugin erweitert das User-CP um verschiedene Tools. Es gibt eine neue Charakterübersicht, die eine Auflistung aller Accounts bietet, die einem User:in zugeordnet sind. Diese Übersicht lässt sich mithilfe von Profilfeldern, Steckbrieffeldern (aus dem Plugin <a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbriefe im UCP</a> von risuena) sowie Angaben aus dem <a href="https://github.com/little-evil-genius/Upload-System" target="_blank">Uploadsystem</a> von little.evil.genius individuell gestalten.<br>
<br>
Ein weiteres Tool ist die Multiregistration: User*innen können direkt im User-CP neue Charaktere registrieren - ganz ohne Ausloggen. Dabei wird der Registrierungsprozess stark vereinfacht, indem wichtige Informationen wie die E-Mail-Adresse und die persönlichen Einstellungen automatisch vom Hauptaccount übernommen werden. Im Registrierungsformular selbst können zwei Arten von Feldern erscheinen: Zum einen gibt es verpflichtende Profilfelder und/oder Steckbrieffelder, die ausgefüllt werden müssen. Zum anderen können bestimmte vorausgefüllte Felder eingeblendet werden, deren Inhalte direkt aus dem Hauptaccount übernommen werden. Diese werden beim Anlegen des Charakters angezeigt, sind jedoch im Formular nicht bearbeitbar. Änderungen an diesen Feldern sind erst nachträglich im User-CP des jeweiligen Charakters möglich. Typische Beispiele für solche automatisch übernommenen Felder sind etwa ein Spitzname, der eigene Discord-Tag oder persönliche Angaben wie Postvorlieben. Diese Informationen sind für alle Charaktere gleich und müssen daher nicht bei jeder Registrierung erneut eingetragen werden.<br>
<br>
Eine weitere Option ist der PDF-Export von Profilfeldern. Wenn diese Funktion aktiviert ist, können User:innen ein automatisch generiertes PDF-Dokument herunterladen.<br>
<br>
Als letztes großes Tool bietet das Plugin eine optionale Charakterideensammlung, die als eine Art Notizbuch fungiert. User:innen können darin neue Charakterideen speichern und verwalten. Die Felder für diese Ideen lassen sich im Admin-CP individuell definieren. Ideen bleiben standardmäßig privat, können jedoch - wenn aktiviert - auch automatisiert als eigenes Thema im Forum veröffentlicht werden. Die Struktur des erstellten Themas wird dabei über ein Template definiert, sodass User:innen keine Codekenntnisse benötigen - sie müssen lediglich das Formular ausfüllen.

## Funktionen im Überblick
### Charakterübersicht im User-CP
Im User-CP steht eine neue Übersicht zur Verfügung, in der alle Charaktere eines/einer User:in angezeigt wird. Diese Übersicht kann durch folgende Daten ergänzt und optisch angepasst werden:
- Inhalte aus MyBB-Profilfeldern
- Inhalte aus der Tabelle "users"
- Inhalte aus Steckbrieffeldern (<a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbriefe im UCP von risuena</a>)
- Inhalte aus dem Uploadsystem (<a href="https://github.com/little-evil-genius/Upload-System" target="_blank">Uploadsystem von little.evil.genius</a>)<br>

Dafür muss nur die Variable {$character['xxx']} entsprechend ausgefüllt und eingefügt werden – anstelle von xxx kann z.B. fid1 (für ein Profilfeld), avatarperson (für ein Steckbrieffeld) oder handyicon (für einen Eintrag aus dem Uploadsystem) verwendet werden.<br>
Zusätzlich gibt es verschiedene Optionen zur Darstellung der Namen der Charaktere:
- {$characternameFormatted} – zeigt den Namen mit der jeweiligen Gruppenfarbe, aber ohne Link
- {$characternameLink} – zeigt den Namen als Link zum jeweiligen Profil, ohne Farbformatierung
- {$characternameFormattedLink} – kombiniert Gruppenfarbe und Link zum Profil
- {$characternameFirst} und {$characternameLast} – zeigt den Namen getrennt in Vor- und Nachname

### Multiregister mit Profil- und Steckbrieffeldern
Das Plugin ermöglicht es User*innen, direkt über das User-CP neue Charaktere zu registrieren – ohne sich ab- und wieder anmelden zu müssen. Der Ablauf ist dabei bewusst einfach gehalten: Name, Passwort und – sofern erforderlich – bestimmte Profil- oder Steckbrieffelder werden im Registrierungsformular ausgefüllt. Die E-Mail-Adresse und grundlegende Einstellungen übernimmt das System automatisch vom Hauptaccount. Die neu erstellten Charaktere werden automatisch den Hauptaccount des aktuell eingeloggten Accounts zugeordnet.<br>
Im Admin-CP kann eingestellt werden, welcher Benutzergruppe neu registrierte Charaktere automatisch zugewiesen werden sollen. Zudem lässt sich definieren, welche Profil- oder Steckbrieffelder im Registrierungsformular verpflichtend ausgefüllt werden müssen. Es besteht außerdem die Möglichkeit, bestimmte Felder automatisch mit Werten aus dem Hauptaccount vorauszufüllen. Diese übernommenen Felder werden im Formular zwar angezeigt, sind dort aber nicht bearbeitbar – Änderungen können erst nachträglich über das User-CP vorgenommen werden.

### PDF-Export ausgewählter Profilfelder
Wenn diese Funktion aktiviert ist, können User:innen bestimmte Profilfelder als PDF-Datei exportieren. Steckbrieffelder werden bewusst nicht einbezogen, da das Steckbrief-Plugin eine eigene Exportmöglichkeit bietet.<br>
Für die Einbindung des Download-Links stehen zwei unterschiedliche Variablen zur Verfügung: eine für die Charakterübersichtsseite im User-CP ({$exportLink}) und eine für das Profil (Template: member_profile). Der Link zum PDF erscheint dort ausschließlich für die User:in selbst (mit allen verknüpften Accounts) sowie für Teammitglieder. Gäste und andere User:innen können den Link nicht sehen.<br>
Da die UID des Charakters als Grundlage dient, kann der PDF-Download-Link prinzipiell an jeder beliebigen Stelle eingebunden werden, an der eine UID verfügbar ist. Das Plugin prüft zusätzlich dann nochmal, ob der/die aktuelle User:in die Berechtigung hat, das jeweilige PDF einzusehen oder herunterzuladen. 

### Charakterideen verwalten und veröffentlichen
Im User-CP steht optional ein Tool zur Verfügung, in dem User:innen neue Charakterideen speichern und verwalten können. Diese Funktion dient als Notizsystem und kann vom Team individuell angepasst werden. Im Admin-CP lassen sich dazu eigene Felder definieren, z.B. für Avatarperson, Alter.<br>
Das Tool bietet eine Erinnerungsfunktion: User:innen können nach einer bestimmten Anzahl von Tagen automatisch an ihre gespeicherte Idee erinnern werden. Diese Erinnerungszeit wird im Admin-CP festgelegt und gilt für alle Ideen einheitlich.<br>
Nach Ablauf der Frist erhalten die User:innen einen Hinweisbanner, mit der Möglichkeit, die jeweilige Idee entweder zu löschen - falls kein Interesse mehr besteht - oder die Erinnerung um denselben Zeitraum zu verlängern. So behalten User:innen den Überblick über ihre geplanten Charakterkonzepte und können bei Bedarf aktiv entscheiden, welche Ideen sie weiterverfolgen möchten.<br>
Jede Idee kann entweder privat für den User:in gespeichert oder - sofern aktiviert - als Thema im Forum veröffentlicht werden. Die Veröffentlichung erfolgt auf Basis eines Templates. So entsteht ein einheitlicher Beitrag, ohne dass die User:innen HTML oder BBCode schreiben müssen.

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein.

# Datenbank-Änderungen
hinzugefügte Tabelle:
- character_manager
- character_manager_fields

# Neue Sprachdateien
- deutsch_du/admin/character_manager.lang.php
- deutsch_du/character_manager.lang.php

# Einstellungen
- verpflichtende Angaben
- verpflichtende Felder
- zu übernehmende Angaben
- zu übernehmende Felder
- Gruppe
- Profilfelder exportieren
- exportierende Profilfelder
- Standard-Avatar
- Charakterideen
- Erinnerung an Charakterideen
- Charakterideen veröffentlichen
- Forum für Charakterideen
<br>
<br>
<b>HINWEIS:</b><br>
Das Plugin ist kompatibel mit den klassischen Profilfeldern von MyBB und/oder dem <a href="https://github.com/katjalennartz/application_ucp">Steckbrief-Plugin von Risuena</a>.

# Neue Template-Gruppe innerhalb der Design-Templates
- Charakter-Manager Templates

# Neue Templates (nicht global!)
- charactermanager
- charactermanager_character
- charactermanager_ideas
- charactermanager_ideas_banner
- charactermanager_ideas_banner_text
- charactermanager_ideas_character
- charactermanager_ideas_fields
- charactermanager_ideas_form
- charactermanager_ideas_form_fields
- charactermanager_ideas_form_prefix
- charactermanager_ideas_form_puplic
- charactermanager_ideas_post
- charactermanager_ideas_post_fields
- charactermanager_registration
- charactermanager_registration_fields
- charactermanager_usercp_nav
  
# Neue Variablen
- header: {$character_manager_banner}
- member_profile: {$character_manager_exportLink}

# Neues CSS - character_manager.css
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern - auch im Default. Nach einem MyBB Upgrade fehlt der Stylesheets im Masterstyle? Im ACP Modul "RPG Erweiterungen" befindet sich der Menüpunkt "Stylesheets überprüfen" und kann von hinterlegten Plugins den Stylesheet wieder hinzufügen.
```css
.character_manager {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-around;
        gap: 10px;
        }

        a.character_manager_button:link,
        a.character_manager_button:hover,
        a.character_manager_button:visited,
        a.character_manager_button:active {
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        display: inline-block;
        padding: 4px 8px;
        margin: 2px 2px 6px 2px;
        border: 1px solid #000;
        font-size: 14px;
        -moz-border-radius: 6px;
        -webkit-border-radius: 6px;
        border-radius: 6px;
        }

        .character_manager_character {
        width: 30%;
        text-align: center;
        }

        .character_manager_username {
        font-size: 16px;
        font-weight: bold;
        }

        .character_manager_avatar img {
        padding: 5px;
        border: 1px solid #ddd;
        background: #fff;
        }

        .character_manager_ideas {
        margin: 10px 0;
        }

        .character_manager_ideas-headline {
        background: #0066a2 url(../../../images/thead.png) top left repeat-x;
        color: #ffffff;
        border-bottom: 1px solid #263c30;
        padding: 8px;
        height: 35px;
        }

        .character_manager_ideas_bit-chara {
        margin: 10px 0 0 0;
        }
        
        .character_manager_ideas_bit-title {
        border-bottom: 2px solid #ddd;
        font-weight: bold;
        }

        .character_manager_ideas_bit-item {
        font-size: 11px;
        }
```

# Benutzergruppen-Berechtigungen setzen
Damit alle Admin-Accounts Zugriff auf die Verwaltung der Felder für die Charakterideen haben im ACP, müssen unter dem Reiter Benutzer & Gruppen » Administrator-Berechtigungen » Benutzergruppen-Berechtigungen die Berechtigungen einmal angepasst werden. Die Berechtigungen für die Felder befinden sich im Tab 'RPG Erweiterungen'.

# Links
<b>ACP</b><br>
index.php?module=rpgstuff-character_manager<br>
<br>
<b>Charakterübersicht</b><br>
usercp.php?action=character_manager<br>
<br>
<b>Multiregister</b><br>
usercp.php?action=character_manager_registration<br>
<br>
<b>PDF</b><br>
usercp.php?action=character_manager_pdf&uid=X<br>
<br>

# Demo
### ACP
<img src="https://stormborn.at/plugins/character_manager_acp_overview.png">
<img src="https://stormborn.at/plugins/character_manager_acp_add.png">
<img src="https://stormborn.at/plugins/character_manager_acp_fields.png">

### Charakterübersicht
<img src="https://stormborn.at/plugins/character_manager_overview.png">

### Multiregister
<img src="https://stormborn.at/plugins/character_manager_multireg.png">

### Charakterideen
<img src="https://stormborn.at/plugins/character_manager_ideas.png">
<img src="https://stormborn.at/plugins/character_manager_ideas_add.png">
