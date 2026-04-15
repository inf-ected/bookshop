<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PostAdminService
{
    public function __construct(private readonly HtmlSanitizerService $sanitizer) {}

    /**
     * Create a new blog post.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $cover): Post
    {
        $post = new Post;
        $post->title = $data['title'];
        $post->slug = $data['slug'];
        $post->excerpt = $data['excerpt'];
        $post->body = $this->sanitizer->sanitize($data['body']);
        $post->status = PostStatus::from($data['status']);
        $post->published_at = $data['published_at'] ?? null;
        if ($post->status === PostStatus::Published && $post->published_at === null) {
            $post->published_at = now();
        }

        if ($cover !== null) {
            $path = $this->uploadCover($cover);
            $post->cover_path = $path;
            $post->cover_thumb_path = $path;
        }

        $post->save();

        return $post;
    }

    /**
     * Update an existing blog post.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data, ?UploadedFile $cover): Post
    {
        $post->title = $data['title'];
        $post->slug = $data['slug'];
        $post->excerpt = $data['excerpt'];
        $post->body = $this->sanitizer->sanitize($data['body']);
        $post->status = PostStatus::from($data['status']);
        $post->published_at = $data['published_at'] ?? null;
        if ($post->status === PostStatus::Published && $post->published_at === null) {
            $post->published_at = now();
        }

        if ($cover !== null) {
            $this->deleteCoverIfExists($post);
            $path = $this->uploadCover($cover);
            $post->cover_path = $path;
            $post->cover_thumb_path = $path;
        }

        $post->save();

        return $post;
    }

    /**
     * Delete a post and its cover image from storage.
     */
    public function delete(Post $post): void
    {
        $this->deleteCoverIfExists($post);
        $post->delete();
    }

    /**
     * Toggle the post status between Draft and Published.
     * Sets published_at to now() when first publishing.
     */
    public function toggleStatus(Post $post): Post
    {
        if ($post->status === PostStatus::Published) {
            $post->status = PostStatus::Draft;
        } else {
            $post->status = PostStatus::Published;
            if ($post->published_at === null) {
                $post->published_at = now();
            }
        }

        $post->save();

        return $post;
    }

    private function uploadCover(UploadedFile $file): string
    {
        $path = $file->store('posts/covers', 's3-public');

        throw_if($path === false, \RuntimeException::class, 'Cover upload failed.');

        return $path;
    }

    private function deleteCoverIfExists(Post $post): void
    {
        if ($post->cover_path !== null) {
            Storage::disk('s3-public')->delete($post->cover_path);
        }
    }
}
