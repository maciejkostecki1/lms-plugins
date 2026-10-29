<?php
/* LMS 1.11-git: direct ERSTE TXT export of selected KSeF purchase invoices.
 * Uses KSeF invoices already imported into LMS.
 * Sender accounts are configured in lms.ini.
 */

function kpe_nrb($value)
{
    $nrb = preg_replace('/[\s-]+/u', '', strtoupper(trim((string) $value)));
    if (substr($nrb, 0, 2) === 'PL') $nrb = substr($nrb, 2);
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
    $address = kpe_field($recipientAddress, 60, 'adres odbiorcy', '/^[\p{L}0-9 .,:;\/\-]+$/uD');
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


function kpe_fingerprint(array $invoice, string $sender): string
{
    $data=[];
    foreach (['id','division_id','invoice_number','seller_name','gross_amount','vat_amount','currency','seller_ten','issue_date','settled'] as $key) $data[$key]=(string)($invoice[$key]??'');
    $data['bank_account']=kpe_nrb($invoice['bank_account']) ?: (string)$invoice['bank_account'];
    $data['sender']=$sender;
    return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
}
function kpe_eligible(array $invoice): bool
{
    try { kpe_export_line('', $invoice, 'Adres do uzupelnienia'); return true; }
    catch(Throwable $e) { return false; }
}
function kpe_ids($input): array
{
    if(!is_array($input) || !$input || count($input)>200) throw new RuntimeException('Zaznacz od 1 do 200 faktur.');
    foreach($input as $id)if(!is_scalar($id) || !preg_match('/^[1-9][0-9]{0,9}$/D',(string)$id))throw new RuntimeException('Niepoprawny identyfikator faktury.');
    return array_values(array_unique(array_map('intval',$input)));
}
function kpe_cents(string $amount): int { return (int)str_replace(',','',kpe_amount($amount)); }
function kpe_bytes(array $lines): string
{
    $bytes=iconv('UTF-8','Windows-1250',implode("\r\n",array_merge(['4120414|1'],$lines))."\r\n");
    if($bytes===false)throw new RuntimeException('Nie można zakodować znaków w Windows-1250.');
    return $bytes;
}
