<?php

namespace KFoobar\Data\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use KFoobar\Data\Tests\Fixtures\Author;
use KFoobar\Data\Tests\Fixtures\Post;
use KFoobar\Data\Tests\Fixtures\PostData;
use KFoobar\Data\Tests\Fixtures\PostRequest;
use Orchestra\Testbench\TestCase;
use ReflectionProperty;

class LaravelIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained();
            $table->string('title');
            $table->boolean('enabled');
            $table->text('metadata');
            $table->timestamp('published_at')->nullable();
            $table->string('secret');
        });
    }

    public function test_request_query_and_body_are_hydrated(): void
    {
        $request = Request::create('/posts?title=Example', 'POST', [
            'id' => '42',
            'enabled' => '0',
            'metadata' => ['source' => 'form'],
            'published_at' => '2026-10-05T12:30:00+02:00',
            'unknown' => 'ignored',
        ]);
        $data = PostData::fromRequest($request);

        self::assertInstanceOf(PostData::class, $data);
        self::assertSame(42, $data->id);
        self::assertSame('Example', $data->title);
        self::assertFalse($data->enabled);
        self::assertSame(['source' => 'form'], $data->metadata);
        self::assertInstanceOf(CarbonImmutable::class, $data->published_at);
        self::assertSame('2026-10-05T12:30:00+02:00', $data->published_at->toIso8601String());
        self::assertSame('draft', $data->status);
        self::assertFalse(property_exists($data, 'unknown'));
    }

    public function test_json_request_is_hydrated_through_a_laravel_route(): void
    {
        Route::post('/v1/posts', fn (Request $request): JsonResponse => response()->json(PostData::fromRequest($request)));

        $this->postJson('/v1/posts', [
            'id' => '42',
            'title' => 'JSON post',
            'enabled' => true,
            'published_at' => null,
        ])->assertOk()->assertJson([
            'id' => 42,
            'title' => 'JSON post',
            'enabled' => true,
            'published_at' => null,
            'status' => 'draft',
        ]);
    }

    public function test_from_request_uses_all_input_without_implicit_validation(): void
    {
        $request = Request::create('/posts', 'POST', ['id' => '42', 'title' => 'Post', 'status' => 'published']);

        self::assertSame('published', PostData::fromRequest($request)->status);
    }

    public function test_validated_form_request_input_can_be_used_explicitly(): void
    {
        Route::post('/v1/posts', fn (PostRequest $request): JsonResponse => response()->json(PostData::fromArray($request->validated())));

        $this->postJson('/v1/posts', [
            'id' => '42',
            'title' => 'Validated post',
            'status' => 'unvalidated',
        ])->assertOk()->assertJson([
            'id' => 42,
            'title' => 'Validated post',
            'status' => 'draft',
        ]);

        $this->postJson('/v1/posts', ['id' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['id', 'title']);
    }

    public function test_persisted_model_casts_hidden_attributes_and_loaded_relationships_are_respected(): void
    {
        self::assertSame(':memory:', config('database.connections.testing.database'));
        $author = Author::create(['name' => 'Writer']);
        $post = Post::create([
            'author_id' => $author->id,
            'title' => 'Persisted post',
            'enabled' => true,
            'metadata' => ['source' => 'model'],
            'published_at' => '2026-10-05 10:30:00',
            'secret' => 'not exported',
        ])->fresh('author');
        $data = PostData::fromModel($post);

        self::assertInstanceOf(PostData::class, $data);
        self::assertSame($post->id, $data->id);
        self::assertSame('Persisted post', $data->title);
        self::assertTrue($data->enabled);
        self::assertSame(['source' => 'model'], $data->metadata);
        self::assertInstanceOf(CarbonImmutable::class, $data->published_at);
        self::assertSame('2026-10-05T10:30:00+00:00', $data->published_at->toIso8601String());
        self::assertSame(['id' => $author->id, 'name' => 'Writer'], $data->author);
        self::assertSame('hidden', $data->secret);
        self::assertSame('draft', $data->status);
    }

    public function test_null_model_dates_and_missing_attributes_preserve_their_meaning(): void
    {
        $post = new Post(['title' => 'Unsaved post', 'published_at' => null]);
        $data = PostData::fromModel($post);

        self::assertNull($data->published_at);
        self::assertFalse((new ReflectionProperty($data, 'id'))->isInitialized($data));
        self::assertSame('draft', $data->status);
        self::assertSame([], $data->author);
    }

    public function test_factories_return_the_concrete_subclass(): void
    {
        $data = new class extends PostData {};

        self::assertInstanceOf($data::class, $data::fromArray());
        self::assertInstanceOf($data::class, $data::fromRequest(new Request()));
        self::assertInstanceOf($data::class, $data::fromModel(new Post()));
    }
}
