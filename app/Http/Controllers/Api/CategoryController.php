<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Menampilkan daftar semua kategori.
     */
    public function index(): JsonResponse
    {
        $categories = Category::withCount('books')->get();

        return response()->json($categories);
    }

    /**
     * Menambah kategori baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    /**
     * Menampilkan detail kategori beserta daftar bukunya.
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json($category->load('books'));
    }

    /**
     * Mengupdate data kategori.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:categories,slug,'.$category->id,
        ]);

        if (isset($validated['name']) && empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * Menghapus kategori.
     */
    public function destroy(Category $category): JsonResponse
    {
        $categoryName = $category->name;
        $category->books()->detach();
        $category->delete();

        return response()->json([
            'message' => "Kategori '{$categoryName}' berhasil dihapus.",
        ]);
    }
}
