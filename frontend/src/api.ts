export const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '')
export type ApiUser = { id:number; name:string; email:string; role:string; student_profile?:unknown; teacher_profile?:unknown }
export async function api<T>(path:string, token?:string, init:RequestInit={}):Promise<T>{
 let response: Response
 try {
  response = await fetch(`${API_BASE}${path}`,{...init,headers:{Accept:'application/json',...(init.body instanceof FormData?{}:{'Content-Type':'application/json'}),...(token?{Authorization:`Bearer ${token}`} : {}),...(init.headers||{})}})
 } catch (error) {
  if (error instanceof TypeError) throw new Error('تعذر الاتصال بخادم MADRASA. تأكد من تشغيل Laravel على المنفذ 8000 وواجهة Vite على 5173، ثم حدّث الصفحة. إذا استمرت المشكلة افتح تعليمات التشغيل المرفقة.')
  throw error
 }
 const data=await response.json().catch(()=>({message:'تعذر قراءة استجابة الخادم'}))
 if(!response.ok){if(response.status===401) throw new Error('انتهت الجلسة أو بيانات الدخول غير صحيحة.'); const errors=data.errors?Object.values(data.errors).flat().join(' '):'';throw new Error(errors||data.message||`خطأ HTTP ${response.status}`)}
 return data as T
}
export async function checkApiHealth():Promise<boolean>{
 const base=API_BASE.replace(/\/api\/v1$/, '')
 try { const r=await fetch(`${base}/api/health`,{headers:{Accept:'application/json'}}); return r.ok } catch { return false }
}
