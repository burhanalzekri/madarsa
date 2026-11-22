import { useEffect, useState, type FormEvent } from 'react'
import { BellRing, Send, RefreshCw, Users, UserRound } from 'lucide-react'
import { api } from './api'

type Classroom = { classroom_id:number; classroom:string; subject:string; grade_subject_id:number }
type Notice = { id:number; title:string; body:string; category:string; classroom_id:number; target_roles:string[]|string|null; is_published:boolean; published_at:string|null; expires_at:string|null }

export default function TeacherCommunicationPanel({token,classrooms,onMessage,onError}:{token:string;classrooms:Classroom[];onMessage:(s:string)=>void;onError:(s:string)=>void}) {
 const [items,setItems]=useState<Notice[]>([]),[classroomId,setClassroomId]=useState(''),[title,setTitle]=useState(''),[body,setBody]=useState(''),[category,setCategory]=useState('تنبيه'),[audience,setAudience]=useState<string[]>(['student','parent']),[expiresAt,setExpiresAt]=useState(''),[published,setPublished]=useState(true),[busy,setBusy]=useState(false)
 async function refresh(){try{const r=await api<{announcements:Notice[]}>('/teacher/announcements',token);setItems(r.announcements||[])}catch(e){onError(e instanceof Error?e.message:'تعذر تحميل الإعلانات')}}
 useEffect(()=>{if(!classroomId&&classrooms[0])setClassroomId(String(classrooms[0].classroom_id))},[classrooms,classroomId])
 useEffect(()=>{void refresh()},[token])
 function toggleAudience(role:string){setAudience(old=>old.includes(role)?old.filter(x=>x!==role):[...old,role])}
 async function submit(e:FormEvent){e.preventDefault();if(!audience.length){onError('حدد جهة واحدة على الأقل.');return}setBusy(true);try{await api('/teacher/announcements',token,{method:'POST',body:JSON.stringify({classroom_id:Number(classroomId),title,body,category,target_roles:audience,is_published:published,expires_at:expiresAt||null})});setTitle('');setBody('');setExpiresAt('');onMessage(published?'تم نشر الإعلان للشعبة المحددة.':'تم حفظ الإعلان كمسودة.');await refresh()}catch(e){onError(e instanceof Error?e.message:'تعذر إرسال الإعلان')}finally{setBusy(false)}}
 return <section className="role-panel teacher-communication"><div className="portal-section-head"><div><h2>التواصل والإعلانات</h2><p>أرسل تنبيهات تعليمية لطلاب الشعب المسندة إليك أو لأولياء أمورهم.</p></div><BellRing size={23}/></div>
  <form className="teacher-form-grid" onSubmit={submit}>
   <label>الشعبة<select value={classroomId} onChange={e=>setClassroomId(e.target.value)} required>{classrooms.map(c=><option key={`${c.classroom_id}-${c.grade_subject_id}`} value={c.classroom_id}>{c.classroom} — {c.subject}</option>)}</select></label>
   <label>تصنيف الإعلان<select value={category} onChange={e=>setCategory(e.target.value)}><option>تنبيه</option><option>واجب</option><option>اختبار</option><option>تذكير</option><option>نشاط</option><option>إعلان المعلم</option></select></label>
   <label>عنوان الإعلان<input value={title} onChange={e=>setTitle(e.target.value)} maxLength={190} required placeholder="مثال: تذكير بموعد تسليم الواجب"/></label>
   <label>انتهاء الإعلان (اختياري)<input type="datetime-local" value={expiresAt} onChange={e=>setExpiresAt(e.target.value)}/></label>
   <label className="teacher-notice-body">نص الإعلان<textarea value={body} onChange={e=>setBody(e.target.value)} rows={4} maxLength={10000} required placeholder="اكتب تفاصيل الإعلان بوضوح..."/></label>
   <div className="teacher-audience"><b>إرسال إلى</b><label><input type="checkbox" checked={audience.includes('student')} onChange={()=>toggleAudience('student')}/><UserRound size={16}/> الطلاب</label><label><input type="checkbox" checked={audience.includes('parent')} onChange={()=>toggleAudience('parent')}/><Users size={16}/> أولياء الأمور</label></div>
   <label className="teacher-check"><input type="checkbox" checked={published} onChange={e=>setPublished(e.target.checked)}/> نشر الإعلان فوراً</label>
   <button className="auth-submit" disabled={busy||!classroomId||!classrooms.length}><Send size={16}/>{busy?'جارٍ الحفظ...':published?'نشر الإعلان':'حفظ كمسودة'}</button>
  </form>
  <div className="teacher-notice-list-head"><h3>إعلاناتي السابقة</h3><button className="btn-light" onClick={refresh} disabled={busy}><RefreshCw size={15}/> تحديث</button></div>
  {items.length?items.map(n=><article className="teacher-notice-card" key={n.id}><div><b>{n.title}</b><span className={n.is_published?'published-pill':'draft-pill'}>{n.is_published?'منشور':'مسودة'}</span></div><small>{n.category} • {classrooms.find(c=>c.classroom_id===n.classroom_id)?.classroom||'الشعبة'} • {(Array.isArray(n.target_roles)?n.target_roles:typeof n.target_roles==='string'?JSON.parse(n.target_roles||'[]'):[]).map(x=>x==='student'?'الطلاب':'أولياء الأمور').join('، ')}</small><p>{n.body}</p><small>{n.published_at?new Date(n.published_at).toLocaleString('ar'):'لم ينشر بعد'}</small></article>):<p className="teacher-empty-notices">لا توجد إعلانات بعد. أنشئ إعلانك الأول من النموذج أعلاه.</p>}
 </section>
}
