<?php

namespace Tests\Unit;

use App\Services\ObjectiveChatService;
use Tests\TestCase;

class ObjectiveChatServiceTest extends TestCase
{
    public function test_it_tightens_wording_and_keeps_logic_in_place(): void
    {
        $draft = (new ObjectiveChatService())->draft(
            'Our objective is to really just keep quotes accurate.',
        );

        $this->assertSame('Keep quotes accurate.', $draft['body']);
        $this->assertSame('Keep quotes accurate', $draft['title']);
        $this->assertStringContainsString('one outcome', $draft['reply']);
    }
}
