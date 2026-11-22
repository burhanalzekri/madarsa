import React from 'react'
import ReactDOM from 'react-dom/client'
import AuthApp from './AuthApp'
import AppErrorBoundary from './AppErrorBoundary'

const root = document.getElementById('root')
if (!root) throw new Error('لم يتم العثور على عنصر تشغيل المنصة #root')

ReactDOM.createRoot(root).render(
  <React.StrictMode>
    <AppErrorBoundary>
      <AuthApp />
    </AppErrorBoundary>
  </React.StrictMode>,
)
