<?php

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Content\ImageProcessor;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\NewsPost;
use App\Models\NewsPostTranslation;
use App\Storage\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/*
| CONTENT: uploads API (POST /api/media) + media actions. Ports the intent of the reference's
| storage-adapters/content tests: sniffed MIME (never the client's), size limits, image processing
| (orientation + metadata stripping), delete protection, capabilities, audit.
*/

beforeEach(function () {
    $this->uploads = sys_get_temp_dir().'/sakila-content-test-'.bin2hex(random_bytes(4));
    config(['sakila.local_uploads_dir' => $this->uploads, 'sakila.storage_driver' => 'local']);
    Storage::reset();
    $this->admin = UserAccounts::create('Ada Admin', 'admin@test.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    $this->author = UserAccounts::create('Arjun Author', 'author@test.local', 'Correct-Horse-9!', Role::AUTHOR);
    $this->editor = UserAccounts::create('Eva Editor', 'editor@test.local', 'Correct-Horse-9!', Role::EDITOR);
});

afterEach(function () {
    if (is_dir($this->uploads)) {
        exec('rm -rf '.escapeshellarg($this->uploads));
    }
    Storage::reset();
});

function gd_png(int $w = 40, int $h = 20): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, (int) imagecolorallocate($img, 200, 30, 30));
    ob_start();
    imagepng($img);

    return (string) ob_get_clean();
}

function gd_jpeg(int $w = 40, int $h = 20): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, (int) imagecolorallocate($img, 30, 120, 200));
    ob_start();
    imagejpeg($img, null, 90);

    return (string) ob_get_clean();
}

/** JPEG with an EXIF APP1 segment: Orientation=6 (rotate 90° CW) + a GPS IFD pointer (fake location). */
function jpeg_with_exif(int $w, int $h): string
{
    $jpeg = gd_jpeg($w, $h);
    // TIFF (big-endian): IFD0 with Orientation (0x0112, SHORT, 6) and GPSInfo pointer (0x8825, LONG).
    $ifd0 = pack('n', 2)
        .pack('nnN', 0x0112, 3, 1).pack('nn', 6, 0)
        .pack('nnNN', 0x8825, 4, 1, 8 + 2 + 2 * 12 + 4)
        .pack('N', 0);
    // GPS IFD: GPSLatitudeRef = "N".
    $gps = pack('n', 1).pack('nnN', 0x0001, 2, 2).'N'."\0\0\0".pack('N', 0);
    $tiff = "MM\0*".pack('N', 8).$ifd0.$gps;
    $app1 = "Exif\0\0".$tiff;
    $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

    return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
}

function upload(mixed $test, string $bytes, string $name, array $fields = [], ?string $origin = 'same'): TestResponse
{
    if ($origin === 'same') {
        $origin = rtrim(url('/'), '/'); // the browser's Origin for this app
    }
    $file = UploadedFile::fake()->createWithContent($name, $bytes);
    $headers = ['Accept' => 'application/json'];
    if ($origin !== null) {
        $headers['Origin'] = $origin;
    }

    return $test->withHeaders($headers)->post('/api/media', ['file' => $file, ...$fields]);
}

