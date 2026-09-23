<?php

namespace Tests\Feature;

use Illuminate\Support\MessageBag;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

/**
 * A rejected image upload is a validation message, not a 500.
 *
 * REPORTED AS: "error 500 when uploading image for recipe". The upload itself
 * succeeded (POST /livewire/upload-file returned 200) — the component blew up
 * on the very next render, so the user saw a crash instead of "that file is
 * too big".
 *
 * Per-file upload errors land under indexed keys (`newDineInImages.0`), so the
 * form reads them back with the wildcard `$errors->get('newDineInImages.*')`.
 * MessageBag answers a wildcard with one array of messages PER MATCHING KEY —
 * nested, not flat. `{{ $message }}` on an inner array is
 * htmlspecialchars(array), which is a 500.
 *
 * The fix lives in the component, not the call sites: four other screens read
 * wildcard errors the same way.
 */
class WildcardUploadErrorRendersTest extends TestCase
{
    private function render($messages): string
    {
        return view('components.input-error', [
            'messages'   => $messages,
            'attributes' => new ComponentAttributeBag([]),
        ])->render();
    }

    public function test_a_wildcard_lookup_renders_every_message_instead_of_throwing(): void
    {
        $bag = new MessageBag();
        $bag->add('newDineInImages.0', 'photo.avif is not a previewable image.');
        $bag->add('newDineInImages.1', 'big.jpg may not be greater than 5120 kilobytes.');

        $html = $this->render($bag->get('newDineInImages.*'));

        $this->assertStringContainsString('photo.avif is not a previewable image.', $html);
        $this->assertStringContainsString('may not be greater than 5120 kilobytes.', $html);
        $this->assertStringNotContainsString('Array', $html);
    }

    public function test_the_ordinary_shapes_still_render(): void
    {
        $bag = new MessageBag();
        $bag->add('name', 'The name field is required.');

        $this->assertStringContainsString(
            'The name field is required.',
            $this->render($bag->get('name')),
        );

        $this->assertStringContainsString(
            'a plain string message',
            $this->render('a plain string message'),
        );
    }

    public function test_no_errors_renders_nothing(): void
    {
        $this->assertSame('', trim($this->render([])));
        $this->assertSame('', trim($this->render(null)));

        // A wildcard that matches no keys — the common case on a clean form.
        $this->assertSame('', trim($this->render((new MessageBag())->get('newDineInImages.*'))));
    }
}
