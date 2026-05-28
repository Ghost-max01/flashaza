
/* ===== Helpers ===== */  
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),3000);}  
function formatAmount(v){  
    v = v.replace(/[^0-9.]/g,'');  
    const parts = v.split('.');  
    if (parts.length>2){ v = parts[0]+'.'+parts[1]; }  
    if (parts[1]){ parts[1]=parts[1].substring(0,2); v = parts[0]+'.'+parts[1]; }  
    let [i,d]=v.split('.');  
    i = (i||'').replace(/\B(?=(\d{3})+(?!\d))/g,',');  
    return d!==undefined ? i+'.'+d : i;  
}  
function parseAmount(v){return parseFloat((v||'').replace(/,/g,''));}  
function validateAmount(v){  
    const a=parseAmount(v);  
    if (isNaN(a) || a<100 || a>5000000){  
        document.getElementById("textview15").style.display="block";  
        confirmBtn.style.opacity="0.5"; confirmBtn.style.cursor="not-allowed";  
        return false;  
    }else{  
        document.getElementById("textview15").style.display="none";  
        confirmBtn.style.opacity="1"; confirmBtn.style.cursor="pointer";  
        return true;  
    }  
}  
function showLoader(show){  
    const loader = document.getElementById('loadingPopup');  
    const gif    = loader.querySelector("img");  
    if (show) {  
        _setLoading(gif);  
        loader.style.display = 'flex';  
    } else {  
        loader.style.display = 'none';  
    }  
}  
/* ===== UI Init ===== */  
const amountInput = document.getElementById('edittext2');  
const confirmBtn  = document.getElementById('linear_confirm');  

function _UI(){  
    const selected = localStorage.getItem("transfer.selected");  
    let payload=null; if(selected){ try{payload=JSON.parse(selected);}catch(e){} }  
    const accountName   = payload?.accountname   || 'Web Tech';  
    const accountNumber = payload?.accountnumber || '9123458653';  
    const bankName      = payload?.bankname      || 'OPay';  
    const bankLogoUrl   = payload?.url           || 'https://logo.clearbit.com/ubagroup.com';  

    const bankLogo = document.getElementById('banklogo');  
    const bankLogoLoader = document.getElementById('bankLogoLoader');  
    bankLogoLoader.style.display='block';  
    bankLogo.style.opacity='0';  
    bankLogo.onload = ()=>{ bankLogoLoader.style.display='none'; bankLogo.style.opacity='1'; };  
    bankLogo.onerror= ()=>{ bankLogoLoader.style.display='none'; bankLogo.style.opacity='1'; };  
    bankLogo.src = bankLogoUrl;  

    document.getElementById('accountname').textContent = accountName;  
    document.getElementById('an_bn').textContent = `${accountNumber} ${bankName}`;  

    // Set the default PIN sheet title according to pin_set from DB  
    document.getElementById('pinSheetTitle').textContent = pinSet ? "Enter Payment PIN" : "Set a new Payment PIN";  

    confirmBtn.style.opacity='0.5'; confirmBtn.style.cursor='not-allowed';  
    document.getElementById('clear').style.display='none';  
    document.getElementById('textview15').style.display='none';  
    document.getElementById('edittext3').style.display='none';  
    document.getElementById('linear112').style.display='none';  
}  
document.addEventListener("DOMContentLoaded", _UI);  

/* ===== Amount input & quick boxes ===== */  
amountInput.addEventListener('input', function(e){  
    const pos = amountInput.selectionStart;  
    const raw = e.target.value;  
    const fm  = formatAmount(raw);  
    e.target.value = fm;  
    document.getElementById("clear").style.display = fm.length>0 ? "block":"none";  
    validateAmount(fm);  
    // Try keep cursor near end  
    const newPos = Math.min(fm.length, pos + (fm.length - raw.length));  
    amountInput.setSelectionRange(newPos, newPos);  
});  
document.getElementById('clear').addEventListener('click', function(){  
    amountInput.value=''; this.style.display='none'; validateAmount('');  
    document.querySelectorAll('.amount-box').forEach(b=>{  
        b.classList.remove('active-box'); b.querySelector('.amount-option').classList.remove('active-option');  
    });  
});  
document.querySelectorAll('.amount-box').forEach(box=>{  
    box.addEventListener('click', function(){  
        document.querySelectorAll('.amount-box').forEach(b=>{  
            b.classList.remove('active-box'); b.querySelector('.amount-option').classList.remove('active-option');  
        });  
        this.classList.add('active-box'); this.querySelector('.amount-option').classList.add('active-option');  
        const amt = this.querySelector('.amount-option').textContent.replace(/[₦,]/g,'');  
        const fm  = formatAmount(amt);  
        amountInput.value = fm; document.getElementById('clear').style.display='block';  
        validateAmount(fm);  
    });  
});  

