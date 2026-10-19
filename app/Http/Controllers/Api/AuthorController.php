<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    /**
     * Menampilkan daftar semua penulis.
     */
    public function index(): JsonResponse
    {
        $authors = Author::withCount('books')->get();

        return response()->json($authors);
    }

    /**
     * Menambah penulis baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:authors,email',
            'bio' => 'nullable|string',
        ]);

        $author = Author::create($validated);

        return response()->json($author, 201);
    }

    /**
     * Menampilkan detail penulis beserta daftar bukunya.
     */
    public function show(Author $author): JsonResponse
    {
        return response()->json($author->load('books'));
    }

    /**
     * Mengupdate data penulis.
     */
    public function update(Request $request, Author $author): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:authors,email,'.$author->id,
            'bio' => 'nullable|string',
        ]);

        $author->update($validated);

        return response()->json($author);
    }

    /**
     * Menghapus penulis (dengan konfirmasi bahwa buku terkait juga terhapus).
     */
    public function destroy(Author $author): JsonResponse
    {
        $booksCount = $author->books()->count();
        $authorName = $author->name;

        $author->delete();

        return response()->json([
            'message' => "Penulis '{$authorName}' berhasil dihapus beserta {$booksCount} buku terkait.",
            'deleted_books_count' => $booksCount,
        ]);
    }
}
