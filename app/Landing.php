<?php
declare(strict_types=1);

namespace DPS;

/**
 * The public front page: the built-in backgrounds and the owner's own image.
 *
 * An uploaded image is accepted only when its first bytes, its contents and
 * PHP's own image reader all agree it is a JPEG, PNG or WebP. The name the
 * visitor's browser sent is never used. The file is kept in <data>/landing
 * under a random name and served only by landing-bg.php.
 */
final class Landing
{
    public const BUILT_IN = ['dawn', 'sea', 'forest', 'paper', 'plain'];
    public const DEFAULT_BACKGROUND = 'dawn';
    public const MAX_BYTES = 3 * 1024 * 1024;
    public const MAX_SIDE = 12000;

    private const TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    private const NAME = '/^background-[a-f0-9]{16}\.(jpg|png|webp)$/D';

    /** Built-in background ids with their display names. @return array<string,string> */
    public static function builtIn(): array
    {
        return [
            'dawn' => t('Dawn'),
            'sea' => t('Sea'),
            'forest' => t('Forest'),
            'paper' => t('Paper'),
            'plain' => t('Plain'),
        ];
    }

    public static function dir(Store $store): string
    {
        return $store->dir() . '/landing';
    }

    /**
     * Check a file on disk.
     *
     * @return array{ext:string,mime:string}|string the type, or the reason it is refused
     */
    public static function inspect(string $path): array|string
    {
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            return t('That file is empty.');
        }
        if ($size > self::MAX_BYTES) {
            return t('The image can be up to 3 MB.');
        }
        $bytes = (string) @file_get_contents($path);
        $head = substr($bytes, 0, 12);
        if (substr($head, 0, 3) === "\xFF\xD8\xFF") {
            $ext = 'jpg';
            $imageType = IMAGETYPE_JPEG;
        } elseif (substr($head, 0, 8) === "\x89PNG\r\n\x1A\n") {
            $ext = 'png';
            $imageType = IMAGETYPE_PNG;
        } elseif (substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') {
            $ext = 'webp';
            $imageType = IMAGETYPE_WEBP;
        } else {
            return t('Use a JPEG, PNG or WebP image.');
        }
        $info = @getimagesize($path);
        if ($info === false || $info[2] !== $imageType || $info[0] < 1 || $info[1] < 1) {
            return t('That file is not a valid image.');
        }
        if ($info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE) {
            return t('The image is too large in pixels. Use one up to {n} pixels on each side.', ['n' => self::MAX_SIDE]);
        }
        // A picture that is also a script or a web page is not a picture.
        if (preg_match('/<\?php|<script|<html|<svg/i', $bytes) === 1) {
            return t('That file is not a valid image.');
        }
        return ['ext' => $ext, 'mime' => self::TYPES[$ext]];
    }

    /**
     * Keep a checked file as the new background and remove the old one.
     * Call inside Store::locked(). Returns the new file name.
     */
    public static function store(Store $store, string $path, string $ext, ?string $old): string
    {
        $dir = self::dir($store);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new StoreException("Cannot create {$dir}.");
        }
        $name = 'background-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $dir . '/' . $name;
        if (!@copy($path, $target)) {
            throw new StoreException('Could not save the image.');
        }
        @chmod($target, 0600);
        if ($old !== null) {
            self::remove($store, $old);
        }
        return $name;
    }

    public static function remove(Store $store, string $name): void
    {
        if (preg_match(self::NAME, $name) === 1) {
            @unlink(self::dir($store) . '/' . $name);
        }
    }

    /** The saved image's path, when the name is one we made and the file is there. */
    public static function imagePath(Store $store, mixed $name): ?string
    {
        if (!is_string($name) || preg_match(self::NAME, $name) !== 1) {
            return null;
        }
        $path = self::dir($store) . '/' . $name;
        return is_file($path) && !is_link($path) ? $path : null;
    }

    /** The exact Content-Type for a saved file name. */
    public static function mime(string $name): ?string
    {
        return preg_match(self::NAME, $name, $m) === 1 ? self::TYPES[$m[1]] : null;
    }

    /** The background to show: 'own' falls back to the default when the file is gone (another node, say). */
    public static function choice(Store $store, array $landing): string
    {
        $bg = (string) ($landing['background'] ?? self::DEFAULT_BACKGROUND);
        if ($bg === 'own') {
            return self::imagePath($store, $landing['image'] ?? null) !== null ? 'own' : self::DEFAULT_BACKGROUND;
        }
        return in_array($bg, self::BUILT_IN, true) ? $bg : self::DEFAULT_BACKGROUND;
    }

    /** The address of the background image for a choice from choice(). */
    public static function src(string $choice, array $landing): string
    {
        if ($choice === 'own') {
            return 'landing-bg.php?v=' . substr(md5((string) ($landing['image'] ?? '')), 0, 8);
        }
        return 'assets/landing/' . $choice . '.svg';
    }
}