/* ===== Confirm button → bottom sheet review ===== */  
confirmBtn.addEventListener('click', function(){  
    if (!validateAmount(amountInput.value)) return;  
    showLoader(true);  
    setTimeout(()=>{ showLoader(false); showBottomSheet(); }, 2000);  
});  
function showBottomSheet(){  
    const selected = localStorage.getItem("transfer.selected");  
    let payload=null; if(selected){ try{payload=JSON.parse(selected);}catch(e){} }  
    const accountName   = payload?.accountname   || 'Web Tech';  
    const accountNumber = payload?.accountnumber || '9123458653';  
    const bankName      = payload?.bankname      || 'OPay';  
    const bankLogoUrl   = payload?.url           || 'https://logo.clearbit.com/ubagroup.com';  
    const amount = amountInput.value;  

    document.getElementById('bs-amount').textContent = amount;  
    document.getElementById('bs-bank-logo').src = bankLogoUrl;  
    document.getElementById('bs-bank-name').textContent = bankName;  
    document.getElementById('bs-account-number').textContent = accountNumber;  
    document.getElementById('bs-account-name').textContent = accountName;  
    document.getElementById('bs-amount-detail').textContent = `₦${amount}`;  
    document.getElementById('bs-deduction').textContent = `-₦${amount}`;  
    document.getElementById('bs-available-balance').textContent = formattedBalance;  

    document.getElementById('bottomSheet').style.display='flex';  
    document.getElementById('mainContainer').style.display='block';  
}  
document.getElementById('bottomSheet').addEventListener('click', function(e){  
    if (e.target === this){ this.style.display='none'; }  
});  
document.getElementById('closeBottomSheet').addEventListener('click', function(){  
    document.getElementById('bottomSheet').style.display='none';  
});  

/* ===== Pay click → balance check → PIN sheet (set or enter) ===== */  
document.getElementById('payButton').addEventListener('click', function(){  
    const amount = parseAmount(amountInput.value);  
    if (isNaN(amount)){ showToast('Enter a valid amount'); return; }  
    if (userBalance < amount){ showToast('Insufficient funds'); return; }  

    // Decide which flow based on DB flag  
    if (!pinSet){  
        pinStage = "set";  
        document.getElementById('pinSheetTitle').textContent = "Set a new Payment PIN";  
    }else{  
        pinStage = "enter";  
        document.getElementById('pinSheetTitle').textContent = "Enter Payment PIN";  
    }  
    clearPinInputs();  
    document.getElementById('bottomSheet').style.display='none';  
    document.getElementById('pinBottomSheet').style.display='flex';  
});  

/* ===== Back button ===== */  
document.getElementById('backButton').addEventListener('click', ()=>window.history.back());  

/* ===== PIN keypad logic ===== */  
let tempPin = "";  
const pinInputs = Array.from(document.querySelectorAll('.pin-input'));  
const pinBoxes  = Array.from(document.querySelectorAll('.pin-box'));  
const keys      = document.querySelectorAll('.key');  
const clearKey  = document.getElementById('clearPinKey');  

function clearPinInputs(){ pinInputs.forEach(i=>i.value=''); setActiveBox(0); }  
function setActiveBox(idx){ pinBoxes.forEach((b,i)=>b.classList.toggle('active', i===idx)); }  
function currentIndex(){ return pinInputs.findIndex(i=>i.value===''); }  

