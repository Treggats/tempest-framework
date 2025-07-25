<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Database\Builder;

use Tempest\Database\Builder\QueryBuilders\SelectQueryBuilder;
use Tempest\Database\Migrations\CreateMigrationsTable;
use Tests\Tempest\Fixtures\Migrations\CreateAuthorTable;
use Tests\Tempest\Fixtures\Migrations\CreateBookTable;
use Tests\Tempest\Fixtures\Migrations\CreateChapterTable;
use Tests\Tempest\Fixtures\Migrations\CreateIsbnTable;
use Tests\Tempest\Fixtures\Migrations\CreatePublishersTable;
use Tests\Tempest\Fixtures\Models\AWithEager;
use Tests\Tempest\Fixtures\Modules\Books\Models\Author;
use Tests\Tempest\Fixtures\Modules\Books\Models\AuthorType;
use Tests\Tempest\Fixtures\Modules\Books\Models\Book;
use Tests\Tempest\Fixtures\Modules\Books\Models\Chapter;
use Tests\Tempest\Integration\FrameworkIntegrationTestCase;

use function Tempest\Database\query;

/**
 * @internal
 */
final class SelectQueryBuilderTest extends FrameworkIntegrationTestCase
{
    public function test_select_query(): void
    {
        $query = query('chapters')
            ->select('title', 'index')
            ->where('`title` = ?', 'Timeline Taxi')
            ->andWhere('`index` <> ?', '1')
            ->orWhere('`createdAt` > ?', '2025-01-01')
            ->orderBy('`index` ASC')
            ->build();

        $expected = <<<SQL
        SELECT title, index
        FROM chapters
        WHERE title = ?
        AND index <> ?
        OR createdAt > ?
        ORDER BY index ASC
        SQL;

        $sql = $query->toSql();
        $bindings = $query->bindings;

        $this->assertSameWithoutBackticks($expected, $sql);
        $this->assertSame(['Timeline Taxi', '1', '2025-01-01'], $bindings);
    }

    public function test_select_without_any_fields_specified(): void
    {
        $query = query('chapters')->select()->build();

        $sql = $query->toSql();

        $expected = <<<SQL
        SELECT *
        FROM `chapters`
        SQL;

        $this->assertSameWithoutBackticks($expected, $sql);
    }

    public function test_select_from_model(): void
    {
        $query = query(Author::class)->select()->build();

        $sql = $query->toSql();

        $expected = <<<SQL
        SELECT authors.name AS `authors.name`, authors.type AS `authors.type`, authors.publisher_id AS `authors.publisher_id`, authors.id AS `authors.id`
        FROM `authors`
        SQL;

        $this->assertSameWithoutBackticks($expected, $sql);
    }

    public function test_multiple_where(): void
    {
        $sql = query('books')
            ->select()
            ->where('title = ?', 'a')
            ->where('author_id = ?', 1)
            ->where('OR author_id = ?', 2)
            ->where('AND author_id <> NULL')
            ->toSql();

        $expected = <<<SQL
        SELECT *
        FROM `books`
        WHERE title = ?
        AND author_id = ?
        OR author_id = ?
        AND author_id <> NULL
        SQL;

        $this->assertSameWithoutBackticks($expected, $sql);
    }

    public function test_multiple_where_field(): void
    {
        $sql = query('books')
            ->select()
            ->whereField('title', 'a')
            ->whereField('author_id', 1)
            ->toSql();

        $expected = <<<SQL
        SELECT *
        FROM `books`
        WHERE books.title = ?
        AND books.author_id = ?
        SQL;

        $this->assertSameWithoutBackticks($expected, $sql);
    }

    public function test_where_statement(): void
    {
        $this->migrate(
            CreateMigrationsTable::class,
            CreatePublishersTable::class,
            CreateAuthorTable::class,
            CreateBookTable::class,
        );

        Book::new(title: 'A')->save();
        Book::new(title: 'B')->save();
        Book::new(title: 'C')->save();
        Book::new(title: 'D')->save();

        $book = Book::select()->where('title = ?', 'B')->first();

        $this->assertSame('B', $book->title);
    }

