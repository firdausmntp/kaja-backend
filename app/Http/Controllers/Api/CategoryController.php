<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::all());
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:categories']);

        $category = Category::create(['name' => $request->name]);

        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate(['name' => 'required|string|unique:categories,name,' . $id]);

        $category->update(['name' => $request->name]);

        return response()->json($category);
    }

    public function destroy($id)
    {
        Category::destroy($id);

        return response()->json(['message' => 'Kategori dihapus']);
    }
}
