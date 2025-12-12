// Thời gian tự động làm mới
let autoRefreshInterval;

// Sao chép email vào clipboard
function copyEmail(event) {
  const emailInput = document.getElementById("tempEmail");
  if (!emailInput) return;

  const emailText = emailInput.value;
  const btn =
    event && event.target
      ? event.target.closest("button") || event.target
      : document.querySelector(".btn-copy");
  const originalText = btn ? btn.textContent : "";

  const showSuccess = () => {
    if (!btn) return;
    btn.textContent = "✓ Đã sao chép!";
    btn.style.background = "#4CAF50";
    setTimeout(() => {
      btn.textContent = originalText;
      btn.style.background = "";
    }, 2000);
  };

  const fallbackCopy = () => {
    emailInput.select();
    document.execCommand("copy");
    showSuccess();
  };

  if (
    navigator.clipboard &&
    typeof navigator.clipboard.writeText === "function"
  ) {
    navigator.clipboard
      .writeText(emailText)
      .then(showSuccess)
      .catch(() => {
        fallbackCopy();
      });
  } else {
    // Không có navigator.clipboard ⇒ dùng fallback
    fallbackCopy();
  }
}

// Tạo email mới
function generateNew() {
  if (!confirm("Tạo email mới? Email cũ vẫn có thể truy cập bằng token.")) {
    return;
  }

  fetch("api.php?action=generate")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        document.getElementById("tempEmail").value = data.email;

        if (data.token) {
          document.getElementById("tokenInput").value = data.token;
        }

        if (data.url) {
          document.getElementById("tokenUrl").textContent = data.url;
        } else if (data.token) {
          const baseUrl = window.location.origin + window.location.pathname;
          document.getElementById("tokenUrl").textContent =
            baseUrl + "?token=" + data.token;
        }

        refreshInbox();
        alert("Email mới đã được tạo!\nToken: " + data.token);
      } else {
        alert(
          "Tạo email mới thất bại: " + (data.message || "Lỗi không xác định")
        );
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Đã xảy ra lỗi khi tạo email mới");
    });
}

// Sao chép token
function copyToken(event) {
  const tokenInput = document.getElementById("tokenInput");
  if (!tokenInput) return;

  const tokenText = tokenInput.value;
  const btn =
    event && event.target
      ? event.target.closest("button") || event.target
      : document.querySelector(".btn-copy-token");
  const originalText = btn ? btn.textContent : "";

  const showSuccess = () => {
    if (!btn) return;
    btn.textContent = "✓ Đã sao chép!";
    btn.style.background = "#4CAF50";
    setTimeout(() => {
      btn.textContent = originalText;
      btn.style.background = "";
    }, 2000);
  };

  const fallbackCopy = () => {
    tokenInput.select();
    document.execCommand("copy");
    showSuccess();
  };

  if (
    navigator.clipboard &&
    typeof navigator.clipboard.writeText === "function"
  ) {
    navigator.clipboard
      .writeText(tokenText)
      .then(showSuccess)
      .catch(() => {
        fallbackCopy();
      });
  } else {
    fallbackCopy();
  }
}

// Sao chép URL token
function copyTokenUrl(event) {
  const tokenUrlEl = document.getElementById("tokenUrl");
  if (!tokenUrlEl) return;

  const tokenUrl = tokenUrlEl.textContent.trim();
  const btn =
    event && event.target
      ? event.target.closest("button") || event.target
      : document.querySelector(".btn-copy-url");
  const originalText = btn ? btn.textContent : "";

  const showSuccess = () => {
    if (!btn) return;
    btn.textContent = "✓";
    btn.style.background = "#4CAF50";
    setTimeout(() => {
      btn.textContent = originalText;
      btn.style.background = "";
    }, 2000);
  };

  const fallbackCopy = () => {
    // Fallback đơn giản: tạo textarea tạm
    const textarea = document.createElement("textarea");
    textarea.value = tokenUrl;
    textarea.style.position = "fixed";
    textarea.style.top = "-1000px";
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand("copy");
    document.body.removeChild(textarea);
    showSuccess();
  };

  if (
    navigator.clipboard &&
    typeof navigator.clipboard.writeText === "function"
  ) {
    navigator.clipboard
      .writeText(tokenUrl)
      .then(showSuccess)
      .catch(() => {
        fallbackCopy();
      });
  } else {
    fallbackCopy();
  }
}

