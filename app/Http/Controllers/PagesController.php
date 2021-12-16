<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;

class PagesController extends Controller
{
    public function dashboard(){
        return view('pages.dashboard');
    }

    public function user(){
        return view('pages.user');
    }
}
