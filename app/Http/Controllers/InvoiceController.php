<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;

class InvoiceController extends Controller
{
    public function index()
    {
        return Invoice::all();
    }

    public function store(InvoiceStoreRequest $request)
    {
        $model = Invoice::create($request->validated());
        return response()->json($model, 201);
    }

    public function show(Invoice $model)
    {
        return response()->json($model);
    }

    public function update(InvoiceUpdateRequest $request, Invoice $model)
    {
        $model->update($request->validated());
        return response()->json($model);
    }

    public function destroy(Invoice $model)
    {
        $model->delete();
        return response()->json(null, 204);
    }
}
