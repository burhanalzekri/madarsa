import React from 'react'

type Props = { children: React.ReactNode }
type State = { failed: boolean; message: string }

/** Prevent a portal rendering exception from leaving the whole application blank. */
export default class AppErrorBoundary extends React.Component<Props, State> {
  state: State = { failed: false, message: '' }

  static getDerivedStateFromError(error: unknown): State {
    return {
      failed: true,
      message: error instanceof Error ? error.message : 'حدث خطأ غير متوقع أثناء عرض الصفحة.',
    }
  }

  componentDidCatch(error: unknown, info: React.ErrorInfo) {
    console.error('[MADRASA UI ERROR]', error, info.componentStack)
  }

  private recover = () => {
    this.setState({ failed: false, message: '' })
    window.location.reload()
  }

  private signOut = () => {
    try { localStorage.removeItem('madrasa-session') } catch { /* storage may be unavailable */ }
    window.location.href = '/'
  }

  render() {
    if (!this.state.failed) return this.props.children
    return (
      <main dir="rtl" style={{minHeight:'100vh',display:'grid',placeItems:'center',padding:24,background:'#f4f7f5',fontFamily:'Tahoma,Arial,sans-serif'}}>
        <section style={{width:'min(100%,560px)',background:'#fff',border:'1px solid #e3eae6',borderRadius:20,padding:28,boxShadow:'0 18px 50px #123d3514',textAlign:'center'}}>
          <div style={{width:58,height:58,margin:'0 auto 16px',borderRadius:18,display:'grid',placeItems:'center',background:'#fff1ed',color:'#b9472f',fontSize:28}}>!</div>
          <h1 style={{margin:'0 0 10px',color:'#173f35',fontSize:23}}>تعذر عرض بوابتك التعليمية</h1>
          <p style={{margin:'0 0 12px',color:'#65746e',lineHeight:1.9}}>حدث خطأ أثناء تحميل إحدى واجهات المنصة. لم تُحذف بيانات حسابك. أعد تحميل الصفحة، وإذا استمرت المشكلة سجّل الخروج ثم أعد الدخول.</p>
          <details style={{margin:'12px 0 20px',textAlign:'right',color:'#8b4b3b'}}><summary style={{cursor:'pointer'}}>تفاصيل الخطأ للمساعدة في التشخيص</summary><pre style={{whiteSpace:'pre-wrap',overflowWrap:'anywhere',fontSize:12,background:'#fff8f5',padding:12,borderRadius:10}}>{this.state.message}</pre></details>
          <div style={{display:'flex',gap:10,justifyContent:'center',flexWrap:'wrap'}}>
            <button onClick={this.recover} style={{border:0,borderRadius:11,padding:'12px 20px',background:'#14584b',color:'#fff',fontWeight:700,cursor:'pointer'}}>إعادة تحميل الصفحة</button>
            <button onClick={this.signOut} style={{border:'1px solid #d7e1dc',borderRadius:11,padding:'12px 20px',background:'#fff',color:'#34564c',fontWeight:700,cursor:'pointer'}}>تسجيل الخروج</button>
          </div>
        </section>
      </main>
    )
  }
}
