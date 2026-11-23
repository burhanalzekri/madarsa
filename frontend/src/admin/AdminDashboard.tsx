import {
  ArrowLeft, Bell, CalendarDays, ClipboardCheck, ClipboardList,
  GraduationCap, Layers3, School, ShieldCheck, Sparkles, Users,
} from 'lucide-react'

type ReportData = {
  students?: number
  teachers?: number
  classrooms?: number
  assignments?: number
  exams?: number
  average_exam_score?: number
  attendance_total?: number
  attendance_summary?: Record<string, number>
  period?: { from?: string; to?: string }
}

type Props = {
  userName: string
  schoolName: string
  schoolYear: string
  reportData: ReportData | null
  reportError: string
  onRefresh: () => void
  onNavigate: (page: string) => void
}

function PanelHeading({ title, description }: { title: string; description: string }) {
  return <div className="panel-heading"><div><h3>{title}</h3><p>{description}</p></div></div>
}

export default function AdminDashboard({ userName, schoolName, schoolYear, reportData, reportError, onRefresh, onNavigate }: Props) {
  const attendance = [
    { key: 'present', label: 'حاضر' },
    { key: 'absent', label: 'غائب' },
    { key: 'late', label: 'متأخر' },
    { key: 'excused', label: 'بعذر' },
  ]
  const total = reportData?.attendance_total ?? 0
  const shortcuts = [
    { id: 'students', title: 'إدارة الطلاب', description: 'الملفات والتسجيلات', Icon: Users },
    { id: 'teachers', title: 'إدارة المعلمين', description: 'الكادر والتخصصات', Icon: GraduationCap },
    { id: 'academic-structure', title: 'الهيكل الأكاديمي', description: 'المراحل والصفوف والمواد', Icon: Layers3 },
    { id: 'enrollments', title: 'التسجيلات والنقل', description: 'تسجيل الطلاب ونقلهم', Icon: ClipboardList },
    { id: 'lessons', title: 'المناهج والدروس', description: 'المحتوى التعليمي', Icon: School },
    { id: 'announcements', title: 'الإعلانات', description: 'التواصل مع مجتمع المدرسة', Icon: Bell },
  ]

  return <>
    <section className="admin-welcome">
      <div><span className="admin-welcome-kicker"><Sparkles size={15}/> مركز قيادة المدرسة</span><h1>مرحباً، {userName}</h1><p>نظرة موحدة على مؤشرات المدرسة المسجلة في قاعدة البيانات.</p></div>
      <div className="admin-welcome-meta"><span><School size={17}/>{schoolName}</span><span><CalendarDays size={17}/>{schoolYear}</span><button className="admin-refresh" onClick={onRefresh}><ArrowLeft size={15}/> تحديث المؤشرات</button></div>
    </section>
    {reportError && <div className="admin-api-warning admin-error" role="alert"><ShieldCheck size={17}/>{reportError}</div>}
    <div className="admin-kpi-grid" aria-live="polite">
      <article className="admin-kpi"><span className="admin-kpi-icon"><Users size={20}/></span><small>الطلاب النشطون</small><strong>{reportData ? reportData.students ?? 0 : '—'}</strong><em>من قاعدة البيانات</em></article>
      <article className="admin-kpi"><span className="admin-kpi-icon"><GraduationCap size={20}/></span><small>المعلمون النشطون</small><strong>{reportData ? reportData.teachers ?? 0 : '—'}</strong><em>من قاعدة البيانات</em></article>
      <article className="admin-kpi"><span className="admin-kpi-icon"><School size={20}/></span><small>الشعب النشطة</small><strong>{reportData ? reportData.classrooms ?? 0 : '—'}</strong><em>من قاعدة البيانات</em></article>
      <article className="admin-kpi"><span className="admin-kpi-icon"><ClipboardCheck size={20}/></span><small>الواجبات المسجلة</small><strong>{reportData ? reportData.assignments ?? 0 : '—'}</strong><em>من قاعدة البيانات</em></article>
    </div>
    <div className="admin-dashboard-grid">
      <section className="panel admin-attendance-panel">
        <PanelHeading title="ملخص الحضور" description={reportData?.period ? `الفترة من ${reportData.period.from ?? '—'} إلى ${reportData.period.to ?? '—'}` : 'إحصائيات الشهر الحالي من قاعدة البيانات'}/>
        {reportData ? <>{attendance.map(item => {
          const value = reportData.attendance_summary?.[item.key] ?? 0
          return <div className="admin-attendance-row" key={item.key}><span>{item.label}</span><div className="admin-attendance-track"><i style={{ width: `${total ? Math.min(100, value / total * 100) : 0}%` }}/></div><b>{value}</b></div>
        })}<div className="admin-attendance-total"><span>إجمالي سجلات الحضور</span><strong>{total}</strong></div></> : <div className="admin-loading">جارٍ تحميل مؤشرات الحضور...</div>}
      </section>
      <section className="panel admin-shortcuts-panel"><PanelHeading title="الوصول السريع" description="انتقل مباشرة إلى المهام الإدارية الأساسية"/><div className="admin-shortcuts">
        {shortcuts.map(({ id, title, description, Icon }) => <button key={id} onClick={() => onNavigate(id)}><span><Icon size={18}/></span><div><b>{title}</b><small>{description}</small></div><ArrowLeft size={16}/></button>)}
      </div></section>
    </div>
    <section className="panel admin-summary-panel"><PanelHeading title="مؤشرات المتابعة" description="أرقام إضافية لمتابعة سير العملية التعليمية"/><div className="admin-summary-grid"><div><span>الاختبارات</span><b>{reportData?.exams ?? '—'}</b></div><div><span>متوسط درجات الاختبارات</span><b>{reportData?.average_exam_score ?? '—'}</b></div><div><span>إجمالي سجلات الحضور</span><b>{reportData?.attendance_total ?? '—'}</b></div><div><span>العام الدراسي</span><b>{schoolYear}</b></div></div></section>
  </>
}
