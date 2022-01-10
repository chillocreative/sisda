<?php

namespace App\Http\Controllers;

use App\Models\Kadun;
use Illuminate\Http\Request;

class KadunController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $kadun = Kadun::all();
        return view('pages.kadun', compact('kadun'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:kadun',
            'code' => 'required|unique:kadun',
        ]);
        Kadun::create($request->all());
        return back()->with('success', 'Kadun berjaya ditambahkan');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $kadun = Kadun::findOrFail($id);
        if($kadun->mpkk->count() < 1){
            $kadun->delete();
            return back()->with('success', 'Kadun berjaya dihapus');
        }
        return back()->with('error', 'Kadun tak bisa dihapus sebab ada MPKK');
    }
}
