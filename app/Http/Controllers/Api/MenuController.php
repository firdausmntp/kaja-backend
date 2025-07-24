<?php

namespace App\Http\Controllers\Api;

use App\Models\Menu;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            'image_url' => 'nullable|url|max:500', // Tambahan untuk URL image
        ]);

        $imagePath = null;

        // Handle file upload
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('menus', 'public');
            // For shared hosting: also copy to public/storage directly
            $this->ensurePublicStorageExists($imagePath, file_get_contents($request->file('image')->getRealPath()));
        }
        // Handle image URL download
        elseif ($request->filled('image_url')) {
            $imagePath = $this->downloadAndSaveImage($request->image_url);

            if (!$imagePath) {
                return response()->json([
                    'message' => 'Gagal mengunduh gambar dari URL yang diberikan.',
                    'errors' => ['image_url' => ['URL tidak valid atau gambar tidak dapat diunduh']]
                ], 422);
            }
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
            'image_url' => 'nullable|url|max:500', // Tambahan untuk URL image
        ]);

        // Handle image update
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('menus', 'public');
            $menu->image_url = $imagePath;
            // For shared hosting: also copy to public/storage directly
            $this->ensurePublicStorageExists($imagePath, file_get_contents($request->file('image')->getRealPath()));
        }
        // Handle image URL download
        elseif ($request->filled('image_url')) {
            $imagePath = $this->downloadAndSaveImage($request->image_url);

            if (!$imagePath) {
                return response()->json([
                    'message' => 'Gagal mengunduh gambar dari URL yang diberikan.',
                    'errors' => ['image_url' => ['URL tidak valid atau gambar tidak dapat diunduh']]
                ], 422);
            }

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

        if (!$menu) {
            return response()->json(['message' => 'Menu tidak ditemukan'], 404);
        }

        // Check if user owns this menu
        if ($menu->user_id !== Auth::user()->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus menu ini'], 403);
        }

        $menu->delete();

        return response()->json(['message' => 'Menu berhasil dihapus']);
    }

    /**
     * Download image from URL and save to storage
     * 
     * @param string $imageUrl
     * @return string|null Returns the saved file path or null on failure
     */
    private function downloadAndSaveImage($imageUrl)
    {
        try {
            // Validate URL format
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                return null;
            }

            // Download image with timeout and size limit
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; KAJA-API/1.0)',
                ])
                ->get($imageUrl);

            // Check if request was successful
            if (!$response->successful()) {
                return null;
            }

            // Check content type
            $contentType = $response->header('Content-Type');
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

            if (!in_array($contentType, $allowedTypes)) {
                return null;
            }

            // Check file size (max 2MB = 2,097,152 bytes)
            $contentLength = $response->header('Content-Length');
            if ($contentLength && $contentLength > 2097152) {
                return null;
            }

            // Get file extension from content type
            $extension = match ($contentType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'jpg'
            };

            // Generate unique filename
            $filename = 'menu_' . time() . '_' . Str::random(10) . '.' . $extension;
            $filepath = 'menus/' . $filename;

            // Save to storage (try symlink method first)
            $saved = Storage::disk('public')->put($filepath, $response->body());

            if ($saved) {
                // For shared hosting: also copy to public/storage directly
                $this->ensurePublicStorageExists($filepath, $response->body());
                return $filepath;
            }

            return null;
        } catch (\Exception $e) {
            // Log error jika diperlukan
            Log::error('Failed to download image from URL: ' . $imageUrl, [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Ensure file exists in public/storage for shared hosting
     * 
     * @param string $filepath
     * @param string $content
     */
    private function ensurePublicStorageExists($filepath, $content)
    {
        try {
            $publicStoragePath = public_path('storage/' . $filepath);
            $publicDir = dirname($publicStoragePath);

            // Create directory if it doesn't exist
            if (!is_dir($publicDir)) {
                mkdir($publicDir, 0755, true);
            }

            // Copy file to public/storage
            file_put_contents($publicStoragePath, $content);

            Log::info('File copied to public storage: ' . $publicStoragePath);
        } catch (\Exception $e) {
            Log::error('Failed to copy file to public storage: ' . $e->getMessage());
        }
    }
}
