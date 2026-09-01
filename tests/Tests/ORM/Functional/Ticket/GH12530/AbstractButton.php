<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket\GH12530;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
abstract class AbstractButton extends AbstractNode
{
    #[ORM\Column(type: 'integer')]
    public int $sortIndex;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    public self|null $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(
        mappedBy: 'parent',
        targetEntity: self::class,
        cascade: ['persist'],
        fetch: 'EAGER',
        indexBy: 'sortIndex',
    )]
    public Collection $children;

    public function __construct(int $sortIndex)
    {
        $this->sortIndex = $sortIndex;
        $this->children  = new ArrayCollection();
    }

    public function addChild(self $child): void
    {
        $child->parent = $this;
        $this->children->set($child->sortIndex, $child);
    }
}
