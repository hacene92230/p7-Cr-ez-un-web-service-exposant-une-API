<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getProducts"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom du produit est obligatoire")]
    #[Assert\Length(min: 3, max: 55, minMessage: "Le nom doit faire au moins {{ limit }} caractères", maxMessage: "Le nom ne peut pas faire plus de {{ limit }} caractères")]
    #[Groups(["getProducts"])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getProducts"])]
    #[Assert\NotBlank(message: "Le prix du produit est obligatoire")]
    #[Assert\Length(min: 1, max: 15, minMessage: "Le prix doit faire au moins {{ limit }} caractères", maxMessage: "Le prix ne peut pas dépasser de {{ limit }} caractères")]
    private ?string $price = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getProducts"])]
    #[Assert\NotBlank(message: "Le modèle du produit est obligatoire")]
    #[Assert\Length(min: 3, max: 20, minMessage: "Le nom doit faire au moins {{ limit }} caractères", maxMessage: "Le nom ne peut pas faire plus de {{ limit }} caractères")]
    private ?string $model = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getProducts"])]
    #[Assert\NotBlank(message: "La description du produit est obligatoire")]
    #[Assert\Length(min: 15, max: 500, minMessage: "La description doit faire au moins {{ limit }} caractères", maxMessage: "La description ne peut pas faire plus de {{ limit }} caractères")]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