it('uploads an image: sniffed type, real dimensions, random key, DTO, audit', function () {
    $this->actingAs($this->author);
    $res = upload($this, gd_png(40, 20), '../../etc/My Photo<script>.png', ['altIt' => '  Bambini a scuola  '])->assertStatus(201);
    $asset = $res->json('asset');
    expect($asset)->toMatchArray([
        'kind' => 'IMAGE', 'mimeType' => 'image/png', 'width' => 40, 'height' => 20,
        'filename' => 'My Photoscript.png', 'alt' => ['it' => 'Bambini a scuola', 'en' => ''], 'uploadedBy' => 'Arjun Author',
    ]);
    $row = MediaAsset::query()->findOrFail($asset['id']);
    expect($row->storageKey)->toMatch('#^\d{4}/\d{2}/[a-f0-9]{32}\.png$#')
        ->and(is_file($this->uploads.'/'.$row->storageKey))->toBeTrue()
        ->and($asset['url'])->toBe('/uploads/'.$row->storageKey)
        ->and($asset['createdAt'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/')
        ->and(AuditLog::where('action', 'MEDIA_UPLOADED')->where('entityId', $row->id)->exists())->toBeTrue();
});

it('auto-orients JPEGs and strips EXIF/GPS metadata', function () {
    $this->actingAs($this->editor);
    $bytes = jpeg_with_exif(40, 20);
    expect($bytes)->toContain('Exif');
    $asset = upload($this, $bytes, 'phone.jpg')->assertStatus(201)->json('asset');
    expect([$asset['width'], $asset['height']])->toBe([20, 40]);
    $stored = (string) file_get_contents($this->uploads.'/'.MediaAsset::query()->findOrFail($asset['id'])->storageKey);
    expect($stored)->not->toContain('Exif')->and(substr($stored, 0, 3))->toBe("\xFF\xD8\xFF");
});

it('caps huge images at 2560px on the longest side', function () {
    $out = ImageProcessor::process(gd_png(3000, 1000), 'image/png');
    expect([$out['width'], $out['height']])->toBe([2560, 853]);
});

it('strips EXIF/XMP chunks from WebP files losslessly when GD cannot re-encode them', function () {
    $vp8 = 'VP8 '.pack('V', 4)."\x01\x02\x03\x04";
    $vp8x = 'VP8X'.pack('V', 10).chr(0x08 | 0x04 | 0x10)."\0\0\0".str_repeat("\0", 6);
    $exif = 'EXIF'.pack('V', 5)."GPS!!\0";
    $xmp = 'XMP '.pack('V', 4).'<x/>';
    $body = $vp8x.$vp8.$exif.$xmp;
    $webp = 'RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body;
    $out = ImageProcessor::stripWebpMetadata($webp);
    expect($out)->not->toContain('EXIF')->not->toContain('XMP ')->toContain('VP8 ')
        ->and(ord($out[20]))->toBe(0x10) // EXIF/XMP flags cleared, alpha kept
        ->and(unpack('V', substr($out, 4, 4))[1])->toBe(strlen($out) - 8);
});

it('rejects files by their bytes, never the client name or type', function (string $bytes, string $name, array $fields, int $status) {
    $this->actingAs($this->author);
    upload($this, $bytes, $name, $fields)->assertStatus($status)->assertJson(['ok' => false]);
    expect(MediaAsset::count())->toBe(0);
})->with([
    'HTML named .jpg' => ['<html><script>alert(1)</script></html>', 'photo.jpg', [], 415],
    'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'logo.svg', [], 415],
    // The garbage after the header decodes as a gigantic canvas: the decompression-bomb guard rejects it.
    'GIF header + script polyglot' => ['GIF89a<script>alert(1)</script>', 'x.gif', [], 413],
    'PDF when an image is required' => ["%PDF-1.4\n%%EOF\n", 'report.pdf', ['kind' => 'IMAGE'], 415],
    'empty file' => ['', 'empty.png', [], 415],
]);

it('rejects oversize uploads and cross-origin or anonymous requests', function () {
    config(['sakila.upload_max_image_mb' => 0.0001]); // ≈ 104 bytes
    $this->actingAs($this->author);
    upload($this, gd_png(40, 20), 'big.png')->assertStatus(413);
    config(['sakila.upload_max_image_mb' => 10]);
    upload($this, gd_png(), 'a.png', [], 'https://evil.example')->assertStatus(403)->assertJson(['message' => 'Cross-origin upload rejected.']);
    upload($this, gd_png(), 'a.png', [], null)->assertStatus(403);
    auth()->logout();
    upload($this, gd_png(), 'a.png')->assertStatus(401);
});

it('stores PDFs as documents', function () {
    $this->actingAs($this->editor);
    upload($this, "%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\ntrailer<< /Root 1 0 R >>\n%%EOF\n", 'Bilancio 2025.pdf')
        ->assertStatus(201)->assertJson(['asset' => ['kind' => 'DOCUMENT', 'mimeType' => 'application/pdf', 'width' => null]]);
});

it('searches, updates and reports usage; blocks deleting files used by live content', function () {
    $this->actingAs($this->editor);
    $id = upload($this, gd_png(), 'school.png')->json('asset.id');

    $this->postJson('/admin/actions/media/search', ['q' => 'school', 'kind' => 'IMAGE', 'page' => 1, 'pageSize' => 24])
        ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $id);
    $this->postJson('/admin/actions/media/update', ['id' => $id, 'input' => [
        'altIt' => 'Bambini', 'altEn' => '', 'captionIt' => '', 'captionEn' => '', 'credit' => 'Sakila', 'focalX' => 0.5, 'focalY' => null,
    ]])->assertOk()->assertJsonPath('data.alt.it', 'Bambini')->assertJsonPath('message', 'Saved');
    $this->postJson('/admin/actions/media/search', ['q' => 'bambini'])->assertJsonPath('data.total', 1);
    $this->postJson('/admin/actions/media/update', ['id' => $id, 'input' => [
        'altIt' => str_repeat('x', 301), 'altEn' => '', 'captionIt' => '', 'captionEn' => '', 'credit' => '', 'focalX' => 2, 'focalY' => null,
    ]])->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['fieldErrors' => ['altIt', 'focalX']]);

    $post = NewsPost::query()->create(['status' => 'PUBLISHED', 'coverImageId' => $id, 'publishedAt' => now()]);
    NewsPostTranslation::query()->create(['postId' => $post->id, 'locale' => 'it', 'title' => 'Scuola', 'slug' => 'scuola', 'body' => ['type' => 'doc', 'content' => []]]);
    $this->postJson('/admin/actions/media/usage', ['id' => $id])->assertOk()
        ->assertJsonPath('data.0', ['kind' => 'news', 'label' => 'Scuola', 'href' => "/admin/news/{$post->id}", 'published' => true, 'where' => 'Cover image']);
    $this->postJson('/admin/actions/media/delete', ['id' => $id])->assertStatus(409)->assertJsonPath('code', 'CONFLICT');

    $post->update(['status' => 'DRAFT']);
    $key = MediaAsset::query()->findOrFail($id)->storageKey;
    $this->postJson('/admin/actions/media/delete', ['id' => $id])->assertOk()->assertJsonPath('message', 'File deleted');
    expect(MediaAsset::find($id))->toBeNull()
        ->and(is_file($this->uploads.'/'.$key))->toBeFalse()
        ->and(NewsPost::find($post->id)->coverImageId)->toBeNull()
        ->and(AuditLog::where('action', 'MEDIA_DELETED')->count())->toBe(1);
});