    public function test_join(): void
    {
        $this->migrate(
            CreateMigrationsTable::class,
            CreatePublishersTable::class,
            CreateAuthorTable::class,
            CreateBookTable::class,
        );

        $author = Author::new(name: 'Brent')->save();
        Book::new(title: 'A', author: $author)->save();

        $query = query('books')->select('books.title AS book_title', 'authors.name')->join('authors on authors.id = books.author_id');

        $this->assertSame(
            [
                'book_title' => 'A',
                'name' => 'Brent',
            ],
            $query->first(),
        );
    }

    public function test_order_by(): void
    {
        $this->migrate(
            CreateMigrationsTable::class,
            CreatePublishersTable::class,
            CreateAuthorTable::class,
            CreateBookTable::class,
        );

        Book::new(title: 'A')->save();
        Book::new(title: 'B')->save();
        Book::new(title: 'C')->save();
        Book::new(title: 'D')->save();

        $book = Book::select()->orderBy('title DESC')->first();

        $this->assertSame('D', $book->title);
    }

    public function test_limit(): void
    {
        $this->migrate(
            CreateMigrationsTable::class,
            CreatePublishersTable::class,
            CreateAuthorTable::class,
            CreateBookTable::class,
        );

        Book::new(title: 'A')->save();
        Book::new(title: 'B')->save();
        Book::new(title: 'C')->save();
        Book::new(title: 'D')->save();

        $books = Book::select()->limit(2)->all();

        $this->assertCount(2, $books);
        $this->assertSame('A', $books[0]->title);
        $this->assertSame('B', $books[1]->title);
    }

    public function test_offset(): void
    {
        $this->migrate(
            CreateMigrationsTable::class,
            CreatePublishersTable::class,
            CreateAuthorTable::class,
            CreateBookTable::class,
        );

        Book::new(title: 'A')->save();
        Book::new(title: 'B')->save();
        Book::new(title: 'C')->save();
        Book::new(title: 'D')->save();

        $books = Book::select()
            ->limit(2)
            ->offset(2)
            ->all();

        $this->assertCount(2, $books);
        $this->assertSame('C', $books[0]->title);
        $this->assertSame('D', $books[1]->title);
    }

    public function test_chunk(): void
    {
        $this->migrate(
            CreateMigrationsTable::class,
            CreatePublishersTable::class,
            CreateAuthorTable::class,
            CreateBookTable::class,
        );

        Book::new(title: 'A')->save();
        Book::new(title: 'B')->save();
        Book::new(title: 'C')->save();
        Book::new(title: 'D')->save();

        $results = [];
        Book::select()->chunk(function (array $chunk) use (&$results): void {
            $results = [...$results, ...$chunk];
        }, 2);
        $this->assertCount(4, $results);

        $results = [];
        Book::select()->where("title <> 'A'")->chunk(function (array $chunk) use (&$results): void {
            $results = [...$results, ...$chunk];
        }, 2);
        $this->assertCount(3, $results);
    }

    public function test_raw(): void
    {
        $this->migrate(
            CreateMigrationsTable::class,
            CreatePublishersTable::class,
            CreateAuthorTable::class,
            CreateBookTable::class,
        );

        Book::new(title: 'A')->save();
        Book::new(title: 'B')->save();
        $books = Book::select()->raw('LIMIT 1')->all();

        $this->assertCount(1, $books);
        $this->assertSame('A', $books[0]->title);
    }

    public function test_select_query_with_conditions(): void
    {
        $query = query('chapters')
            ->select('title', 'index')
            ->when(
                true,
                fn (SelectQueryBuilder $query) => $query
                    ->where('`title` = ?', 'Timeline Taxi')
                    ->andWhere('`index` <> ?', '1')
                    ->orWhere('`createdAt` > ?', '2025-01-01'),
            )
            ->when(
                false,
                fn (SelectQueryBuilder $query) => $query
                    ->where('`title` = ?', 'Timeline Uber')
                    ->andWhere('`index` <> ?', '2')
                    ->orWhere('`createdAt` > ?', '2025-01-02'),
            )
            ->orderBy('`index` ASC')
            ->build();

        $expected = <<<SQL
        SELECT title, index
        FROM `chapters`
        WHERE `title` = ?
        AND `index` <> ?
        OR `createdAt` > ?
        ORDER BY `index` ASC
        SQL;

        $sql = $query->toSql();
        $bindings = $query->bindings;

        $this->assertSameWithoutBackticks($expected, $sql);
        $this->assertSame(['Timeline Taxi', '1', '2025-01-01'], $bindings);
    }

