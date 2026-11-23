import { useEffect, useState, type ChangeEvent } from 'react'
import { AlertCircle, CheckCircle2, Download, FileSpreadsheet, LoaderCircle, Upload, X } from 'lucide-react'
import { API_BASE, api } from './api'

type Preview = { message:string; total:number; valid:number; invalid:number; rows:{line:number;name:string;student_number:string;errors:string[]}[] }
type Result = { message:string; total:number; created:number; failed:number; errors:{line:number;errors:string[]}[] }
type Classroom = {id:number;name:string;grade?:{name:string};academicYear?:{name:string}}

export default function StudentBulkImport({ token, onClose, onComplete }:{token:string;onClose:()=>void;onComplete:(message:string)=>void}) {
 const [file,setFile]=useState<File|null>(null)
 const [preview,setPreview]=useState<Preview|null>(null)
 const [result,setResult]=useState<Result|null>(null)
 const [busy,setBusy]=useState(false)
 const [error,setError]=useState('')
 const [classrooms,setClassrooms]=useState<Classroom[]>([])
 const [defaultClassroom,setDefaultClassroom]=useState('')
 useEffect(()=>{api<any>('/classrooms?per_page=100',token).then(r=>setClassrooms(r.data||[])).catch(()=>setClassrooms([]))},[token])
 function choose(e:ChangeEvent<HTMLInputElement>){setFile(e.target.files?.[0]||null);setPreview(null);setResult(null);setError('')}
 async function send(dryRun:boolean){if(!file)return;setBusy(true);setError('');try{const body=new FormData();body.append('file',file);body.append('dry_run',dryRun?'1':'0');if(defaultClassroom)body.append('default_classroom_id',defaultClassroom);const response=await fetch(`${API_BASE}/admin/students/import`,{method:'POST',headers:{Accept:'application/json',Authorization:`Bearer ${token}`},body});const data=await response.json().catch(()=>({message:'تعذر قراءة استجابة الخادم'}));if(!response.ok)throw new Error(data.message||Object.values(data.errors||{}).flat().join(' ')||'تعذر معالجة الملف');if(dryRun)setPreview(data as Preview);else{setResult(data as Result);onComplete(data.message||'اكتمل الاستيراد')}}catch(e){setError(e instanceof Error?e.message:'حدث خطأ غير متوقع')}finally{setBusy(false)}}
 async function downloadTemplate(){try{const response=await fetch(`${API_BASE}/admin/students/import-template`,{headers:{Authorization:`Bearer ${token}`}});if(!response.ok)throw new Error('تعذر تنزيل النموذج');const blob=await response.blob();const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='madrasa-students-template.csv';a.click();URL.revokeObjectURL(url)}catch(e){setError(e instanceof Error?e.message:'تعذر تنزيل النموذج')}}
 return <div className="modal-backdrop" onMouseDown={e=>{if(e.target===e.currentTarget&&!busy)onClose()}}><section className="modal-card student-import-modal" role="dialog" aria-modal="true" aria-labelledby="student-import-title">
  <div className="modal-head"><div><span className="eyebrow">استيراد آمن إلى قاعدة البيانات</span><h2 id="student-import-title">استيراد الطلاب من Excel / CSV</h2></div><button type="button" className="icon-quiet" onClick={onClose} disabled={busy} aria-label="إغلاق"><X/></button></div>
  <div className="import-instructions"><FileSpreadsheet size={22}/><div><b>ابدأ بالنموذج الجاهز</b><p>نزّل ملف CSV، أضف بيانات الطلاب، ثم ارفعه للمعاينة قبل الحفظ. يقبل النظام حتى 500 طالب في الدفعة.</p></div><button type="button" className="btn-light" onClick={downloadTemplate}><Download size={16}/> تنزيل النموذج</button></div>
  <label className="import-dropzone"><Upload size={25}/><b>{file?file.name:'اختر ملف CSV من جهازك'}</b><span>الحد الأقصى 2 ميجابايت • CSV بترميز UTF-8</span><input type="file" accept=".csv,text/csv,text/plain" onChange={choose}/></label>
  <label className="form-field import-classroom-select"><span>تعيين شعبة افتراضية للطلاب الذين لم يحددوا شعبة في الملف (اختياري)</span><select value={defaultClassroom} onChange={e=>{setDefaultClassroom(e.target.value);setPreview(null);setResult(null)}}><option value="">بدون شعبة افتراضية</option>{classrooms.map(c=><option key={c.id} value={c.id}>{c.grade?.name||'صف غير محدد'} / {c.name} — {c.academicYear?.name||''} (#{c.id})</option>)}</select></label>
  <div className="import-field-note"><b>الحقول المطلوبة:</b> الاسم وكلمة المرور. البريد الإلكتروني والرقم المدرسي والشعبة وبقية البيانات اختيارية. كلمة المرور الأولية يجب تغييرها بعد أول تسجيل دخول.</div>
  {error&&<div className="import-error"><AlertCircle size={17}/>{error}</div>}
  {preview&&<div className="import-preview"><div className="import-summary"><span><b>{preview.total}</b> إجمالي الصفوف</span><span className="valid"><b>{preview.valid}</b> صالح</span><span className="invalid"><b>{preview.invalid}</b> يحتاج تصحيحاً</span></div><div className="import-rows">{preview.rows.slice(0,12).map(row=><div className="import-row" key={row.line}><span>السطر {row.line}</span><b>{row.name||'اسم غير محدد'}</b>{row.errors.length?<small className="invalid">{row.errors.join('، ')}</small>:<small className="valid">جاهز للاستيراد</small>}</div>)}{preview.rows.length>12&&<small>يتم عرض أول 12 صفاً فقط في المعاينة.</small>}</div></div>}
  {result&&<div className="import-result"><CheckCircle2 size={20}/><b>{result.message}</b><span>تم الحفظ: {result.created} • تعذر: {result.failed}</span>{result.errors.slice(0,10).map(e=><small key={e.line}>السطر {e.line}: {e.errors.join('، ')}</small>)}</div>}
  <div className="modal-actions"><button type="button" className="btn-light" onClick={onClose} disabled={busy}>إغلاق</button>{!result&&<button type="button" className="btn-primary" disabled={!file||busy} onClick={()=>send(!preview)}>{busy?<LoaderCircle className="spin" size={17}/>:preview?<CheckCircle2 size={17}/>:<FileSpreadsheet size={17}/>} {busy?'جارٍ المعالجة...':preview?'تأكيد استيراد السجلات الصالحة':'معاينة الملف'}</button>}</div>
  {preview&&preview.invalid>0&&!result&&<p className="import-caution">سيتم استيراد الصفوف الصالحة فقط، وتجاوز الصفوف التي تحتوي على أخطاء. صحح الملف وأعد المعاينة إذا رغبت في استيراد جميع الطلاب.</p>}
 </section></div>
}
