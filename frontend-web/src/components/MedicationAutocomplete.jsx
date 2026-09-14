import { forwardRef, useEffect, useId, useRef, useState } from 'react';
import { searchMedications } from '../services/medicationService';
import './MedicationAutocomplete.css';

const DEBOUNCE_MS = 300;
const MIN_QUERY_LENGTH = 2;

const MedicationAutocomplete = forwardRef(function MedicationAutocomplete(
  { value, onChange, onSelectMedication, required = false, ariaLabel },
  forwardedRef,
) {
  // ML-168 : la liste affichée et son ouverture se déduisent de la saisie
  // courante, elles ne sont donc pas stockées telles quelles. L'état ne retient
  // que le résultat réseau *et la requête à laquelle il répond* : dès que la
  // saisie change, ce résultat cesse mécaniquement de correspondre, sans qu'un
  // effet ait à venir le remettre à zéro.
  const [results, setResults] = useState({ query: '', items: [] });
  const [isDismissed, setIsDismissed] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const containerRef = useRef(null);
  const listboxId = useId();

  const query = value.trim();
  const suggestions = results.query === query ? results.items : [];
  const isOpen = !isDismissed && suggestions.length > 0;

  useEffect(() => {
    // Saisie trop courte : on ne déclenche pas de recherche. Rien à réinitialiser
    // ici — `suggestions` ci-dessus est déjà vide puisque `results.query` ne
    // correspond plus.
    if (query.length < MIN_QUERY_LENGTH) {
      return undefined;
    }

    let cancelled = false;

    const timeoutId = setTimeout(async () => {
      try {
        const items = await searchMedications(query);
        if (!cancelled) {
          setResults({ query, items });
        }
      } catch {
        // La requête est mémorisée même en échec : sans ça, `results.query` ne
        // correspondrait jamais et une saisie identique relancerait la recherche
        // en boucle.
        if (!cancelled) {
          setResults({ query, items: [] });
        }
      }
    }, DEBOUNCE_MS);

    return () => {
      cancelled = true;
      clearTimeout(timeoutId);
    };
  }, [query]);

  useEffect(() => {
    function handleClickOutside(event) {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsDismissed(true);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleInputChange = (event) => {
    onChange(event.target.value);
    // Toute frappe rouvre la liste et annule la sélection clavier en cours.
    setIsDismissed(false);
    setActiveIndex(-1);
  };

  const selectSuggestion = (suggestion) => {
    onChange(suggestion.name);
    onSelectMedication?.(suggestion);
    setIsDismissed(true);
    setActiveIndex(-1);
  };

  const handleKeyDown = (event) => {
    if (!isOpen) {
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActiveIndex((current) => (current + 1) % suggestions.length);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActiveIndex((current) => (current <= 0 ? suggestions.length - 1 : current - 1));
    } else if (event.key === 'Enter') {
      if (activeIndex >= 0) {
        event.preventDefault();
        selectSuggestion(suggestions[activeIndex]);
      }
    } else if (event.key === 'Escape') {
      setIsDismissed(true);
    }
  };

  const activeOptionId = activeIndex >= 0 ? `${listboxId}-option-${activeIndex}` : undefined;

  return (
    <div className="medication-autocomplete" ref={containerRef}>
      <input
        ref={forwardedRef}
        type="text"
        role="combobox"
        aria-expanded={isOpen}
        aria-autocomplete="list"
        aria-controls={listboxId}
        aria-activedescendant={activeOptionId}
        aria-label={ariaLabel}
        autoComplete="off"
        value={value}
        onChange={handleInputChange}
        onKeyDown={handleKeyDown}
        onFocus={() => setIsDismissed(false)}
        required={required}
      />

      {isOpen && (
        <ul className="medication-autocomplete-list" role="listbox" id={listboxId}>
          {suggestions.map((suggestion, index) => (
            <li
              key={suggestion.name}
              id={`${listboxId}-option-${index}`}
              role="option"
              aria-selected={index === activeIndex}
              className={index === activeIndex ? 'active' : undefined}
              onMouseDown={(event) => {
                event.preventDefault();
                selectSuggestion(suggestion);
              }}
            >
              {suggestion.name}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
});

export default MedicationAutocomplete;
