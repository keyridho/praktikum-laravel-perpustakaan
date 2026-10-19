<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BookController extends Controller
{
    /**
     * Menampilkan daftar semua buku dengan relasi penulis dan kategori.
     * Mendukung pencarian (judul/nama penulis), filter kategori, dan filter stok.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::with(['author', 'categories']);

        // 4.a: Pencarian buku berdasarkan judul atau nama penulis
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('author', function ($authorQuery) use ($search): void {
                        $authorQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // 4.b: Filter buku berdasarkan kategori (id atau slug)
        if ($request->filled('category_id')) {
            $query->whereHas('categories', function ($q) use ($request): void {
                $q->where('categories.id', $request->input('category_id'));
            });
        } elseif ($request->filled('category')) {
            $category = $request->input('category');
            $query->whereHas('categories', function ($q) use ($category): void {
                $q->where('categories.slug', $category)
                    ->orWhere('categories.name', 'like', "%{$category}%");
            });
        }

        // 4.c: Filter buku berdasarkan ketersediaan stok (stok > 0)
        if ($request->boolean('in_stock') || $request->boolean('available') || $request->query('stock_status') === 'in_stock') {
            $query->where('stock', '>', 0);
        }

        // 4.e: Filter buku yang stoknya hampir habis / habis (< 5)
        if ($request->boolean('low_stock') || $request->query('stock_status') === 'low_stock') {
            $query->where('stock', '<', 5);
        }

        return response()->json($query->get());
    }

    /**
     * Menambah buku baru (dengan memilih penulis dan kategori).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'isbn' => 'required|string|max:255|unique:books,isbn',
            'published_year' => 'required|integer|min:1900|max:'.date('Y'),
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'author_id' => 'required|exists:authors,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $book = Book::create(Arr::except($validated, ['category_ids']));

        if (! empty($validated['category_ids'])) {
            $book->categories()->attach($validated['category_ids']);
        }

        return response()->json($book->load(['author', 'categories']), 201);
    }

    /**
     * Menampilkan detail buku beserta informasi penulis dan kategori.
     */
    public function show(Book $book): JsonResponse
    {
        return response()->json($book->load(['author', 'categories']));
    }

    /**
     * Mengupdate data buku (termasuk stok dan kategori).
     */
    public function update(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'isbn' => 'sometimes|required|string|max:255|unique:books,isbn,'.$book->id,
            'published_year' => 'sometimes|required|integer|min:1900|max:'.date('Y'),
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'author_id' => 'sometimes|required|exists:authors,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $book->update(Arr::except($validated, ['category_ids']));

        if (array_key_exists('category_ids', $validated)) {
            $book->categories()->sync($validated['category_ids'] ?? []);
        }

        return response()->json($book->load(['author', 'categories']));
    }

    /**
     * Menghapus buku.
     */
    public function destroy(Book $book): JsonResponse
    {
        $title = $book->title;
        $book->categories()->detach();
        $book->delete();

        return response()->json([
            'message' => "Buku '{$title}' berhasil dihapus.",
        ]);
    }

    /**
     * 4.d: Menampilkan statistik: total buku, total penulis, total kategori.
     */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'total_books' => Book::count(),
            'total_authors' => Author::count(),
            'total_categories' => Category::count(),
        ]);
    }

    /**
     * 4.e: Menampilkan daftar buku yang stoknya hampir habis / habis (< 5).
     */
    public function lowStock(): JsonResponse
    {
        $books = Book::with(['author', 'categories'])
            ->where('stock', '<', 5)
            ->get();

        return response()->json($books);
    }
}
