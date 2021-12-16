<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function store(Request $request){
        $request->validate([
            'name' => 'required',
            'no_kad' => 'required',
            'phone' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        $checkNoKad = User::where('no_kad', $request->no_kad)->first();
        $checkEmail = User::where('email', $request->email)->first();
        $checkMaster = Role::find($request->role_id)->first();
        if(!$checkNoKad){
            if(!$checkEmail){
                if(!Auth::user()->role->name == 'master' && $checkMaster == 'master'){
                    return back()->with('error', 'Cant create master user');
                }
                $request['password'] = Hash::make($request->password);
                User::create($request->all());
                return back()->with('success', 'Successfully added user');
            }
            return back()->with('error', 'Email already user');
        }else{
            return back()->with('error', 'No Kad already used');
        }
    }

    public function destroy($id){
        User::findOrFail($id)->delete();
        return back()->with('success', 'User deleted successfully');
    }
}
