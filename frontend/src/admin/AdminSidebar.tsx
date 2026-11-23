import { GraduationCap, LogOut, School, Sparkles, X } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

type NavItem = { id: string; label: string; icon: LucideIcon; section: string; key?: string }
type User = { name: string; email: string }
type Props = {
  schoolName: string
  platformName: string
  user: User
  nav: NavItem[]
  page: string
  mobileOpen: boolean
  counts: Record<string, number>
  onNavigate: (id: string) => void
  onCloseMobile: () => void
  onLogout: () => void
}

const sections = ['الرئيسية', 'شؤون المدرسة', 'الإدارة الأكاديمية', 'التعليم', 'المتابعة', 'التواصل', 'النظام']

export default function AdminSidebar({ schoolName, platformName, user, nav, page, mobileOpen, counts, onNavigate, onCloseMobile, onLogout }: Props) {
  return <aside className={`sidebar ${mobileOpen ? 'sidebar-open' : ''}`} aria-label="القائمة الرئيسية">
    <div className="brand"><div className="brand-mark"><GraduationCap size={25}/></div><div><b>{platformName || 'مدرستي'}</b><small>منصة التعليم الذكي</small></div><button className="mobile-close" onClick={onCloseMobile} aria-label="إغلاق القائمة"><X/></button></div>
    <div className="school-card"><div className="school-avatar"><School size={22}/></div><div><b>{schoolName}</b><small>لوحة إدارة المدرسة</small></div><span className="online-dot"/></div>
    <nav className="nav-scroll">{sections.map(section => <div className="nav-group" key={section}><div className="nav-caption">{section}</div>{nav.filter(item => item.section === section).map(item => <button key={item.id} className={`nav-link ${page === item.id ? 'selected' : ''}`} aria-current={page === item.id ? 'page' : undefined} onClick={() => onNavigate(item.id)}><item.icon size={18}/><span>{item.label}</span>{item.key && <em>{counts[item.key] ?? 0}</em>}</button>)}</div>)}</nav>
    <div className="sidebar-footer"><div className="help-card"><Sparkles size={18}/><b>مساحة تعليمية متكاملة</b><p>كل أدوات المدرسة في مكان واحد.</p></div><button className="admin-profile auth-logout" onClick={onLogout} title="تسجيل الخروج"><div className="admin-avatar">{user.name?.slice(0,1) || 'م'}</div><div><b>{user.name || 'مدير المدرسة'}</b><small>مدير المدرسة • {user.email}</small><span className="logout-visible-label"><LogOut size={13}/> تسجيل الخروج</span></div><LogOut size={18}/></button></div>
  </aside>
}
