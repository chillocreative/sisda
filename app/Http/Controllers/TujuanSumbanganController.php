<?php

namespace App\Http\Controllers;

use App\Models\TujuanSumbangan;
use Illuminate\Http\Request;

class TujuanSumbanganController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tujuanSumbangan = TujuanSumbangan::all();
        return view('pages.tujuan-sumbangan', compact('tujuanSumbangan'));
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
            'name' => 'required|unique:tujuan_sumbangan',
        ]);
        TujuanSumbangan::create($request->all());
        return back()->with('success', 'Tujuan Sumbangan berjaya ditambahkan');
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
        $tujuanSumbangan = TujuanSumbangan::findOrFail($id);
        $tujuanSumbangan->delete();
        return back()->with('success', 'Tujuan Sumbangan berjaya dihapus');
    }
}
