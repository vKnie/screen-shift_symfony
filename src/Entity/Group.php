<?php

namespace App\Entity;

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`group`')]
class Group
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Screen>
     */
    #[ORM\OneToMany(targetEntity: Screen::class, mappedBy: 'groupeScreen', orphanRemoval: true)]
    private Collection $screens;

    public function __construct()
    {
        $this->screens = new ArrayCollection();
        $this->pictures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Screen>
     */
    public function getScreens(): Collection
    {
        return $this->screens;
    }

    public function addScreen(Screen $screen): static
    {
        if (!$this->screens->contains($screen)) {
            $this->screens->add($screen);
            $screen->setGroupeScreen($this);
        }

        return $this;
    }

    public function removeScreen(Screen $screen): static
    {
        if ($this->screens->removeElement($screen)) {
            // set the owning side to null (unless already changed)
            if ($screen->getGroupeScreen() === $this) {
                $screen->setGroupeScreen(null);
            }
        }

        return $this;
    }
}
