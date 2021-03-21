<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $suppliers = Supplier::withCount('products')->get();
        return response()->json(['message' => compact('suppliers')], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|unique:suppliers,name|min:3|max:45',
            ]);
        if ($validator->fails())
        {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $supplier = Supplier::create([
            'name'=>$request->name,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        return response()->json(['message' => $supplier->name.' created successfully'], Response::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $supplier = Supplier::with('products')->findOrFail($id);
        return response()->json(['message' => compact('supplier')], Response::HTTP_OK);

    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|unique:suppliers,name|min:3|max:45',
            ]);
        if ($validator->fails())
        {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $supplier = Supplier::find($id);
        if (!$supplier){
            return response()->json(['message' => 'Supplier Not Found'], Response::HTTP_NOT_FOUND);
        }
        $supplier->update([
            'name'=>$request->name
        ]);
        return response()->json(['message' => 'Supplier Details edited successfully'], Response::HTTP_OK);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        try {
            $supplier->delete();
            return response()->json(['message' => 'Supplier Details deleted successfully'], Response::HTTP_OK);
        }catch (\Exception $exception)
        {
            return response()->json(['message' => 'Something went wrong on our side', 'ex'=>$exception], Response::HTTP_OK);
        }


    }
}
