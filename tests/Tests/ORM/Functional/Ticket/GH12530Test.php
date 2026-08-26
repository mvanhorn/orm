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
        ]);

        $this->_em->getConfiguration()->addFilter(GH12530UnrelatedFilter::class, GH12530UnrelatedFilter::class);
    }

    public function testIndexByUsesCurrentColumnAliasAfterEnablingFilter(): void
    {
        $childButton      = new GH12530ChildButton(1);
        $childButtonChild = new GH12530ChildButton(2, $childButton);

        $otherButton      = new GH12530OtherChildButton(3);
        $otherButtonChild = new GH12530OtherChildButton(4, $otherButton);

        $this->_em->persist($childButton);
        $this->_em->persist($childButtonChild);
        $this->_em->persist($otherButton);
        $this->_em->persist($otherButtonChild);
        $this->_em->flush();
        $this->_em->clear();

        $childButtons = $this->_em->getRepository(GH12530ChildButton::class)->findBy(['parent' => null]);

        self::assertCount(1, $childButtons);
        self::assertTrue($childButtons[0]->children->containsKey(2));

        $this->_em->clear();
        $this->_em->getFilters()->enable(GH12530UnrelatedFilter::class);

        $otherButtons = $this->_em->getRepository(GH12530OtherChildButton::class)->findBy(['parent' => null]);

        self::assertCount(1, $otherButtons);
        self::assertTrue($otherButtons[0]->children->containsKey(4));
    }
}

final class GH12530UnrelatedFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        return '';
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'gh12530_node')]
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
    #[ORM\Column(type: 'integer')]
    public int $id;
}

#[ORM\Entity]
#[ORM\Table(name: 'gh12530_button')]
abstract class GH12530AbstractButton extends GH12530AbstractNode
{
    #[ORM\Column(type: 'integer')]
    public int $sortIndex;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    public self|null $parent;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, fetch: 'EAGER', indexBy: 'sortIndex')]
    public Collection $children;

    public function __construct(int $sortIndex, self|null $parent = null)
    {
        $this->sortIndex = $sortIndex;
        $this->parent    = $parent;
        $this->children  = new ArrayCollection();

        if ($parent !== null) {
            $parent->children->set($sortIndex, $this);
        }
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'gh12530_child_button')]
class GH12530ChildButton extends GH12530AbstractButton
{
}

#[ORM\Entity]
#[ORM\Table(name: 'gh12530_other_child_button')]
class GH12530OtherChildButton extends GH12530AbstractButton
{
}
