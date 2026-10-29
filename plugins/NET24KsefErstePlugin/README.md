# Eksport przelewów KSeF do Erste — 1.0.0

Wtyczka `NET24KsefErstePlugin` dodaje do LMS pozycję **Finanse → Przelewy KSeF → ERSTE**. Generuje plik TXT z wybranych faktur zakupowych KSeF, także z opcjonalnym mechanizmem podzielonej płatności (MPP).

## Wymagania

- LMS z obsługą wtyczek, klasami `ConfigHelper`, `AccessRights` i istniejącym importem faktur KSeF do tabeli `ksefinvoices`.
- PostgreSQL; kod korzysta z jego wyrażeń regularnych w zapytaniach.
- PHP zgodne z używaną wersją LMS, rozszerzenia `mbstring` i `iconv`.
- Operator przypisany do odpowiedniego podmiotu w LMS i posiadający uprawnienie `ksef_payment_export`.

Wtyczka nie importuje faktur KSeF i nie dostarcza migracji tabel LMS/KSeF. Od wydania 1.0.0 wtyczka deklaruje numer wersji.

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

   Zastąp `1` rzeczywistym identyfikatorem podmiotu z LMS. Dla kolejnych podmiotów dodaj `sender_nrb_2` itd. Wartość musi być poprawnym polskim rachunkiem NRB (26 cyfr); akceptowany jest też prefiks `PL`. Przed eksportem rachunek normalizowany jest do NRB i sprawdzany MOD97. Powyższy tekst jest znacznikiem do zastąpienia, nie działającym numerem rachunku. Nie dodawaj własnego `lms.ini` ani wygenerowanych przelewów do repozytorium.

## Sprawdzenie działania

1. Otwórz **Finanse → Przelewy KSeF → ERSTE**.
2. Wybierz podmiot i daty wystawienia faktur. Domyślny zakres to poprzedni miesiąc.
3. Sprawdź faktury, rachunki odbiorców i adresy. Wybierz faktury oraz ewentualnie MPP. Eksport obejmuje pełną kwotę brutto wskazanych dokumentów, nie wylicza pozostałej kwoty po częściowych wpłatach.
4. Przygotuj podgląd przelewów i sprawdź odbiorców, rachunki, adresy, MPP, liczbę dokumentów oraz sumę pełnych kwot brutto. Następnie osobno potwierdź pobranie TXT i sprawdź plik w podglądzie importu banku przed zatwierdzeniem jakiejkolwiek płatności. Sam eksport nie zleca przelewów i nie oznacza faktur jako rozliczonych w LMS.

Strona listy zawiera do 200 poprawnych dokumentów i ma przyciski paginacji. Walidacja następuje przed podziałem na strony; odrzucone rekordy nie zajmują miejsca poprawnym. Lista obejmuje PLN, dodatnie kwoty oraz rachunki spełniające walidację. Liczba pominięć dotyczy przeskanowanego zakresu. Format pliku: Windows-1250, zakończenia wierszy CRLF, separator `|`. MPP wymaga także prawidłowego NIP i kwoty VAT.

## Bezpieczne przygotowanie eksportu

- „Wszystkie nierozliczone” pomija faktury rozliczone. Można wybrać je pojedynczo, ale pobranie wymaga osobnego potwierdzenia ryzyka ponownej płatności.
- Rachunki, kwoty, odbiorcy, stan rozliczenia i rachunek nadawcy są powiązane z listą oraz zatwierdzonym podglądem. Zmiana danych wymaga nowego podglądu. Potwierdzenie jest jednorazowe i wygasa po 10 minutach, a lista po 15 minutach.
- Adres jest wymagany tylko dla zaznaczonych dokumentów, także po stronie serwera. Błąd nie zastępuje adresu kropką.
- Sesja operatora zapamiętuje ostatnie pobrania i ostrzega o ich powtórzeniu. Jest to lokalna pomoc, **nie pełny rejestr przelewów ani dowód zapłaty**; po zakończeniu sesji nie zastępuje historii banku.
- Wybrane faktury są pobierane grupowo. Eksport wciąż nie zmienia żadnego księgowania ani pola rozliczenia i nie komunikuje się z bankiem.
- Aktualizacja nie wymaga migracji bazy. Po podmianie plików odśwież stronę i przygotuj nowy podgląd; stare formularze nie mogą pobrać TXT bez nowego potwierdzenia.

## Testy

`php tests/workflow.php` sprawdza normalizację rachunków, walidację adresów i kwot, podgląd, jednorazowe potwierdzenie, zmianę danych, rozliczone dokumenty, historię pobrań i paginację. Używa wyłącznie fikcyjnych faktur i atrap bazy/sesji. Nie wykonuje przelewów i nie łączy się z produkcyjną bazą. Format pliku i kwoty należy nadal sprawdzić w podglądzie banku.

## Rozwiązywanie problemów

- **Brak menu:** sprawdź aktywację wtyczki, classmap Composer i uprawnienie operatora.
- **Brak podmiotu / brak dostępu:** sprawdź przypisanie użytkownika do podmiotu.
- **Pusta lista:** sprawdź import KSeF, daty wystawienia, walutę PLN i poprawność danych odbiorcy.
- **Brak poprawnego NRB nadawcy:** sprawdź klucz `ksef_erste.sender_nrb_ID` dla wybranego podmiotu.

## Aktualizacja i wyłączenie

Przed aktualizacją zachowaj kopię katalogu i konfiguracji. Po podmianie kodu odśwież autoloader. Wyłączenie wtyczki w LMS usuwa jej stronę z użycia; nie cofa plików wcześniej zaimportowanych do banku. Wtyczka nie ma własnego schematu bazy ani zadań cron.

## Zrzut ekranu

Widok przygotowano z szablonu wtyczki z fikcyjnymi dostawcami i fakturami. Numery rachunków złożone z zer są wyłącznie znacznikami przykładowymi. Wygląd zależy od motywu i wersji LMS.

![Eksport Erste — wybór podmiotu, faktur i MPP](../../docs/screenshots/erste-eksport.jpg)

## Wsparcie rozwoju

Jeśli wtyczki są dla Ciebie przydatne, możesz dobrowolnie wesprzeć ich dalszy rozwój:

[☕ Postaw kawę — wesprzyj rozwój wtyczek LMS](https://buycoffee.to/kostek_mostek)

Wsparcie jest dobrowolne i nie stanowi opłaty za instalację ani gwarancji pomocy technicznej.
