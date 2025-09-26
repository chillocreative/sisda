<?php

namespace App\Http\Controllers;

use App\Models\BantuanLain;
use Database\Seeders\BantaunLain;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BantuanLainController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bantuanLain = BantuanLain::all();
        return view('pages.bantuan-lain.index', compact('bantuanLain'));
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
            'name' => 'required|unique:bantuan_lain',
        ]);
        BantuanLain::create($request->all());
        return back()->with('success', 'Bantuan Lain berjaya ditambahkan');
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
        $bantuanLain = BantuanLain::findOrFail($id);
        return view('pages.bantuan-lain.edit', compact('bantuanLain'));
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
        $bantuanLain = BantuanLain::findOrFail($id);
        $request->validate([
            'name' => ['required', Rule::unique('bantuan_lain')->ignore($bantuanLain->id, 'id')],
        ]);
        $bantuanLain->update($request->all());
        return back()->with('success', 'Bantuan Lain berjaya diupdate');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $bantuanLain = BantuanLain::findOrFail($id);
        $bantuanLain->delete();
        return back()->with('success', 'Bantuan Lain berjaya dipadam');
    }
}
