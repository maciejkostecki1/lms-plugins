/* Normalize only explicitly painted legacy UI colors. Values and handlers are
   untouched; diagrams, artwork and user-authored rich text are excluded. */
(() => {
    'use strict';
    const excluded = '.data-template-message-area,.hg-ui,svg,canvas,.lms-theme-sample,.tox,.mce-content-body,[contenteditable="true"],[data-lms-theme-preserve]';
    const scope = '#lms-ui-module-view,.ui-dialog,.ui-datepicker,.ui-menu,.select2-dropdown,.chosen-drop,.lms-ui-popup';
    const named = {red:[255,0,0],green:[0,128,0],blue:[0,0,255],orange:[255,165,0],maroon:[128,0,0],black:[0,0,0],white:[255,255,255],gray:[128,128,128],grey:[128,128,128],sienna:[160,82,45]};
    function rgb(value) {
        if (named[value.toLowerCase()]) return [...named[value.toLowerCase()],1];
        const match = value.match(/^rgba?\(([^)]+)\)/);
        if (!match) return null;
        const values = match[1].split(/[\s,\/]+/).map(Number);
        return values.length >= 3 ? [values[0],values[1],values[2],values[3] ?? 1] : null;
    }
    function adapt(element) {
        if (!(element instanceof HTMLElement) || !element.closest(scope) || element.closest(excluded)) return;
        const color = rgb(element.style.color || '');
        if (color && color[3] > .5) {
            const [r,g,b] = color;
            let ink = 'text';
            if (r > g * 1.35 && r > b * 1.35) ink = g > b * 1.5 ? 'warning' : 'danger';
            else if (g > r * 1.25 && g > b * 1.1) ink = 'success';
            else if (b > r * 1.25 && b > g * 1.05) ink = 'info';
            else if (Math.max(r,g,b) >= 80 && Math.max(r,g,b) < 180) ink = 'muted';
            if (element.dataset.lmsInk !== ink) element.dataset.lmsInk = ink;
        } else element.removeAttribute('data-lms-ink');
        const background = rgb(element.style.backgroundColor || '');
        if (background && background[3] > .6 && Math.min(...background.slice(0,3)) >= 155) {
            if (element.dataset.lmsSurface !== 'panel') element.dataset.lmsSurface = 'panel';
        } else element.removeAttribute('data-lms-surface');
    }
    function scan(node) {
        if (!(node instanceof Element)) return;
        adapt(node);
        node.querySelectorAll('[style]').forEach(adapt);
    }
    function start() {
        scan(document.body);
        const pending = new Set();
        let timer;
        const flush = () => { pending.forEach(scan); pending.clear(); timer = undefined; };
        new MutationObserver(records => {
            for (const record of records) {
                if (record.type === 'attributes') pending.add(record.target);
                else record.addedNodes.forEach(node => { if (node.nodeType === 1) pending.add(node); });
            }
            if (pending.size && timer === undefined) timer = window.setTimeout(flush, 30);
        }).observe(document.body, {subtree:true,childList:true,attributes:true,attributeFilter:['style']});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',start,{once:true});
    else start();
})();
