const input = document.getElementById('accountInput');
const searching = document.getElementById('searching');
const recipientDetails = document.getElementById('recipientDetails');
const recipientName = document.getElementById('recipientName');
const recipientAccount = document.getElementById('recipientAccount');
let verified = null;

function cleanAccountName(raw) {
    if (!raw) return '';
    let text = String(raw).trim().split(/\r?\n/)[0].trim();
    text = text.replace(/^(account\s*(name|holder)|acct\s*name|name)\s*[:\-\s]+/i, '').trim();
    text = text.replace(/\s*[-–—].*$/,'').replace(/\s*\([^)]*\d[^)]*\).*$/,'').trim();
    const words = text.match(/[A-Za-z]{2,}/g) || [];
    if (words.length === 0) return text;
    return words.slice(0, 3).join(' ');
}

// Input listener
input.addEventListener('input', function() {
    const value = this.value.trim();
    if (/^\d{10}$/.test(value)) {
        this.disabled = true;
        searching.style.display = 'flex';
        
        // FIXED: send as form-urlencoded so PHP's $_POST can read values and receive plain text response
        fetch('verify_account.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'account_number=' + encodeURIComponent(value) + '&bank_code=999992'
        })
        .then(res => res.text())
        .then(text => {
            text = text.trim();
            const lower = text.toLowerCase();
            const hasHtml = /<[^>]+>/.test(text);
            const cleanedName = cleanAccountName(text);
            const wordCount = (cleanedName.match(/\b[A-Za-z]{2,}\b/g) || []).length;
            const isError = !text || lower.startsWith('error:') || lower.includes('warning') || lower.includes('cannot modify') || lower.includes('headers already sent') || hasHtml || wordCount < 2;
            const isValidName = !isError && cleanedName.length >= 3 && /^[A-Za-z0-9 '\-\.]+$/.test(cleanedName) && wordCount >= 2;
            if (!isValidName) {
                alert(text || "Unable to verify account");
                input.disabled = false;
                searching.style.display = 'none';
            } else {
                verified = {
                    accountNumber: value,
                    accountName: cleanedName
                };
                recipientName.textContent = cleanedName;
                recipientAccount.textContent = value;
                searching.style.display = 'none';
                recipientDetails.style.display = 'flex';
                this.disabled = false;
            }
        })
        .catch((err) => {
            console.error(err);
            alert("Network error");
            input.disabled = false;
            searching.style.display = 'none';
            this.disabled = false;
        });
    }
});

// Save & redirect
recipientDetails.addEventListener('click', function() {
    if (!verified) return;
    const payload = {
        accountnumber: verified.accountNumber,
        bankname: "OPay",
        accountname: verified.accountName,
        url: "https://webtech.net.ng/up/uploads/Screenshot_20250321_000721.png"
    };
    localStorage.setItem('transfer.selected', JSON.stringify(payload));
    window.location.href = 'next.php';
});

// Tab switch
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(t => t.classList.add('inactive'));
        this.classList.remove('inactive');
        this.classList.add('active');
        
        document.getElementById('recentsList').style.display = (this.dataset.tab === 'recents') ? 'block':'none';
        document.getElementById('favouritesList').style.display = (this.dataset.tab === 'favourites') ? 'block':'none';
    });
});

// clickable recents/favourites items
document.querySelectorAll('#recentsList .recipient-details, #favouritesList .recipient-details').forEach(item => {
    item.addEventListener('click', function() {
        const accName = this.querySelector('.recipient-name')?.textContent?.trim();
        const accNumber = this.querySelector('.recipient-account')?.textContent?.trim();
        if (!accName || !accNumber) return;
        const payload = {
            accountnumber: accNumber,
            bankname: "OPay",
            accountname: accName,
            url: "https://webtech.net.ng/up/uploads/Screenshot_20250321_000721.png"
        };
        localStorage.setItem('transfer.selected', JSON.stringify(payload));
        window.location.href = 'next.php';
    });
});