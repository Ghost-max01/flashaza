async function fetchBanks() {
    const listView = document.querySelector(".list-view");
    if (!listView) return;

    console.log('bn-list.js: fetching bank list from ./bn.php');
    try {
        let res = await fetch("./bn.php", { cache: 'no-cache' });
        console.log('bn-list.js: fetch response', res.status, res.statusText);
        let responseText = await res.text();
        if (!res.ok) {
            throw new Error('HTTP ' + res.status + ': ' + responseText);
        }

        let banks;
        try {
            let text = responseText;
            if (text.trim().startsWith('<') || text.indexOf('<br') !== -1) {
                const idx = text.indexOf('[');
                if (idx !== -1) text = text.slice(idx);
            }
            banks = JSON.parse(text);
        } catch (parseErr) {
            throw new Error('Invalid JSON from bn.php: ' + responseText.slice(0, 200));
        }

        const listViewEl = document.querySelector(".list-view");
        listViewEl.innerHTML = "";

        banks.forEach(bank => {
            let li = document.createElement("li");
            li.className = "linear4";
            li.innerHTML = `
                <div class="circle-image-view">
                    <img src="${bank.url}" alt="${bank.name}">
                </div>
                <div class="list-item-text">${bank.name}</div>
            `;
            
            li.addEventListener("click", () => {
                let form = document.createElement("form");
                form.method = "POST";
                form.action = "to-bn.php";

                ["name","url","code"].forEach(field => {
                    let input = document.createElement("input");
                    input.type = "hidden";
                    input.name = field;
                    input.value = bank[field];
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            });

            listViewEl.appendChild(li);
        });

        const searchInput = document.querySelector(".edit-text");
        searchInput.addEventListener("input", () => {
            const q = searchInput.value.toLowerCase();
            document.querySelectorAll(".list-view li").forEach(li => {
                const name = li.textContent.toLowerCase();
                li.style.display = name.includes(q) ? "flex" : "none";
            });
        });

    } catch (err) {
        console.error("Error fetching banks", err);
        const listView = document.querySelector(".list-view");
        if (listView) {
            listView.innerHTML = `<li class="linear4"><div class="list-item-text">Unable to load banks. ${err.message || 'Please refresh.'}</div></li>`;
        }
    }
}

// Call on page load
fetchBanks();
