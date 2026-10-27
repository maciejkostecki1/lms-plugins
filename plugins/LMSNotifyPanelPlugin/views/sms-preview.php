<div id="lnp-sms-preview" style="align-self:stretch;min-width:0">
<p id="lnp-sms-template-count" role="status" aria-live="polite"></p>
<label><span>Przykładowa kwota zadłużenia (zł)</span><input id="lnp-sms-example-debt" type="number" min="0.01" step="0.01" value="285.00"></label>
<?php foreach([1=>'Net-24h Maciej Kostecki',2=>'Meganecik'] as $companyId=>$companyName): ?>
<h4><?= $e($companyName) ?> — podgląd SMS</h4>
<pre id="lnp-sms-preview-<?= $companyId ?>" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>
<p id="lnp-sms-count-<?= $companyId ?>" role="status" aria-live="polite"></p>
<?php endforeach ?>
</div>
<script>
(()=>{
    const template=document.getElementById('lnp-sms-template'),amount=document.getElementById('lnp-sms-example-debt'),days=document.querySelector('[name="block_after_days"]');
    const companies={1:'Net-24h Maciej Kostecki',2:'Meganecik'};
    const extension=new Set(Array.from('^{}\\[~]|€\f'));
    const extra=new Set(Array.from('£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¤¡ÄÖÑÜ§¿äöñüà'));
    const measure=text=>{
        let units=0,gsm=true;
        for(const c of text){
            if(extension.has(c)){units+=2;continue;}
            const code=c.codePointAt(0);
            if((code>=32&&code<=126&&c!=='`')||c==='\n'||c==='\r'||extra.has(c)){units++;continue;}
            gsm=false;
        }
        return {chars:Array.from(text).length,units:gsm?units:text.length,limit:gsm?160:70,encoding:gsm?'GSM-7':'Unicode'};
    };
    const update=()=>{
        document.getElementById('lnp-sms-template-count').textContent='Znaki szablonu: '+Array.from(template.value).length+' (liczniki gotowych SMS-ów poniżej).';
        const debt=Number(amount.value),dayCount=Number(days.value);
        const tokens=template.value.match(/%[a-zA-Z_]+/g)||[];
        let error='';
        if(!Number.isFinite(debt)||debt<=0)error='Podaj dodatnią przykładową kwotę.';
        else if(!Number.isInteger(dayCount)||dayCount<0)error='Podaj poprawną liczbę dni.';
        else if(!template.value.includes('%debt')||!template.value.includes('%company'))error='Wymagane symbole: %debt i %company.';
        else if(tokens.some(t=>!['%debt','%company','%payment_request','%days'].includes(t)))error='Nieznany symbol w szablonie.';
        for(const [id,company] of Object.entries(companies)){
            const output=document.getElementById('lnp-sms-preview-'+id),counter=document.getElementById('lnp-sms-count-'+id);
            if(error){output.textContent='';counter.textContent=error;counter.classList.add('red');continue;}
            const replacements={'%debt':debt.toFixed(2).replace('.',','),'%company':company,'%days':String(dayCount),'%payment_request':dayCount>0?'Prosimy o wplate w ciagu '+dayCount+' dni od tej wiadomosci.':'Prosimy o wplate.'};
            const text=template.value.replace(/%[a-zA-Z_]+/g,token=>replacements[token]);
            output.textContent=text;
            const result=measure(text),fits=result.units<=result.limit;
            counter.textContent=result.chars+' znaków · '+result.units+'/'+result.limit+' jednostek · '+result.encoding+' · '+(fits?'Mieści się w 1 SMS-ie':'Przekroczony limit 1 SMS-a');
            counter.classList.toggle('red',!fits);
        }
    };
    for(const input of [template,amount,days])input.addEventListener('input',update);
    update();
})();
</script>
