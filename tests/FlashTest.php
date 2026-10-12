<?php

use Laf\UI\Component\Alert;
use Laf\UI\Flash;
use PHPUnit\Framework\TestCase;

/**
 * Flash messages: queued in the session (simulated in the CLI by a $_SESSION array), drained once.
 */
class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        Flash::clear();
    }

    protected function tearDown(): void
    {
        Flash::clear();
        unset($_SESSION);
    }

    /** @test */
    public function messages_queue_in_order_and_drain_once()
    {
        Flash::success('Saved');
        Flash::error('Oops <b>');
        $this->assertTrue(Flash::has());
        $this->assertCount(2, $_SESSION[Flash::SESSION_KEY]);

        $alerts = Flash::drain();
        $this->assertCount(2, $alerts);
        $this->assertInstanceOf(Alert::class, $alerts[0]);
        $first = (string)$alerts[0]->draw();
        $second = (string)$alerts[1]->draw();
        $this->assertStringContainsString('alert-success', $first);
        $this->assertStringContainsString('Saved', $first);
        $this->assertStringContainsString('alert-danger', $second);
        $this->assertStringContainsString('Oops &lt;b&gt;', $second, 'escaped by default');
        $this->assertStringContainsString('alert-dismissible', $first);

        $this->assertFalse(Flash::has());
        $this->assertSame([], Flash::drain(), 'drained once');
    }

    /** @test */
    public function html_messages_are_drawn_raw_and_the_legacy_session_keys_are_read()
    {
        Flash::html('<strong>Done</strong>', Alert::Type_Info);
        $_SESSION['flash_message'] = 'Old style <i>';
        $_SESSION['flash_type'] = Alert::Type_Warning;

        $entries = Flash::pull();
        $this->assertSame(['message' => '<strong>Done</strong>', 'type' => 'info', 'html' => true], $entries[0]);
        $this->assertSame(['message' => 'Old style <i>', 'type' => 'warning', 'html' => false], $entries[1]);
        $this->assertArrayNotHasKey('flash_message', $_SESSION, 'legacy keys are consumed too');
        $this->assertFalse(Flash::has());

        Flash::html('<em>x</em>');
        $this->assertStringContainsString('<em>x</em>', (string)Flash::drain()[0]->draw());
    }

    /** @test */
    public function without_a_session_messages_live_for_the_request()
    {
        unset($_SESSION);
        Flash::warning('No session here');
        $this->assertTrue(Flash::has());
        $this->assertSame('No session here', Flash::pull()[0]['message']);
        $this->assertFalse(Flash::has());
        $_SESSION = [];
    }
}
