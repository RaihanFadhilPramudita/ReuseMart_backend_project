<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JabatanController extends Controller
{
    public function index()
    {
        $jabatan = Jabatan::all();
        return response()->json(['data' => $jabatan]);
    }

    public function show($id)
    {
        $jabatan = Jabatan::with('pegawai')->findOrFail($id);
        return response()->json(['data' => $jabatan]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_jabatan' => 'required|string|max:255|unique:jabatan,NAMA_JABATAN',
            'gaji' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jabatan = new Jabatan();
        $jabatan->NAMA_JABATAN = $request->nama_jabatan;
        $jabatan->GAJI = $request->gaji;
        $jabatan->save();

        return response()->json([
            'message' => 'Position created successfully',
            'data' => $jabatan
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_jabatan' => 'string|max:255|unique:jabatan,NAMA_JABATAN,'.$id.',ID_JABATAN',
            'gaji' => 'numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jabatan = Jabatan::findOrFail($id);

        if ($request->has('nama_jabatan')) {
            $jabatan->NAMA_JABATAN = $request->nama_jabatan;
        }

        if ($request->has('gaji')) {
            $jabatan->GAJI = $request->gaji;
        }

        $jabatan->save();

        return response()->json([
            'message' => 'Position updated successfully',
            'data' => $jabatan
        ]);
    }

    public function destroy($id)
    {
        $jabatan = Jabatan::findOrFail($id);

        if ($jabatan->pegawai()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete position with associated employees',
                'employee_count' => $jabatan->pegawai()->count()
            ], 422);
        }

        $jabatan->delete();

        return response()->json(['message' => 'Position deleted successfully']);
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');

        if (!$keyword) {
            return response()->json(['message' => 'Parameter q diperlukan.'], 422);
        }

        $jabatan = Jabatan::where('NAMA_JABATAN', 'like', "%{$keyword}%")
            ->orWhere('GAJI', 'like', "%{$keyword}%")
            ->paginate(10);

        return response()->json(['data' => $jabatan]);
    }


    public function employees($id)
    {
        $jabatan = Jabatan::findOrFail($id);
        $employees = $jabatan->pegawai()->with('jabatan')->get();

        return response()->json(['data' => $employees]);
    }
}
