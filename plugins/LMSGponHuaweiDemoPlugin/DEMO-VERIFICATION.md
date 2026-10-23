# DEMO RO — wariant uproszczony

Community RO jest przechowywane jawnie w osobnej tabeli demo; brak kodu szyfrowania i pliku klucza. Konfiguracja odbywa się przez stronę LMS, bez pliku JSON.

Aktywne operacje lokalne: dodawanie i edycja konfiguracji odczytu OLT, usuwanie jego danych wyłącznie z demo, zapis pomiarów i historii. Administracyjne formularze wymagają `full_access`, tokenu CSRF i walidacji pól; usuwanie dodatkowo jawnego potwierdzenia i transakcji.

Operacje zmiany fizycznego OLT nie są dostarczane. W kodzie komunikacji z urządzeniem są wyłącznie odczyty. Brak transportu WAN i procedur rejestracji, restartu, resetu, kasowania ONT oraz zmian VLAN/profili. Dane dostępowe do zmiany konfiguracji urządzeń nie są obsługiwane.

Paczka jest oddzielna od pełnej wersji; pełna wersja zachowuje szyfrowanie. Kopie sprzed migracji są przechowywane przez administratora poza katalogiem WWW i nie są częścią paczki demonstracyjnej.

Weryfikacja po uproszczeniu: wszystkie pliki PHP poprawne; dodawanie, edycja, osobny formularz RO, zachowanie pustego hasła i usuwanie zależności sprawdzone w wycofanej transakcji. Sprawdzono CSRF, odmowę zapisu dla nieadministratora i brak community w HTML. Po usunięciu klucza konto kolektora odczytało temperatury 32 portów. Szablon konfiguracji pochodzi z pełnej wersji; modyfikacje dotyczą wyłącznie demo i obsługi RO.

## Wariant ograniczony 1.1

Pozostawiono listę ONT z filtrami i odczytem diagnostyki, listę VLAN, alerty oraz konfigurację odczytu. Usunięto moduły, szablony i biblioteki Auto Find, rejestracji, szczegółów ONT/WAN, zarządzania service-portami i profili. Linki do usuniętych stron zostały zastąpione tekstem. Inwentaryzacja nie odpytuje już nazw line/service profili. Dotychczasowe dane lokalnej instalacji nie są kasowane przy ograniczeniu paczki.

Testy wersji ograniczonej: cztery strony renderują się poprawnie z adapterem blokującym zapisy bazy podczas GET. Test dodania, edycji i pełnego lokalnego usunięcia OLT przeszedł w wycofanej transakcji. Kontrola kodu potwierdziła brak odnośników do usuniętych modułów oraz instrukcji zmiany konfiguracji OLT.
