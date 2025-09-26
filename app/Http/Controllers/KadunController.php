<?php

namespace App\Http\Controllers;

use App\Models\Kadun;
use App\Models\Parlimen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KadunController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $parlimen = Parlimen::all();
        $kadun = Kadun::all();
        return view('pages.kadun.index', compact('parlimen', 'kadun'));
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
            'parlimen_id' => 'required',
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
        $kadun = Kadun::findOrFail($id);
        $parlimen = Parlimen::all();
        return view('pages.kadun.edit', compact('kadun', 'parlimen'));
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
        $kadun = Kadun::findOrFail($id);
        $request->validate([
            'name' => ['required', Rule::unique('kadun')->ignore($kadun->id, 'id')],
            'parlimen_id' => 'required',
        ]);
        $kadun->update($request->all());
        return back()->with('success', 'Kadun berjaya diupdate');
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
            return back()->with('success', 'Kadun berjaya dipadam');
        }
        return back()->with('error', 'Kadun tak bisa dipadam sebab ada MPKK');
    }
}
