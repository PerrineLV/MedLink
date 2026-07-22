import { forwardRef, useEffect, useId, useRef, useState } from 'react';
import './PatientAutocomplete.css';

function patientLabel(patient) {
  return `${patient.firstName} ${patient.lastName}`;
}

// Combobox patient : liste déroulante complète au focus (query vide), filtrée
// au fil de la saisie (autosuggestion) — mêmes rôles ARIA et navigation
// clavier que MedicationAutocomplete, mais filtrage local (la liste des
// patients rattachés est déjà chargée en mémoire, pas de recherche serveur).
const PatientAutocomplete = forwardRef(function PatientAutocomplete(
  { patients, value, onChange, onSelectPatient, placeholder, required = false, ariaLabel },
  forwardedRef,
) {
  const [isOpen, setIsOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const containerRef = useRef(null);
  const listboxId = useId();

  const query = value.trim().toLowerCase();
  const suggestions =
    query === ''
      ? patients
      : patients.filter((patient) => patientLabel(patient).toLowerCase().includes(query));

  useEffect(() => {
    function handleClickOutside(event) {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const selectSuggestion = (patient) => {
    onChange(patientLabel(patient));
    onSelectPatient(patient);
    setIsOpen(false);
    setActiveIndex(-1);
  };

  const handleKeyDown = (event) => {
    if (!isOpen || suggestions.length === 0) {
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
      setIsOpen(false);
    }
  };

  const activeOptionId = activeIndex >= 0 ? `${listboxId}-option-${activeIndex}` : undefined;

  return (
    <div className="patient-autocomplete" ref={containerRef}>
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
        placeholder={placeholder}
        value={value}
        onChange={(event) => {
          onChange(event.target.value);
          setIsOpen(true);
        }}
        onKeyDown={handleKeyDown}
        onFocus={() => patients.length > 0 && setIsOpen(true)}
        required={required}
      />

      {isOpen && suggestions.length > 0 && (
        <ul className="patient-autocomplete-list" role="listbox" id={listboxId}>
          {suggestions.map((patient, index) => (
            <li
              key={patient.id}
              id={`${listboxId}-option-${index}`}
              role="option"
              aria-selected={index === activeIndex}
              className={index === activeIndex ? 'active' : undefined}
              onMouseDown={(event) => {
                event.preventDefault();
                selectSuggestion(patient);
              }}
            >
              {patientLabel(patient)}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
});

export default PatientAutocomplete;
