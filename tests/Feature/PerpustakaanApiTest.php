<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can list all authors and show author details with books', function () {
    $author = Author::factory()->hasBooks(3)->create();

    $response = $this->getJson('/api/authors');
    $response->assertStatus(200)
        ->assertJsonFragment(['name' => $author->name]);

    $showResponse = $this->getJson("/api/authors/{$author->id}");
    $showResponse->assertStatus(200)
        ->assertJsonPath('name', $author->name)
        ->assertJsonCount(3, 'books');
});

test('can create, update, and delete author with cascade', function () {
    $createResponse = $this->postJson('/api/authors', [
        'name' => 'Pramoedya Ananta Toer',
        'email' => 'pram@example.com',
        'bio' => 'Sastrawan Indonesia',
    ]);
    $createResponse->assertStatus(201)
        ->assertJsonPath('name', 'Pramoedya Ananta Toer');

    $authorId = $createResponse->json('id');

    $updateResponse = $this->putJson("/api/authors/{$authorId}", [
        'name' => 'Pramoedya A. Toer',
    ]);
    $updateResponse->assertStatus(200)
        ->assertJsonPath('name', 'Pramoedya A. Toer');

    $deleteResponse = $this->deleteJson("/api/authors/{$authorId}");
    $deleteResponse->assertStatus(200);
    $this->assertDatabaseMissing('authors', ['id' => $authorId]);
});

test('can list, create, and delete categories', function () {
    $category = Category::factory()->create(['name' => 'Fiksi', 'slug' => 'fiksi']);

    $response = $this->getJson('/api/categories');
    $response->assertStatus(200)
        ->assertJsonFragment(['name' => 'Fiksi']);

    $createResponse = $this->postJson('/api/categories', [
        'name' => 'Non-Fiksi',
    ]);
    $createResponse->assertStatus(201)
        ->assertJsonPath('slug', 'non-fiksi');

    $categoryId = $createResponse->json('id');

    $deleteResponse = $this->deleteJson("/api/categories/{$categoryId}");
    $deleteResponse->assertStatus(200);
    $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
});

test('can create book with author and categories', function () {
    $author = Author::factory()->create();
    $categories = Category::factory()->count(2)->create();

    $response = $this->postJson('/api/books', [
        'title' => 'Bumi Manusia',
        'isbn' => '978-979-973-123-0',
        'published_year' => 1980,
        'price' => 95000,
        'stock' => 10,
        'author_id' => $author->id,
        'category_ids' => $categories->pluck('id')->toArray(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('title', 'Bumi Manusia')
        ->assertJsonCount(2, 'categories')
        ->assertJsonPath('author.id', $author->id);
});

test('can search books by title or author name', function () {
    $author = Author::factory()->create(['name' => 'Andrea Hirata']);
    $book = Book::factory()->create([
        'title' => 'Laskar Pelangi',
        'author_id' => $author->id,
    ]);

    $responseTitle = $this->getJson('/api/books?search=Laskar');
    $responseTitle->assertStatus(200)
        ->assertJsonFragment(['title' => 'Laskar Pelangi']);

    $responseAuthor = $this->getJson('/api/books?search=Hirata');
    $responseAuthor->assertStatus(200)
        ->assertJsonFragment(['title' => 'Laskar Pelangi']);
});

test('can filter books by category and stock availability', function () {
    $category = Category::factory()->create();
    $inStockBook = Book::factory()->create(['stock' => 15]);
    $inStockBook->categories()->attach($category->id);

    $outOfStockBook = Book::factory()->create(['stock' => 0]);
    $outOfStockBook->categories()->attach($category->id);

    $responseCategory = $this->getJson("/api/books?category_id={$category->id}");
    $responseCategory->assertStatus(200);

    $responseStock = $this->getJson('/api/books?in_stock=1');
    $responseStock->assertStatus(200);
    $books = $responseStock->json();
    expect(collect($books)->every(fn ($b) => $b['stock'] > 0))->toBeTrue();
});

test('can fetch library statistics and low stock books', function () {
    Book::factory()->create(['stock' => 2]);

    $statsResponse = $this->getJson('/api/statistics');
    $statsResponse->assertStatus(200)
        ->assertJsonStructure(['total_books', 'total_authors', 'total_categories']);

    $lowStockResponse = $this->getJson('/api/books/low-stock');
    $lowStockResponse->assertStatus(200);
    $lowStockBooks = $lowStockResponse->json();
    expect(collect($lowStockBooks)->every(fn ($b) => $b['stock'] < 5))->toBeTrue();
});
