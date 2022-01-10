<?php

namespace App\Http\Controllers;

use App\Models\MulaCulaan;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class PagesController extends Controller
{
    public function dashboard(){
        $roles = Role::all();
        $culaan = MulaCulaan::all();
        $data = [
            'roles' => $roles,
            'culaan' => $culaan,
        ];
        return view('pages.dashboard', $data);
    }

    public function user(){
        if(Route::is('user-superadmin')){
            $role = Role::where('name', 'superadmin')->first();
            $title = 'User Superadmin';
        }elseif(Route::is('user-admin')){
            $role = Role::where('name', 'admin')->first();
            $title = 'User Admin';
        }elseif(Route::is('user-user')){
            $role = Role::where('name', 'user')->first();
            $title = 'User';
        }

        return view('pages.user', compact('role', 'title'));
    }
}
