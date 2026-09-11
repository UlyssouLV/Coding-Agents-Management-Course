/**
 * Script frontend de la page de connexion Account.
 *
 * Ce fichier gère:
 * - le formulaire de connexion (email + mot de passe),
 * - l'appel à POST /api/accounts/login,
 * - la redirection vers l'espace client en cas de succès.
 */
const accountLoginForm = document.getElementById("account-login-form");
const accountLoginFeedbackElement = document.getElementById("account-login-feedback");

/**
 * Affiche un message de retour utilisateur dans la page.
 *
 * @param {HTMLElement|null} targetElement Element de destination du feedback.
 * @param {string} message Texte à afficher.
 * @param {boolean} isError Indique si le message est une erreur.
 */
function setFeedback(targetElement, message, isError) {
  if (!targetElement) {
    return;
  }

  targetElement.textContent = message;
  targetElement.style.color = isError ? "crimson" : "green";
}

/**
 * Appelle une route API JSON et centralise la gestion des erreurs HTTP.
 *
 * @param {string} endpoint Route API cible.
 * @param {Object} payload Données JSON envoyées.
 * @param {string} actionLabel Libellé de l'action pour les messages d'erreur.
 * @returns {Promise<Object>} Réponse JSON du backend.
 * @throws {Error} Si la réponse HTTP n'est pas en succès.
 */
async function postJson(endpoint, payload, actionLabel) {
  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });

  const rawResponse = await response.text();
  let data = {};
  try {
    data = rawResponse ? JSON.parse(rawResponse) : {};
  } catch (_parseError) {
    data = {};
  }

  if (!response.ok) {
    const backendMessage = data.error || "";
    const fallbackMessage = rawResponse ? rawResponse.slice(0, 180) : "";
    const details = backendMessage || fallbackMessage || "Aucun détail serveur.";
    throw new Error(
      `Erreur ${actionLabel} (${response.status} ${response.statusText}) - ${details}`
    );
  }

  return data;
}

/**
 * Appelle l'API backend pour connecter un Account existant.
 *
 * @param {string} email Email de l'Account.
 * @param {string} password Mot de passe de l'Account.
 * @returns {Promise<Object>} Réponse JSON du backend.
 */
async function loginAccount(email, password) {
  return postJson("/api/accounts/login", { email, password }, "connexion Account");
}

if (accountLoginForm) {
  accountLoginForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(accountLoginForm);
    const email = String(formData.get("accountEmail") || "").trim();
    const password = String(formData.get("accountPassword") || "");

    if (!email || !password) {
      setFeedback(
        accountLoginFeedbackElement,
        "L'email et le mot de passe sont obligatoires.",
        true
      );
      return;
    }

    setFeedback(accountLoginFeedbackElement, "Connexion en cours...", false);

    try {
      await loginAccount(email, password);
      setFeedback(accountLoginFeedbackElement, "Connexion réussie.", false);
      window.location.href = new URL("client-area.html", window.location.href).toString();
    } catch (error) {
      const message = error instanceof Error ? error.message : "Erreur inconnue.";
      setFeedback(accountLoginFeedbackElement, message, true);
    }
  });
}
