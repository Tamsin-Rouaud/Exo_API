<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use OpenApi\Attributes as OA;

#[Route('/api/books')]
#[OA\Tag(name: 'Books')]
class BookController extends AbstractController
{
    #[Route('', name: 'books', methods: ['GET'])]
    #[OA\Get(
        summary: 'Récupère tous les livres avec liens HATEOAS',
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                description: 'Numéro de page',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                description: 'Nombre d’éléments par page',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 3)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des livres',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 42),
                            new OA\Property(property: 'title', type: 'string', example: 'Le Seigneur des Anneaux'),
                            new OA\Property(property: 'coverText', type: 'string', example: 'Un anneau pour les gouverner...'),
                            new OA\Property(property: 'comment', type: 'string', nullable: true, example: 'Classique'),
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
                    )
                )
            )
        ]
    )]
    public function getAllBooks(
        BookRepository $bookRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllBooks-" . $page . "-" . $limit;

        $bookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit) {
            $item->tag('booksCache');
            return $bookRepository->findAllPageWithPagination($page, $limit);
        });

        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBookList = $serializer->serialize($bookList, 'json', $context);

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    // Les autres méthodes restent inchangées pour l'instant.



    #[Route('/api/books/{id}', name: 'detail_book', methods:['GET'])]
    public function getDetailBook(int $id, Book $book , SerializerInterface $serializer, VersioningService $versioningService ): JsonResponse
    {
        $version = $versioningService->getVersion();
         $context = SerializationContext::create()->setGroups(['getBooks']);
         $context->setVersion($version);
        $jsonBook = $serializer->serialize($book, 'json', $context);

        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
        }

     #[Route('/api/books/{id}', name: 'delete_book', methods:['DELETE'])]
     #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un livre')]
    public function deleteBook(Book $book , EntityManagerInterface $em, TagAwareCacheInterface $cache  ): JsonResponse
    {
        $cache->invalidateTags(['booksCache']);
        $em->remove($book);
        $em->flush();

        return new JsonResponse( null,Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name:"create_book", methods:['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, AuthorRepository $authorRepository ,UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator ): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
        
        $errors = $validator->validate($book);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }
      
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $book->setAuthor($authorRepository->find($idAuthor));

        
        $em->persist($book);
        $em->flush(); 

        $jsonBook = $serializer->serialize($book, 'json', $context);

        $location = $urlGenerator->generate('detail_book', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL );

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" =>$location], true);
    }

    #[Route('/api/books/{id}', name:"update_book", methods:['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un livre')]
    public function updateBook(Request $request, SerializerInterface $serializer, Book $currentBook, EntityManagerInterface $em, AuthorRepository $authorRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache  )
    {

        $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');
        
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

         $errors = $validator->validate($currentBook);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $currentBook->setAuthor($authorRepository->find($idAuthor));

        
        $em->persist($currentBook);
        $em->flush();

         $cache->invalidateTags(['booksCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
        
}