    public function test_select_first_with_non_object_model(): void
    {
        $this->migrate(CreateMigrationsTable::class, CreatePublishersTable::class, CreateAuthorTable::class);

        query('authors')->insert(
            ['id' => 1, 'name' => 'Brent'],
            ['id' => 2, 'name' => 'Other'],
        )->execute();

        $author = query('authors')
            ->select()
            ->whereField('id', 2)
            ->first();

        $this->assertSame(['id' => 2, 'name' => 'Other', 'type' => null, 'publisher_id' => null], $author);
    }

    public function test_select_all_with_non_object_model(): void
    {
        $this->migrate(CreateMigrationsTable::class, CreatePublishersTable::class, CreateAuthorTable::class);

        query('authors')->insert(
            ['id' => 1, 'name' => 'Brent', 'type' => AuthorType::B],
            ['id' => 2, 'name' => 'Other', 'type' => null],
            ['id' => 3, 'name' => 'Another', 'type' => AuthorType::A],
        )->execute();

        $authors = query('authors')
            ->select()
            ->where('name <> ?', 'Brent')
            ->all();

        $this->assertSame(
            [['id' => 2, 'name' => 'Other', 'type' => null, 'publisher_id' => null], ['id' => 3, 'name' => 'Another', 'type' => 'a', 'publisher_id' => null]],
            $authors,
        );
    }

    public function test_select_includes_belongs_to(): void
    {
        $query = query(Book::class)->select();

        $this->assertSameWithoutBackticks(<<<SQL
        SELECT books.title AS `books.title`, books.author_id AS `books.author_id`, books.id AS `books.id`
        FROM `books`
        SQL, $query->build()->toSql());
    }

    public function test_with_belongs_to_relation(): void
    {
        $query = query(Book::class)
            ->select()
            ->with('author', 'chapters', 'isbn')
            ->build();

        $this->assertSameWithoutBackticks(<<<SQL
        SELECT books.title AS `books.title`, books.author_id AS `books.author_id`, books.id AS `books.id`, authors.name AS `author.name`, authors.type AS `author.type`, authors.publisher_id AS `author.publisher_id`, authors.id AS `author.id`, chapters.title AS `chapters.title`, chapters.contents AS `chapters.contents`, chapters.book_id AS `chapters.book_id`, chapters.id AS `chapters.id`, isbns.value AS `isbn.value`, isbns.book_id AS `isbn.book_id`, isbns.id AS `isbn.id`
        FROM `books`
        LEFT JOIN authors ON authors.id = books.author_id
        LEFT JOIN chapters ON chapters.book_id = books.id
        LEFT JOIN isbns ON isbns.book_id = books.id
        SQL, $query->toSql());
    }

    public function test_select_query_execute_with_relations(): void
    {
        $this->seed();

        $books = query(Book::class)
            ->select()
            ->with('author', 'chapters', 'isbn')
            ->all();

        $this->assertCount(4, $books);
        $this->assertSame('LOTR 1', $books[0]->title);
        $this->assertSame('LOTR 2', $books[1]->title);
        $this->assertSame('LOTR 3', $books[2]->title);
        $this->assertSame('Timeline Taxi', $books[3]->title);

        $book = $books[0];
        $this->assertSame('Tolkien', $book->author->name);
        $this->assertCount(3, $book->chapters);

        $this->assertSame('LOTR 1.1', $book->chapters[0]->title);
        $this->assertSame('LOTR 1.2', $book->chapters[1]->title);
        $this->assertSame('LOTR 1.3', $book->chapters[2]->title);

        $this->assertSame('lotr-1', $book->isbn->value);
    }

    public function test_eager_loads_combined_with_manual_loads(): void
    {
        $query = AWithEager::select()->with('b.c')->toSql();

        $this->assertSameWithoutBackticks(<<<SQL
        SELECT a.b_id AS `a.b_id`, a.id AS `a.id`, b.c_id AS `b.c_id`, b.id AS `b.id`, c.name AS `b.c.name`, c.id AS `b.c.id`
        FROM `a`
        LEFT JOIN b ON b.id = a.b_id
        LEFT JOIN c ON c.id = b.c_id
        SQL, $query);
    }

