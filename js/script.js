// js/script.js

function initAddressAutocomplete(cityInputId, postalInputId) {
  const cityInput = document.getElementById(cityInputId);
  const postalInput = document.getElementById(postalInputId);
  if (!cityInput || !postalInput) return;

  let suggestionBox = null;

  cityInput.addEventListener("input", async function() {
    const query = cityInput.value.trim();
    if (query.length < 2) {
      hideSuggestions();
      return;
    }

    try {
      const response = await fetch(`https://geo.api.gouv.fr/communes?nom=${encodeURIComponent(query)}&limit=5`);
      const data = await response.json();
      if (!Array.isArray(data) || data.length === 0) {
        hideSuggestions();
        return;
      }
      showSuggestions(data);
    } catch (error) {
      console.error("Erreur autocomplete:", error);
      hideSuggestions();
    }
  });

  function showSuggestions(communes) {
    hideSuggestions();
    suggestionBox = document.createElement("div");
    suggestionBox.style.border = "1px solid #ccc";
    suggestionBox.style.background = "#fff";
    suggestionBox.style.position = "absolute";
    suggestionBox.style.zIndex = "999";
    suggestionBox.style.width = cityInput.offsetWidth + "px";

    communes.forEach(commune => {
      const item = document.createElement("div");
      item.style.padding = "5px";
      item.style.cursor = "pointer";

      let codePostal = commune.codesPostaux && commune.codesPostaux.length ? commune.codesPostaux[0] : "";
      item.textContent = `${commune.nom} (${codePostal})`;
      item.addEventListener("click", () => {
        cityInput.value = commune.nom;
        postalInput.value = codePostal;
        hideSuggestions();
      });
      suggestionBox.appendChild(item);
    });

    // Positionner en dessous de l'input
    const rect = cityInput.getBoundingClientRect();
    suggestionBox.style.left = rect.left + window.scrollX + "px";
    suggestionBox.style.top = rect.bottom + window.scrollY + "px";

    document.body.appendChild(suggestionBox);
  }

  function hideSuggestions() {
    if (suggestionBox) {
      document.body.removeChild(suggestionBox);
      suggestionBox = null;
    }
  }

  document.addEventListener("click", function(e) {
    if (suggestionBox && !suggestionBox.contains(e.target) && e.target !== cityInput) {
      hideSuggestions();
    }
  });
}