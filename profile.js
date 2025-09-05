document.addEventListener("DOMContentLoaded", () => {
  const notesList = document.getElementById("user-notes-list");
  const currentUser = localStorage.getItem("utenteLoggato");

  if (!currentUser) {
    window.location.href = "login.html";
    return;
  }

  let userNotes = JSON.parse(localStorage.getItem("userNotes") || "[]");

  function renderNotes() {
    notesList.innerHTML = "";

    if (userNotes.length === 0) {
      notesList.innerHTML = "<p>Non hai ancora creato note.</p>";
      return;
    }

    userNotes.forEach((note, index) => {
      const noteDiv = document.createElement("div");
      noteDiv.classList.add("note-card");

      // Se la nota è in modalità modifica (allowEdit = true) e pubblica, mostra textarea
      if (note.public && note.allowEdit && note.isEditing) {
        noteDiv.innerHTML = `
          <textarea data-index="${index}" class="note-textarea">${note.content || note.testo}</textarea>
          <p>Tag: ${note.tags || ""}</p>
          <p>Cartella: ${note.folder || ""}</p>
          <label>
            <input type="checkbox" class="visibility-toggle" data-index="${index}" checked>
            Pubblica
          </label>
          <label>
            <input type="checkbox" class="edit-toggle" data-index="${index}" checked>
            Consenti modifiche agli altri
          </label>
          <br>
          <button class="save-note-btn" data-index="${index}">Salva</button>
          <button class="delete-note-btn" data-index="${index}">Elimina</button>
          <hr>
        `;
      } else {
        // Modalità normale: testo non modificabile, checkbox visibilità e modifica
        noteDiv.innerHTML = `
          <p>${note.content || note.testo}</p>
          <p>Tag: ${note.tags || ""}</p>
          <p>Cartella: ${note.folder || ""}</p>
          <label>
            <input type="checkbox" class="visibility-toggle" data-index="${index}" ${note.public ? "checked" : ""}>
            Pubblica
          </label>
          <label>
            <input type="checkbox" class="edit-toggle" data-index="${index}" ${note.public ? "" : "disabled"} ${note.allowEdit ? "checked" : ""}>
            Consenti modifiche agli altri
          </label>
          <br>
          <button class="delete-note-btn" data-index="${index}">Elimina</button>
          <hr>
        `;
      }

      notesList.appendChild(noteDiv);
    });
  }

  notesList.addEventListener("change", (e) => {
    const index = e.target.dataset.index;

    if (e.target.classList.contains("visibility-toggle")) {
      userNotes[index].public = e.target.checked;
      if (!e.target.checked) {
        userNotes[index].allowEdit = false;
      }
      // Se la nota non è pubblica o non ha modifica, togli la modalità editing
      userNotes[index].isEditing = false;
    }

    if (e.target.classList.contains("edit-toggle")) {
      if (userNotes[index].public) {
        userNotes[index].allowEdit = e.target.checked;
        // Se abilito la modifica, attivo la modalità editing per mostrare textarea
        userNotes[index].isEditing = e.target.checked;
      }
    }

    localStorage.setItem("userNotes", JSON.stringify(userNotes));
    aggiornaNotePubbliche();
    renderNotes();
  });

  notesList.addEventListener("click", (e) => {
    const index = e.target.dataset.index;

    if (e.target.classList.contains("save-note-btn")) {
      const newText = document.querySelector(`textarea[data-index="${index}"]`).value.trim();
      userNotes[index].content = newText;
      // Dopo il salvataggio esci dalla modalità editing
      userNotes[index].isEditing = false;
      localStorage.setItem("userNotes", JSON.stringify(userNotes));
      aggiornaNotePubbliche();
      renderNotes();
    }

    if (e.target.classList.contains("delete-note-btn")) {
      userNotes.splice(index, 1); // rimuove la nota dall'array
      localStorage.setItem("userNotes", JSON.stringify(userNotes));
      aggiornaNotePubbliche();
      renderNotes();
    }
  });

  function aggiornaNotePubbliche() {
    const notePubbliche = userNotes.filter(n => n.public).map(n => ({
      autore: currentUser,
      testo: n.content,
      tag: (n.tags || "").split(",").map(t => t.trim()),
      cartella: n.folder,
      data: new Date().toISOString(),
      allowEdit: n.allowEdit,
      public: true
    }));
    localStorage.setItem("notePubbliche", JSON.stringify(notePubbliche));
  }

  renderNotes();
});
