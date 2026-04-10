<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContactImageController extends Controller
{
    // ── Avatar ────────────────────────────────────────────────────────────────

    public function uploadAvatar(Request $request, Contact $contact)
    {
        abort_if($contact->owner_user_id !== $request->user()->id, 403);

        $request->validate(['image' => 'required|image|max:20480']);

        $path = "contacts/{$contact->id}/avatar.jpg";

        $this->resizeAndStore($request->file('image'), $path, 400, 75);

        if ($contact->avatar && $contact->avatar !== $path) {
            Storage::disk('public')->delete($contact->avatar);
        }

        $contact->update(['avatar' => $path]);

        return response()->json(['avatar_url' => url('api/img/' . $path)]);
    }

    public function deleteAvatar(Request $request, Contact $contact)
    {
        abort_if($contact->owner_user_id !== $request->user()->id, 403);

        if ($contact->avatar) {
            Storage::disk('public')->delete($contact->avatar);
            $contact->update(['avatar' => null]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Card images ───────────────────────────────────────────────────────────

    public function uploadCardImage(Request $request, Contact $contact)
    {
        abort_if($contact->owner_user_id !== $request->user()->id, 403);

        $request->validate([
            'image' => 'required|image|max:20480',
            'side'  => 'required|in:front,back',
        ]);

        $side  = $request->input('side');
        $col   = $side === 'front' ? 'card_image_front' : 'card_image_back';
        $path  = "contacts/{$contact->id}/card_{$side}.jpg";

        $this->resizeAndStore($request->file('image'), $path, 1200, 80);

        if ($contact->$col && $contact->$col !== $path) {
            Storage::disk('public')->delete($contact->$col);
        }

        $contact->update([$col => $path]);

        return response()->json(['card_url' => url('api/img/' . $path)]);
    }

    public function deleteCardImage(Request $request, Contact $contact, string $side)
    {
        abort_if($contact->owner_user_id !== $request->user()->id, 403);
        abort_if(!in_array($side, ['front', 'back']), 422);

        $col = $side === 'front' ? 'card_image_front' : 'card_image_back';

        if ($contact->$col) {
            Storage::disk('public')->delete($contact->$col);
            $contact->update([$col => null]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Copy card image from URL ──────────────────────────────────────────────

    public function copyFromUrl(Request $request, Contact $contact)
    {
        abort_if($contact->owner_user_id !== $request->user()->id, 403);

        $request->validate([
            'side' => 'required|in:front,back',
            'url'  => 'required|url',
        ]);

        $side = $request->input('side');
        $col  = $side === 'front' ? 'card_image_front' : 'card_image_back';
        $path = "contacts/{$contact->id}/card_{$side}.jpg";

        $imageData = @file_get_contents($request->input('url'));
        abort_if($imageData === false, 422, 'Failed to fetch image from URL');

        $src = @imagecreatefromstring($imageData);
        abort_if($src === false, 422, 'Invalid image format');

        $origW   = imagesx($src);
        $origH   = imagesy($src);
        $maxWidth = 1200;

        if ($origW > $maxWidth) {
            $newW = $maxWidth;
            $newH = (int) round($origH * ($maxWidth / $origW));
        } else {
            $newW = $origW;
            $newH = $origH;
        }

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        ob_start();
        imagejpeg($dst, null, 80);
        $jpeg = ob_get_clean();

        if ($contact->$col && $contact->$col !== $path) {
            Storage::disk('public')->delete($contact->$col);
        }

        Storage::disk('public')->put($path, $jpeg);
        $contact->update([$col => $path]);

        return response()->json(['card_url' => url('api/img/' . $path)]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function resizeAndStore(\Illuminate\Http\UploadedFile $file, string $storagePath, int $maxWidth, int $quality): void
    {
        [$origW, $origH] = getimagesize($file->getRealPath());

        if ($origW > $maxWidth) {
            $newW = $maxWidth;
            $newH = (int) round($origH * ($maxWidth / $origW));
        } else {
            $newW = $origW;
            $newH = $origH;
        }

        $mime = $file->getMimeType();
        $src  = match (true) {
            str_contains($mime, 'png')  => imagecreatefrompng($file->getRealPath()),
            str_contains($mime, 'webp') => imagecreatefromwebp($file->getRealPath()),
            str_contains($mime, 'gif')  => imagecreatefromgif($file->getRealPath()),
            default                      => imagecreatefromjpeg($file->getRealPath()),
        };

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        // Capture output vào buffer → dùng Storage::put để tránh path issue trên Windows
        ob_start();
        imagejpeg($dst, null, $quality);
        $jpeg = ob_get_clean();

        Storage::disk('public')->put($storagePath, $jpeg);
    }
}
