<?php

namespace App\Http\Controllers;

use App\Mail\RegisterMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function index(){
        if(Auth::check()){
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request){
        $request->validate([
            'no_kad' => 'required',
            'password' => 'required',
        ]);
        
        $credentials = [
            'no_kad' => $request->no_kad,
            'password' => $request->password,
        ];
        dump($credentials);
        if(Auth::attempt($credentials)){
            dd('login');
            // if(Auth::user()->approved == 0){
            //     Auth::logout();
            //     return redirect()->route('login')->with('error', 'Akaun masih tidak aktif, masih menunggu kelulusan');
            // }
            // return redirect()->route('dashboard');
        }
        dd('gagal');
        // return back()->with('error', 'Wrong username or password');
    }

    public function register(){
        return view('auth.register');
    }

    public function registerStore(Request $request){
        $request->validate([
            'name' => 'required',
            'no_kad' => 'required|unique:users',
            'phone' => 'required|numeric',
            'email' => 'email|unique:users',
            'password' => 'required|confirmed',
        ]);
        $request['role_id'] = 3;
        $request['password'] = Hash::make($request->password);
        $user = User::create($request->all());

        if($request->email){
            Mail::to($user->email)->send(new RegisterMail($user));
        }

        return back()->with('success', 'Successfully registered account');
    }
    
    public function logout(){
        Auth::logout();
        return redirect('/');
    }
}
