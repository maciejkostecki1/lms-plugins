<?php
/* LMS 1.11-git: direct ERSTE TXT export of selected KSeF purchase invoices.
 * Uses KSeF invoices already imported into LMS.
 * Sender accounts are configured in lms.ini.
 */

function kpe_nrb($value)
{
    $nrb = preg_replace('/[\s-]+/u', '', (string) $value);
    if (!preg_match('/^[0-9]{26}$/D', $nrb)) {
        return false;
    }
    // Polish NRB MOD97 (check digits move to end with country code PL=2521).
    $check = substr($nrb, 2) . '2521' . substr($nrb, 0, 2);
    $remainder = 0;
    for ($i = 0, $n = strlen($check); $i < $n; $i++) {
        $remainder = ($remainder * 10 + (int) $check[$i]) % 97;
    }
    return $remainder === 1 ? $nrb : false;
}

function kpe_field($value, $max, $label, $allowedPattern)
{
    $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
    if ($value === '' || mb_strlen($value, 'UTF-8') > $max ||
        strpos($value, '|') !== false || !preg_match($allowedPattern, $value)) {
        throw new RuntimeException('Niepoprawne pole ' . $label . ' (maks. ' . $max . ' znaków).');
    }
    return $value;
}

function kpe_amount($value)
{
    $s = trim((string) $value);
    if (!preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,5}))?$/D', $s, $m)) {
        throw new RuntimeException('Niepoprawna kwota (wymagane PLN, 2 miejsca po przecinku): ' . $s);
    }
    $whole = ltrim($m[1], '0');
    if ($whole === '') $whole = '0';
    $decimals = isset($m[2]) ? $m[2] : '';
    if (strlen($decimals) > 2 && rtrim(substr($decimals, 2), '0') !== '') {
        throw new RuntimeException('Kwota ma niezerowe ułamki grosza: ' . $s);
    }
    $fraction = str_pad(substr($decimals, 0, 2), 2, '0');
    if ($whole === '0' && $fraction === '00') {
        throw new RuntimeException('Kwota przelewu musi być większa od zera.');
    }
    // Bank limit 13 characters including comma.
    $formatted = $whole . ',' . $fraction;
    if (strlen($formatted) > 13) {
        throw new RuntimeException('Kwota przekracza limit formatu ERSTE.');
    }
    return $formatted;
}

function kpe_export_line($sender, $invoice, $recipientAddress, $splitPayment = false)
{
    $recipient = kpe_nrb($invoice['bank_account']);
    if ($recipient === false) {
        throw new RuntimeException('Nieprawidłowy NRB odbiorcy: ' . $invoice['invoice_number']);
    }
    if (strtoupper(trim((string) $invoice['currency'])) !== 'PLN') {
        throw new RuntimeException('Waluta inna niż PLN: ' . $invoice['invoice_number']);
    }
    $name = kpe_field(mb_substr(trim($invoice['seller_name']), 0, 80, 'UTF-8'), 80, 'nazwa odbiorcy', '/^[\p{L}0-9 `!@#$%^&*()_+\-=\[\]{};:.,?\/]+$/uD');
    $address = kpe_field(trim((string) $recipientAddress) === '' ? '.' : $recipientAddress, 60, 'adres odbiorcy', '/^[\p{L}0-9 .,:;\/\-]+$/uD');
    $title = kpe_field('Faktura ' . $invoice['invoice_number'], 140, 'tytuł przelewu', '/^[\p{L}0-9 `!@#$%^&*()_+\-=\[\]{};:.,?\/]+$/uD');
    $type = '1';
    $nipField = '';
    if ($splitPayment) {
        $nip = trim((string) ($invoice['seller_ten'] ?? ''));
        if (!preg_match('/^[0-9]{10}$/D', $nip)) {
            throw new RuntimeException('MPP: nieprawidłowy NIP sprzedawcy na fakturze ' . $invoice['invoice_number']);
        }
        $number = kpe_field($invoice['invoice_number'], 35, 'numer faktury MPP', '/^[\p{L}0-9 .,:;\/\-]+$/uD');
        $vat = kpe_amount($invoice['vat_amount']);
        $grossCents = (int) str_replace(',', '', kpe_amount($invoice['gross_amount']));
        $vatCents = (int) str_replace(',', '', $vat);
        if ($vatCents > $grossCents) {
            throw new RuntimeException('MPP: VAT przekracza brutto na fakturze ' . $invoice['invoice_number']);
        }
        $type = '6';
        $title = '/VAT/' . $vat . '/IDC/' . $nip . '/INV/' . $number . '/TXT/Faktura';
        $nipField = ''; // Specyfikacja MPP: NIP zawarty w tytule; po dacie brak osobnego pola NIP.
    }
    $amount = kpe_amount($invoice['gross_amount']);
    // 10 fields followed by final separator: transfer type, sender, recipient,
    // recipient name, address, amount, payment channel, title, date, NIP.
    return implode('|', array($type, $sender, $recipient, $name, $address, $amount, '1', $title, '', $nipField)) . '|';
}

