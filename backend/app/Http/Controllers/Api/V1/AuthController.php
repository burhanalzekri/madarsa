<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
 public function login(Request $request) {
  $data=$request->validate(['login'=>['required','string','max:190'],'password'=>['required','string','max:200'],'device_name'=>['nullable','string','max:100']]);
  $user=User::query()->where('email',$data['login'])->orWhere('phone',$data['login'])->first();
  if(!$user || !$user->is_active || !Hash::check($data['password'],$user->password)) throw ValidationException::withMessages(['login'=>['بيانات الدخول غير صحيحة أو الحساب موقوف.']]);
  $token=$user->createToken($data['device_name']??'madrasa-client')->plainTextToken;
  return response()->json(['token'=>$token,'token_type'=>'Bearer','user'=>$user],200);
 }
 public function me(Request $request){return response()->json(['user'=>$request->user()->load(['studentProfile','teacherProfile'])]);}
 public function logout(Request $request){$request->user()->currentAccessToken()?->delete();return response()->json(['message'=>'تم تسجيل الخروج بنجاح.']);}
}
