<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\Since;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;
use OpenApi\Attributes as OA;

/**
 * @Hateoas\Relation(
 *  "self",
 *  href = @Hateoas\Route(
 *      "detail_book",
 *      parameters = {"id" = "expr(object.getId())"}
 *  ),
 *  exclusion = @Hateoas\Exclusion(groups={"getBooks"})
 * )
 *
 * @Hateoas\Relation(
 *  "delete",
 *  href = @Hateoas\Route(
 *      "delete_book",
 *      parameters = {"id" = "expr(object.getId())"}
 *  ),
 *  exclusion = @Hateoas\Exclusion(
 *      groups={"getBooks"},
 *      excludeIf = "expr(not is_granted('ROLE_ADMIN'))"
 *  )
 * )
 *
 * @Hateoas\Relation(
 *  "update",
 *  href = @Hateoas\Route(
 *      "update_book",
 *      parameters = {"id" = "expr(object.getId())"}
 *  ),
 *  exclusion = @Hateoas\Exclusion(
 *      groups={"getBooks"},
 *      excludeIf = "expr(not is_granted('ROLE_ADMIN'))"
 *  )
 * )
 */

#[OA\Schema(
    schema: 'Book',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'title', type: 'string', example: 'Le Seigneur des Anneaux'),
        new OA\Property(property: 'coverText', type: 'string', example: 'Un anneau pour les gouverner tous...'),
        new OA\Property(property: 'comment', type: 'string', nullable: true, example: 'Un classique'),
        new OA\Property(
            property: '_links',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'self',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'href', type: 'string', example: '/api/books/42')
                    ]
                ),
                new OA\Property(
                    property: 'delete',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'href', type: 'string', example: '/api/books/42')
                    ]
                ),
                new OA\Property(
                    property: 'update',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'href', type: 'string', example: '/api/books/42')
                    ]
                ),
            ]
        )
    ]
)]

#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getBooks", "getAuthors"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getBooks", "getAuthors"])]
    #[Assert\NotBlank(message:"Le titre du livre est obligatoire")]
    #[Assert\Length(min:3, max: 255, minMessage: "Le titre du livre doit faire au moins 3 caractères", maxMessage:"Le titre du livre ne peut pas faire plus de 255 caractères")]

    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(["getBooks", "getAuthors"])]
    private ?string $coverText = null;

    #[ORM\ManyToOne(inversedBy: 'books')]
    #[ORM\JoinColumn(nullable:false, onDelete:'CASCADE')]
    #[Groups(["getBooks"])]
    
    private ?Author $author = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(["getBooks"])]
    #[Since("2.0")]
    private ?string $comment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCoverText(): ?string
    {
        return $this->coverText;
    }

    public function setCoverText(?string $coverText): static
    {
        $this->coverText = $coverText;

        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }
}
