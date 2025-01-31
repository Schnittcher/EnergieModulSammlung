# AbrechnungsdatenWohnung
Diese Instanz stellt die Abrechnungsdaten für eine Wohnung bereit.

### Inhaltsverzeichnis

- [AbrechnungsdatenWohnung](#abrechnungsdatenwohnung)
    - [Inhaltsverzeichnis](#inhaltsverzeichnis)
    - [1. Funktionsumfang](#1-funktionsumfang)
    - [2. Voraussetzungen](#2-voraussetzungen)
    - [3. Einrichten der Instanzen in IP-Symcon](#3-einrichten-der-instanzen-in-ip-symcon)
    - [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
      - [Statusvariablen](#statusvariablen)
      - [Profile](#profile)
    - [6. WebFront](#6-webfront)
    - [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Generieren eines Reports als CSV über das Webfront
  * Verbrauch über einen Zeitraum
  * Zählerstand zu einem Zeitpunkt
  * Konfigurierbar, ob der Verbrauch bzw. der Zählerstand im Report enthalten sein soll.
* Versand des Reports per E-Mail über das Webfront
* Versand eines Reports als CSV per E-Mail zu einem wiederkehrenden Zeitpunkt (täglich, wöchentlich, monatlich, jährlich ...)
* Pro Instanz wird ein Webfront erstellt. Das Webfront besitzt das Passwort, welches in der CSV Datei hinterlegt wurde, welche über den Konfigurator eingespielt worden ist.

### 2. Voraussetzungen

* mindestens IPS Version 6.3
* Symcon_Smart_Metering Modul für die Zählervariablen

### 3. Einrichten der Instanzen in IP-Symcon

Diese Instanz muss über den Konfigurator angelegt werden.

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Aktiv | Über diese CheckBox kann die Instanz ein- bzw. ausgeschaltet und somit das automatische Senden nach hinterlegtem Intervall aktiviert. bzw. deaktiviert werden. 
**Benachichtigungseinstellungen** |
SMTP Instanz | Diese Einstellung wird automatisch über den Konfigurator gefüllt und wird zum Versand der E-Mails benötigt.
Mieter E-Mail | Hier kann eine Liste (Nach ; getrennt) hinterlegt werden, welche für den Versand der Reports an die Mieter verwendet wird, solange im Webfront keine andere E-Mail Adresse hinterlegt wurde.
Liste | Diese Liste wird über den Konfigurator mit allen Wohnungen und en dazugehörigen Zählern gefüllt.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Verbrauch|Boolean|Diese Variable gibt an, ob der Report den Verbrauch für den angegeben Zeitraum enthält.
Zählerstand|Boolean|Diese Variable gibt an, ob der Report den Zählerstand zum Enddatum enthält.
Sende Bericht per E-Mail| Boolean | Über diese Variable kann hinterlegt werden, ob der Report, welcher über das Webfront erstellt, wird ebenfalls per E-Mail versendet wird, oder nur als Download im Webfront bereit steht.
E-Mail Adresse | String | Hier kann die E-Mail Adresse hinterlegt werden, an welche der Report versendet werden soll. Nach dem Versand des Reports, wird dieses Feld zurückgesetzt, damit die E-Mail Adresse aus der Instanzkonfiguration wieder verwendet wird.
Startdatum | Integer | Das Startdatum wird nur angezeigt, wenn auch der Verbrauch im Report angezeigt werden soll.
Enddatum | Integer | Das Enddatum wird immer benötigt, für den Verbrauch, sowie für den Zählerstand. Der Zählerstand wird immer zu diesem Enddatum abgelesen.
Aktion | Integer | Über diese Variable kann der Bericht erstellt werden.
Bericht | Medienobjekt | In diesem Medienobjekt wird der Report gespeichert, sodass er über das Webfront heruntergeladen werden kann.

#### Profile

Name   | Typ
------ | -------
 Abrechungsdaten.Aktion| String

### 6. WebFront

Über das Webfront kann ein Bericht generiert werden, sowie Einstellungen für diesen vorgenommen werden. Es kann eingestellt werden, ob der Bericht nur den Verbrauch, den Zählerstand oder beides enthalten soll.

### 7. PHP-Befehlsreferenz

`array SAW_generateReport(string $startDatum, string $endDatum, bool $Verbrauch, bool $Zaehlerstand, bool $sendMail)`
Diese Funktion generiert den Report und versendet ihn ggf. per E-Mail.
Ebenfalls gibt diese Funktion ein Array mit dem Report als CSV und als HTML zurück.

Beispiel:
`SAG_generateReport('01.01.2023 00:00', '31.01.2023 23:59', true, true, true);`

Dieses Beispiel erstellt einen Report vom 01.01.2023 bis zum 31.01.2023, welcher den Verbrauch, sowie den Zählerstand zum 31.01.2023 23:59 Uhr enthält und versendet den Report per E-Mail an die E-Mail Adresse welche im Webfront bzw. in der Instanzkonfiguration hinterlegt ist.