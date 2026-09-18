// SONDE ML-175 — À SUPPRIMER, NE JAMAIS MERGER.
//
// Vulnérabilité XSS volontaire, destinée à vérifier que la règle de blocage
// sur résultats de code scanning empêche réellement un merge. Le flux est
// celui que CodeQL reconnaît : une donnée contrôlée par l'utilisateur
// (`location.hash`) atteint un sink de rendu HTML sans assainissement.
//
// Ce composant n'est importé nulle part et n'entre donc dans aucun bundle.
export default function ProbeCodeScanning() {
  const payload = window.location.hash.slice(1);

  return <div dangerouslySetInnerHTML={{ __html: payload }} />;
}
