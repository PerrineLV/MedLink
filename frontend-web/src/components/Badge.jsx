import './Badge.css'

export default function Badge({ level, label }) {
  return <span className={`badge badge-${level}`}>{label}</span>
}
