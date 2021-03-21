<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lcobucci\JWT\Builder;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $orders = Order::with('products')->withCount('products')->get();
        return response()->json(['message' => compact('orders')], Response::HTTP_OK);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSupplierOrders($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier_products = $supplier->products()->get()->pluck('id');
        $orders = Order::whereHas('products', function ($q) use($supplier_products){
            $q->whereIn('products.id', $supplier_products);
        })->with('products')->withCount('products')->get();
        return response()->json(['message' => compact('orders')], Response::HTTP_OK);
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
                'product_ids.*' => 'required|exists:products,id',
                'order_number' => 'required|min:3|max:45',
            ]);
        if ($validator->fails())
        {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        DB::beginTransaction();
        try {
            $order = Order::create([
                'order_number'=>$request->order_number,
                'created_at'=>now(),
                'updated_at'=>now()
            ]);
            foreach ($request->product_ids as $product_id) {
                $product = Product::find($product_id);
                if ($product->quantity > 0 ){
                    $order->products()->attach($product_id === null ? [] : $product_id, ['created_at' => now(), 'updated_at' => now()]);
                    $product->decrement('quantity', 1);
                }
            }
            DB::commit();
            return response()->json(['message' => $order->order_number.' added successfully'], Response::HTTP_OK);
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
        $order = Order::with('products')->findOrFail($id);
        $product_ids = $order->products()->get()->pluck('id');
        return response()->json(['message' => compact('order', 'product_ids')], Response::HTTP_OK);
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
                'product_ids.*' => 'required|exists:products,id',
                'order_number' => 'required|min:3|max:45',
            ]);
        if ($validator->fails())
        {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        DB::beginTransaction();
        try {
            $order = Order::find($id);
            if(!$order){
                return response()->json(['message' => 'Order Not Found'], Response::HTTP_NOT_FOUND);
            }
            $order->update([
                'order_number'=>$request->order_number,
                'updated_at'=>now()
            ]);

            $order->products()->detach();

            foreach ($request->product_ids as $product_id) {
                $order->products()->attach($product_id === null ? [] : $product_id, ['created_at' => now(), 'updated_at' => now()]);
            }

            DB::commit();
            return response()->json(['message' => $order->order_number.' added successfully'], Response::HTTP_OK);
        }catch (\Exception $e){
            DB::rollback();
            return response()->json(['message' => $e.'Something Went Wrong'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $order = Order::findOrFail($id);
        try {
            DB::table('order_details')
                ->where('order_id', '=', $order->id)
                ->update(['deleted_at'=>now()]);
            $order->delete();
            return response()->json(['message' => 'Order Details deleted successfully'], Response::HTTP_OK);
        }catch (\Exception $exception)
        {
            return response()->json(['message' => 'Something went wrong on our side', 'ex'=>$exception], Response::HTTP_OK);
        }
    }
    /**
     * Orders Chart Data
     *
     * @return false|\Illuminate\Http\JsonResponse|string
     */
    public function ordersChartData(){
        $order_dates = Order::whereHas('products')->pluck( 'created_at');
        $month_array = array();
        $order_dates = json_decode( $order_dates );
        if ( ! empty( $order_dates ) ) {
            foreach ( $order_dates as $unformatted_date ) {
                $date = new \DateTime( $unformatted_date );
                $day = $date->format( 'd' );
                $month_name = $date->format( 'd-M' );
                $month_array[ $day ] = $month_name;
            }
        }
        $month_name_array = array();
        $monthly_order_count_array = array();
        if ( ! empty( $month_array ) ) {
            foreach ( $month_array as $day => $month_name ){
                $monthly_order_count = Order::whereHas('products')->withCount('products')->count();
                array_push( $monthly_order_count_array, $monthly_order_count );
                array_push( $month_name_array, $month_name );
            }
        }
        if (!empty($monthly_order_count_array)){
            $max = max( $monthly_order_count_array );
            $max = round(( $max + 10/2 ) / 10 ) * 10;
        }else{
            $max = 0;
        }
        return json_encode([
            'month' => $month_name_array,
            'order_count_data' => $monthly_order_count_array,
            'max' => $max,
        ]);
    }

    /**
     * Suppliers Orders Chart Data
     *
     * @return false|\Illuminate\Http\JsonResponse|string
     */
    public function suppliersOrdersChartData($id){
        $supplier = Supplier::findOrFail($id);
        $supplier_products = $supplier->products()->get()->pluck('id');
        $order_dates = Order::whereHas('products', function ($q) use($supplier_products){
            $q->whereIn('products.id', $supplier_products);
        })->pluck( 'created_at' );
        $month_array = array();
        $order_dates = json_decode( $order_dates );
        if ( ! empty( $order_dates ) ) {
            foreach ( $order_dates as $unformatted_date ) {
                $date = new \DateTime( $unformatted_date );
                $day = $date->format( 'd' );
                $month_name = $date->format( 'd-M' );
                $month_array[ $day ] = $month_name;
            }
        }
        $month_name_array = array();
        $monthly_order_count_array = array();
        if ( ! empty( $month_array ) ) {
            foreach ( $month_array as $day => $month_name ){
                $orders_count = Order::whereHas('products', function ($q) use($supplier_products){
                    $q->whereIn('products.id', $supplier_products);
                })->withCount('products')->count();
                $monthly_order_count = Order::whereHas('products', function ($q) use($supplier_products){
                    $q->whereIn('products.id', $supplier_products);
                })->withCount('products')->count();
                array_push( $monthly_order_count_array, $monthly_order_count );
                array_push( $month_name_array, $month_name );
            }
        }
        if (!empty($monthly_order_count_array)){
            $max = max( $monthly_order_count_array );
            $max = round(( $max + 10/2 ) / 10 ) * 10;
        }else{
            $max = 0;
        }
        return json_encode([
            'month' => $month_name_array,
            'order_count_data' => $monthly_order_count_array,
            'max' => $max,
        ]);
    }
}
