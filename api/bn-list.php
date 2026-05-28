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
                    <img src="https://cdn3.iconfinder.com/data/icons/feather-5/24/x-512.png" alt="Clear" width="20" height="20">
                </div>
                <div class="text-view-header" style="padding: 8px;">Select Bank</div>
            </div>
            <div class="linear-sracg">
                <div class="image-view">
                    <img src="https://cdn3.iconfinder.com/data/icons/feather-5/24/search-512.png" alt="Search" width="20" height="20">
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

    function getPaystackLogoUrl(bank) {
        if (!bank) return '';
        const code = (bank.code || '').toString().trim();
        const paystackCode = /^[0-9]{3,4}$/.test(code) ? code : '';
        if (paystackCode) {
            return `https://paystack.com/banks/${encodeURIComponent(paystackCode)}.png`;
        }
        const slugSource = (bank.slug || bank.name || '').toString().trim().toLowerCase();
        const slug = slugSource.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return slug ? `https://paystack.com/banks/${encodeURIComponent(slug)}.png` : '';
    }

    // ─── Build a single list item ───
    function createBankItem(bank) {
        const li = document.createElement('li');
        li.className = 'linear4';
        li.dataset.name = (bank.name || '').toLowerCase();

        const logoUrl = getPaystackLogoUrl(bank) || bank.logo || '';
        let logoHtml;
        if (logoUrl) {
            logoHtml = `<img
                class="bank-logo-img"
                src="${logoUrl}"
                alt="${bank.name}"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
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
            // Pass selection back to your existing bn-list.js handler or session
            if (typeof window.onBankSelected === 'function') {
                window.onBankSelected(bank);
            } else {
                // Fallback: store in sessionStorage and go back
                sessionStorage.setItem('selected_bank', JSON.stringify(bank));
                history.back();
            }
        });

        return li;
    }

    // ─── Fetch & render bank list ───
    async function loadBanks() {
        const listEl = document.getElementById('bankList');

        try {
            const res = await fetch('paystack-banks.php');
            if (!res.ok) {
                const text = await res.text();
                throw new Error('Network response was not ok: ' + text);
            }
            const payload = await res.json();
            const banks = Array.isArray(payload.data) ? payload.data : [];

            if (!banks.length) {
                throw new Error('No banks returned from Paystack');
            }

            // Sort alphabetically
            banks.sort((a, b) => (a.name || '').localeCompare(b.name || ''));

            listEl.innerHTML = '';

            let currentLetter = '';
            banks.forEach(bank => {
                const firstLetter = bank.name[0].toUpperCase();

                // Insert alphabet divider when the letter changes
                if (firstLetter !== currentLetter) {
                    currentLetter = firstLetter;
                    const divider = document.createElement('li');
                    divider.className = 'linearA';
                    divider.innerHTML = `<div class="text-view-gray">${currentLetter}</div>`;
                    listEl.appendChild(divider);
                }

                listEl.appendChild(createBankItem(bank));
            });

        } catch (err) {
            console.warn('Paystack fetch failed, falling back to backup source:', err);
            try {
                const backupRes = await fetch('https://nigerianbanks.xyz/');
                if (!backupRes.ok) throw new Error('Backup response not ok');
                const backupBanks = await backupRes.json();
                backupBanks.sort((a, b) => a.name.localeCompare(b.name));
                listEl.innerHTML = '';
                let currentLetter = '';
                backupBanks.forEach(bank => {
                    const firstLetter = bank.name[0].toUpperCase();
                    if (firstLetter !== currentLetter) {
                        currentLetter = firstLetter;
                        const divider = document.createElement('li');
                        divider.className = 'linearA';
                        divider.innerHTML = `<div class="text-view-gray">${currentLetter}</div>`;
                        listEl.appendChild(divider);
                    }
                    listEl.appendChild(createBankItem(bank));
                });
            } catch (backupErr) {
                listEl.innerHTML = `<li class="linear4 loading-item">
                    <div class="list-item-text">Failed to load banks. Please try again.</div>
                </li>`;
                console.error('Bank list error:', err, backupErr);
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