<?php
namespace App\Tests;

use App\Entity\Messages;
use App\Enum\TypeMessage;
use App\Service\MessageCheckerService;
use PHPUnit\Framework\TestCase;

class MessageCheckerTest extends TestCase {
    public function testRules(): void {
        $service = new MessageCheckerService();
        $message = new Messages();

        // Règle 1 : Validation contenu
        $this->assertTrue($service->isContentValid("Hello"));
        $this->assertFalse($service->isContentValid("   "));

        // Règle 2 : Édition
        $message->setTypeMessage(TypeMessage::TEXTE);
        $message->setIsDeleted(false);
        $this->assertTrue($service->canMessageBeEdited($message));
    }
}