// Ẩn/hiện form khôi phục
function toggleRestoreForm() {
  const form = document.getElementById("restoreForm");
  form.style.display = form.style.display === "none" ? "flex" : "none";
}

// Khôi phục email
function restoreEmail() {
  const token = document.getElementById("restoreToken").value.trim();

  if (!token) {
    alert("Vui lòng nhập token trước");
    return;
  }

  fetch("api.php?action=restore", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: "token=" + encodeURIComponent(token),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        document.getElementById("tempEmail").value = data.email;
        document.getElementById("tokenInput").value = data.token;
        document.getElementById("tokenUrl").textContent =
          window.location.origin + "/?token=" + data.token;
        document.getElementById("restoreForm").style.display = "none";
        document.getElementById("restoreToken").value = "";
        refreshInbox();
        alert("Khôi phục email thành công!");
      } else {
        alert(data.message || "Token không hợp lệ");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Đã xảy ra lỗi");
    });
}

// Làm mới inbox
function refreshInbox() {
  fetch("api.php?action=get_emails")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        updateEmailList(data.emails);
        document.querySelector(
          ".inbox-header h2"
        ).textContent = `Hộp thư (${data.count})`;
      }
    })
    .catch((error) => console.error("Error:", error));
}

// Cập nhật danh sách email
function updateEmailList(emails) {
  const emailList = document.getElementById("emailList");

  if (emails.length === 0) {
    emailList.innerHTML = `
            <div class="no-emails">
                <p>📭 Không có email nào</p>
                <p class="hint">Email mới sẽ xuất hiện tại đây</p>
            </div>
        `;
    return;
  }

  emailList.innerHTML = emails
    .map(
      (email) => `
        <div class="email-item" onclick="viewEmail(${email.id})">
            <div class="email-from">
                <strong>Từ:</strong> ${escapeHtml(email.from_email)}
            </div>
            <div class="email-subject">
                ${escapeHtml(email.subject)}
            </div>
            <div class="email-date">
                ${formatDate(email.received_at, email.timestamp)}
            </div>
        </div>
    `
    )
    .join("");
}

