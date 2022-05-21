# Release Informationen für payever

## v2.5.0 (2022-05-11)

### Änderungen
- Abhängigkeiten wurden aktualisiert, um die Verwendung von PHP8 zu ermöglichen

## v2.4.0 (2021-10-15)

### Hinzugefügt
- Openbank-Zahlungsmethode;
- Lieferadresse zum Create/SubmitPaymentRequest;

## v2.3.1 (2021-09-03)

### Änderungen
- Preisformat im Rechnungs-PDF-Dokument

## v2.3.0 (2021-08-31)

### Hinzugefügt
- PAN ID in Rechnungsdokument verschieben

## v2.2.0 (2021-07-06)

### Implementiert
- Iframe-Problem behoben;
- Fehler beim Synchronisieren behoben;
- Behandlung von abgelaufenen Shop-Sitzungen in Checkout-Rückrufen;
- PayPal im Backend umbenannt;

### Hinzugefügt
- vatRate, SKU-Attribute zum Warenkorb;

## v2.1.0 (2021-01-21)

### Implementiert
- Zahlung ausführen;

## v2.0.0 (2020-12-15)

### Hinzugefügt
- Neue Zahlungsoption Santander Ratenkredit Österreich
- PayEx-Bilder
- Spezifikation unterstützer Ceres und IO Versionen
### Behoben
- Fehler bei der Rückleitung des Nutzers nach dem Checkout

## v1.10.0 (2020-08-04)

### Veröffentlichung
- Unterschrift für Zahlungsbenachrichtigungen

## v1.9.0 (2020-06-25)

### Veröffentlichung
- neue Zahlungsoptionen Swedbank Kreditkarte und Swedbank Rechnung

## v1.8.1 (2020-05-15)

### Veröffentlichung
- Automatische Stornierung nicht abgeschlossener Bestellungen

### Entfernt
- Automatisches Löschen nicht abgeschlossener Bestellungen

## v1.8.0 (2020-05-06)

### Veröffentlichung
- nicht abgeschlossene Bestellungen automatisch löschen
- anrede

## v1.7.1 (2020-04-15)

### Behoben
- aktualisierung des bestellstatus durch Benachrichtigung

## v1.7.0 (2020-03-26)

### Veröffentlichung
- option bestellung vor zahlung erstellen

## v1.6.0 (2020-02-24)

### Veröffentlichung
- plugin registry und kommandos API
- neue zahlungsoption Direktüberweisung 

## v1.5.1 (2019-12-23)

### Veröffentlichung
- beschreibungen und bilder für den marktplatz

## v1.5.0 (2019-12-10)

### Veröffentlichung
- synchronisationsprozess für verschiedene plugins übersicht

## v1.4.0 (2019-11-18)

### Verbesserungen
- länder/währungen überprüfen

### Veröffentlichung
- payever API SDK 2.0

## v1.3.0 (2019-06-14)

### Veröffentlichung
- neue zahlungsoption Stripe Lastschrift

## v1.2.2 (2019-04-12)

### Verbesserungen
- Vereinfachtes Notification handling
- Übersetzung für Zahlungsoptionen

### Behoben
- Bug, der in Zusammenhang mit einigen Android Endgeräten aufgetreten ist
- Business Slug umbenannt zu Business UUID
- PAN-ID für die Zahlung

## v1.1.1 (2018-12-21)

### Veröffentlichung
- shipping_goods/cancel funktionalität

### Änderungen
- bestellungen duplizieren

## v1.1.0 (2018-10-05)

### Veröffentlichung
- implementierte API SDK
- neue zahlungsoption Santander Ratenkauf

## v1.0.3 (2018-07-10)

### Änderungen
- Debug-modus hinzugefügt
- Übersetzungen für log

## v1.0.2 (2018-06-29)

### Änderungen
- Erstattungen möglich
- Update bezüglich der neuen Plugin-Architektur
- kleinere Bugfixes 

## v1.0.1 (2018-04-17)

### Veröffentlichung
- Feste Routen
- Migration für Zahlungsoptionen erstellt
- Übersetzungsdateien für Protokolle hinzugefügt
- Übersetzt changelog_de.md, support_contact_de.md, user_guide_de.md
- Geändert payever Zahlung "channel"

## v1.0.0 (2018-04-17)

### Veröffentlichung
- Erster Release des Moduls
