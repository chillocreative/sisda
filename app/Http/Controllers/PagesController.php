<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;

class PagesController extends Controller
{
    public function dashboard(){
        $roles = Role::all();
        return view('pages.dashboard', compact('roles'));
    }

    public function user(){
        return view('pages.user');
    }
}
