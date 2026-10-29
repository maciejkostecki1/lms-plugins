# Odbiornik raportów SMSAPI i pobieranie przez prywatny LMS

Ten wariant nie wymaga wystawiania LMS do internetu. Publiczny hosting odbiera raport, a wtyczka LMS pobiera go uwierzytelnionym połączeniem HTTPS. Kod nie zawiera prywatnej konfiguracji ani klucza.

## Wymagania

- Publiczny adres HTTPS z poprawnym certyfikatem i PHP 7.4+ z PDO SQLite.
- Prywatny, zapisywalny katalog poza katalogiem WWW, dostępny dla właściwej puli PHP.
- Po stronie LMS: PHP CLI z cURL, dostęp do bazy i harmonogram co około minutę.
- Zsynchronizowany czas obu serwerów; uwierzytelnione żądania mają ograniczoną ważność.

## 1. Pliki hostingu

W wybranym katalogu publicznym, np. `smsapi-reports/`, umieść:

```text
receive.php
exchange.php
RelayQueue.php
NotifySmsRelayProtocol.php
config-location.php
```

Pierwsze trzy pliki pochodzą z katalogu `relay/`. `NotifySmsRelayProtocol.php` skopiuj z `lib/`. Ostatni plik tworzony jest indywidualnie dla hostingu:

```php
<?php
define('LNP_RELAY_CONFIG', '/srv/sms-relay-private/config.json');
```

Ścieżkę należy dostosować do uprawnień i `open_basedir` hostingu. Nie umieszczaj w tym pliku samego klucza.

## 2. Prywatna konfiguracja

W prywatnym katalogu przygotuj konfigurację o strukturze:

```json
{
  "secret": "WSTAW_TUTAJ_64_ZNAKI_HEX_WYGENEROWANE_Z_32_LOSOWYCH_BAJTOW",
  "database": "/srv/sms-relay-private/reports.sqlite"
}
```

Wartość `secret` w przykładzie jest oznaczeniem miejsca do uzupełnienia, nie działającym kluczem. Wygeneruj kryptograficznie losowe 32 bajty zapisane szesnastkowo, np. PHP `bin2hex(random_bytes(32))`. Zapisz wynik bezpośrednio w chronionej konfiguracji; nie publikuj go w czacie, repozytorium, parametrach URL ani dziennikach.

Prywatny katalog powinien mieć uprawnienia 0700, a konfiguracja i SQLite 0600. Właścicielem musi być użytkownik odpowiedniej puli PHP. Kolejka tworzy bazę przy pierwszym użyciu. Nigdy nie udostępniaj bazy i pliku konfiguracyjnego przez HTTP.

## 3. Serwer WWW

Udostępnij tylko `receive.php` i `exchange.php`. Wyłącz logowanie parametrów żądań, ponieważ raport SMSAPI zawiera numer odbiorcy. Odbiornik sam sprawdza adres źródłowy SMSAPI i nie ufa nagłówkom przekazanym przez klienta.

Przykład dla Nginx — dostosuj ścieżki, pulę PHP i istniejący vhost:

```nginx
location = /smsapi-reports/receive.php {
    access_log off;
    client_max_body_size 8k;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/example.org/public/smsapi-reports/receive.php;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_intercept_errors off;
}
location = /smsapi-reports/exchange.php {
    access_log off;
    client_max_body_size 8k;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/example.org/public/smsapi-reports/exchange.php;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_intercept_errors off;
}
location ^~ /smsapi-reports/ { return 404; }
```

W panelach generujących konfigurację, takich jak ISPConfig, dodaj te ustawienia także w obsługiwanym polu dodatkowych dyrektyw witryny, aby kolejny zapis nie usunął zmian. Przed przeładowaniem wykonaj test składni konfiguracji WWW.

## 4. Prywatna konfiguracja LMS

Utwórz `/etc/lms/smsapi-relay.json`, dostępną tylko dla użytkownika uruchamiającego pobieranie:

```json
{
  "url": "https://example.org/smsapi-reports/exchange.php",
  "secret": "TEN_SAM_PRYWATNY_KLUCZ_64_HEX_CO_NA_HOSTINGU"
}
```

Przekaż klucz szyfrowanym kanałem. Nie jest to token SMSAPI i nie należy zastępować go tokenem operatora.

Z katalogu LMS sprawdź połączenie bez stosowania raportów:

```bash
php plugins/LMSNotifyPanelPlugin/bin/sms-reports.php --config-file=/etc/lms/lms.ini
```

Następnie przetwórz raporty:

```bash
php plugins/LMSNotifyPanelPlugin/bin/sms-reports.php --config-file=/etc/lms/lms.ini --run
```

Te polecenia nie wysyłają SMS-ów, nie wykonują blokad i nie modyfikują naliczania. Zastosowanie potwierdzenia może zarejestrować wcześniej jawnie zamówione odliczanie, zgodnie z zasadami wtyczki.

## 5. Harmonogram

Skopiuj dołączone `sms-reports.service` i `sms-reports.timer` do `/etc/systemd/system/` pod nazwami `lms-sms-reports.service` i `lms-sms-reports.timer`. Dopasuj `WorkingDirectory`, `ExecStart`, ścieżkę PHP oraz użytkownika usługi.

```bash
systemctl daemon-reload
systemctl enable --now lms-sms-reports.timer
systemctl start lms-sms-reports.service
systemctl show lms-sms-reports.service -p Result -p ExecMainStatus
```

Można zamiast tego stosować pojedynczy wpis cron co minutę. Nie instaluj obu wariantów bez potrzeby. Wtyczka posiada dodatkową blokadę równoczesnego pobierania.

## 6. Skierowanie raportów

W ustawieniach SMSAPI wtyczki wpisz swój publiczny adres `https://example.org/smsapi-reports/receive.php`. Jest on przekazywany w parametrze `notify_url` każdej nowej wiadomości, z pierwszeństwem przed globalnym adresem konta SMSAPI. Nie kieruj raportów na domenę przykładową ani na odbiornik cudzej instalacji.

Globalnego pola callback konta SMSAPI nie trzeba zmieniać. Adres przekazany przy wysyłce nie przekierowuje raportów wcześniej wysłanych wiadomości.

## Statusy i niezawodność

- QUEUE/ACCEPTED: przyjęto do kolejki; nie dowodzą doręczenia i nie rozpoczynają licznika.
- SENT: operator potwierdził wysłanie, ale nie ma jeszcze końcowego raportu doręczenia.
- DELIVERED: doręczono na dany numer.
- EXPIRED/UNDELIVERED/FAILED/REJECTED/STOP: brak skutecznego doręczenia na dany numer.

Każdy numer ma osobny status. Obecna reguła ostrzeżenia wymaga SENT/DELIVERED dla wszystkich numerów powiązanej wysyłki. Zmiana na negatywny status uniemożliwia wykorzystanie tego ostrzeżenia do blokady.

Raporty są potwierdzane dopiero po zapisaniu w LMS. Przerwanie między zapisem a potwierdzeniem prowadzi do ponownego, idempotentnego przetworzenia, bez ponownej wysyłki i bez przesuwania pierwszego terminu. Ślady potwierdzeń pozostają 7 dni. Dostosuj kopie i retencję kolejki do polityki swojej organizacji.

Źródła i format raportów: [dokumentacja SMSAPI](https://www.smsapi.pl/docs/#9-raporty-callback). Lista źródłowych IP w `NotifySmsRelayProtocol.php` wymaga utrzymywania zgodności z dokumentacją operatora.
