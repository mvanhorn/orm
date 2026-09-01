<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket\GH12530;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class OtherChildButton extends AbstractButton
{
}
