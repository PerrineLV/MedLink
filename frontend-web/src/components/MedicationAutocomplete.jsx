import { forwardRef, useEffect, useId, useRef, useState } from 'react'
import { searchMedications } from '../services/medicationService'
import './MedicationAutocomplete.css'

const DEBOUNCE_MS = 300
const MIN_QUERY_LENGTH = 2

const MedicationAutocomplete = forwardRef(function MedicationAutocomplete(
  { value, onChange, onSelectMedication, required = false },
  forwardedRef,
) {
  const [suggestions, setSuggestions] = useState([])
  const [isOpen, setIsOpen] = useState(false)
  const [activeIndex, setActiveIndex] = useState(-1)
  const containerRef = useRef(null)
  const listboxId = useId()

  useEffect(() => {
    const query = value.trim()
    if (query.length < MIN_QUERY_LENGTH) {
      setSuggestions([])
      setIsOpen(false)
      return undefined
    }

    let cancelled = false

    const timeoutId = setTimeout(async () => {
      try {
        const results = await searchMedications(query)
        if (!cancelled) {
          setSuggestions(results)
          setIsOpen(results.length > 0)
          setActiveIndex(-1)
        }
      } catch {
        if (!cancelled) {
          setSuggestions([])
          setIsOpen(false)
        }
      }
    }, DEBOUNCE_MS)

    return () => {
      cancelled = true
      clearTimeout(timeoutId)
    }
  }, [value])

  useEffect(() => {
    function handleClickOutside(event) {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const selectSuggestion = (suggestion) => {
    onChange(suggestion.name)
    onSelectMedication?.(suggestion)
    setIsOpen(false)
    setSuggestions([])
  }

  const handleKeyDown = (event) => {
    if (!isOpen || suggestions.length === 0) {
      return
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActiveIndex((current) => (current + 1) % suggestions.length)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveIndex((current) => (current <= 0 ? suggestions.length - 1 : current - 1))
    } else if (event.key === 'Enter') {
      if (activeIndex >= 0) {
        event.preventDefault()
        selectSuggestion(suggestions[activeIndex])
      }
    } else if (event.key === 'Escape') {
      setIsOpen(false)
    }
  }

  const activeOptionId = activeIndex >= 0 ? `${listboxId}-option-${activeIndex}` : undefined

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
        autoComplete="off"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        onKeyDown={handleKeyDown}
        onFocus={() => suggestions.length > 0 && setIsOpen(true)}
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
                event.preventDefault()
                selectSuggestion(suggestion)
              }}
            >
              {suggestion.name}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
})

export default MedicationAutocomplete
