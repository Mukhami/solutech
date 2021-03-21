<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $products = Product::whereHas('suppliers')->with('suppliers')->get();
        return response()->json(['message' => compact('products')], Response::HTTP_OK);
    }

    /**
     * Display a listing of a supplier's products.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSupplierProducts($id)
    {
        $supplier = Supplier::findOrFail($id);
        $products = $supplier->products()->get();
        return response()->json(['message' => compact('products')], Response::HTTP_OK);
    }

    /**
     * Display a listing of a supplier's products.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderProducts()
    {
        $products = Product::whereHas('suppliers')->where('quantity', '>', 0)->get();
        $products->map(function ($product){
           unset($product->created_at);
           unset($product->updated_at);
           unset($product->deleted_at);
        });
        return response()->json(['message' => compact('products')], Response::HTTP_OK);
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
                'supplier_id' => 'required|exists:suppliers,id',
                'name' => 'required|min:3|max:45',
                'description' => 'required|min:3|max:45',
                'quantity' => 'required|integer|min:1|max:1000000000',
            ]);
        if ($validator->fails())
        {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        DB::beginTransaction();
        try {
            $supplier = Supplier::find($request->supplier_id);
            $supplier->products()->create([
                'name'=>$request->name,
                'description'=>$request->description,
                'quantity'=>$request->quantity,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
            DB::commit();
            return response()->json(['message' => $supplier->name."'s Product Added successfully"], Response::HTTP_OK);

        }catch (\Exception $e){
            DB::rollback();
            return response()->json(['message' => $e.'Something Went Wrong'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $product = Product::with('suppliers')->findOrFail($id);
        return response()->json(['message' => compact('product')], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|min:3|max:45',
                'description' => 'required|min:3|max:45',
                'quantity' => 'required|integer|min:1|max:1000000000',
            ]);
        if ($validator->fails())
        {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $product = Product::find($id);
        if (!$product){
            return response()->json(['message' => 'Product Not Found'], Response::HTTP_NOT_FOUND);
        }
        $product->update([
            'name'=>$request->name,
            'description'=>$request->description,
            'quantity'=>$request->quantity,
            'updated_at'=>now(),
        ]);
        return response()->json(['message' => 'Product Details edited successfully'], Response::HTTP_OK);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $orders = Order::whereHas('products', function ($q) use($product)
        {
            $q->where('products.id','=',$product->id);
        })->exists();
        if ($orders){
            return response()->json(['message' => $product->name.' is already included in an order, hence cannot be deleted'], Response::HTTP_CONFLICT);
        }

        try {
            SupplierProduct::where('product_id', '=', $product->id)->first()->delete();
            $product->delete();
            return response()->json(['message' => 'Product deleted successfully'], Response::HTTP_OK);

        }catch (\Exception $exception)
        {
            return response()->json(['message' => 'Something went wrong on our side', 'ex'=>$exception], Response::HTTP_OK);
        }
    }
}
