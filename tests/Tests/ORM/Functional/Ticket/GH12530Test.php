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

        $this->createSchemaForModels(
            GH12530AbstractNode::class,
            GH12530AbstractButton::class,
            GH12530ChildButton::class,
            GH12530OtherChildButton::class,
        );
    }

    public function testIndexByAliasIsReusedAfterEnablingUnrelatedFilter(): void
    {
        $childButton      = new GH12530ChildButton(1);
        $otherChildButton = new GH12530OtherChildButton(2);
        $childButton->addChild(new GH12530ChildButton(11));
        $otherChildButton->addChild(new GH12530OtherChildButton(22));

        $this->_em->persist($childButton);
        $this->_em->persist($otherChildButton);
        $this->_em->flush();
        $this->_em->clear();

        $buttons = $this->_em->getRepository(GH12530ChildButton::class)->findBy(['parent' => null]);

        self::assertCount(1, $buttons);
        self::assertSame([11], $buttons[0]->children->getKeys());

        $this->_em->clear();
        $this->_em->getConfiguration()->addFilter('unrelated', GH12530UnrelatedFilter::class);
        $this->_em->getFilters()->enable('unrelated');

        $buttons = $this->_em->getRepository(GH12530OtherChildButton::class)->findBy(['parent' => null]);

        self::assertCount(1, $buttons);
        self::assertSame([22], $buttons[0]->children->getKeys());
    }
}

#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorMap([
    'child' => GH12530ChildButton::class,
    'other-child' => GH12530OtherChildButton::class,
])]
abstract class GH12530AbstractNode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;
}

#[ORM\Entity]
abstract class GH12530AbstractButton extends GH12530AbstractNode
{
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    public self|null $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(
        targetEntity: self::class,
        mappedBy: 'parent',
        cascade: ['persist'],
        fetch: 'EAGER',
        indexBy: 'sortIndex',
    )]
    public Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function addChild(self $child): void
    {
        $this->children->add($child);
        $child->parent = $this;
    }
}

#[ORM\Entity]
final class GH12530ChildButton extends GH12530AbstractButton
{
    #[ORM\Column]
    public int $sortIndex;

    public function __construct(int $sortIndex)
    {
        parent::__construct();

        $this->sortIndex = $sortIndex;
    }
}

#[ORM\Entity]
final class GH12530OtherChildButton extends GH12530AbstractButton
{
    #[ORM\Column]
    public int $sortIndex;

    public function __construct(int $sortIndex)
    {
        parent::__construct();

        $this->sortIndex = $sortIndex;
    }
}

final class GH12530UnrelatedFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        return '';
    }
}
