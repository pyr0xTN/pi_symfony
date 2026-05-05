<?php
namespace App\Service;

use App\Entity\Messages;
use App\Enum\TypeMessage;

class MessageCheckerService {
    public function isContentValid(string $content): bool {
        return !empty(trim($content));
    }

    public function canMessageBeEdited(Messages $message): bool {
        if ($message->isDeleted()) return false;
        return $message->getTypeMessage() === TypeMessage::TEXTE;
    }
}