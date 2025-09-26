<?php

namespace App\Http\Controllers;

use App\Models\Kadun;
use App\Models\MPKK;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MPKKController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $mpkk = MPKK::all();
        $kadun = Kadun::all();
        return view('pages.mpkk.index', compact('mpkk', 'kadun'));
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
            'name' => 'required|unique:mpkk',
            'kadun_id' => 'required',
        ]);
        MPKK::create($request->all());
        return back()->with('success', 'MPKK berjaya ditambahkan');
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
        $mpkk = MPKK::findOrFail($id);
        $kadun = Kadun::all();
        return view('pages.mpkk.edit', compact('mpkk', 'kadun'));
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
        $mpkk = MPKK::findOrFail($id);
        $request->validate([
            'name' => ['required', Rule::unique('mpkk')->ignore($mpkk->id, 'id')],
            'kadun_id' => 'required',
        ]);
        $mpkk->update($request->all());
        return back()->with('success', 'MPKK berjaya diupdate');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $mpkk = MPKK::findOrFail($id);
        $mpkk->delete();
        return back()->with('success', 'MPKK berjaya dipadam');
    }
}
