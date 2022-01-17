<?php

namespace App\Http\Controllers;

use App\Mail\ApprovedMail;
use App\Mail\RegisterMail;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function store(Request $request){
        $request->validate([
            'name' => 'required',
            'no_kad' => 'required|unique:users',
            'phone' => 'required|numeric',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed',
        ]);
        
        $checkMaster = Role::find($request->role_id)->first();
        if(!Auth::user()->role->name == 'superadmin' && $checkMaster == 'superadmin'){
            return back()->with('error', 'Can\'t create superadmin user');
        }

        $request['password'] = Hash::make($request->password);
        $user = User::create($request->all());

        Mail::to($user->email)->send(new RegisterMail($user));

        return back()->with('success', 'Successfully added user');
    }

    public function edit($id){
        $user = User::findOrFail($id);
        $role = Role::where('name', '!=', 'superadmin')->get();
        if(Auth::user()->role_id < $user->role_id){
            return view('pages.user.edit', compact('user', 'role'));
        }elseif(Auth::user()->id == $user->id){
            return redirect()->route('profile');
        }else{
            return back()->with('error', 'You cannot edit this user');
        }
    }

    public function update(Request $request, $id){
        $user = User::findOrFail($id);
        $request->validate([
            'name' => 'required',
            'phone' => 'required|numeric',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id, 'id')],
        ]);
        $user->update($request->all());
        return back()->with('success', 'User updated successfully');
    }

    public function destroy($id){
        $user = User::findOrFail($id);
        if(Auth::user()->role->id < $user->role_id){
            if($user->id != Auth::user()->id){
                $user->delete();
                return back()->with('success', 'User deleted successfully');
            }
        }
        return back()->with('error', 'Can\'t delete this user');
    }

    public function approved($id){
        $user = User::findOrFail($id);
        if($user->approved == 0){
            $user->update([$user->approved = 1]);
            Mail::to($user->email)->send(new ApprovedMail($user));
        }else{
            $user->update([$user->approved = 0]);
        }
    }

    public function resetPassword(Request $request, $id){
        $request->validate([
            'password' => 'required|confirmed',
        ]);
        $user = User::find($id);
        if($user->role->name == 'user'){
            $user->update([$user->password = Hash::make($request->password)]);
            return back()->with('success', 'Password reset successfully');
        }else{
            return back()->with('error', 'Can not reset password this user');
        }
    }

    public function profile(){
        $user = User::find(Auth::user()->id);
        return view('pages.profile', compact('user'));
    }

    public function profileUpdate(Request $request){
        $user = User::find(Auth::user()->id);
        $request->validate([
            'name' => 'required',
            'phone' => 'required|numeric',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id, 'id')],
        ]);
        
        $user->update($request->all());
        return back()->with('success', 'Profile updated successfully');
    }

    public function updatePassword(Request $request){
        $request->validate([
            'password_lama' => 'required',
            'password' => 'required|confirmed',
        ]);
        $user = User::find(Auth::user()->id);
        if(Hash::check($request->password_lama, $user->password)){
            $user->update([$user->password = Hash::make($request->password)]);
            return back()->with('success', 'Password berjaya diupdate');    
        }else{
            return back()->with('error', 'Password lama salah');
        }
    }
}
