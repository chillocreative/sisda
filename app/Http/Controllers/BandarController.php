<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Negeri;
use Illuminate\Http\Request;

class BandarController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bandar = Bandar::all();
        $negeri = Negeri::all();
        return view('pages.bandar.index', compact('bandar', 'negeri'));
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
            'name' => 'required|unique:bandar',
        ]);
        Bandar::create($request->all());
        return back()->with('success', 'Bandar berjaya ditambahkan');
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
        $negeri = Negeri::all();
        $bandar = Bandar::findOrFail($id);
        return view('pages.bandar.edit', compact('negeri', 'bandar'));
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
        $bandar = Bandar::findOrFail($id);
        $request->validate([
            'name' => 'required|unique:bandar,name,' . $bandar->id, 
        ]);
        $bandar->update($request->all());
        return back()->with('success', 'Bandar berjaya diupdate');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $bandar = Bandar::findOrFail($id);
        $bandar->delete();
        return back()->with('success', 'Bandar berjaya dihapus');
    }
}
