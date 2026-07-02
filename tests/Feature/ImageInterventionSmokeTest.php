<?php

namespace TCG\Voyager\Tests\Feature;

use Intervention\Image\Facades\Image as InterventionImage;
use Intervention\Image\Constraint;
use TCG\Voyager\Tests\TestCase;

class ImageInterventionSmokeTest extends TestCase
{
    protected $withDummy = false;

    /**
     * Drives the exact intervention/image call chain used by
     * ContentTypes/Image.php, MultipleImage.php and VoyagerMediaController.php
     * through a booted Laravel 13 app (package auto-discovery resolves the facade).
     */
    public function testInterventionImageFacadeWorksInsideBootedApp()
    {
        $gd = imagecreatetruecolor(200, 100);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 120, 30, 200));
        ob_start();
        imagepng($gd);
        $raw = ob_get_clean();

        // ContentTypes/Image.php path
        $img = InterventionImage::make($raw)->orientate();
        $this->assertSame(200, $img->width());
        $this->assertSame(100, $img->height());

        $resized = InterventionImage::make($raw)->orientate()->resize(100, 50, function (Constraint $c) {
            $c->aspectRatio();
            $c->upsize();
        })->encode('png', 75);
        $this->assertGreaterThan(0, strlen((string) $resized));

        // VoyagerMediaController fit/crop path
        $fit = InterventionImage::make($raw)->orientate()->fit(50, 50)->encode('png', 75);
        $this->assertGreaterThan(0, strlen((string) $fit));

        $crop = InterventionImage::make($raw)->crop(80, 40, 0, 0)->encode('png', 75);
        $this->assertGreaterThan(0, strlen((string) $crop));
    }
}
