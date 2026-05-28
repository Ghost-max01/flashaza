function formatAmount(amount) {
    return new Intl.NumberFormat('en-NG',{minimumFractionDigits:2, maximumFractionDigits:2}).format(parseFloat(amount || 0));
  }

  function getStatusClass(status) {
    switch(status) {
      case "success": return "status-success";
      case "reversed": return "status-reversed";
      case "pending": return "status-pending";
      case "failed": return "status-failed";
      default: return "status-success";
    }
  }

  function getStatusText(status) {
    switch(status) {
      case "success": return "Successful";
      case "reversed": return "Reversed";
      case "pending": return "Pending";
      case "failed": return "Failed";
      default: return "Successful";
    }
  }

  function showPopupMenu(options, transaction) {
    const menu = document.getElementById("popupMenu");
    menu.innerHTML = "";

    options.forEach(opt => {
      const btn = document.createElement("div");
      btn.textContent = opt;
      btn.style.padding = "10px 12px";
      btn.style.cursor = "pointer";
      btn.style.userSelect = "none";
      btn.style.color = "var(--text-color)";
      btn.onmouseenter = () => btn.style.background = "var(--filter-bg)";
      btn.onmouseleave = () => btn.style.background = "transparent";
      btn.onclick = (e) => {
        e.stopPropagation();
        menu.classList.add("hidden");
        updateStatus(transaction, opt.toLowerCase());
      };
      menu.appendChild(btn);
    });

    menu.classList.remove("hidden");

    // click outside to close
    const closeOnOutside = (e) => {
      if (!menu.contains(e.target)) {
        menu.classList.add("hidden");
        document.removeEventListener("click", closeOnOutside);
      }
    };
    setTimeout(() => document.addEventListener("click", closeOnOutside), 0);
  }

  function updateStatus(transaction, newStatus) {
    fetch("transaction-history.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "update_status", id: transaction.id, status: newStatus })
    })
    .then(res => res.json())
    .then(data => {
      if (data && data.success) {
        // refresh list as requested
        location.reload();
      } else {
        alert("Failed to update status");
      }
    })
    .catch(err => {
      console.error(err);
      alert("Error updating status");
    });
  }

  function generateTransactionCard(data) {
    const card = document.createElement("div");
    card.className = "lv-transaction-card";

    const img = document.createElement("img");
    const info = document.createElement("div"); info.className = "transaction-info";
    const top = document.createElement("div"); top.className = "transaction-top";
    const bottom = document.createElement("div"); bottom.className = "transaction-bottom";

    const nameSpan = document.createElement("span"); nameSpan.className = "accountname";
    const amountSpan = document.createElement("span"); amountSpan.className = "amount";
    const dateSpan = document.createElement("span"); dateSpan.className = "date";
    const statusSpan = document.createElement("span"); statusSpan.className = "status";

    // Default status: if missing/null/empty -> treat as 'success'
    const statusVal = (data.status && String(data.status).trim() !== "") ? String(data.status).toLowerCase() : "success";

    // Build UI text & icon per category/type
    if (data.category === "money") {
      if (data.type === "sent") {
        img.src = "images/history/out.png";
        nameSpan.textContent = "Transfer to " + (data.accountname || "");
        amountSpan.textContent = "-₦" + formatAmount(data.amount);
        amountSpan.style.color = "var(--text-color)";
      } else {
        img.src = "images/history/in.png";
        nameSpan.textContent = "Transfer from " + (data.accountname || "");
        amountSpan.textContent = "+₦" + formatAmount(data.amount);
        amountSpan.style.color = "var(--success-color)";
      }
    } else if (data.category === "airtime") {
      img.src = "images/history/airtime.png";
      nameSpan.textContent = "Airtime";
      amountSpan.textContent = "-₦" + formatAmount(data.amount);
      amountSpan.style.color = "var(--text-color)";
    } else if (data.category === "data") {
      img.src = "images/history/data.png";
      nameSpan.textContent = "Mobile data";
      amountSpan.textContent = "-₦" + formatAmount(data.amount);
      amountSpan.style.color = "var(--text-color)";
    } else if (data.category === "fee") {
      img.src = "images/history/out.png";
      nameSpan.textContent = "Electronic Money Transfer Levy";
      amountSpan.textContent = "-₦50.00";
      amountSpan.style.color = "var(--text-color)";
    } else if (data.category === "owealth") {
      img.src = "images/history/owealth.png";
      nameSpan.textContent = "Owealth Interest Earned";
      amountSpan.textContent = "+₦" + formatAmount(data.amount);
      amountSpan.style.color = "var(--success-color)";
    } else if (data.category === "bonus") {
      img.src = "images/history/data.png";
      nameSpan.textContent = data.type === "data" ? "Bonus from Data Purchase" : "Bonus from Airtime Purchase";
      const cbText = (data.cb && String(data.cb).includes(".")) ? data.cb : (data.cb ? data.cb + ".00" : "0.00");
      amountSpan.textContent = "+₦" + cbText;
      amountSpan.style.color = "var(--success-color)";
    } else if (data.category && data.category.toLowerCase().includes("deposit")) {
      img.src = "images/history/deposit.png";
      nameSpan.textContent = "Auto-save to Owealth Balance";
      amountSpan.textContent = "₦" + formatAmount(data.amount);
      amountSpan.style.color = "var(--text-color)";
    } else {
      // fallback
      img.src = "images/history/out.png";
      nameSpan.textContent = data.accountname || "Transaction";
      amountSpan.textContent = "₦" + formatAmount(data.amount || "0");
      amountSpan.style.color = "var(--text-color)";
    }

    // date and status
    dateSpan.textContent = data.date1 || data.date || "";
    statusSpan.textContent = getStatusText(statusVal);
    statusSpan.className = "status " + getStatusClass(statusVal);

    // Compose nodes
    top.appendChild(nameSpan);
    top.appendChild(amountSpan);

    bottom.appendChild(dateSpan);
    bottom.appendChild(statusSpan);

    info.appendChild(top);
    info.appendChild(bottom);

    card.appendChild(img);
    card.appendChild(info);

    // Navigate on card click (keep your behavior)
    let longPressActive = false;
    card.addEventListener('click', function () {
      if (longPressActive) { 
        longPressActive = false;
        return; 
      }
      const pid = data.product_id || data.productId || data.id || null;
      if (!pid) return;

      const category = (data.category || "").toLowerCase();
      const type = (data.type || "").toLowerCase();
      const bankname = (data.bankname || "").toLowerCase();

      let targetUrl = null;

      // Check if category is "money" and type is "sent" or "received" AND bankname is "opay"
      if (category === "money" && (type === "sent" || type === "received") && bankname === "opay") {
        targetUrl = "opy-receipt.php?product_id=" + encodeURIComponent(pid);
      } else if (category === "money") {
        if (type === "received" && bankname !== "opay") {
          // special case: received from non-OPay bank
          targetUrl = "from-bnk-receipt.php?product_id=" + encodeURIComponent(pid);
        } else {
          // normal money transactions for non-OPay banks
          targetUrl = "bnk_receipt.php?product_id=" + encodeURIComponent(pid);
        }
      } else if (category === "airtime") {
        targetUrl = "airtime-receipt.php?product_id=" + encodeURIComponent(pid);
      } else if (category === "data") {
        targetUrl = "data-receipt.php?product_id=" + encodeURIComponent(pid);
      } else {
        if (bankname === "opay") {
          targetUrl = "opy-receipt.php?product_id=" + encodeURIComponent(pid);
        } else if (bankname) {
          targetUrl = "bnk_receipt.php?product_id=" + encodeURIComponent(pid);
        }
      }

      if (targetUrl) {
        window.location.href = targetUrl;
      }
    });

    // ---- Long-press on STATUS ONLY ----
    let pressTimer;
    const pressDuration = 700;

    const startPress = (e) => {
      e.stopPropagation();
      clearTimeout(pressTimer);
      pressTimer = setTimeout(() => {
        longPressActive = true;

        const category = (data.category || "").toLowerCase();
        const type = (data.type || "").toLowerCase();
        const bankname = (data.bankname || "").toLowerCase();

        let options = [];
        if (category === "money" && type === "sent") {
          options = (bankname === "opay")
            ? ["Success", "Failed", "Reversed"]
            : ["Success", "Failed", "Pending"];
        } else if (category === "money" && type === "received") {
          options = (bankname === "opay")
            ? ["Success", "Failed", "Reversed"]
            : ["Success", "Failed", "Reversed"];
        } else if (category === "airtime" || category === "data") {
          options = ["Success", "Failed", "Reversed"];
        }

        if (options.length) {
          showPopupMenu(options, data);
        }
      }, pressDuration);
    };

    const cancelPress = (e) => {
      clearTimeout(pressTimer);
    };

    // mouse + touch events for status label
    statusSpan.addEventListener("mousedown", startPress);
    statusSpan.addEventListener("mouseup", cancelPress);
    statusSpan.addEventListener("mouseleave", cancelPress);
    statusSpan.addEventListener("touchstart", startPress, { passive: true });
    statusSpan.addEventListener("touchend", cancelPress);
    statusSpan.addEventListener("click", (e) => {
      if (longPressActive) {
        e.preventDefault();
        e.stopPropagation();
        longPressActive = false;
      }
    });

    return card;
  }

  function generateAllTransactionCards() {
    const list = document.getElementById("listSection");
    list.innerHTML = "";
    transactionData.forEach(tx => {
      const card = generateTransactionCard(tx);
      list.appendChild(card);
    });
  }

  // Check subscription status on page load
  document.addEventListener('DOMContentLoaded', function() {
    if (!HAS_SUBSCRIPTION) {
      showSubscriptionDialog();
    } else {
      // simulate loading then show monthSummary and list
      setTimeout(() => {
        document.getElementById("loading").style.display = "none";
        document.getElementById("monthSummary").style.display = "block";
        document.getElementById("listSection").style.display = "block";
        generateAllTransactionCards();
      }, 700);
    }
  });