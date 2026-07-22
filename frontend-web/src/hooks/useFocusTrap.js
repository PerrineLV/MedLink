import { useEffect } from 'react';

const FOCUSABLE_SELECTOR =
  'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

// Focus initial + piège du Tab pour les boîtes de dialogue custom
// (role="alertdialog") : sans ça, un utilisateur clavier peut tabuler vers le
// contenu derrière une boîte censée être modale.
//
// isActive doit être dans les dépendances de l'effet : certains appelants
// (ex. SessionExpiryWarning) gardent la même instance de composant et
// basculent juste un `return null`, donc la ref ne change jamais d'identité
// — sans isActive en dépendance, l'effet ne se redéclencherait jamais à
// l'ouverture.
export function useFocusTrap(containerRef, isActive = true) {
  useEffect(() => {
    const container = containerRef.current;
    if (!isActive || !container) return undefined;

    const focusable = Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR));
    focusable[0]?.focus();

    const handleKeyDown = (event) => {
      if (event.key !== 'Tab' || focusable.length === 0) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    container.addEventListener('keydown', handleKeyDown);
    return () => container.removeEventListener('keydown', handleKeyDown);
  }, [containerRef, isActive]);
}
