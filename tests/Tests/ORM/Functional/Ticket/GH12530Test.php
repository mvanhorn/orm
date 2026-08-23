<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\Tests\OrmFunctionalTestCase;

final class GH12530Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEntitySchema([
            GH12530AbstractNode::class,
            GH12530AbstractButton::class,
            GH12530ChildButton::class,
            GH12530OtherChildButton::class,
            GH12530FilterTarget::class,
        ]);

        $this->_em->getConfiguration()->addFilter(GH12530Filter::class, GH12530Filter::class);
    }

    public function testIndexedCollectionsAreHydratedAfterEnablingUnrelatedFilter(): void
    {
        $childParent = new GH12530ChildButton(10);
        $child       = new GH12530ChildButton(11, $childParent);

        $otherParent = new GH12530OtherChildButton(20);
        $otherChild  = new GH12530OtherChildButton(21, $otherParent);

        $this->_em->persist($childParent);
        $this->_em->persist($child);
        $this->_em->persist($otherParent);
        $this->_em->persist($otherChild);
        $this->_em->flush();
        $this->_em->clear();

        $childParent = $this->_em->getRepository(GH12530ChildButton::class)->findOneBy(['parent' => null]);

        self::assertNotNull($childParent);
        self::assertSame([11], $childParent->children->getKeys());

        $this->_em->clear();
        $this->_em->getFilters()->enable(GH12530Filter::class)->setParameter('value', 'not-present');

        $otherParent = $this->_em->getRepository(GH12530OtherChildButton::class)->findOneBy(['parent' => null]);

        self::assertNotNull($otherParent);
        self::assertSame([21], $otherParent->children->getKeys());
    }
}

#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'child' => GH12530ChildButton::class,
    'other' => GH12530OtherChildButton::class,
])]
abstract class GH12530AbstractNode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int|null $id = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    public GH12530AbstractNode|null $parent;

    /** @var Collection<int, GH12530AbstractNode> */
    #[ORM\OneToMany(
        targetEntity: self::class,
        mappedBy: 'parent',
        fetch: 'EAGER',
        indexBy: 'sortIndex',
    )]
    public Collection $children;

    public function __construct(GH12530AbstractNode|null $parent = null)
    {
        $this->parent   = $parent;
        $this->children = new ArrayCollection();
    }
}

#[ORM\Entity]
abstract class GH12530AbstractButton extends GH12530AbstractNode
{
    #[ORM\Column]
    public int $sortIndex;

    public function __construct(int $sortIndex, GH12530AbstractNode|null $parent = null)
    {
        parent::__construct($parent);

        $this->sortIndex = $sortIndex;
    }
}

#[ORM\Entity]
final class GH12530ChildButton extends GH12530AbstractButton
{
}

#[ORM\Entity]
final class GH12530OtherChildButton extends GH12530AbstractButton
{
}

#[ORM\Entity]
final class GH12530FilterTarget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int|null $id = null;

    #[ORM\Column(name: 'filter_value')]
    public string $filterValue;
}

final class GH12530Filter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->name !== GH12530FilterTarget::class) {
            return '';
        }

        return $targetTableAlias . '.filter_value = ' . $this->getParameter('value');
    }
}
