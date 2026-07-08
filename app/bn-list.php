<?php
if (session_status()===PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Selection</title>
    <link rel="stylesheet" href="../css/bn-list.css">
    <style>
        /* Logo fix styles — only for list items, no UI/UX changes */
        .bank-logo-img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: contain;
            background: #f4f4f4;
            border: 1px solid #eee;
            flex-shrink: 0;
        }
        .bank-logo-fallback {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            color: #fff;
            flex-shrink: 0;
        }
        .linear4 {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .list-item-text {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="linear-layout">
        <div class="linear1">
            <div class="linear2">
                <div class="image-view">
                    <img src="../images/toban/clear.png" alt="Clear" width="20" height="20">
                </div>
                <div class="text-view-header" style="padding: 8px;">Select Bank</div>
            </div>
            <div class="linear-sracg">
                <div class="image-view">
                    <img src="../images/toban/search.png" alt="Search" width="20" height="20">
                </div>
                <input type="text" id="bankSearchInput" class="edit-text" placeholder="Search Bank Name">
            </div>
        </div>
        
        <div class="linear18">
            <div class="linear8">
                <div class="linear-frequent">
                    <div class="text-view">Frequently Used Bank</div>
                </div>
                
                <div class="linear5">
                    <div class="linear10">
                        <div class="linear12">
                            <div class="image-view">
                                <img src="../images/toban/opay.png" alt="OPay" width="50" height="50">
                            </div>
                            <div class="text-view-small">OPay</div>
                            <div class="image-view"></div>
                        </div>
                        <div class="linear14">
                            <div class="image-view">
                                <img src="../images/toban/access.png" alt="Access Bank" width="50" height="50">
                            </div>
                            <div class="text-view-small">Access Bank</div>
                        </div>
                        <div class="linear13">
                            <div class="image-view">
                                <img src="../images/toban/uba.png" alt="UBA" width="45" height="45">
                            </div>
                            <div class="text-view-small">United Bank For<br>Africa</div>
                        </div>
                    </div>
                    
                    <div class="linear11">
                        <div class="linear15">
                            <div class="image-view">
                                <img src="../images/toban/first.png" alt="First Bank" width="45" height="45" style="margin-top: 15px;">
                            </div>
                            <div class="text-view-small">First Bank Of<br>Nigeria</div>
                        </div>
                        <div class="linear16">
                            <div class="image-view">
                                <img src="../images/toban/gt.png" alt="GTBank" width="45" height="45">
                            </div>
                            <div class="text-view-small">Guaranty Trust Bank</div>
                        </div>
                        <div class="linear17">
                            <div class="image-view">
                                <img src="../images/toban/zenith.png" alt="Zenith Bank" width="45" height="45">
                            </div>
                            <div class="text-view-small">Zenith Bank</div>
                        </div>
                    </div>
                </div>
                
                <div class="linearA">
                    <div class="text-view-gray">A</div>
                </div>
                
                <ul class="list-view" id="bankList">
                    <li class="linear4 loading-item">
                        <div class="list-item-text">Loading banks...</div>
                    </li>
                </ul>
            </div>
            
            <div class="alphabet-sidebar" id="alphabetSidebar">
                A<br><br>B<br><br>C<br><br>D<br><br>E<br><br>F<br><br>G<br><br>H<br><br>I<br><br>J<br><br>K<br><br>L<br><br>M<br><br>N<br><br>O<br><br>P<br><br>Q<br><br>R<br><br>S<br><br>T<br><br>U<br><br>V<br><br>W<br><br>X<br><br>Y<br><br>Z
            </div>
        </div>
    </div>
</body>

<script>
    // ─── Palette for fallback avatars (when a bank has no logo URL) ───
    const FALLBACK_COLORS = [
        '#1a73e8','#e53935','#43a047','#fb8c00',
        '#8e24aa','#00897b','#f4511e','#039be5'
    ];

    function getFallbackColor(name) {
        let hash = 0;
        for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
        return FALLBACK_COLORS[Math.abs(hash) % FALLBACK_COLORS.length];
    }

    function getInitials(name) {
        return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
    }

    window.addEventListener('error', function(event) {
        console.error('bn-list.js global error:', event.message, event.filename, event.lineno, event.colno, event.error);
    });
    window.addEventListener('unhandledrejection', function(event) {
        console.error('bn-list.js unhandled promise rejection:', event.reason);
    });

    function getPaystackLogoUrl(bank) {
        if (!bank) return '';
        // Only use actual image URLs from NigerianBanks or local; no external fallback
        if (bank.logo && String(bank.logo).startsWith('http')) {
            return bank.logo;
        }
        return '';
    }

    function submitBankSelection(bank) {
        console.log('bn-list: submitting bank selection', bank);
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'to-bn.php';
        ['name', 'url', 'code'].forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = field;
            input.value = bank[field] || '';
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    // ─── Build a single list item ───
    function createBankItem(bank) {
        const li = document.createElement('li');
        li.className = 'linear4';
        li.dataset.name = (bank.name || '').toLowerCase();

        const logoUrl = getPaystackLogoUrl(bank) || bank.logo || '';
        console.debug('bn-list: createBankItem', { name: bank.name, code: bank.code, logoUrl });
        let logoHtml;
        if (logoUrl) {
            logoHtml = `<img
                class="bank-logo-img"
                src="${logoUrl}"
                alt="${bank.name}"
                onerror="console.warn('Bank logo failed to load', this.src, this.alt); this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="bank-logo-fallback" style="background:${getFallbackColor(bank.name)};display:none;">
                ${getInitials(bank.name)}
            </div>`;
        } else {
            logoHtml = `<div class="bank-logo-fallback" style="background:${getFallbackColor(bank.name)};">
                ${getInitials(bank.name)}
            </div>`;
        }

        li.innerHTML = `
            ${logoHtml}
            <div class="list-item-text">${bank.name}</div>
        `;

        li.addEventListener('click', () => {
            submitBankSelection({
                name: bank.name || bank.bank_name || '',
                url: bank.logo || bank.url || '',
                code: bank.code || bank.bank_code || bank.id || ''
            });
        });

        return li;
    }

    // ─── Fetch & render bank list (logos now come from paystack-banks.php server-side) ───
    async function loadBanks() {
        const listEl = document.getElementById('bankList');

        try {
            // Primary: Paystack bank list (may already have some logos from server-side enrichment)
            const payRes = await fetch('paystack-banks.php');
            if (!payRes.ok) {
                const text = await payRes.text();
                console.error('bn-list: Paystack error:', text);
                throw new Error('Paystack error: ' + text);
            }
            const payPayload = await payRes.json();
            const payBanks = Array.isArray(payPayload.data) ? payPayload.data : [];
            console.log('bn-list: Paystack banks loaded', payBanks.length);

            // ── Client-side logo enrichment (multi-strategy matching) ──
            let logoByCode = {};   // bank code → logo URL
            let logoBySlug = {};   // slug → logo URL
            let logoByName = {};   // normalized name → logo URL
            let logoByShort = {};  // short name (first 2 words) → logo URL

            // Aggressive normalization: strip common suffixes for better matching
            function normalizeName(name) {
                return String(name || '').toLowerCase()
                    .replace(/[^a-z0-9 ]/g, ' ')
                    .replace(/\b(plc|limited|ltd|nigeria|ng|lc)\b/g, '')
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            // Even more aggressive: strip "microfinance bank", "mfb", "bank" for short matching
            function shortName(name) {
                return normalizeName(name)
                    .replace(/\b(microfinance bank|microfinance|mfb|bank|digital|financial|services|finance|money|mobile)\b/g, '')
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            // Local overrides mapping for major banks
            function getLocalLogo(code, slug, name) {
                code = String(code || '').trim();
                slug = String(slug || '').toLowerCase().trim();
                name = String(name || '').toLowerCase().trim();

                if (code === '999992' || code === '100004' || slug.includes('opay') || slug.includes('paycom') || name.includes('opay')) {
                    return '../images/toban/opay.png';
                }
                if (code === '044' || slug.includes('access') || name.includes('access bank')) {
                    return '../images/toban/access.png';
                }
                if (code === '011' || slug.includes('first-bank') || name.includes('first bank')) {
                    return '../images/toban/first.png';
                }
                if (code === '058' || slug.includes('gtb') || slug.includes('guaranty-trust') || name.includes('guaranty trust')) {
                    return '../images/toban/gt.png';
                }
                if (code === '033' || slug === 'uba' || slug.includes('united-bank-for-africa') || name.includes('united bank for africa') || name === 'uba') {
                    return '../images/toban/uba.png';
                }
                if (code === '057' || slug.includes('zenith') || name.includes('zenith bank')) {
                    return '../images/toban/zenith.png';
                }
                return '';
            }

            // Store a logo into all relevant maps
            function indexLogo(entry, logoUrl) {
                const code = String(entry.code || '').trim();
                const slug = String(entry.slug || '').trim();
                const name = entry.name || entry.bank_name || '';
                const nName = normalizeName(name);
                const sName = shortName(name);

                if (code && logoUrl) logoByCode[code] = logoUrl;
                if (slug && logoUrl) logoBySlug[slug] = logoUrl;
                if (nName && logoUrl && !logoByName[nName]) logoByName[nName] = logoUrl;
                if (sName && sName.length > 2 && logoUrl && !logoByShort[sName]) logoByShort[sName] = logoUrl;
            }

            const SM_BASE = 'https://supermx1.github.io/nigerian-banks-api/';

            // Fetch both logo sources in parallel (best-effort, never blocks)
            await Promise.allSettled([
                // Source 1: supermx1 GitHub dataset — indexed by code, slug, AND name
                fetch(SM_BASE + 'data.json')
                    .then(r => r.ok ? r.json() : Promise.reject('not ok'))
                    .then(banks => {
                        banks.forEach(b => {
                            let logo = String(b.logo || '').trim();
                            if (!logo) return;
                            if (!logo.startsWith('http')) logo = SM_BASE + logo;
                            indexLogo(b, logo);
                        });
                        console.log('bn-list: supermx1 indexed — codes:', Object.keys(logoByCode).length,
                            'slugs:', Object.keys(logoBySlug).length,
                            'names:', Object.keys(logoByName).length);
                    }),
                // Source 2: NigerianBanks.xyz — indexed by name
                fetch('https://nigerianbanks.xyz/')
                    .then(r => r.ok ? r.json() : Promise.reject('not ok'))
                    .then(banks => {
                        banks.forEach(nb => {
                            const logo = nb.logo || nb.url || nb.image || nb.logo_url || nb.icon || '';
                            if (logo && String(logo).startsWith('http')) {
                                indexLogo(nb, logo);
                            }
                        });
                        console.log('bn-list: NigerianBanks indexed — names:', Object.keys(logoByName).length);
                    })
            ]);

            // Build the list with multi-strategy logo resolution
            const merged = payBanks.map(pb => {
                const name = pb.name || pb.bank_name || '';
                const code = (pb.code || pb.bank_code || pb.id || '') + '';
                const slug = pb.slug || '';
                const nName = normalizeName(name);
                const sName = shortName(name);
                const serverLogo = pb.logo || '';

                // Priority chain: local override → server → code → slug → full name → short name → speculative URL
                const local = getLocalLogo(code, slug, name);
                const logo = local
                    || serverLogo
                    || logoByCode[code]
                    || logoBySlug[slug]
                    || logoByName[nName]
                    || logoByShort[sName]
                    || (slug ? SM_BASE + 'logos/' + slug + '.png' : '');

                return { name, code, logo };
            });

            const withLogos = merged.filter(b => b.logo).length;
            console.log('bn-list: Banks with logos', withLogos, '/', merged.length);

            merged.sort((a, b) => (a.name || '').localeCompare(b.name || ''));

            listEl.innerHTML = '';
            let currentLetter = '';
            merged.forEach(bank => {
                const firstLetter = (bank.name || '').charAt(0).toUpperCase();
                if (firstLetter !== currentLetter) {
                    currentLetter = firstLetter;
                    const divider = document.createElement('li');
                    divider.className = 'linearA';
                    divider.innerHTML = `<div class="text-view-gray">${currentLetter}</div>`;
                    listEl.appendChild(divider);
                }
                listEl.appendChild(createBankItem({ name: bank.name, code: bank.code, logo: bank.logo }));
            });

        } catch (err) {
            console.error('bn-list: primary load failed, fallback to NigerianBanks only:', err.message);
            try {
                const backupRes = await fetch('https://nigerianbanks.xyz/');
                if (!backupRes.ok) throw new Error('Backup response not ok');
                const backupBanks = await backupRes.json();
                backupBanks.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                listEl.innerHTML = '';
                let currentLetter = '';
                backupBanks.forEach(bank => {
                    const firstLetter = (bank.name || bank.bank_name || '').charAt(0).toUpperCase();
                    if (firstLetter !== currentLetter) {
                        currentLetter = firstLetter;
                        const divider = document.createElement('li');
                        divider.className = 'linearA';
                        divider.innerHTML = `<div class="text-view-gray">${currentLetter}</div>`;
                        listEl.appendChild(divider);
                    }
                    const logo = bank.logo || bank.url || bank.image || '';
                    listEl.appendChild(createBankItem({ name: bank.name || bank.bank_name, code: bank.code || bank.bank_code, logo: logo }));
                });
            } catch (backupErr) {
                listEl.innerHTML = `<li class="linear4 loading-item">
                    <div class="list-item-text">Failed to load banks. Please try again.</div>
                </li>`;
                console.error('bn-list: both primary and backup failed:', err.message, backupErr.message);
            }
        }
    }

    // ─── Live search ───
    document.getElementById('bankSearchInput').addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const items = document.querySelectorAll('#bankList .linear4');
        const dividers = document.querySelectorAll('#bankList .linearA');

        // Hide all dividers first
        dividers.forEach(d => d.style.display = 'none');

        items.forEach(li => {
            const match = li.dataset.name && li.dataset.name.includes(query);
            li.style.display = match ? '' : 'none';

            // Show the divider for this letter if at least one item is visible
            if (match) {
                let prev = li.previousElementSibling;
                while (prev) {
                    if (prev.classList.contains('linearA')) {
                        prev.style.display = '';
                        break;
                    }
                    prev = prev.previousElementSibling;
                }
            }
        });
    });

    // ─── Alphabet sidebar tap-to-scroll ───
    document.getElementById('alphabetSidebar').addEventListener('click', function (e) {
        const letter = e.target.textContent.trim();
        if (!letter || letter.length !== 1) return;

        const dividers = document.querySelectorAll('#bankList .linearA');
        for (const d of dividers) {
            if (d.querySelector('.text-view-gray')?.textContent.trim() === letter) {
                d.scrollIntoView({ behavior: 'smooth', block: 'start' });
                break;
            }
        }
    });

    // Load on page ready
    loadBanks();
</script>

<script src="../js/bn-list.js?ver=2" defer></script>

<script>
  document.addEventListener("contextmenu", function(e){ e.preventDefault(); });
  document.onkeydown = function(e) {
    if (e.keyCode == 123) return false;
    if (e.ctrlKey && e.shiftKey && (e.keyCode == 'I'.charCodeAt(0) || e.keyCode == 'J'.charCodeAt(0))) return false;
    if (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) return false;
  }
</script>
</html>