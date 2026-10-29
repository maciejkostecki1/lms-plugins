/* No network requests; preferences belong to this browser and LMS operator. */
(() => {
    'use strict';
    const script = document.currentScript;
    const root = document.documentElement;
    const key = 'lms-theme-switcher:v1:user:' + (script?.dataset.userId || '0');
    const themes = [
        ['original', 'Oryginalny'],
        ['mono', 'Czarno-biały'],
        ['blue', 'Niebieski'],
        ['green', 'Zielony'],
        ['red', 'Czerwony'],
        ['modern', 'Nowoczesny'],
        ['dark', 'Ciemny'],
        ['graphite', 'Grafitowy'],
        ['pigeon', 'Gołębi'],
        ['grey', 'Szary matowy'],
        ['lightgrey', 'Jasny szary'],
        ['manual', 'Ręczny'],
        ['fire', 'Ognisty'],
        ['ocean', 'Ocean głębinowy']
    ];
    const fonts = [
        ['system', 'Systemowa'],
        ['arial', 'Arial'],
        ['segoe', 'Segoe UI'],
        ['verdana', 'Verdana']
    ];
    const manualDefaults = {menu:'#303030',menuText:'#f2f2f2',main:'#e9e9e9',mainText:'#252525'};
    const manualProperties = ['bg','surface','text','muted','border','header','stripe','hover','accent','menu','menu-text','menu-hover','success','danger','warning','info'];
    const validColor = value => typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value);
    const rgb = hex => [1,3,5].map(offset => parseInt(hex.slice(offset,offset+2),16));
    const mix = (a,b,amount) => '#' + rgb(a).map((v,i) => Math.round(v*(1-amount)+rgb(b)[i]*amount).toString(16).padStart(2,'0')).join('');
    const luminance = hex => rgb(hex).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
    const contrast = (a,b) => (Math.max(luminance(a),luminance(b))+.05)/(Math.min(luminance(a),luminance(b))+.05);
    let state = { theme: 'original', font: 'system', size: '14', compact: false, density: 'standard', menuWidth: 'auto', sticky: false, manual:{...manualDefaults} };
    let saved = true;
    const valid = (items, value) => items.some(item => item[0] === value);
    const normalize = value => ({
        theme: valid(themes, value?.theme) ? value.theme : 'original',
        font: valid(fonts, value?.font) ? value.font : 'system',
        size: ['13', '14', '15', '16'].includes(value?.size) ? value.size : '14',
        density: ['compact','standard','spacious'].includes(value?.density) ? value.density : (value?.compact === true ? 'compact' : 'standard'),
        compact: value?.density ? value.density === 'compact' : value?.compact === true,
        menuWidth: ['auto','200','240','280','320','360'].includes(value?.menuWidth) ? value.menuWidth : 'auto',
        sticky: value?.sticky === true,
        manual: Object.fromEntries(Object.entries(manualDefaults).map(([name,fallback])=>[name,validColor(value?.manual?.[name])?value.manual[name].toLowerCase():fallback]))
    });
    try { state = normalize(JSON.parse(localStorage.getItem(key))); }
    catch (_) { /* Storage may be disabled; selection still works in this tab. */ }
    function apply() {
        const creative = ['fire','ocean'].includes(state.theme);
        root.toggleAttribute('data-lms-creative', creative);
        root.toggleAttribute('data-lms-dim', creative || ['dark', 'graphite', 'grey', 'green', 'red'].includes(state.theme));
        root.toggleAttribute('data-lms-compat', state.theme !== 'original');
        state.compact = state.density === 'compact';
        root.toggleAttribute('data-lms-compact', state.compact);
        root.dataset.lmsDensity = state.density;
        root.toggleAttribute('data-lms-sticky', state.sticky);
        if (state.menuWidth === 'auto') root.style.removeProperty('--lt-menu-width');
        else root.style.setProperty('--lt-menu-width',state.menuWidth+'px');
        root.toggleAttribute('data-lms-menu-width',state.menuWidth !== 'auto');
        refreshLayout();
        if (state.theme === 'manual') {
            const c = state.manual;
            const palette = {bg:c.main,surface:mix(c.main,c.mainText,.025),text:c.mainText,muted:mix(c.main,c.mainText,.80),border:mix(c.main,c.mainText,.30),header:mix(c.main,c.mainText,.10),stripe:mix(c.main,c.mainText,.045),hover:mix(c.main,c.mainText,.14),accent:c.mainText,menu:c.menu,'menu-text':c.menuText,'menu-hover':mix(c.menu,c.menuText,.12)};
            const light = luminance(c.main) > .4;
            const statusColors = light ? {success:'#216431',danger:'#922525',warning:'#725200',info:'#245482'} : {success:'#d9f5e0',danger:'#ffe0e0',warning:'#fff0c2',info:'#ddeaff'};
            const surfaces = ['bg','surface','header','stripe','hover'].map(name=>palette[name]);
            if (!surfaces.every(background=>contrast(palette.muted,background)>=4.5)) palette.muted=c.mainText;
            Object.entries(statusColors).forEach(([name,color])=>{
                let adjusted=color;
                for(let step=0;step<=10;step++) {
                    adjusted=mix(color,light?'#000000':'#ffffff',step/10);
                    if(surfaces.every(background=>contrast(adjusted,background)>=4.5))break;
                }
                palette[name]=adjusted;
            });
            Object.entries(palette).forEach(([name,value])=>root.style.setProperty('--lt-'+name,value));
            root.dataset.lmsManualTone = luminance(c.main) > .4 ? 'light' : 'dark';
        } else {
            manualProperties.forEach(name=>root.style.removeProperty('--lt-'+name));
            root.removeAttribute('data-lms-manual-tone');
        }
        if (state.theme === 'original') {
            root.removeAttribute('data-lms-theme');
            root.removeAttribute('data-lms-font');
            root.removeAttribute('data-lms-text-size');
        } else {
            root.dataset.lmsTheme = state.theme;
            root.dataset.lmsFont = state.font;
            root.dataset.lmsTextSize = state.size;
        }
    }
    function updateStickyOffset() {
        if(!document.body)return;
        const toolbar=document.getElementById('lms-ui-tool-panels');
        const fixed=toolbar && getComputedStyle(toolbar).position==='fixed';
        const bottom=fixed ? toolbar.getBoundingClientRect().bottom : 0;
        root.style.setProperty('--lt-sticky-top',Math.max(0,Math.min(bottom,window.innerHeight/3))+'px');
    }
    function refreshLayout() {
        updateStickyOffset();
        if(!document.body)return;
        document.querySelectorAll('#lms-ui-module-view [data-lms-sticky-row],#lms-ui-module-view [data-lms-table-scroll]').forEach(e=>{e.removeAttribute('data-lms-sticky-row');e.removeAttribute('data-lms-table-scroll');});
        if(!state.sticky)return;
        document.querySelectorAll('#lms-ui-module-view table').forEach(table=>{
            if(table.closest('.tox,[contenteditable="true"],.data-template-message-area') || table.querySelector('table'))return;
            const row=table.tHead?.rows[0] || (table.rows[0]?.querySelector('th') ? table.rows[0] : null);
            if(!row)return;
            row.setAttribute('data-lms-sticky-row','');
            const parent=table.parentElement;
            // Only an existing table-only scroll wrapper may be height-limited.
            if(parent && parent.id!=='lms-ui-module-view' && parent.children.length===1 && parent.firstElementChild===table){
                const style=getComputedStyle(parent);
                if(/auto|scroll/.test(style.overflowX+' '+style.overflowY))parent.setAttribute('data-lms-table-scroll','');
            }
        });
    }
    function startLayout() {
        refreshLayout();
        let pending=false;
        new MutationObserver(()=>{
            if(pending)return;
            pending=true;
            requestAnimationFrame(()=>{pending=false;refreshLayout();});
        }).observe(document.getElementById('lms-ui-module-view')||document.body,{childList:true,subtree:true});
        window.addEventListener('resize',refreshLayout,{passive:true});
        window.addEventListener('scroll',updateStickyOffset,{passive:true});
        const toolbar=document.getElementById('lms-ui-tool-panels');
        if(toolbar && window.ResizeObserver)new ResizeObserver(updateStickyOffset).observe(toolbar);
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',startLayout,{once:true});
    else startLayout();
    window.addEventListener('storage', event => {
        if (document.getElementById('lms-theme-settings') || (event.key !== key && event.key !== null)) return;
        try { state = normalize(event.newValue === null ? null : JSON.parse(event.newValue)); }
        catch (_) { state = normalize(null); }
        apply();
    });
    apply(); // Run in <head> to avoid a flash of the wrong theme.
    function mount() {
        const host = document.getElementById('lms-theme-settings');
        if (!host || document.getElementById('lms-theme-switcher')) return;
        const bar = document.createElement('section');
        bar.id = 'lms-theme-switcher';
        bar.className = 'no-print';
        bar.setAttribute('aria-label', 'Ustawienia motywu');
        const title = document.createElement('strong');
        title.textContent = 'Ustawienia motywu';
        bar.append(title);
        function makeSelect(labelText, name, choices) {
            const label = document.createElement('label');
            label.append(document.createTextNode(labelText + ' '));
            const select = document.createElement('select');
            select.id = 'lms-theme-' + name;
            select.name = 'lms-appearance-' + name;
            choices.forEach(([value, text]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = text;
                select.append(option);
            });
            label.append(select);
            bar.append(label);
            select.addEventListener('change', () => {
                state[name] = select.value;
                update(true);
            });
            return select;
        }
        const theme = makeSelect('Motyw', 'theme', themes);
        const font = makeSelect('Czcionka', 'font', fonts);
        const size = makeSelect('Tekst', 'size', ['13', '14', '15', '16'].map(n => [n, n + ' px']));
        const density = makeSelect('Gęstość widoku', 'density', [['compact','Kompaktowa'],['standard','Standardowa'],['spacious','Przestronna']]);
        const menuWidth = makeSelect('Szerokość menu', 'menuWidth', [['auto','Domyślna LMS'],...['200','240','280','320','360'].map(n=>[n,n+' px'])]);
        menuWidth.title = 'Szerokość menu na komputerze; menu telefonu zachowuje automatyczny układ.';
        const stickyLabel = document.createElement('label');
        const sticky = document.createElement('input');
        sticky.type='checkbox'; sticky.id='lms-theme-sticky';
        stickyLabel.append(sticky,document.createTextNode(' Przyklejone nagłówki tabel'));
        bar.append(stickyLabel);
        sticky.addEventListener('change',()=>{state.sticky=sticky.checked;update(true);});
        const manualPanel = document.createElement('fieldset');
        manualPanel.id = 'lms-theme-manual';
        const manualLegend = document.createElement('legend');
        manualLegend.textContent = 'Własne kolory';
        manualPanel.append(manualLegend);
        const manualGrid = document.createElement('div');
        manualGrid.className = 'lms-theme-manual-grid';
        manualPanel.append(manualGrid);
        const manualControls = {};
        [['menu','Menu — tło'],['menuText','Menu — tekst'],['main','Część główna — tło'],['mainText','Część główna — tekst']].forEach(([name,text])=>{
            const group=document.createElement('div');
            group.className='lms-theme-manual-field';
            const label=document.createElement('label');
            label.htmlFor='lms-color-'+name;
            label.textContent=text;
            const picker=document.createElement('input');
            picker.type='color'; picker.id='lms-color-'+name;
            const hex=document.createElement('input');
            hex.type='text'; hex.id='lms-color-hex-'+name; hex.maxLength=7; hex.spellcheck=false;
            hex.setAttribute('aria-label','Kod koloru: '+text);
            const change = value => { if(!validColor(value))return; state.manual[name]=value.toLowerCase();update(true); };
            picker.addEventListener('input',()=>change(picker.value));
            hex.addEventListener('input',()=>{hex.setAttribute('aria-invalid',String(!validColor(hex.value)));change(hex.value);});
            group.append(label,picker,hex);manualGrid.append(group);
            manualControls[name]={picker,hex};
        });
        const manualContrast=document.createElement('p');
        manualContrast.className='lms-theme-manual-contrast';
        manualContrast.setAttribute('role','status');
        const manualReset=document.createElement('button');
        manualReset.type='button';manualReset.textContent='Przywróć domyślne kolory ręczne';
        manualReset.addEventListener('click',()=>{state.manual={...manualDefaults};update(true);});
        manualPanel.append(manualContrast,manualReset);
        const reset = document.createElement('button');
        reset.type = 'button';
        reset.textContent = 'Przywróć oryginalny';
        bar.append(reset);
        const status = document.createElement('span');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.className = 'lms-theme-status';
        bar.append(status);
        reset.addEventListener('click', () => {
            state = { theme: 'original', font: 'system', size: '14', compact: false, density: 'standard', menuWidth: 'auto', sticky: false, manual:{...manualDefaults} };
            update(true);
        });
        function update(persist) {
            apply();
            if (persist) {
                try { localStorage.setItem(key, JSON.stringify(state)); saved = true; }
                catch (_) { saved = false; }
            }
            theme.value = state.theme;
            font.value = state.font;
            size.value = state.size;
            density.value = state.density;
            menuWidth.value = state.menuWidth;
            sticky.checked = state.sticky;
            manualPanel.hidden=state.theme!=='manual';
            Object.entries(manualControls).forEach(([name,controls])=>{
                controls.picker.value=state.manual[name];
                controls.hex.value=state.manual[name];
                controls.hex.removeAttribute('aria-invalid');
            });
            manualContrast.textContent='Kontrast tekstu: menu '+contrast(state.manual.menu,state.manual.menuText).toFixed(1)+':1 · część główna '+contrast(state.manual.main,state.manual.mainText).toFixed(1)+':1. Dla czytelnego tekstu wybierz co najmniej 4,5:1.';
            font.disabled = size.disabled = state.theme === 'original';
            status.textContent = !saved ? 'Wybór działa tylko w tej karcie — zapis jest niedostępny.'
                : state.theme === 'original' ? (state.compact ? 'Oryginalny motyw · tryb kompaktowy' : 'Oryginalny wygląd LMS')
                    : 'Wybór zapamiętany w tej przeglądarce';
            document.querySelectorAll('[data-lms-theme-choice]').forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.lmsThemeChoice === state.theme));
            });
        }
        host.prepend(bar);
        bar.after(manualPanel);
        document.querySelectorAll('[data-lms-theme-choice]').forEach(button => {
            button.addEventListener('click', () => {
                if (!valid(themes, button.dataset.lmsThemeChoice)) return;
                state.theme = button.dataset.lmsThemeChoice;
                update(true);
            });
        });
        update(false);
        window.addEventListener('storage', event => {
            if (event.storageArea !== localStorage || (event.key !== key && event.key !== null)) return;
            try { state = normalize(event.newValue === null ? null : JSON.parse(event.newValue)); }
            catch (_) { state = normalize(null); }
            update(false);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
    else mount();
})();
