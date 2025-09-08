document.addEventListener("DOMContentLoaded", () => {
  const messageDiv = document.getElementById("message");
  const registerForm = document.getElementById("registerForm");
  const loginForm = document.getElementById("loginForm");

  // --- Utility ---
  function isProtectedPage() {
    const path = location.pathname.toLowerCase();
    return path.endsWith('/home.html') ||
           path.endsWith('/newnote.html') ||
           path.endsWith('/profile.html');
  }
  function getLoggedUser() {
    return localStorage.getItem("utenteLoggato");
  }
  const USER_NOTES_KEY = (u) => `userNotes_${u}`;

  // --- REGISTER ---
  if (registerForm) {
    registerForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const username = document.getElementById("regUsername").value.trim();
      const password = document.getElementById("regPassword").value;

      if (!username || !password) {
        if (messageDiv) messageDiv.textContent = "Compila tutti i campi.";
        return;
      }

      const users = JSON.parse(localStorage.getItem("users") || "{}");
      if (users[username]) {
        if (messageDiv) messageDiv.textContent = "Utente già registrato.";
        return;
      }

      users[username] = password;
      localStorage.setItem("users", JSON.stringify(users));

      alert("Registrazione completata! Ora puoi accedere.");
      window.location.href = "login.html";
    });
  }

  // --- LOGIN ---
  if (loginForm) {
    loginForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const username = document.getElementById("loginUsername").value.trim();
      const password = document.getElementById("loginPassword").value;

      const users = JSON.parse(localStorage.getItem("users") || "{}");
      if (!users[username]) {
        if (messageDiv) messageDiv.textContent = "Utente non registrato.";
        return;
      }
      if (users[username] !== password) {
        if (messageDiv) messageDiv.textContent = "Password errata.";
        return;
      }

      localStorage.setItem("utenteLoggato", username);
      window.location.href = "home.html";
    });
  }

  // --- Header + redirect solo su pagine protette ---
  function mostraEmailUtente() {
    const username = getLoggedUser();
    const userEmailSpan = document.getElementById("user-email");
    if (userEmailSpan) userEmailSpan.textContent = username || 'ospite';
    if (isProtectedPage() && !username) {
      window.location.href = "login.html";
    }
  }
  mostraEmailUtente();

  // --- Sidebar dinamica (tag/cartelle) ---
  function aggiornaSidebarTagsECartelle(note) {
    const tagList = document.getElementById("dynamic-tag-list");
    const folderList = document.getElementById("dynamic-folder-list");
    if (!tagList || !folderList) return;

    tagList.innerHTML = '';
    folderList.innerHTML = '';

    const allTags = new Set();
    const allFolders = new Set();

    note.forEach(n => {
      (n.tag || []).forEach(tag => allTags.add(tag));
      if (n.cartella) allFolders.add(n.cartella);
    });

    [...allTags].sort().forEach(tag => {
      const li = document.createElement("li");
      li.innerHTML = `<a href="#" data-filter-type="tag" data-filter-value="${tag}">#${tag}</a>`;
      tagList.appendChild(li);
    });
    [...allFolders].sort().forEach(folder => {
      const li = document.createElement("li");
      li.innerHTML = `<a href="#" data-filter-type="folder" data-filter-value="${folder}">${folder}</a>`;
      folderList.appendChild(li);
    });

    document.querySelectorAll('.tags-sidebar a, #dynamic-folder-list a').forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        mostraNotePubbliche(this.dataset.filterType, this.dataset.filterValue);
      });
    });
  }

  // --- Home: mostra note pubbliche ---
  function mostraNotePubbliche(filterType = null, filterValue = null) {
    const contenitore = document.querySelector(".notes-list");
    if (!contenitore) return; // pagina senza lista note (es. login/register)

    contenitore.innerHTML = "";
    const notePubbliche = JSON.parse(localStorage.getItem("notePubbliche") || "[]");
    let noteDaMostrare = notePubbliche;

    if (filterType && filterValue) {
      if (filterType === "tag") {
        noteDaMostrare = noteDaMostrare.filter(n => (n.tag || []).includes(filterValue));
      } else if (filterType === "folder") {
        noteDaMostrare = noteDaMostrare.filter(n => n.cartella === filterValue);
      }
    }

    if (noteDaMostrare.length === 0) {
      contenitore.innerHTML = "<p>Nessuna nota pubblica disponibile.</p>";
      aggiornaSidebarTagsECartelle([]);
      return;
    }

    noteDaMostrare.forEach(n => {
      const div = document.createElement("div");
      div.className = "note";
      div.innerHTML = `
        <p>${n.testo}</p>
        <p><small>Autore: ${n.autore}</small></p>
        <p><small>Tag: ${(n.tag || []).join(", ") || "—"}</small></p>
        <p><small>Cartella: ${n.cartella || "Nessuna"}</small></p>
      `;
      contenitore.appendChild(div);
    });

    aggiornaSidebarTagsECartelle(noteDaMostrare);
  }

  // --- New note: salvataggio ---
  const noteForm = document.getElementById("noteForm");
  if (noteForm) {
    noteForm.addEventListener("submit", (e) => {
      e.preventDefault();

      const noteText = document.getElementById("noteText").value.trim();
      const noteTags = document.getElementById("noteTags").value
        .trim().split(",").map(t => t.trim().toLowerCase()).filter(Boolean);
      const noteFolder = document.getElementById("noteFolder").value.trim();

      // IMPORTANTE: assicurati che i radio in newnote.html abbiano value="pubblica" / "privata"
      const publicRadio = document.querySelector('input[name="noteVisibility"]:checked');
      const isPublic = publicRadio ? publicRadio.value === "pubblica" : false;

      const username = getLoggedUser();
      if (!noteText || !username) return;

      const nuovaNota = {
        content: noteText,
        tags: noteTags.join(", "),       // salviamo come stringa, poi la splitteremo
        folder: noteFolder,
        public: isPublic,
        allowEdit: false,
        createdAt: new Date().toISOString()
      };

      // Salva note per-utente
      const userKey = USER_NOTES_KEY(username);
      const userNotes = JSON.parse(localStorage.getItem(userKey) || "[]");
      userNotes.unshift(nuovaNota);
      localStorage.setItem(userKey, JSON.stringify(userNotes));

      // Se pubblica, aggiorna la bacheca pubblica SOLO con questa nota
      if (isPublic) {
        aggiornaNotePubbliche(username, [nuovaNota]);
      }

      // Feedback + redirect
      const noteMessage = document.getElementById("noteMessage");
      if (noteMessage) {
        noteMessage.textContent = "Nota salvata!";
        noteMessage.style.color = "#28a745";
      }
      // Vai in Home (o "profile.html" se preferisci)
      setTimeout(() => { window.location.href = "home.html"; }, 400);
    });
  }

  // Aggiorna note pubbliche: append solo delle note passate
  function aggiornaNotePubbliche(username, notesArray) {
    const notePubbliche = JSON.parse(localStorage.getItem("notePubbliche") || "[]");

    // Evita duplicati (stesso autore + stesso testo + stessa cartella)
    const uniqKey = (n) => `${n.autore}__${n.testo}__${n.cartella || ""}`;
    const esistenti = new Set(notePubbliche.map(uniqKey));

    const nuoveNote = (notesArray || []).filter(n => n.public).map(n => ({
      autore: username,
      testo: n.content,
      tag: (n.tags || "").split(",").map(t => t.trim().toLowerCase()).filter(Boolean),
      cartella: n.folder || "",
      data: new Date().toISOString(),
      allowEdit: n.allowEdit,
      public: true
    })).filter(n => !esistenti.has(uniqKey(n)));

    const aggiornate = [...nuoveNote, ...notePubbliche]; // most-recent first
    localStorage.setItem("notePubbliche", JSON.stringify(aggiornate));
  }

  // Mostra bacheca pubblica solo se la pagina la contiene
  if (document.querySelector(".notes-list")) {
    mostraNotePubbliche();
  }
});