// Xem chi tiết email
function viewEmail(id) {
  fetch(`api.php?action=get_email&id=${id}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showEmailModal(data.email);
      }
    })
    .catch((error) => console.error("Error:", error));
}

// Hiển thị modal email
function showEmailModal(email) {
  const modal = document.getElementById("emailModal");
  const content = document.getElementById("emailContent");

  let bodyContent = email.body;
  let isHtml = false;
  let isPartialHtml = false;

  if (
    bodyContent.includes("<html") ||
    bodyContent.includes("<!DOCTYPE") ||
    bodyContent.includes("</html>")
  ) {
    isHtml = true;
  } else if (
    bodyContent.includes("<div") ||
    bodyContent.includes("<table") ||
    bodyContent.includes("<p>") ||
    bodyContent.includes("<br>") ||
    bodyContent.includes("<br />") ||
    bodyContent.includes("<br/>") ||
    bodyContent.includes("<a ") ||
    bodyContent.includes("<img") ||
    bodyContent.includes("<span") ||
    bodyContent.includes("<strong") ||
    bodyContent.includes("<em>") ||
    bodyContent.includes("<h1") ||
    bodyContent.includes("<h2") ||
    bodyContent.includes("<h3")
  ) {
    const htmlTagCount = (bodyContent.match(/<[^>]+>/g) || []).length;
    const closingTagCount = (bodyContent.match(/<\/[^>]+>/g) || []).length;

    if (htmlTagCount > 3 && closingTagCount > 0) {
      isHtml = true;
    } else {
      isPartialHtml = true;
      isHtml = true;
    }
  }

  let cleanText = bodyContent;
  if (isHtml) {
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = bodyContent;

    const styleTags = tempDiv.querySelectorAll("style");
    styleTags.forEach((tag) => tag.remove());

    const scriptTags = tempDiv.querySelectorAll("script");
    scriptTags.forEach((tag) => tag.remove());

    cleanText = tempDiv.textContent || tempDiv.innerText || "";

    cleanText = cleanText
      .replace(/\n\s*\n\s*\n/g, "\n\n")
      .replace(/[ \t]+/g, " ")
      .replace(/^\s+/gm, "")
      .trim();
  } else {
    cleanText = bodyContent;
  }

  const viewToggle = isHtml
    ? `
        <div class="view-toggle">
            <button onclick="toggleEmailView('html')" id="btnHtml" class="active">🌐 Hiển thị HTML</button>
            <button onclick="toggleEmailView('clean')" id="btnClean">✨ Văn bản sạch</button>
            <button onclick="toggleEmailView('raw')" id="btnRaw">📝 Mã gốc</button>
        </div>
    `
    : "";

  let defaultView = "";
  if (isHtml) {
    let htmlToRender = bodyContent;
    if (
      isPartialHtml ||
      (!bodyContent.includes("<html") && !bodyContent.includes("<!DOCTYPE"))
    ) {
      htmlToRender = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
    </style>
</head>
<body>
${bodyContent}
</body>
</html>`;
    }
    defaultView = renderHtmlEmail(htmlToRender);
  } else {
    defaultView = `<div style="white-space: pre-wrap; word-wrap: break-word; font-family: inherit; line-height: 1.6;">${escapeHtml(
      cleanText
    )}</div>`;
  }

  content.innerHTML = `
        <h3>📧 ${escapeHtml(email.subject)}</h3>
        <div class="email-meta">
            <p><strong>Từ:</strong> ${escapeHtml(email.from_email)}</p>
            <p><strong>Đến:</strong> ${escapeHtml(email.to_email)}</p>
            <p><strong>Thời gian:</strong> ${formatDate(
              email.received_at,
              email.timestamp
            )}</p>
        </div>
        ${viewToggle}
        <div class="email-body" id="emailBodyContainer">
            ${defaultView}
        </div>
    `;

  content.dataset.htmlContent = bodyContent;
  content.dataset.cleanContent = cleanText;
  content.dataset.isHtml = isHtml;
  content.dataset.isPartialHtml = isPartialHtml;

  modal.style.display = "block";
}

// Render HTML email an toàn trong iframe
function renderHtmlEmail(htmlContent) {
  const iframeId = "emailIframe_" + Date.now();

  setTimeout(() => {
    const iframe = document.getElementById(iframeId);
    if (iframe) {
      const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
      iframeDoc.open();
      iframeDoc.write(htmlContent);
      iframeDoc.close();

      setTimeout(() => {
        try {
          const body = iframeDoc.body;
          const html = iframeDoc.documentElement;
          const height = Math.max(
            body.scrollHeight,
            body.offsetHeight,
            html.clientHeight,
            html.scrollHeight,
            html.offsetHeight
          );
          iframe.style.height = height + 40 + "px";
        } catch (e) {
          iframe.style.height = "600px";
        }
      }, 200);
    }
  }, 10);

  return `<iframe id="${iframeId}" sandbox="allow-same-origin allow-popups" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; min-height: 400px; background: white; display: block;"></iframe>`;
}

