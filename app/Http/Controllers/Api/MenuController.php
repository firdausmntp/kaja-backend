<?php

namespace App\Http\Controllers\Api;

use App\Models\Menu;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::with(['category', 'merchant:id,name'])
            ->get()
            ->map(function ($menu) {
                $menu->image_url = $menu->image_url ? asset('storage/' . $menu->image_url) : null;
                return $menu;
            });

        return response()->json($menus);
    }

    public function show($id)
    {
        $menu = Menu::with(['category', 'merchant:id,name'])->find($id);

        if (! $menu) {
            return response()->json(['message' => 'Menu tidak ditemukan'], 404);
        }

        $menu->image_url = $menu->image_url ? asset('storage/' . $menu->image_url) : null;

        return response()->json($menu);
    }

    /**
     * Get menus owned by current seller
     */
    public function myMenus(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'penjual') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $menus = Menu::with(['category', 'merchant:id,name'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($menu) {
                $menu->image_url = $menu->image_url ? asset('storage/' . $menu->image_url) : null;
                return $menu;
            });

        return response()->json([
            'message' => 'Menus retrieved successfully',
            'data' => $menus
        ]);
    }

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'penjual') {
            return response()->json(['message' => 'Hanya penjual yang dapat menambah menu'], 403);
        }

        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('menus', 'public');
        }

        $menu = Menu::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'image_url' => $imagePath,
            'category_id' => $request->category_id,
            'user_id' => Auth::user()->id,
        ]);

        return response()->json(['message' => 'Menu berhasil dibuat', 'menu' => $menu]);
    }


    public function update(Request $request, $id)
    {
        if (Auth::user()->role !== 'penjual') {
            return response()->json(['message' => 'Hanya penjual yang dapat mengedit menu'], 403);
        }

        $menu = Menu::find($id);

        if (! $menu) {
            return response()->json(['message' => 'Menu tidak ditemukan'], 404);
        }

        // Check if user owns this menu
        if ($menu->user_id !== Auth::user()->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk mengedit menu ini'], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric',
            'stock' => 'sometimes|required|integer|min:0',
            'category_id' => 'sometimes|required|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('menus', 'public');
            $menu->image_url = $imagePath;
        }

        $menu->fill($request->only(['name', 'description', 'price', 'stock', 'category_id']));
        $menu->save();

        return response()->json(['message' => 'Menu berhasil diperbarui', 'menu' => $menu]);
    }


    public function destroy($id)
    {
        if (Auth::user()->role !== 'penjual') {
            return response()->json(['message' => 'Hanya penjual yang dapat menghapus menu'], 403);
        }

        $menu = Menu::find($id);

        if (! $menu) {
            return response()->json(['message' => 'Menu tidak ditemukan'], 404);
        }

        // Check if user owns this menu
        if ($menu->user_id !== Auth::user()->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus menu ini'], 403);
        }

        $menu->delete();

        return response()->json(['message' => 'Menu berhasil dihapus']);
    }
}