$userid = (int) Auth::GetCurrentUser();
$divisions = $DB->GetAll(
    'SELECT d.id, d.name FROM divisions d JOIN userdivisions ud ON ud.divisionid = d.id WHERE ud.userid = ? ORDER BY d.id',
    array($userid)
);
$divisions = $divisions ?: array();
$allowed = array_map('intval', array_column($divisions, 'id'));
$divisionId = isset($_REQUEST['division']) ? (int) $_REQUEST['division'] : (isset($allowed[0]) ? $allowed[0] : 0);
if (!in_array($divisionId, $allowed, true)) {
    die('Brak dostępu do podmiotu');
}

$defaultFrom = date('Y-m-01', strtotime('first day of last month'));
$defaultTo = date('Y-m-t', strtotime('first day of last month'));
$dateFrom = isset($_REQUEST['date_from']) ? (string) $_REQUEST['date_from'] : $defaultFrom;
$dateTo = isset($_REQUEST['date_to']) ? (string) $_REQUEST['date_to'] : $defaultTo;

// LMS datepicker: YYYY/MM/DD. Akceptujemy rowniez YYYY-MM-DD.
$dateFrom = str_replace('/', '-', trim($dateFrom));
$dateTo = str_replace('/', '-', trim($dateTo));

$from = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFrom);
$to = DateTimeImmutable::createFromFormat('!Y-m-d', $dateTo);
if (!$from || !$to || $from->format('Y-m-d') !== $dateFrom || $to->format('Y-m-d') !== $dateTo || $from > $to) {
    die('Nieprawidłowy zakres dat');
}
$start = $from->getTimestamp();
$end = $to->modify('+1 day')->getTimestamp();