it('finds files referenced inside rich-text bodies', function () {
    $this->actingAs($this->editor);
    $id = upload($this, gd_png(), 'inline.png')->json('asset.id');
    $post = NewsPost::query()->create(['status' => 'DRAFT']);
    NewsPostTranslation::query()->create(['postId' => $post->id, 'locale' => 'en', 'title' => 'Inline', 'slug' => 'inline', 'body' => [
        'type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => '/uploads/x.png', 'assetId' => $id]]],
    ]]);
    $this->postJson('/admin/actions/media/usage', ['id' => $id])
        ->assertJsonPath('data.0', ['kind' => 'news', 'label' => 'Inline (EN)', 'href' => "/admin/news/{$post->id}", 'published' => false, 'where' => 'Body text']);
});

it('enforces media capabilities on actions', function () {
    $this->actingAs($this->author); // authors may upload + edit metadata, never delete
    $id = upload($this, gd_png(), 'a.png')->json('asset.id');
    $this->postJson('/admin/actions/media/delete', ['id' => $id])->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    auth()->logout();
    $this->postJson('/admin/actions/media/search', [])->assertStatus(401);
});

it('renders the media library page with paging and filters', function () {
    $this->actingAs($this->editor);
    upload($this, gd_png(), 'one.png');
    $this->get('/admin/media?kind=IMAGE&q=one&page=abc')->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/media/index')
            ->where('kind', 'IMAGE')->where('q', 'one')->where('list.total', 1)->where('list.page', 1)
            ->where('canEdit', true)->where('canDelete', true));
});
