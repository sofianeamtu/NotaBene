document.addEventListener("DOMContentLoaded", () => {
  const messageDiv = document.getElementById("message");
  const registerForm = document.getElementById("registerForm");
  const loginForm = document.getElementById("loginForm");

  // --- LOGIN/REGISTER (se presenti su questa pagina) ---
  if (registerForm) {
    registerForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const username = document.getElementById("regUsername").value.trim();
      const password = document.getElementById("regPassword").value;

      if (!username || !password) {
        messageDiv.textContent = "Compila tutti i campi.";
        return;
      }

      const users = JSON.parse(localStorage.getItem("users") || "{}");
      if (users[username]) {
        messageDiv.textContent = "Utente già registrato.";
        return;
      }

      users[username] = password;
      localStorage.setItem("users", JSON.stringify(users));

      alert("Registrazione completata! Ora puoi accedere.");
      window.location.href = "login.html";
    });
  }

  if (loginForm) {
    loginForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const username = document.getElementById("loginUsername").value.trim();
      const password = document.getElementById("loginPassword").value;

      const users = JSON.parse(localStorage.getItem("users") || "{}");

      if (!users[username]) {
        messageDiv.textContent = "Utente non registrato.";
        return;
      }

      if (users[username] !== password) {
        messageDiv.textContent = "Password errata.";
        return;
      }

      localStorage.setItem("utenteLoggato", username);
      window.location.href = "home.html";
    });
  }

  function mostraEmailUtente() {
    const username = localStorage.getItem("utenteLoggato");
    if (username) {
      const userEmailSpan = document.getElementById("user-email");
      if (userEmailSpan) {
        userEmailSpan.textContent = username;
      }
    } else {
      window.location.href = "login.html";
    }
  }

  mostraEmailUtente();

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

  function mostraNotePubbliche(filterType = null, filterValue = null) {
    const contenitore = document.querySelector(".notes-list");
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
      return;
    }

    noteDaMostrare.forEach(n => {
      const div = document.createElement("div");
      div.className = "note";
      div.innerHTML = `
        <p>${n.testo}</p>
        <p><small>Autore: ${n.autore}</small></p>
        <p><small>Tag: ${n.tag.join(", ")}</small></p>
        <p><small>Cartella: ${n.cartella || "Nessuna"}</small></p>
      `;
      contenitore.appendChild(div);
    });

    aggiornaSidebarTagsECartelle(noteDaMostrare);
  }

  const noteForm = document.getElementById("noteForm");
  if (noteForm) {
    noteForm.addEventListener("submit", (e) => {
      e.preventDefault();

      const noteText = document.getElementById("noteText").value.trim();
      const noteTags = document.getElementById("noteTags").value.trim().split(",").map(t => t.trim().toLowerCase()).filter(t => t);
      const noteFolder = document.getElementById("noteFolder").value.trim();
      const publicRadio = document.querySelector('input[name="noteVisibility"]:checked');
      const isPublic = publicRadio ? publicRadio.value === "pubblica" : false;

      const username = localStorage.getItem("utenteLoggato");
      if (!noteText || !username) return;

      const nuovaNota = {
        content: noteText,
        tags: noteTags.join(", "),
        folder: noteFolder,
        public: isPublic,
        allowEdit: false
      };

      const userNotes = JSON.parse(localStorage.getItem("userNotes") || "[]");
      userNotes.unshift(nuovaNota);
      localStorage.setItem("userNotes", JSON.stringify(userNotes));

      if (isPublic) {
        aggiornaNotePubbliche(username);
      }

      noteForm.reset();
      const noteMessage = document.getElementById("noteMessage");
      if (noteMessage) {
        noteMessage.textContent = "Nota salvata!";
        noteMessage.style.color = "#28a745";
      }

      mostraNotePubbliche();
    });
  }

  function aggiornaNotePubbliche(username) {
    const allUsers = JSON.parse(localStorage.getItem("users") || "{}");
    const noteUtente = JSON.parse(localStorage.getItem("userNotes") || "[]");

    const notePubbliche = JSON.parse(localStorage.getItem("notePubbliche") || "[]")
      .filter(n => n.autore !== username);

    const nuoveNote = noteUtente.filter(n => n.public).map(n => ({
      autore: username,
      testo: n.content,
      tag: (n.tags || "").split(",").map(t => t.trim().toLowerCase()),
      cartella: n.folder,
      data: new Date().toISOString(),
      allowEdit: n.allowEdit,
      public: true
    }));

    localStorage.setItem("notePubbliche", JSON.stringify([...notePubbliche, ...nuoveNote]));
  }

  mostraNotePubbliche();
});
