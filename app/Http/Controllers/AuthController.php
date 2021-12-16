<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function index(){
        if(Auth::check()){
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request){
        $credentials = [
            'no_kad' => $request->no_kad,
            'password' => $request->password,
        ];
        if(Auth::attempt($credentials)){
            return redirect()->route('dashboard');
        }
        return back()->with('error', 'Wrong username or password');
    }
    
    public function logout(){
        Auth::logout();
        return redirect('/');
    }
}