keys.forEach(key=>{  
    if (key.id === 'clearPinKey') return;  
    key.addEventListener('click', ()=>{  
        const v = key.getAttribute('data-value');  
        const idx = currentIndex();  
        if (idx === -1) return; // all filled  
        pinInputs[idx].value = v;  
        setActiveBox(Math.min(idx+1, 3));  
        checkPinCompletion();  
    });  
});  
clearKey.addEventListener('click', ()=>{  
    // remove last filled  
    let last = -1;  
    for (let i=pinInputs.length-1;i>=0;i--){ if (pinInputs[i].value!==''){ last=i; break; } }  
    if (last>=0){ pinInputs[last].value=''; setActiveBox(last); }  
});  

function checkPinCompletion(){  
    const allFilled = pinInputs.every(i=>i.value!=='');  
    if (!allFilled) return;  
    const pin = pinInputs.map(i=>i.value).join('');  
    if (pinStage === 'set'){  
        tempPin = pin;  
        pinStage = 'confirm';  
        document.getElementById('pinSheetTitle').textContent = "Confirm Payment PIN";  
        clearPinInputs();  
    }else if (pinStage === 'confirm'){  
        if (pin !== tempPin){ showToast("PINs do not match"); clearPinInputs(); return; }  
        savePinToServer(pin);  
    }else if (pinStage === 'enter'){  
        verifyPin(pin);  
    }  
}  

document.getElementById('closePinBottomSheet').addEventListener('click', function(){  
    document.getElementById('pinBottomSheet').style.display='none';  
    document.getElementById('bottomSheet').style.display='flex';  
    pinStage = pinSet ? "enter" : "set";  
});  

/* ===== Server calls (same file) ===== */  
async function savePinToServer(pin){  
    showLoader(true);  
    try{  
        const form = new FormData();  
        form.append('action','save_pin');  
        form.append('pin', pin);  
        const res = await fetch(window.location.href, { method:'POST', body: form, credentials:'same-origin' });  
        const data = await res.json();  
        showLoader(false);  
        if (data.ok){  
            pinSet = 1; // update local flag  
            showToast('PIN set successfully');  
            // After set, directly proceed as verified  
            processTransactionAfterVerified();  
        }else{  
            showToast(data.msg || 'Failed to set PIN');  
            clearPinInputs();  
        }  
    }catch(err){  
        showLoader(false);  
        showToast('Network error while setting PIN');  
        clearPinInputs();  
    }  
}  

async function verifyPin(pin){  
    showLoader(true);  
    try{  
        const form = new FormData();  
        form.append('action','verify_pin');  
        form.append('pin', pin);  
        const res = await fetch(window.location.href, { method:'POST', body: form, credentials:'same-origin' });  
        const data = await res.json();  
        showLoader(false);  
    if (data.ok){  
            processTransactionAfterVerified();  
        }else{  
            showToast('Incorrect PIN');  
            clearPinInputs();  
        }  
    }catch(err){  
        showLoader(false);  
        showToast('Network error while verifying PIN');  
        clearPinInputs();  
    }  
}  

/* ===== After correct PIN → save to localStorage (no URL) → go loader.php ===== */  
function processTransactionAfterVerified(){  
    const receiver = localStorage.getItem("transfer.selected");  
    let r=null; if(receiver){ try{ r=JSON.parse(receiver);}catch(e){} }  

    const amount = parseAmount(amountInput.value);  
    const remark = document.getElementById('edittext1').value || '';  

    const tx = {  
        accountname:  r?.accountname  || '',  
        bankname:     r?.bankname     || '',  
        accountnumber:r?.accountnumber|| '',  
        url:          r?.url          || '',  
        amount:       amount,  
        remark:       remark,  
        created_at:   Date.now()  
    };  

    localStorage.setItem('transfer.tx.pending', JSON.stringify(tx));  

    // Close PIN bottom sheet and go  
    document.getElementById('pinBottomSheet').style.display='none';  
    showLoader(true);  
    setTimeout(()=>{  
        showLoader(false);  
        // No query params — loader.php should read from localStorage  
        window.location.href = 'loader.php';  
    }, 400);  
}  
  function _setLoading(imageElement, type, text) {  
    const gifUrl = "https://webtech.net.ng/gif/loading.gif";  
    // Add timestamp query so browser sees it as new  
    imageElement.src = gifUrl + "?t=" + new Date().getTime();  
}  