/**
 * Script frontend de la page d'inscription Account.
 *
 * Ce fichier gère:
 * - le formulaire d'inscription (email + mot de passe),
 * - l'appel à POST /api/accounts,
 * - la redirection vers la page de connexion en cas de succès.
 */
const accountRegisterForm = document.getElementById("account-register-form");
const accountRegisterFeedbackElement = document.getElementById("account-register-feedback");

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
 * Appelle l'API backend pour créer un nouvel Account.
 *
 * @param {string} email Email du nouvel Account.
 * @param {string} password Mot de passe du nouvel Account.
 * @returns {Promise<Object>} Réponse JSON du backend.
 */
async function registerAccount(email, password) {
  return postJson("/api/accounts", { email, password }, "création Account");
}

if (accountRegisterForm) {
  accountRegisterForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(accountRegisterForm);
    const email = String(formData.get("newAccountEmail") || "").trim();
    const password = String(formData.get("newAccountPassword") || "");

    if (!email || !password) {
      setFeedback(
        accountRegisterFeedbackElement,
        "L'email et le mot de passe sont obligatoires.",
        true
      );
      return;
    }

    setFeedback(accountRegisterFeedbackElement, "Création en cours...", false);

    try {
      await registerAccount(email, password);
      setFeedback(
        accountRegisterFeedbackElement,
        "Account créé avec succès. Tu peux maintenant te connecter.",
        false
      );
      accountRegisterForm.reset();
    } catch (error) {
      const message = error instanceof Error ? error.message : "Erreur inconnue.";
      setFeedback(accountRegisterFeedbackElement, message, true);
    }
  });
}
