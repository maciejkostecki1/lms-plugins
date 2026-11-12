<div id="lnp-email-preview" style="width:100%;min-width:0;box-sizing:border-box;margin:12px 0 22px;padding:15px;border:1px solid #c7cbd1;border-radius:5px">

<h3 style="margin-top:0">Podgląd wiadomości e-mail</h3>
<p>Symulacja na przykładowych danych. Bez wysyłania wiadomości.</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:15px">

<div>
<label for="lnp-mail-debt" style="display:block">Przykładowe zadłużenie (zł)</label>
<input id="lnp-mail-debt" type="number" step="0.01" value="285.00" style="width:100%;box-sizing:border-box">
</div>

<div>
<label for="lnp-mail-balance" style="display:block">Przykładowe saldo (zł)</label>
<input id="lnp-mail-balance" type="number" step="0.01" value="285.00" style="width:100%;box-sizing:border-box">
</div>

<div>
<label for="lnp-mail-monthly" style="display:block">Abonament miesięczny (zł)</label>
<input id="lnp-mail-monthly" type="number" step="0.01" value="100.00" style="width:100%;box-sizing:border-box">
</div>

</div>

<div style="margin-bottom:12px">
<strong>Temat:</strong>
<div id="lnp-mail-preview-subject" style="padding:10px;margin-top:5px;border:1px solid #ccc;white-space:pre-wrap;overflow-wrap:anywhere"></div>
</div>

<div>
<strong>Treść wiadomości:</strong>
<pre id="lnp-mail-preview-body" style="white-space:pre-wrap;overflow-wrap:anywhere;max-width:100%;box-sizing:border-box;padding:15px;margin-top:5px;border:1px solid #ccc;font:inherit;line-height:1.5"></pre>
</div>

<p id="lnp-mail-preview-error" role="status" aria-live="polite" style="color:#b00020"></p>

<small style="display:block;white-space:normal;overflow-wrap:anywhere">
Obsługiwane zmienne: %cid, %name, %customername, %balance,
%debt, %monthly, %percent, %bankaccount, %payment_request.
Liczba dni dla %payment_request pochodzi z ustawienia blokady powyżej.
</small>

</div>

<script>
(() => {
    const root = document.getElementById('lnp-email-preview');
    const form = root.closest('form');
    if (!form) return;

    const subject = form.querySelector('[name="subject"]');
    const body = form.querySelector('[name="body"]');
    const days = form.querySelector('[name="block_after_days"]');

    const debt = document.getElementById('lnp-mail-debt');
    const balance = document.getElementById('lnp-mail-balance');
    const monthly = document.getElementById('lnp-mail-monthly');

    const outSubject = document.getElementById('lnp-mail-preview-subject');
    const outBody = document.getElementById('lnp-mail-preview-body');
    const error = document.getElementById('lnp-mail-preview-error');

    if (!subject || !body || !days) return;

    const money = value => {
        const number = Number(value);
        return Number.isFinite(number)
            ? number.toLocaleString('pl-PL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).replace(/\u00a0/g, ' ')
            : '0,00';
    };

    const update = () => {
        const d = Number(debt.value);
        const b = Number(balance.value);
        const m = Number(monthly.value);
        const n = Number(days.value);

        const validDays = Number.isInteger(n) && n >= 0 && n <= 365;
        const paymentRequest = validDays && n > 0
            ? 'Prosimy o wpłatę w ciągu ' + n + ' dni od tej wiadomości.'
            : 'Prosimy o wpłatę.';

        const percent = m > 0
            ? (d / m * 100).toFixed(2).replace('.', ',')
            : '0';

        const variables = {
            '%cid': '1234',
            '%name': 'Jan Kowalski',
            '%customername': 'Jan Kowalski',
            '%balance': money(b),
            '%debt': money(d),
            '%monthly': money(m),
            '%percent': percent,
            '%bankaccount': 'PRZYKŁADOWY NUMER RACHUNKU',
            '%payment_request': paymentRequest
        };

        const unknown = new Set();

        const render = text => text.replace(
            /%[a-zA-Z_]+/g,
            token => {
                if (Object.prototype.hasOwnProperty.call(variables, token)) {
                    return variables[token];
                }
                unknown.add(token);
                return token;
            }
        );

        outSubject.textContent = render(subject.value);
        outBody.textContent = render(body.value);

        const warnings = [];
        if (!validDays) warnings.push('Nieprawidłowa liczba dni.');
        if (unknown.size) {
            warnings.push('Nieznane zmienne: ' + [...unknown].join(', '));
        }
        error.textContent = warnings.join(' ');
    };

    for (const input of [subject, body, days, debt, balance, monthly]) {
        input.addEventListener('input', update);
        input.addEventListener('change', update);
    }

    update();
})();
</script>
