<?php

namespace Tests\Feature;

use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HelpUploadTest extends TestCase
{
    use RefreshDatabase;

    private const GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
    }

    private function submit(UploadedFile $file)
    {
        return $this->post(route('help.store'), [
            'gmail' => 'a@example.test', 'pesan' => 'Butuh bantuan pesanan', 'upload_file' => $file,
        ]);
    }

    public function test_gif_polyglot_with_html_extension_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('poc.html', self::GIF.'<script>alert(1)</script>');

        $this->submit($file)->assertSessionHasErrors('upload_file');

        $this->assertSame([], Storage::disk('public')->allFiles('help-uploads'));
        $this->assertSame(0, Comment::count());
    }

    public function test_stored_name_uses_content_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('photo.GIF', self::GIF);

        $this->submit($file)->assertSessionHasNoErrors();

        $stored = Storage::disk('public')->allFiles('help-uploads');
        $this->assertCount(1, $stored);
        $this->assertStringEndsWith('.gif', $stored[0]);
    }
}
