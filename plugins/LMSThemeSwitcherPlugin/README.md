# Motywy LMS — 2.0.2

Wtyczka wyglądu do LMS/LMS+. Pozwala operatorowi dostosować kolory, czcionkę i układ panelu bez edycji plików źródłowych LMS.

## Możliwości

- 12 gotowych motywów: Czarno-biały, Niebieski, Zielony, Czerwony, Nowoczesny, Ciemny, Grafitowy, Gołębi, Szary matowy, Jasny szary, Ognisty i Ocean głębinowy.
- Oryginalny wygląd LMS oraz tryb Ręczny: własne kolory tła i tekstu menu oraz części głównej, z podglądem współczynnika kontrastu.
- Lokalne czcionki: Systemowa, Arial, Segoe UI i Verdana; tekst 13–16 px.
- Trzy gęstości: Kompaktowa, Standardowa i Przestronna. Zmieniają odstępy, wysokości pól i przycisków oraz wielkość podglądów bez zmiany wybranego rozmiaru czcionki.
- Szerokość menu na komputerze: domyślna LMS albo 200, 240, 280, 320 lub 360 px. Menu telefonu zachowuje automatyczną szerokość.
- Opcjonalne przyklejone nagłówki tabel, uwzględniające wysokość górnego paska LMS. Tabele z własnym przewijaniem mają obszar do 70% wysokości okna.
- Automatyczny zapis ustawień osobno dla operatora i przeglądarki oraz synchronizacja między otwartymi kartami.

Ustawienia znajdują się w **Konfiguracja → Motywy LMS → Ustawienia motywu**. Zmiana jest widoczna od razu. Przycisk **Przywróć oryginalny** resetuje także gęstość, szerokość menu i przyklejanie nagłówków.

Motyw **Gołębi** ma menu `#86a1ac`, jasnoszare tło `#e9e9e9` i czarny tekst `#000000`.

## Instalacja

1. Skopiuj katalog `LMSThemeSwitcherPlugin` do katalogu `plugins` instalacji LMS. Zachowaj nazwę katalogu.
2. Zapewnij odczyt plików przez serwer WWW: zwykle katalogi `755`, pliki `644`. Wtyczka nie wymaga prawa zapisu.
3. W katalogu głównym LMS odśwież indeks klas:

   ```sh
   composer dump-autoload --optimize --no-scripts --no-plugins
   ```

4. W **Konfiguracja → Wtyczki** włącz **Motywy LMS**.
5. Odśwież panel i otwórz **Konfiguracja → Motywy LMS** (`?m=lmstheme`).

Operatorowi bez pełnych praw można przydzielić uprawnienie **Motywy LMS — własne ustawienia wyglądu** w standardowej konfiguracji praw LMS.

Odświeżenie indeksu klas jest standardowym krokiem instalowania wtyczki. Aktualizuje generowane pliki Composer, bez modyfikacji kodu źródłowego LMS. Nie trzeba dodawać wpisów w `lms.ini`, wykonywać migracji bazy ani konfigurować zadań cyklicznych.

Opcjonalna kontrola instalacji, uruchamiana z katalogu głównego LMS:

```sh
php plugins/LMSThemeSwitcherPlugin/check-install.php
```

## Zgodność i zasady działania

Sprawdzono z LMS 27.80 i PHP 8.2. Wymagany jest nowoczesny mechanizm wtyczek LMS z obsługą hooków Smarty, modułów, menu i uprawnień oraz standardowa powłoka interfejsu LMS. Inne wersje i niestandardowe widoki mogą wymagać dostosowania.

- Ustawienia są lokalne dla konta operatora w danej przeglądarce. Nie przenoszą się automatycznie między komputerami.
- Przy niedostępnym zapisie w przeglądarce wybór działa w bieżącej karcie.
- Oryginalny wygląd jest domyślny. Nowe ustawienia układu działają również z oryginalnym motywem.
- Style dotyczą ekranu. Logowanie, dokumenty do pobrania, odpowiedzi API oraz wydruki nie są przemalowywane.
- Treść edytorów, obrazy, mapy i własne kolory zapisane w treści szablonów wiadomości są wyłączone z automatycznego dopasowania.
- Wtyczka nie zmienia formularzy operacyjnych, tokenów CSRF, uprawnień ani konfiguracji urządzeń. Nie wysyła danych do usług zewnętrznych i nie pobiera zewnętrznych fontów.
- Dowolna ręczna paleta może mieć słaby kontrast; przy jej wyborze pomaga wskaźnik kontrastu.

## Aktualizacja 2.0.2

Zachowano jawne tła ostrzegawcze oraz kolory tabel w treściach wiadomości i obszarach `data-lms-theme-preserve`. Wyłączenia działają także w CSS, a nie tylko w adapterze JavaScript. Przyklejone nagłówki mogą ograniczać wysokość wyłącznie istniejącego przewijanego opakowania samej tabeli; nie zmieniają przewijania wspólnych sekcji z przyciskami. Test PHP działa niezależnie od ustawienia asercji. Dotychczasowe preferencje i format zapisu w przeglądarce pozostają zgodne.

## Aktualizacja 2.0.1

Wzmocniono różnice pomiędzy gęstościami i objęto nimi pola, przyciski oraz panele. W przykładowej tabeli przy czcionce 15 px wiersze miały około 33 / 56 / 100 px. Rzeczywista wysokość zależy od zawartości i szerokości tabeli. Telefon zachowuje większe obszary dotykowe.

Wersja 2.0 dodała szerokość menu i przyklejone nagłówki. Starszy zapis `compact=true` jest automatycznie zachowywany jako gęstość Kompaktowa.

## Testy

Z katalogu wtyczki:

```sh
php tests/integration.php
node tests/layout-preferences.cjs
node --check assets/switcher.js
node --check assets/compatibility.js
```

Testy sprawdzają dołączanie zasobów, pomijanie odpowiedzi innych niż panel LMS, zachowanie formularzy i migrację ustawień gęstości. Test instalacji wymaga pełnej instalacji LMS; testy integracji używają lokalnych atrap klas.

Test przeglądarkowy `tests/browser.cjs` wymaga Playwright i Chrome (albo ustawienia `LMS_TEST_BROWSER`). Moduł Playwright można wskazać przez `LMS_TEST_PLAYWRIGHT`. Test używa osobnego profilu i sztucznego dokumentu z przechwyceniem żądań; sprawdza kolory ostrzeżeń, treść chronioną, przewijanie sekcji, edycję kolorów oraz druk. Nie zmienia preferencji prawdziwego operatora. Nie jest wymagany do działania wtyczki.
