# Huawei GPON — DEMO RO

Samodzielna wtyczka demonstracyjna obok pełnej wersji. Ma własne menu, moduły, uprawnienia i tabele `huawei_gpon_demo_*`.

Zakres demo: **Lista ONT**, **VLAN OLT**, **Alerty OLT** oraz niezbędna **Konfiguracja wtyczki**. Nie ma Auto Find, rejestracji klienta, szczegółów ONT/WAN, service-portów ani profili.

Demo odczytuje OLT przez SNMP. Nie zawiera kodu zmiany konfiguracji OLT/ONT: rejestracji, restartu, resetu, usuwania ONT, konfiguracji WAN, zmian VLAN ani profili. Moduły i przyciski usuniętych funkcji nie są dostarczane. Usuwanie OLT na stronie konfiguracji usuwa tylko lokalne dane demo.

Community RO jest zapisywane **jawnie w bazie**, bez szyfrowania i pliku klucza. Jest maskowane w formularzu, nie wraca do HTML ani logów. Zmianę konfiguracji odczytu i usuwanie lokalnego OLT może wykonać tylko administrator LMS (`full_access`), z ochroną CSRF. Dane dostępowe PostgreSQL nadal pochodzą z systemowej konfiguracji LMS.

Wersja: `1.1.0-demo-ro-lite`.

## Instalacja

1. Umieść katalog `LMSGponHuaweiDemoPlugin` w `/var/www/html/lms/plugins/`.
2. W katalogu głównym LMS uruchom `composer dump-autoload --optimize --no-scripts --no-interaction`. Wtyczkę włącz po utworzeniu schematu bazy w kroku 4.
3. Wymagane: PHP CLI i WWW 8.0+, PDO PostgreSQL, SNMP, DOM i zlib, net-snmp (`snmpget`, `snmpbulkwalk`), coreutils (`timeout`, `flock`), `setfacl`, `runuser`, Python 3, cron i logrotate. Konto bazy musi móc utworzyć tabele podczas instalacji.
4. Po wykonaniu kopii bazy uruchom `php /var/www/html/lms/plugins/LMSGponHuaweiDemoPlugin/bin/demo-db.php schema`. Następnie włącz `LMSGponHuaweiDemoPlugin` w konfiguracji wtyczek LMS.
5. Otwórz **Huawei GPON — DEMO RO → Konfiguracja wtyczki**. Dodaj OLT z nazwą i IPv4. W sekcji Dostęp SNMP zapisz community RO, a w Ustawieniach urządzenia zaznacz aktywne odczyty i zapisz. Nie potrzeba pliku JSON ani klucza szyfrowania.
6. Jako root uruchom `bash /var/www/html/lms/plugins/LMSGponHuaweiDemoPlugin/bin/install-collectors.sh`. Instalator tworzy konto `lms-huawei-demo`, nadaje mu odczyt konfiguracji LMS i instaluje harmonogram czterech kolektorów oraz rotację logów. Domyślne ścieżki odpowiadają LMS w `/var/www/html/lms`.
7. Pierwszy odczyt wykonaj kolejno skryptami `collect-inventory.php`, `collect-onts.php`, `collect-status.php`. Przykład: `runuser -u lms-huawei-demo -- flock -n /var/lib/lms-huawei-demo-collector/collector.lock php /var/www/html/lms/plugins/LMSGponHuaweiDemoPlugin/bin/collect-inventory.php`. Kolejne odczyty wykonuje cron. Logi: `/var/log/lms-huawei-demo/`.

Przy równoległej pełnej wersji kolektory współdzielą blokadę odczytu, aby nie odpytywać OLT równocześnie. Odczyty zapisują historię wyłącznie w lokalnej bazie demo; RO dotyczy urządzenia, nie bazy LMS.

## Alerty i udostępnianie

Alerty wymagają odbiornika trapów i bezpiecznego eksportu do `/var/lib/lms-huawei-alerts/alerts.json`, czytelnego dla serwera WWW. Przykład formatu: `config/alerts.example.json`. Nie zapisuj community ani surowych nagłówków trapów w eksporcie.

Archiwum nie zawiera bazy, prawdziwych community, danych klientów ani konfiguracji tego serwera. Dostęp do działającego demo może pokazywać rzeczywiste dane lokalnej instalacji. Szczegóły sprawdzeń: `DEMO-VERIFICATION.md`.
