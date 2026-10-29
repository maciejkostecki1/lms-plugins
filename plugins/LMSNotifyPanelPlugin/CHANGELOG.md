# Zmiany

## 0.8.4 — 29.10.2026

- Raport DELIVERED na jeden numer wystarcza do uruchomienia zamówionego odliczania; drugi numer może oczekiwać lub zakończyć się błędem.
- Status SENT nie jest traktowany jako doręczenie SMS.
- Komunikat o doręczeniu na liście i w karcie klienta jest niezależny od odliczania i automatycznej blokady.
- Kolejne raporty nie przesuwają początku licznika; zachowano progi, rozliczenia i reguły e-maili.
- Testy regresji dla wielu numerów, statusów doręczenia i odliczania.

## 0.8.3 — 29.10.2026

- Wspólny próg powiadomienia dla ręcznych SMS-ów i e-maili, pojedynczych i zbiorczych, także z historią płatności.
- Kontrola progu w podglądzie oraz ponownie przed wykonaniem pozycji kolejki; obliczenia w groszach.
- Osobne statystyki klientów ponad progiem i spełniających wszystkie obecne warunki blokady.
- Ustawienia i szablony faktur w zakładce bieżących faktur; zapis formularza pozostaje w tej zakładce.
- Nazwa klienta i link do karty w historii SMS.
- Zaktualizowana instrukcja wymagań, instalacji i publicznego odbiornika raportów.

## 0.8.2 — 29.10.2026

- Oddzielny odbiornik raportów SMSAPI na publicznym hostingu, z prywatną kolejką SQLite.
- Uwierzytelnione pobieranie raportów przez prywatny LMS, z podpisami żądań i odpowiedzi oraz ochroną przed powtórzeniem.
- Adres raportów przekazywany do SMSAPI przy nowej wysyłce przez `notify_url`.
- Timer systemd pobierający raporty co około minutę; bez konieczności wystawiania LMS do internetu.

## 0.8.1 — 29.10.2026

- Przyciski powiadomień i wspólny checkbox odliczania w karcie klienta, dodane przez hook wtyczki.
- Naprawa podglądu zbiorczego SMS-a oraz kontrola zmian numerów i treści od czasu podglądu.
- Trwały rejestr SMSAPI, obsługa raportów statusu i odliczania po potwierdzeniu wszystkich numerów.
- Normalizacja i deduplikacja polskich numerów; kontrola długości jednej części przed wywołaniem operatora.
- Osobne, domyślnie wyłączone zezwolenie na automatyczne SMS-y.

Wydanie 0.8.0 oraz wcześniejsze wersje są dostępne w historii repozytorium. Ich opisy ręcznej wysyłki bez progu nie dotyczą 0.8.3.