    public function test_group_by(): void
    {
        $sql = query('authors')
            ->select()
            ->groupBy('name')
            ->toSql();

        $expected = <<<SQL
        SELECT *
        FROM authors
        GROUP BY name
        SQL;

        $this->assertSameWithoutBackticks($expected, $sql);
    }

    public function test_having(): void
    {
        $sql = query('authors')
            ->select()
            ->having('name = ?', 'Brent')
            ->toSql();

        $expected = <<<SQL
        SELECT *
        FROM authors
        HAVING name = ?
        SQL;

        $this->assertSameWithoutBackticks($expected, $sql);
    }

    public function test_paginate(): void
    {
        $this->seed();

        $page1 = query(Chapter::class)
            ->select()
            ->paginate(itemsPerPage: 2);

        $this->assertSame(1, $page1->currentPage);
        $this->assertSame(7, $page1->totalPages);
        $this->assertSame(13, $page1->totalItems);
        $this->assertSame(2, $page1->itemsPerPage);
        $this->assertSame(0, $page1->offset);
        $this->assertSame(2, $page1->limit);
        $this->assertSame(2, $page1->nextPage);
        $this->assertSame(null, $page1->previousPage);
        $this->assertSame(true, $page1->hasNext);
        $this->assertSame(false, $page1->hasPrevious);

        $this->assertSame('LOTR 1.1', $page1->data[0]->title);
        $this->assertSame('LOTR 1.2', $page1->data[1]->title);

        $page3 = query(Chapter::class)
            ->select()
            ->paginate(itemsPerPage: 2, currentPage: 3);

        $this->assertSame(3, $page3->currentPage);
        $this->assertSame('LOTR 2.2', $page3->data[0]->title);
        $this->assertSame('LOTR 2.3', $page3->data[1]->title);

        $page7 = query(Chapter::class)
            ->select()
            ->paginate(itemsPerPage: 2, currentPage: 7);

        $this->assertSame(7, $page7->currentPage);
        $this->assertSame('Timeline Taxi Chapter 4', $page7->data[0]->title);

        // capped to last page, so this will be page 7
        $page10 = query(Chapter::class)
            ->select()
            ->paginate(itemsPerPage: 2, currentPage: 10);

        $this->assertSame(7, $page10->currentPage);
        $this->assertSame('Timeline Taxi Chapter 4', $page10->data[0]->title);
    }

    private function seed(): void
    {
        $this->migrate(
            CreateMigrationsTable::class,
            CreatePublishersTable::class,
            CreateAuthorTable::class,
            CreateBookTable::class,
            CreateChapterTable::class,
            CreateIsbnTable::class,
        );

        query('authors')->insert(
            ['name' => 'Brent'],
            ['name' => 'Tolkien'],
        )->execute();

        query('books')->insert(
            ['title' => 'LOTR 1', 'author_id' => 2],
            ['title' => 'LOTR 2', 'author_id' => 2],
            ['title' => 'LOTR 3', 'author_id' => 2],
            ['title' => 'Timeline Taxi', 'author_id' => 1],
        )->execute();

        query('isbns')->insert(
            ['value' => 'lotr-1', 'book_id' => 1],
            ['value' => 'lotr-2', 'book_id' => 2],
            ['value' => 'lotr-3', 'book_id' => 3],
            ['value' => 'tt', 'book_id' => 4],
        )->execute();

        query('chapters')->insert(
            ['title' => 'LOTR 1.1', 'book_id' => 1],
            ['title' => 'LOTR 1.2', 'book_id' => 1],
            ['title' => 'LOTR 1.3', 'book_id' => 1],
            ['title' => 'LOTR 2.1', 'book_id' => 2],
            ['title' => 'LOTR 2.2', 'book_id' => 2],
            ['title' => 'LOTR 2.3', 'book_id' => 2],
            ['title' => 'LOTR 3.1', 'book_id' => 3],
            ['title' => 'LOTR 3.2', 'book_id' => 3],
            ['title' => 'LOTR 3.3', 'book_id' => 3],
            ['title' => 'Timeline Taxi Chapter 1', 'book_id' => 4],
            ['title' => 'Timeline Taxi Chapter 2', 'book_id' => 4],
            ['title' => 'Timeline Taxi Chapter 3', 'book_id' => 4],
            ['title' => 'Timeline Taxi Chapter 4', 'book_id' => 4],
        )->execute();
    }
}
