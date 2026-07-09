import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './index.css'
import App from './App.jsx'
import { AuthProvider } from './contexts/AuthContext.jsx'
import { InvitationsBadgeProvider } from './contexts/InvitationsBadgeContext.jsx'
import { MessagesBadgeProvider } from './contexts/MessagesBadgeContext.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <InvitationsBadgeProvider>
          <MessagesBadgeProvider>
            <App />
          </MessagesBadgeProvider>
        </InvitationsBadgeProvider>
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>,
)