$csrfKey = 'ksef_payment_csrf';
$csrfToken = $SESSION->get($csrfKey);
if (!is_string($csrfToken) || !preg_match('/^[a-f0-9]{64}$/D', $csrfToken)) {
    $csrfToken = bin2hex(random_bytes(32));
    $SESSION->save($csrfKey, $csrfToken);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provided = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
    if (!is_string($provided) || !hash_equals($csrfToken, $provided)) {
        $error = 'Niepoprawny token CSRF. Odśwież stronę i spróbuj ponownie.';
    } elseif (($_POST['action'] ?? '') !== 'export') {
        $error = 'Nieznana operacja.';
    } else {
        try {
            $sender = kpe_nrb(
                ConfigHelper::getConfig(
                    'ksef_erste.sender_nrb_' . $divisionId,
                    ''
                )
            );
            if ($sender === false) {
                throw new RuntimeException('Brak poprawnego 26-cyfrowego NRB nadawcy dla podmiotu ' . $divisionId);
            }
            $ids = $_POST['invoice_ids'] ?? array();
            if (!is_array($ids) || count($ids) < 1 || count($ids) > 200) {
                throw new RuntimeException('Zaznacz od 1 do 200 faktur.');
            }
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (!isset($_POST['recipient_address']) || !is_array($_POST['recipient_address'])) {
                throw new RuntimeException('Brak danych adresowych odbiorców.');
            }
            if (in_array(0, $ids, true)) {
                throw new RuntimeException('Nieprawidłowy identyfikator faktury.');
            }
            $mppIds = $_POST['mpp_ids'] ?? array();
            if (!is_array($mppIds)) {
                throw new RuntimeException('Niepoprawny wybór MPP.');
            }
            $mppIds = array_values(array_unique(array_map('intval', $mppIds)));
            if (array_diff($mppIds, $ids)) {
                throw new RuntimeException('MPP zaznaczono dla faktury spoza eksportu.');
            }
            $lines = array('4120414|1');
            $selected = array();
            foreach ($ids as $id) {
                $invoice = $DB->GetRow(
                    'SELECT i.id, i.division_id, i.invoice_number,
                            i.seller_name, i.gross_amount, i.currency,
                            i.bank_account, i.issue_date, i.seller_ten, i.vat_amount
                     FROM ksefinvoices i
                     JOIN userdivisions ud ON ud.divisionid = i.division_id
                     WHERE i.id = ? AND i.division_id = ?
                       AND ud.userid = ?
                       AND i.issue_date >= ? AND i.issue_date < ?',
                    array($id, $divisionId, $userid, $start, $end)
                );
                if (!$invoice) {
                    throw new RuntimeException('Faktura niedostępna w wybranym okresie: ID ' . $id);
                }
                $postedAddress = $_POST['recipient_address'][$id] ?? '';
                $invoice['export_address'] = trim((string) $postedAddress);
                $lines[] = kpe_export_line($sender, $invoice, $invoice['export_address'], in_array($id, $mppIds, true));
                $selected[] = $invoice;
            }

            $utf8 = implode("\r\n", $lines) . "\r\n";
            $bytes = iconv('UTF-8', 'Windows-1250', $utf8);
            if ($bytes === false) {
                throw new RuntimeException('Nie można zakodować znaków w Windows-1250.');
            }
            // Generate filename without storing the TXT file on the server.
            $filename = 'ERSTE_' . $divisionId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.txt';

            // Important: LMS closes its own session at the end of index.php.
            // Save it before exit; ordinary PHP header()+exit() would skip that.
            $SESSION->close();
            header('Content-Type: text/plain; charset=windows-1250');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($bytes));
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            echo $bytes;
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$invoices = $DB->GetAll(
    <<<SQL
SELECT i.id, i.invoice_number, i.seller_name,
            i.gross_amount, i.vat_amount, i.seller_ten, i.currency, i.bank_account,
            i.issue_date, i.settled
     FROM ksefinvoices i
     JOIN userdivisions ud ON ud.divisionid = i.division_id
     WHERE i.division_id = ?
       AND ud.userid = ?
       AND i.issue_date >= ?
       AND i.issue_date < ?
       AND i.gross_amount > 0
       AND UPPER(TRIM(i.currency)) = ?
       AND REGEXP_REPLACE(
           REGEXP_REPLACE(UPPER(COALESCE(i.bank_account, '')), '^PL', ''),
           '[[:space:]]', '', 'g'
       ) ~ '^[0-9]{26}$'
     ORDER BY i.issue_date DESC, i.id DESC
     LIMIT 200
SQL,
    array($divisionId, $userid, $start, $end, 'PLN')
);
// Pokazuj tylko faktury kwalifikujace sie do przelewu ERSTE.
$invoices = array_values(array_filter($invoices ?: array(), function ($invoice) {
    $account = trim((string) ($invoice['bank_account'] ?? ''));

    // Sprawdz nazwe odbiorcy zgodnie z wymaganiami eksportu.
    try {
        kpe_field(
            mb_substr(trim((string) ($invoice['seller_name'] ?? '')), 0, 80, 'UTF-8'),
            80,
            'nazwa odbiorcy',
            '/^[\\p{L}0-9 `!@#$%^&*()_+\\-=\\[\\]{};:.,?\\/]+$/uD'
        );
    } catch (Throwable $e) {
        return false;
    }

    return kpe_nrb($account) !== false
        && (float) $invoice['gross_amount'] > 0
        && strtoupper(trim((string) $invoice['currency'])) === 'PLN';
}));

$SMARTY->assign('ksef_payment_divisions', $divisions);
$SMARTY->assign('ksef_payment_division_id', $divisionId);
$SMARTY->assign('ksef_payment_date_from', $dateFrom);
$SMARTY->assign('ksef_payment_date_to', $dateTo);
$SMARTY->assign('ksef_payment_invoices', $invoices ?: array());
$SMARTY->assign('ksef_payment_csrf', $csrfToken);
$SMARTY->assign('ksef_payment_error', $error);
$SMARTY->display('ksef/ksefpaymentexport.html');
