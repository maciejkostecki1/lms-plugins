<?php
if (!defined('SYS_DIR') || empty($AUTH) || empty($AUTH->islogged)) {
    http_response_code(403);
    exit;
}
$layout['pagetitle'] = 'Motywy LMS';
$SMARTY->display('header.html');
?>
<div id="lms-theme-settings-page">
    <h1>Motywy LMS</h1>
    <p class="lms-theme-intro">Wybierz wygląd, w którym pracuje Ci się najlepiej. Zmiany zobaczysz od razu w całym panelu.</p>
    <div id="lms-theme-settings"></div>
    <section class="lms-theme-group" aria-label="Jasne motywy">
    <h2 class="lms-theme-group-heading">Jasne motywy</h2>
    <div class="lms-theme-cards" role="group" aria-label="Jasne motywy">
        <button type="button" class="lms-theme-card" data-lms-theme-choice="lightgrey" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#d5d5d5;--sample-bg:#e9e9e9;--sample-accent:#606060;--sample-surface:#f2f2f2;--sample-line:#cecece" aria-hidden="true"><span></span></span>
            <strong>Jasny szary</strong><span>Neutralne szarości, jasne menu i ciemny tekst. Bez beżowych teł.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="pigeon" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#86a1ac;--sample-bg:#e9e9e9;--sample-accent:#000000;--sample-surface:#e9e9e9;--sample-line:#a6afb3" aria-hidden="true"><span></span></span>
            <strong>Gołębi</strong><span>Gołębie menu, jasnoszare tło i całkowicie czarny tekst.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="mono" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#181818;--sample-bg:#ededed;--sample-accent:#555" aria-hidden="true"><span></span></span>
            <strong>Czarno-biały</strong><span>Neutralne kolory i wyraźny kontrast.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="blue" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#112e55;--sample-bg:#edf3fb;--sample-accent:#1258a5" aria-hidden="true"><span></span></span>
            <strong>Niebieski</strong><span>Granatowe menu i niebieskie akcenty.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="modern" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#182b32;--sample-bg:#f2f5f7;--sample-accent:#087568" aria-hidden="true"><span></span></span>
            <strong>Nowoczesny</strong><span>Jasne panele, delikatne obramowania i zieleń.</span>
        </button>
    </div>
    </section>
    <section class="lms-theme-group" aria-label="Ciemne i neutralne">
    <h2 class="lms-theme-group-heading">Ciemne i neutralne</h2>
    <div class="lms-theme-cards" role="group" aria-label="Ciemne i neutralne">
        <button type="button" class="lms-theme-card" data-lms-theme-choice="dark" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#111111;--sample-bg:#191919;--sample-accent:#929292;--sample-surface:#272727;--sample-line:#3b3b3b" aria-hidden="true"><span></span></span>
            <strong>Ciemny</strong><span>Głęboka czerń i ciemne panele. Bez kolorowych akcentów.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="graphite" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#232323;--sample-bg:#303030;--sample-accent:#aaaaaa;--sample-surface:#3b3b3b;--sample-line:#535353" aria-hidden="true"><span></span></span>
            <strong>Grafitowy</strong><span>Miękkie odcienie grafitu i łagodniejszy kontrast.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="grey" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#414141;--sample-bg:#555555;--sample-accent:#bdbdbd;--sample-surface:#626262;--sample-line:#808080" aria-hidden="true"><span></span></span>
            <strong>Szary matowy</strong><span>Wyłącznie odcienie szarości, bez białych powierzchni.</span>
        </button>
    </div>
    </section>
    <section class="lms-theme-group" aria-label="Kolorowe motywy">
    <h2 class="lms-theme-group-heading">Kolorowe motywy</h2>
    <div class="lms-theme-cards" role="group" aria-label="Kolorowe motywy">
        <button type="button" class="lms-theme-card" data-lms-theme-choice="green" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#122018;--sample-bg:#18271f;--sample-accent:#8fb99c;--sample-surface:#22362b;--sample-line:#4c6a56" aria-hidden="true"><span></span></span>
            <strong>Zielony</strong><span>Głęboka leśna zieleń i spokojne, ciemne panele.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="red" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#211519;--sample-bg:#2b1d20;--sample-accent:#cb8e9c;--sample-surface:#3b272b;--sample-line:#80535e" aria-hidden="true"><span></span></span>
            <strong>Czerwony</strong><span>Stonowane bordo i ciepłe odcienie ciemnej czerwieni.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="fire" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#190e0b;--sample-bg:#261712;--sample-accent:#ffc078;--sample-surface:#362018;--sample-line:#885638" aria-hidden="true"><span></span></span>
            <strong>Ognisty</strong><span>Rozżarzony bursztyn, miedź i ciemne tło.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="ocean" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#06131f;--sample-bg:#071d2a;--sample-accent:#79e6f3;--sample-surface:#102d3b;--sample-line:#2f6978" aria-hidden="true"><span></span></span>
            <strong>Ocean głębinowy</strong><span>Głęboki granat i turkusowa bioluminescencja.</span>
        </button>
    </div>
    </section>
    <section class="lms-theme-group" aria-label="Własny i oryginalny wygląd">
    <h2 class="lms-theme-group-heading">Własny i oryginalny wygląd</h2>
    <div class="lms-theme-cards" role="group" aria-label="Własny i oryginalny wygląd">
        <button type="button" class="lms-theme-card" data-lms-theme-choice="manual" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#303030;--sample-bg:#e9e9e9;--sample-accent:#606060;--sample-surface:#f2f2f2;--sample-line:#cecece" aria-hidden="true"><span></span></span>
            <strong>Ręczny</strong><span>Wybierz własne kolory menu, części głównej i tekstu.</span>
        </button>
        <button type="button" class="lms-theme-card" data-lms-theme-choice="original" aria-pressed="false">
            <span class="lms-theme-sample" style="--sample-menu:#cebd9b;--sample-bg:#ebe4d6;--sample-accent:#8b7855" aria-hidden="true"><span></span></span>
            <strong>Oryginalny</strong><span>Standardowy wygląd Twojego LMS.</span>
        </button>
    </div>
    </section>
    <p class="lms-theme-note">Gęstość widoku reguluje odstępy w tabelach i formularzach. Przyklejone nagłówki pozostają widoczne podczas przewijania; szersze tabele przewijasz w ich obszarze. Szerokość menu zmienisz na komputerze. Ustawienia dotyczą Twojego konta w tej przeglądarce. Nie zmieniają wyglądu innym operatorom. Zapis jest automatyczny.</p>
    <noscript>Do wyboru i zapamiętywania motywu potrzebna jest włączona obsługa JavaScript.</noscript>
</div>
<?php
$SMARTY->display('footer.html');
