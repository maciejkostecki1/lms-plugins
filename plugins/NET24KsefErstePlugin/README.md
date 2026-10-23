# Eksport przelewów KSeF do Erste

Wtyczka `NET24KsefErstePlugin` dodaje do LMS pozycję **Finanse → Przelewy KSeF → ERSTE**. Generuje plik TXT z wybranych faktur zakupowych KSeF, także z opcjonalnym mechanizmem podzielonej płatności (MPP).

## Wymagania

- LMS z obsługą wtyczek, klasami `ConfigHelper`, `AccessRights` i istniejącym importem faktur KSeF do tabeli `ksefinvoices`.
- PostgreSQL; kod korzysta z jego wyrażeń regularnych w zapytaniach.
- PHP zgodne z używaną wersją LMS, rozszerzenia `mbstring` i `iconv`.
- Operator przypisany do odpowiedniego podmiotu w LMS i posiadający uprawnienie `ksef_payment_export`.

Wtyczka nie importuje faktur KSeF i nie dostarcza migracji tabel LMS/KSeF. W kodzie nie zadeklarowano numeru wersji wtyczki.

## Instalacja

Poniższe ścieżki zakładają LMS w `/var/www/html/lms`; dostosuj je do swojej instalacji.

1. Wykonaj kopię bazy, konfiguracji LMS i listy aktywnych wtyczek.
2. Skopiuj cały katalog `NET24KsefErstePlugin` z repozytorium do `/var/www/html/lms/plugins/`, zachowując nazwę. Pliki powinny być czytelne dla PHP/serwera WWW; proces WWW nie potrzebuje prawa zapisu do kodu.
3. W katalogu głównym LMS odśwież autoloader:

   ```sh
   cd /var/www/html/lms
   composer dump-autoload --optimize --no-scripts --no-interaction
   ```

4. Włącz `NET24KsefErstePlugin` w konfiguracji wtyczek LMS. `NET24KsefPaymentExportPlugin` udostępnia moduł o tej samej nazwie `ksefpaymentexport`; nie włączaj obu równocześnie dla tej samej strony, aby uniknąć zależności od kolejności ładowania.
5. Nadaj operatorowi uprawnienie **Eksport przelewów KSeF do ERSTE** (`ksef_payment_export`) i dostęp do właściwego podmiotu.
6. W systemowej konfiguracji LMS, np. `/etc/lms/lms.ini`, dodaj sekcję rachunków nadawcy:

   ```ini
   [ksef_erste]
   sender_nrb_1 = "UZUPELNIJ_26_CYFROWYM_NRB"
   ```

   Zastąp `1` rzeczywistym identyfikatorem podmiotu z LMS. Dla kolejnych podmiotów dodaj `sender_nrb_2` itd. Wartość musi być poprawnym polskim NRB: 26 cyfr, bez prefiksu `PL`. Powyższy tekst jest znacznikiem do zastąpienia, nie działającym numerem rachunku. Nie dodawaj własnego `lms.ini` ani wygenerowanych przelewów do repozytorium.

## Sprawdzenie działania

1. Otwórz **Finanse → Przelewy KSeF → ERSTE**.
2. Wybierz podmiot i daty wystawienia faktur. Domyślny zakres to poprzedni miesiąc.
3. Sprawdź faktury, rachunki odbiorców i adresy. Wybierz faktury oraz ewentualnie MPP. Eksport obejmuje pełną kwotę brutto wskazanych dokumentów, nie wylicza pozostałej kwoty po częściowych wpłatach.
4. Pobierz TXT i sprawdź dane w podglądzie importu banku przed zatwierdzeniem jakiejkolwiek płatności. Sam eksport nie zleca przelewów i nie oznacza faktur jako rozliczonych w LMS.

Lista ma limit 200 dokumentów i obejmuje PLN, dodatnie kwoty oraz rachunki spełniające walidację. Format pliku: Windows-1250, zakończenia wierszy CRLF, separator `|`. MPP wymaga także prawidłowego NIP i kwoty VAT.

## Rozwiązywanie problemów

- **Brak menu:** sprawdź aktywację wtyczki, classmap Composer i uprawnienie operatora.
- **Brak podmiotu / brak dostępu:** sprawdź przypisanie użytkownika do podmiotu.
- **Pusta lista:** sprawdź import KSeF, daty wystawienia, walutę PLN i poprawność danych odbiorcy.
- **Brak poprawnego NRB nadawcy:** sprawdź klucz `ksef_erste.sender_nrb_ID` dla wybranego podmiotu.

## Aktualizacja i wyłączenie

Przed aktualizacją zachowaj kopię katalogu i konfiguracji. Po podmianie kodu odśwież autoloader. Wyłączenie wtyczki w LMS usuwa jej stronę z użycia; nie cofa plików wcześniej zaimportowanych do banku. Wtyczka nie ma własnego schematu bazy ani zadań cron.
