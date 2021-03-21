<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
//controllers
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function () {
    Route::prefix('user')->group(function () {
        Route::prefix('register')->group(function () {
            Route::post('/', [AuthController::class,'register']);
            Route::post('email-unique',  [AuthController::class,'emailUnique']);
        });
        Route::prefix('login')->group(function () {
            Route::post('/', [AuthController::class,'login']);
            Route::post('password/forgot', [AuthController::class,'forgotPassword']);
            Route::post('password/update', [AuthController::class,'updatePassword']);
        });
    });

    Route::middleware('auth:api')->group(function (){
        //supplier CRUD
        Route::resource('suppliers',SupplierController::class)->except(['create', 'edit']);
        //products CRUD
        Route::resource('products',ProductController::class)->except(['create', 'edit']);
        Route::get('supplier-products/{id}',[ProductController::class,'getSupplierProducts']);
        Route::get('create-order-products',[ProductController::class,'getOrderProducts']);
        //orders CRUD
        Route::resource('orders',OrderController::class)->except(['create', 'edit']);
        Route::get('supplier-orders/{id}',[OrderController::class,'getSupplierOrders']);
        Route::get('orders-chart',[OrderController::class,'ordersChartData']);
        Route::get('supplier-orders-chart/{id}',[OrderController::class,'suppliersOrdersChartData']);

    });
});