// Chuyển đổi giữa các chế độ xem email
function toggleEmailView(view) {
  const container = document.getElementById("emailBodyContainer");
  const content = document.getElementById("emailContent");
  const htmlContent = content.dataset.htmlContent;
  const cleanContent = content.dataset.cleanContent;
  const isHtml = content.dataset.isHtml === "true";
  const isPartialHtml = content.dataset.isPartialHtml === "true";

  const btnClean = document.getElementById("btnClean");
  const btnHtml = document.getElementById("btnHtml");
  const btnRaw = document.getElementById("btnRaw");

  if (btnClean) btnClean.classList.remove("active");
  if (btnHtml) btnHtml.classList.remove("active");
  if (btnRaw) btnRaw.classList.remove("active");

  if (view === "clean") {
    if (btnClean) btnClean.classList.add("active");
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = htmlContent;
    const styleTags = tempDiv.querySelectorAll("style");
    styleTags.forEach((tag) => tag.remove());
    const scriptTags = tempDiv.querySelectorAll("script");
    scriptTags.forEach((tag) => tag.remove());
    let textOnly = tempDiv.textContent || tempDiv.innerText || cleanContent;
    textOnly = textOnly
      .replace(/\n\s*\n\s*\n/g, "\n\n")
      .replace(/[ \t]+/g, " ")
      .replace(/^\s+/gm, "")
      .trim();
    container.innerHTML = `<div style="white-space: pre-wrap; word-wrap: break-word; line-height: 1.6;">${escapeHtml(
      textOnly
    )}</div>`;
  } else if (view === "html" && isHtml) {
    if (btnHtml) btnHtml.classList.add("active");

    let htmlToRender = htmlContent;
    if (
      isPartialHtml ||
      (!htmlContent.includes("<html") && !htmlContent.includes("<!DOCTYPE"))
    ) {
      htmlToRender = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
    </style>
</head>
<body>
${htmlContent}
</body>
</html>`;
    }

    container.innerHTML = renderHtmlEmail(htmlToRender);
  } else if (view === "raw") {
    if (btnRaw) btnRaw.classList.add("active");
    container.innerHTML = `<pre style="white-space: pre-wrap; word-wrap: break-word; font-size: 12px;">${escapeHtml(
      htmlContent
    )}</pre>`;
  }
}

// Đóng modal
function closeModal() {
  document.getElementById("emailModal").style.display = "none";
}

// Đóng modal khi click ra ngoài
window.onclick = function (event) {
  const modal = document.getElementById("emailModal");
  if (event.target === modal) {
    modal.style.display = "none";
  }
};

// Escape HTML
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Định dạng ngày giờ theo múi giờ máy người dùng
function formatDate(dateString, timestamp) {
  let date;
  if (timestamp) {
    date = new Date(timestamp * 1000);
  } else {
    date = new Date(dateString);
  }

  const day = String(date.getDate()).padStart(2, "0");
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const year = date.getFullYear();
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");

  return `${day}/${month}/${year} ${hours}:${minutes}`;
}

// Bắt đầu auto refresh
function startAutoRefresh() {
  autoRefreshInterval = setInterval(refreshInbox, 10000);
}

// Dừng auto refresh
function stopAutoRefresh() {
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval);
  }
}

// Chuyển toàn bộ timestamp sang giờ local
function convertTimestampsToLocal() {
  const dateElements = document.querySelectorAll(".email-date[data-timestamp]");
  dateElements.forEach((element) => {
    const timestamp = element.getAttribute("data-timestamp");
    const unixTimestamp = element.getAttribute("data-unix");
    if (timestamp) {
      element.textContent = formatDate(timestamp, unixTimestamp);
    }
  });
}

// Khởi tạo
document.addEventListener("DOMContentLoaded", function () {
  convertTimestampsToLocal();
  startAutoRefresh();
});

// Dừng auto refresh khi tab ẩn
document.addEventListener("visibilitychange", function () {
  if (document.hidden) {
    stopAutoRefresh();
  } else {
    startAutoRefresh();
  }
});
